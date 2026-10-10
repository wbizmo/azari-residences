<?php

namespace Tests\Feature\PhaseThree;

use App\Models\AccommodationType;
use App\Models\Booking;
use App\Models\ChannelConnection;
use App\Models\ChannelReservation;
use App\Models\ChannelWebhookInbox;
use App\Models\ChannelOutboxEvent;
use App\Models\InventoryDate;
use App\Models\Property;
use App\Models\User;
use App\Services\PhaseThree\ChannelEventInboxService;
use App\Services\PhaseThree\ChannelSandboxEventProcessor;
use App\Services\PhaseThree\ChannelSandboxOutboxDispatcher;
use App\Services\PhaseThree\ChannelOutboxService;
use App\Services\PhaseThree\OwnerYieldApprovalService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PhaseThreeGapCompletionTest extends TestCase
{
    use RefreshDatabase;

    private function setupRoom(bool $sandbox = false): array
    {
        $owner = User::factory()->create(['email_verified_at'=>now()]);
        $property = Property::factory()->create(['owner_id'=>$owner->id,'currency'=>'NGN']);
        $room = AccommodationType::query()->create([
            'property_id'=>$property->id,'name'=>'Phase 3 studio','slug'=>'phase3-studio',
            'code'=>'PH3G-'.$property->id,'adult_capacity'=>2,'child_capacity'=>0,
            'max_guests'=>2,'total_inventory'=>4,'base_rate'=>20000,'currency'=>'NGN',
            'is_active'=>true,'is_published'=>true,
        ]);
        if (! $sandbox) return [$owner,$property,$room];
        $connection = ChannelConnection::query()->create([
            'property_id'=>$property->id,'accommodation_type_id'=>$room->id,
            'name'=>'Sandbox','provider'=>'resavar_sandbox',
            'webhook_secret'=>'secret-for-test', 'import_url'=>'https://example.org/calendar.ics',
            'is_active'=>true,'fail_closed'=>true,'status'=>'healthy',
            'last_successful_sync_at'=>now(),
        ]);
        return [$owner,$property,$room,$connection];
    }

    private function capture(ChannelConnection $connection, string $eventId, array $payload): int
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $sig = hash_hmac('sha256', $timestamp.'.'.$eventId.'.'.$body, 'secret-for-test');
        return app(ChannelEventInboxService::class)->capture($connection, $body, $eventId, $timestamp, $sig)['id'];
    }

    private function supplierEvent(int $version, string $status = 'active'): array
    {
        $start = CarbonImmutable::today()->addDays(12);
        return ['reservation_id'=>'supplier-1','version'=>$version,'status'=>$status,
            'starts_on'=>$start->toDateString(),'ends_on'=>$start->addDays(2)->toDateString(),
            'quantity'=>1];
    }

    public function test_replay_and_out_of_order_supplier_events_converge_once(): void
    {
        config(['reserva.channels.sandbox_webhooks_enabled'=>true]);
        [,,$room,$connection] = $this->setupRoom(true);
        $worker=app(ChannelSandboxEventProcessor::class);
        $cancel=$this->capture($connection,'cancel-v3',$this->supplierEvent(3,'cancelled'));
        $this->assertSame('processed',$worker->process($cancel));
        $this->assertSame('processed',$worker->process($cancel));
        $older=$this->capture($connection,'confirm-v2',$this->supplierEvent(2));
        $this->assertSame('ignored',$worker->process($older));
        $this->assertSame('cancelled',ChannelReservation::query()->firstOrFail()->status);
        $latest=$this->capture($connection,'rebook-v4',$this->supplierEvent(4));
        $this->assertSame('processed',$worker->process($latest));
        $reservation=ChannelReservation::query()->firstOrFail();
        $this->assertSame('active',$reservation->status);
        $this->assertSame(4,(int) $reservation->metadata['supplier_version']);
        $this->assertDatabaseCount('channel_reservations',1);
        $this->assertSame(0,ChannelWebhookInbox::query()->where('status','received')->count());
        $this->assertSame('healthy',$connection->fresh()->status);
    }

    public function test_unmapped_supplier_event_cannot_mutate_inventory_and_enters_manual_review(): void
    {
        config(['reserva.channels.sandbox_webhooks_enabled'=>true]);
        [,,$room,$connection] = $this->setupRoom(true);
        $event=$this->capture($connection,'malformed',['status'=>'active']);
        $this->assertSame('manual_review',app(ChannelSandboxEventProcessor::class)->process($event));
        $this->assertDatabaseCount('channel_reservations',0);
        $this->assertSame('manual_review',ChannelWebhookInbox::query()->findOrFail($event)->status);
    }

    public function test_supplier_oversell_marks_connection_conflicted_and_closes_inventory(): void
    {
        config(['reserva.channels.sandbox_webhooks_enabled'=>true]);
        [,,$room,$connection] = $this->setupRoom(true);
        $payload=$this->supplierEvent(1);$payload['quantity']=5;
        $event=$this->capture($connection,'overbooked',$payload);
        $this->assertSame('manual_review',app(ChannelSandboxEventProcessor::class)->process($event));
        $this->assertSame('conflict',$connection->fresh()->status);
        $start=CarbonImmutable::today()->addDays(12);
        $this->assertSame(0,app(\App\Services\Bookings\AzariAvailabilityEngine::class)
            ->availableQuantity($room,$start,$start->addDay()));
    }

    public function test_sandbox_outbox_is_explicitly_simulated_not_falsely_published(): void
    {
        config(['reserva.channels.sandbox_webhooks_enabled'=>true]);
        [,,$room,$connection] = $this->setupRoom(true);
        $outbox=app(ChannelOutboxService::class)->enqueue($connection,'inventory.changed','rate-101',['rate'=>100]);
        $this->assertSame('simulated',app(ChannelSandboxOutboxDispatcher::class)->simulate($outbox->id));
        $this->assertSame('simulated',app(ChannelSandboxOutboxDispatcher::class)->simulate($outbox->id));
        $this->assertNull($outbox->fresh()->published_at);
        $this->assertSame(1,$outbox->fresh()->attempts);
    }

    public function test_owner_only_ical_dry_run_detects_external_oversell_without_writing(): void
    {
        [$owner,$property,$room]=$this->setupRoom();
        $connection=ChannelConnection::query()->create([
            'property_id'=>$property->id, 'accommodation_type_id'=>$room->id,
            'provider'=>'ical','name'=>'Calendar', 'import_url'=>'https://example.org/supplier.ics',
            'is_active'=>true,'fail_closed'=>true,'status'=>'healthy',
            'last_successful_sync_at'=>now(),
        ]);
        $start=CarbonImmutable::today()->addDays(15);
        $calendar="BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:external-1\r\nDTSTART;VALUE=DATE:"
            .$start->format('Ymd')."\r\nDTEND;VALUE=DATE:"
            .$start->addDay()->format('Ymd')."\r\nSUMMARY:Occupied\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
        $guest=User::factory()->create(['email_verified_at'=>now()]);
        $this->actingAs($guest)->post(route('user.owner.channels.preview-import',$connection),[
            'calendar'=>$calendar])->assertNotFound();
        $result=$this->actingAs($owner)->post(route('user.owner.channels.preview-import',$connection),[
            'calendar'=>$calendar])->assertOk()->json();
        $this->assertSame(1,$result['new_reservations']);
        $this->assertTrue($result['can_import_without_operator_review']);
        $this->assertFalse($result['applied']);
        $this->assertDatabaseCount('channel_reservations',0);
        $oversold=str_replace('UID:external-1', 'UID:external-2', $calendar);
        // Simulate 5 active overlapping supplier holds with distinct UIDs.
        $block=preg_replace('/^.*?BEGIN:VEVENT/s','BEGIN:VEVENT', $oversold);
        $block=explode('END:VEVENT', $block)[0].'END:VEVENT';
        $events='';
        for ($i=0;$i<5;$i++) $events.=str_replace('external-2','external-'.$i,$block)."\r\n";
        $overbooked="BEGIN:VCALENDAR\r\nVERSION:2.0\r\n".$events."END:VCALENDAR\r\n";
        $preview=$this->post(route('user.owner.channels.preview-import',$connection),[
            'calendar'=>$overbooked])->assertOk()->json();
        $this->assertSame(1,$preview['potential_oversell_count']);
        $this->assertFalse($preview['can_import_without_operator_review']);
        $this->assertDatabaseCount('channel_reservations',0);
    }

    public function test_fx_checkout_lock_is_off_by_default_and_one_time_when_explicitly_enabled(): void
    {
        $user=User::factory()->create();
        $rate=\App\Models\FxReferenceRate::query()->create([
            'base_currency'=>'USD','quote_currency'=>'NGN',
            'units_per_base'=>'1500.00000000','source'=>'test','source_reference'=>'test-1',
            'observed_at'=>now()->subMinute(),'expires_at'=>now()->addMinutes(15),
        ]);
        $fx=app(\App\Services\PhaseThree\FxQuoteService::class);
        $locked=$fx->lock($rate,12345,$user->id);
        try {
            $fx->consumeForCheckout($locked->id,$user->id,12345,'NGN');
            $this->fail('Uncertified FX checkout must be disabled.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('fx',$exception->errors());
        }
        config(['reserva.fx.checkout_enabled'=>true]);
        try {
            $fx->consumeForCheckout($locked->id,$user->id+100,12345,'NGN');
            $this->fail('Cross-user FX quote reuse must be blocked.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('fx',$exception->errors());
        }
        $this->assertNotNull($fx->consumeForCheckout($locked->id,$user->id,12345,'NGN')->consumed_at);
        try {
            $fx->consumeForCheckout($locked->id,$user->id,12345,'NGN');
            $this->fail('Replayed FX quote must be blocked.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('fx',$exception->errors());
        }
    }

    public function test_owner_must_approve_exact_previewed_rate_and_can_undo(): void
    {
        [$owner,$property,$room]=$this->setupRoom();
        $from=CarbonImmutable::today()->addDays(12);$to=$from->addDays(7);
        for ($i=0;$i<5;$i++) Booking::factory()->create([
            'property_id'=>$property->id,'accommodation_type_id'=>$room->id,
            'check_in'=>$from->addDays($i)->toDateString(),
            'check_out'=>$from->addDays($i+1)->toDateString(),
            'rooms'=>1,'status'=>'confirmed',
        ]);
        $approval=app(OwnerYieldApprovalService::class);
        $preview=$approval->preview($room,$from,$to);
        $this->assertTrue($preview['eligible']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/',$preview['revision']);
        $result=$this->actingAs($owner)->post(route('user.owner.commercial.yield-approve',[$property,$room]),[
            'from_date'=>$from->toDateString(), 'to_date'=>$to->toDateString(),
            'accepted_rate'=>(string) $preview['suggested_rate'],
            'expected_revision'=>$preview['revision'],
        ])->assertRedirect();
        $log=\App\Models\InventoryChangeLog::query()->latest()->firstOrFail();
        $this->assertSame('owner_yield_approved',$log->source);
        $this->assertDatabaseHas('inventory_dates',['accommodation_type_id'=>$room->id,
            'date'=>$from->toDateString()]);
        $this->assertEquals(20000,$room->fresh()->base_rate);
        $this->assertDatabaseCount('inventory_change_logs',1);
        app(\App\Services\Bookings\InventoryBulkUpdateService::class)->undo($log,$owner->id);
        $this->assertNotNull($log->fresh()->reverted_at);
    }

    public function test_stale_or_cross_owner_pricing_approval_cannot_mutate_calendar(): void
    {
        [$owner,$property,$room]=$this->setupRoom();
        $from=CarbonImmutable::today()->addDays(12);$to=$from->addDays(7);
        for($i=0;$i<5;$i++) Booking::factory()->create([
            'property_id'=>$property->id,'accommodation_type_id'=>$room->id,
            'check_in'=>$from->addDays($i)->toDateString(),
            'check_out'=>$from->addDays($i+1)->toDateString(),
            'status'=>'confirmed',
        ]);
        $proposal=app(OwnerYieldApprovalService::class)->preview($room,$from,$to);
        $data=['from_date'=>$from->toDateString(),'to_date'=>$to->toDateString(),
            'accepted_rate'=>(string)$proposal['suggested_rate'],'expected_revision'=>$proposal['revision']];
        $this->actingAs(User::factory()->create(['email_verified_at'=>now()]))
            ->post(route('user.owner.commercial.yield-approve',[$property,$room]),$data)->assertNotFound();
        InventoryDate::query()->create(['accommodation_type_id'=>$room->id,
            'date'=>$from->toDateString(),'stop_sell'=>true]);
        $this->actingAs($owner)->post(route('user.owner.commercial.yield-approve',[$property,$room]),$data)
            ->assertSessionHasErrors('accepted_rate');
        $this->assertDatabaseCount('inventory_change_logs',0);
    }
}
