<?php

namespace Tests\Feature\PhaseThree;

use App\Models\AccommodationType;
use App\Models\Booking;
use App\Models\ChannelConnection;
use App\Models\ChannelOutboxEvent;
use App\Models\ChannelWebhookInbox;
use App\Models\FxQuoteLock;
use App\Models\FxReferenceRate;
use App\Models\Property;
use App\Models\User;
use App\Services\PhaseThree\ChannelEventInboxService;
use App\Services\PhaseThree\ChannelMappingAuditService;
use App\Services\PhaseThree\ChannelOutboxService;
use App\Services\PhaseThree\FxQuoteService;
use App\Services\PhaseThree\OwnerYieldAdvisor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PhaseThreeNextFiveFoundationsTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $provider = 'resavar_sandbox'): array
    {
        $owner = User::factory()->create(['email_verified_at'=>now()]);
        $property = Property::factory()->create(['owner_id'=>$owner->id,'currency'=>'NGN']);
        $room = AccommodationType::query()->create([
            'property_id'=>$property->id, 'name'=>'Phase Three Suite', 'slug'=>'phase-three-suite',
            'code'=>'PH3-'.$property->id, 'adult_capacity'=>2, 'child_capacity'=>0,
            'max_guests'=>2, 'total_inventory'=>4, 'base_rate'=>20000, 'currency'=>'NGN',
            'is_active'=>true,'is_published'=>true,
        ]);
        $connection = ChannelConnection::query()->create([
            'property_id'=>$property->id,'accommodation_type_id'=>$room->id,
            'name'=>'Sandbox connection','provider'=>$provider,'webhook_secret'=>'dev-test-hmac-secret',
            'import_url'=>'https://example.com/testing.ics', 'is_active'=>true,
            'fail_closed'=>true,'status'=>'healthy','last_successful_sync_at'=>now(),
        ]);
        return [$owner,$property,$room,$connection];
    }

    private function webhook(ChannelConnection $connection, string $id, string $payload = '{"action":"confirmed"}', ?string $time = null): array
    {
        $time ??= (string) time();
        $signature = hash_hmac('sha256', $time.'.'.$id.'.'.$payload, 'dev-test-hmac-secret');
        try {
            $response = $this->call('POST', route('channels.webhook',$connection), [], [], [], [
                'CONTENT_TYPE'=>'application/json',
                'HTTP_X_RESAVAR_EVENT_ID'=>$id, 'HTTP_X_RESAVAR_TIMESTAMP'=>$time,
                'HTTP_X_RESAVAR_SIGNATURE'=>$signature,
            ], $payload);
            return [$response->status(), $response->json()];
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
            // The local Laravel test kernel may propagate service exceptions directly.
            return [$exception->getStatusCode(), null];
        }
    }

    public function test_webhook_is_disabled_by_default_and_ical_must_never_impersonate_api_provider(): void
    {
        [, , , $connection] = $this->fixture();
        $this->assertSame(403, $this->webhook($connection, 'evt-1')[0]);
        config(['reserva.channels.sandbox_webhooks_enabled'=>true]);
        $this->assertSame(202, $this->webhook($connection, 'evt-2')[0]);
        $body = '{"action":"confirmed"}';
        $ts = (string) time();
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $signature = hash_hmac('sha256', $ts.'.evt-2.'.$body, 'dev-test-hmac-secret');
            $this->assertTrue(app(ChannelEventInboxService::class)->capture(
                $connection->fresh(), $body, 'evt-2', $ts, $signature)['duplicate']);
        }
        $this->assertDatabaseCount('channel_webhook_inbox', 1);
        $connection->update(['provider'=>'ical']);
        $this->assertSame(403, $this->webhook($connection->fresh(), 'evt-3')[0]);
    }

    public function test_signed_inbox_deduplicates_and_rejects_stale_and_changed_events_without_leaking_payload(): void
    {
        config(['reserva.channels.sandbox_webhooks_enabled'=>true]);
        [, , , $connection] = $this->fixture();
        $this->assertSame(202, $this->webhook($connection, 'evt-A')[0]);
        $this->assertSame(200, $this->webhook($connection, 'evt-A')[0]);
        $this->assertSame(409, $this->webhook($connection, 'evt-A', '{"action":"cancelled"}')[0]);
        $this->assertSame(403, $this->webhook($connection, 'evt-B', '{}', (string) (time()-400))[0]);
        $this->assertDatabaseCount('channel_webhook_inbox', 1);
        $row = ChannelWebhookInbox::query()->firstOrFail();
        $this->assertNotEquals('{"action":"confirmed"}', $row->encrypted_payload);
        $this->assertStringNotContainsString('confirmed', $row->toJson());
        $this->assertSame('received', $row->status); // no unauthorised inventory application
    }

    public function test_outbox_rolls_back_and_repeated_transaction_intents_are_idempotent(): void
    {
        config(['reserva.channels.sandbox_webhooks_enabled'=>true]);
        [, , , $connection] = $this->fixture();
        $service = app(ChannelOutboxService::class);
        try {
            DB::transaction(function () use ($service,$connection): void {
                $service->enqueue($connection,'inventory.changed','same-1',['qty'=>2]);
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException $ignored) {}
        $this->assertDatabaseCount('channel_outbox', 0);
        $first = $service->enqueue($connection, 'inventory.changed', 'same-1', ['qty'=>2]);
        $second = $service->enqueue($connection, 'inventory.changed', 'same-1', ['qty'=>2]);
        $this->assertSame($first->id, $second->id);
        $this->assertSame('pending', ChannelOutboxEvent::query()->firstOrFail()->status);
        try {
            $service->enqueue($connection, 'inventory.changed', 'same-1', ['qty'=>3]);
            $this->fail('The same event key must reject conflicting payloads.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('event', $exception->errors());
        }
        $connection->forceFill(['is_active'=>false,'status'=>'disconnected'])->save();
        try {
            $service->enqueue($connection, 'inventory.changed', 'next-1', ['qty'=>2]);
            $this->fail('Disconnected providers must not publish.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('provider', $exception->errors());
        }
    }

    public function test_channel_mapping_readiness_is_scoped_by_owner_and_fails_without_currency_or_sync_evidence(): void
    {
        [$owner,$property,$room,$connection] = $this->fixture('ical');
        $this->assertTrue(app(ChannelMappingAuditService::class)->inspect($connection)['ready_for_ical_reconciliation']);
        $this->assertFalse(app(ChannelMappingAuditService::class)->inspect($connection)['certified_for_ari_publication']);
        $stranger = User::factory()->create(['email_verified_at'=>now()]);
        $this->actingAs($stranger)->get(route('user.owner.channels.health',$connection))->assertNotFound();
        $this->actingAs($owner)->get(route('user.owner.channels.health',$connection))->assertOk();
        $room->update(['currency'=>'USD']);
        $result=app(ChannelMappingAuditService::class)->inspect($connection->fresh());
        $this->assertFalse($result['ready_for_ical_reconciliation']);
        $this->assertNotEmpty($result['issues']);
    }

    public function test_fx_fixed_point_rounding_and_currency_precision_cannot_use_floats(): void
    {
        $fx = app(FxQuoteService::class);
        $this->assertSame(15000000, $fx->convertMinor(10000,'USD','NGN','1500.00000000'));
        $this->assertSame(1, $fx->convertMinor(1,'USD','USD','0.50000000'));
        $this->assertSame(50, $fx->convertMinor(100,'USD','JPY','50.00000000'));
        $this->assertSame(12345, $fx->convertMinor(12345,'USD','KWD','0.10000000'));
        $this->expectException(ValidationException::class);
        $fx->convertMinor(PHP_INT_MAX,'USD','KWD','9999.00000000');
    }

    public function test_fx_rate_snapshot_is_immutable_and_stale_sources_fail_closed(): void
    {
        $rate = FxReferenceRate::query()->create([
            'base_currency'=>'USD','quote_currency'=>'NGN',
            'units_per_base'=>'1500.12345678','source'=>'operator-reviewed',
            'source_reference'=>'manual-fixture-1','observed_at'=>now()->subMinute(),
            'expires_at'=>now()->addMinutes(15),
        ]);
        $fx = app(FxQuoteService::class);
        $quote = $fx->lock($rate, 12345);
        $this->assertSame(18519024, (int) $quote->quote_minor);
        $this->assertNotNull($quote->expires_at);
        $rate->update(['units_per_base'=>'1000.00000000']);
        $this->assertSame('1500.12345678', (string) $quote->fresh()->locked_rate);
        $rate->update(['expires_at'=>now()->subSecond()]);
        $this->expectException(ValidationException::class);
        $fx->lock($rate->fresh(), 12345);
    }

    public function test_language_selection_persists_to_user_and_invalid_locale_fails_closed(): void
    {
        $user=User::factory()->create(['email_verified_at'=>now()]);
        $this->actingAs($user)->post(route('language.update'),['locale'=>'fr'])->assertRedirect();
        $this->assertSame('fr', $user->fresh()->locale);
        $this->assertSame('fr', session('locale'));
        $this->get(route('home'))->assertOk();
        $this->assertSame('Appartements', __('resarva.nav.apartments'));
        $this->post(route('language.update'),['locale'=>'invalid-code'])->assertSessionHasErrors('locale');
        $this->assertSame('fr', $user->fresh()->locale);
    }

    public function test_yield_advice_uses_confirmed_demand_with_bounded_owner_only_suggestions(): void
    {
        [$owner,$property,$room] = $this->fixture('ical');
        $from=CarbonImmutable::today()->addDays(10);
        for ($i=0; $i<5; $i++) {
            Booking::factory()->create([
                'property_id'=>$property->id, 'accommodation_type_id'=>$room->id,
                'check_in'=>$from->addDays($i)->toDateString(),
                'check_out'=>$from->addDays($i+1)->toDateString(),
                'rooms'=>1, 'status'=>'confirmed',
            ]);
        }
        $result=app(OwnerYieldAdvisor::class)->preview($room,$from,$from->addDays(7),19000,21000);
        $this->assertTrue($result['eligible']);
        $this->assertGreaterThanOrEqual(19000, $result['suggested_rate']);
        $this->assertLessThanOrEqual(21000, $result['suggested_rate']);
        $this->assertSame(20000.0, (float) $room->fresh()->base_rate);
        $this->assertFalse($result['applied']);
    }

    public function test_yield_advice_never_changes_prices_and_requires_owner_authorization(): void
    {
        [$owner, $property, $room] = $this->fixture('ical');
        $from=CarbonImmutable::today()->addDays(10);
        $route=route('user.owner.commercial.yield-preview',[$property,$room]);
        $args='?from_date='.$from->toDateString().'&to_date='.$from->addDays(8)->toDateString();
        $this->actingAs(User::factory()->create(['email_verified_at'=>now()]))
            ->get($route.$args)->assertNotFound();
        $result=$this->actingAs($owner)->get($route.$args)->assertOk()->json();
        $this->assertFalse($result['eligible']);
        $this->assertNull($result['suggested_rate']);
        $this->assertFalse($result['applied']);
        $this->assertEquals(20000, $room->fresh()->base_rate);
    }
}
