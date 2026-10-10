<?php

namespace Tests\Feature\PhaseFour;

use App\Models\Booking;
use App\Models\DiningPartner;
use App\Models\DiningRequest;
use App\Models\TravelOffer;
use App\Models\TravelSupplier;
use App\Models\TripAssembly;
use App\Models\TripItinerary;
use App\Models\User;
use App\Services\Travel\DiningConciergeService;
use App\Services\Travel\MobileDemandGate;
use App\Services\Travel\TripAccountingService;
use App\Services\Travel\TripAssemblyService;
use App\Services\Travel\TripRecoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\FakeDiningReservationVerifier;
use App\Services\Travel\DiningReservationVerificationService;
use Tests\TestCase;

final class PhaseFourBatchTwoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('travel.dining_enabled', true);
        config()->set('travel.trip_assembly_enabled', true);
    }

    private function partner(): DiningPartner
    {
        return DiningPartner::query()->create([
            'name'=>'Example dining partner','integration_key'=>'sandbox','status'=>'published',
            'latitude'=>6.5244,'longitude'=>3.3792,'address'=>'15 Market Street',
            'city'=>'Lagos','timezone'=>'Africa/Lagos','disclosures'=>'Reservation not guaranteed.',
            'dietary_options'=>['vegetarian'],'accessibility'=>['step-free'],
            'details_verified_at'=>now(),'reviewed_by'=>User::factory()->create()->id,
        ]);
    }

    public function test_dining_concierge_is_idempotent_and_does_not_confirm_or_charge(): void
    {
        $guest=User::factory()->create();
        $trip=TripItinerary::query()->create(['user_id'=>$guest->id,'name'=>'Long weekend']);
        $partner=$this->partner();
        $input=['idempotency_key'=>(string)Str::uuid(),'party_size'=>2,
            'requested_for'=>now()->addDays(2)->toDateTimeString(),
            'trip_itinerary_id'=>$trip->id,'dietary_notes'=>'Private allergy note'];
        $service=app(DiningConciergeService::class);
        $first=$service->create($guest,$partner,$input);
        $again=$service->create($guest,$partner,$input);
        $this->assertSame($first->id,$again->id);
        $this->assertSame('pending_concierge',$first->status);
        $this->assertNull($first->provider_reference);
        $this->assertFalse($first->supplier_share_consent);
        $this->assertSame('Private allergy note',$first->private_preferences['dietary_notes']);
        $this->assertStringNotContainsString('allergy', (string)\DB::table('dining_requests')
            ->where('id',$first->id)->value('private_preferences'));
        $this->assertDatabaseCount('dining_requests',1);
        $this->assertDatabaseCount('payments',0);
        $cancel=$service->cancel($guest,$first);
        $this->assertSame('cancelled',$cancel->status);
        $this->assertSame('cancelled',$service->cancel($guest,$first)->status);
    }

    public function test_foreign_itinerary_and_stale_partner_are_rejected(): void
    {
        $guest=User::factory()->create();
        $stranger=User::factory()->create();
        $trip=TripItinerary::query()->create(['user_id'=>$stranger->id,'name'=>'Private trip']);
        $partner=$this->partner();
        $payload=['idempotency_key'=>(string)Str::uuid(),'party_size'=>2,
            'requested_for'=>now()->addDay()->toDateTimeString(),'trip_itinerary_id'=>$trip->id];
        try {
            app(DiningConciergeService::class)->create($guest,$partner,$payload);
            $this->fail('Foreign trip must be denied');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('trip_itinerary_id',$e->errors());
        }
        $partner->update(['details_verified_at'=>now()->subDays(100)]);
        unset($payload['trip_itinerary_id']);
        try {
            app(DiningConciergeService::class)->create($guest,$partner,$payload);
            $this->fail('Stale partner cannot receive enquiries');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('dining_partner_id',$e->errors());
        }
        $this->assertDatabaseCount('dining_requests',0);
    }

    public function test_trip_assembly_is_idempotent_and_rechecks_changed_components_without_mutation(): void
    {
        $guest=User::factory()->create();
        $staff=User::factory()->create();
        $trip=TripItinerary::query()->create(['user_id'=>$guest->id,'name'=>'Multi-product trip']);
        $stay=Booking::factory()->create(['user_id'=>$guest->id,
            'trip_itinerary_id'=>$trip->id,'currency'=>'USD','total'=>230.50,'status'=>'confirmed']);
        $partner=$this->partner();
        $dining=app(DiningConciergeService::class)->create($guest,$partner,[
            'idempotency_key'=>(string)Str::uuid(),
            'party_size'=>2,'requested_for'=>now()->addDays(2)->toDateTimeString(),
            'trip_itinerary_id'=>$trip->id,
        ]);
        $snap=app(TripAccountingService::class)->snapshot($trip);
        $this->assertCount(2,$snap['items']);
        $this->assertSame(23050,$snap['totals']['USD']['quoted_minor']);
        $this->assertFalse($snap['can_charge_bundle']);

        $key=(string)Str::uuid();
        $service=app(TripAssemblyService::class);
        $first=$service->prepare($guest,$trip,$key);
        $same=$service->prepare($guest,$trip,$key);
        $this->assertSame($first->id,$same->id);
        $this->assertSame(1,TripAssembly::query()->count());
        $this->assertSame('ready_for_manual_review',$service->recheck($staff,$first)->status);
        $dining->update(['status'=>'cancelled','cancelled_at'=>now()]);
        $this->assertSame('reconciliation_required',$service->recheck($staff,$first)->status);
        $this->assertSame('confirmed',$stay->fresh()->status);
        $this->assertDatabaseCount('trip_assembly_events',3);
        $this->assertDatabaseCount('payments',0);
    }

    public function test_trip_cannot_be_assembled_by_other_user(): void
    {
        $guest=User::factory()->create();
        $other=User::factory()->create();
        $trip=TripItinerary::query()->create(['user_id'=>$guest->id,'name'=>'Private trip']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(TripAssemblyService::class)->prepare($other,$trip,(string)Str::uuid());
    }

    public function test_real_provider_confirmation_requires_certified_adapter_consent_and_matching_record(): void
    {
        $staff=User::factory()->create(['is_admin'=>true]);
        $guest=User::factory()->create();
        $partner=$this->partner();
        $service=app(DiningConciergeService::class);
        $input=['idempotency_key'=>(string)Str::uuid(),'party_size'=>2,
            'requested_for'=>now()->addDays(2)->toDateTimeString()];
        $request=$service->create($guest,$partner,$input);
        config()->set('travel.dining_provider_confirmation_enabled',true);
        config()->set('travel.dining_adapters',['sandbox'=>FakeDiningReservationVerifier::class]);
        try {
            app(DiningReservationVerificationService::class)->verify($staff,$request,'partner-table-009');
            $this->fail('Cannot confirm before customer consents to supplier handoff');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('provider',$e->errors());
        }
        $this->assertSame('pending_concierge',$request->fresh()->status);
        $request->update(['supplier_share_consent'=>true]);
        $confirmed=app(DiningReservationVerificationService::class)->verify($staff,$request,'partner-table-009');
        $this->assertSame('confirmed',$confirmed->status);
        $again=app(DiningReservationVerificationService::class)->verify($staff,$request,'partner-table-009');
        $this->assertSame('confirmed',$again->status);
        $this->assertDatabaseCount('dining_request_events',1);

        $service->cancel($guest,$request);
        $this->assertSame('cancellation_requested',$request->fresh()->status);
        config()->set('travel.dining_test_state','cancelled');
        $cancelled=app(DiningReservationVerificationService::class)->verify($staff,$request,'partner-cancelled-009');
        $this->assertSame('cancelled',$cancelled->status);
        $this->assertSame('partner-table-009',$cancelled->provider_reference);
        $this->assertSame('cancelled',app(DiningReservationVerificationService::class)
            ->verify($staff,$request,'partner-cancelled-009')->status);
        $this->assertDatabaseCount('dining_request_events',2);
        $this->assertDatabaseCount('payments',0);
        $this->assertDatabaseCount('refunds',0);
    }

    public function test_unlicensed_dining_partner_cannot_be_marked_confirmed(): void
    {
        $staff=User::factory()->create(['is_admin'=>true]);
        $guest=User::factory()->create();
        $partner=$this->partner();
        $request=app(DiningConciergeService::class)->create($guest,$partner,[
            'idempotency_key'=>(string)Str::uuid(),'party_size'=>2,
            'requested_for'=>now()->addDay()->toDateTimeString(),
            'supplier_share_consent'=>true,
        ]);
        config()->set('travel.dining_provider_confirmation_enabled',true);
        config()->set('travel.dining_adapters',[]);
        $this->expectException(ValidationException::class);
        app(DiningReservationVerificationService::class)->verify($staff,$request,'uncertified-reference');
    }

    public function test_trip_recovery_protects_preexisting_stay_and_never_confirms_unconfigured_supplier(): void
    {
        $guest=User::factory()->create();
        $staff=User::factory()->create();
        $trip=TripItinerary::query()->create(['user_id'=>$guest->id,'name'=>'Recovery trip']);
        $stay=Booking::factory()->create(['user_id'=>$guest->id,
            'trip_itinerary_id'=>$trip->id,'status'=>'confirmed']);
        $partner=$this->partner();
        app(DiningConciergeService::class)->create($guest,$partner,[
            'idempotency_key'=>(string)Str::uuid(),'party_size'=>2,
            'requested_for'=>now()->addDays(2)->toDateTimeString(),
            'trip_itinerary_id'=>$trip->id,
        ]);
        $assembly=app(TripAssemblyService::class)->prepare($guest,$trip,(string)Str::uuid());
        $recovery=app(TripRecoveryService::class);
        $this->assertSame(2,$recovery->prepare($assembly));
        $this->assertSame(0,$recovery->prepare($assembly));
        $steps=$assembly->steps()->get();
        $this->assertCount(2,$steps);
        $stayStep=$steps->firstWhere('item_type','stay');
        $this->assertSame('preexisting_stay',$stayStep->status);
        try {
            $recovery->reconcile($staff,$stayStep);
            $this->fail('Accommodation may not be changed by package recovery');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('step',$e->errors());
        }
        $diningStep=$steps->firstWhere('item_type','dining');
        $this->assertSame('needs_provider',$recovery->reconcile($staff,$diningStep)->status);
        $this->assertSame('compensation_requested',$recovery->requestCompensation($staff,$diningStep)->status);
        $this->assertSame(3,(int)\DB::table('trip_assembly_events')
            ->where('trip_assembly_id',$assembly->id)->count());
        $this->assertSame('confirmed',$stay->fresh()->status);
        $this->assertDatabaseCount('payments',0);
        $this->assertDatabaseCount('refunds',0);
    }

    public function test_dining_reminders_are_once_only_and_only_for_verified_reservations(): void
    {
        $guest=User::factory()->create();
        $partner=$this->partner();
        $item=app(DiningConciergeService::class)->create($guest,$partner,[
            'idempotency_key'=>(string)Str::uuid(),'party_size'=>2,
            'requested_for'=>now()->addHours(4)->toDateTimeString(),
            'supplier_share_consent'=>true,
        ]);
        \Illuminate\Support\Facades\Artisan::call('resavar:dining-arrival-reminders');
        $this->assertSame(0,$guest->fresh()->notifications()->count());
        $item->update(['status'=>'confirmed','provider_confirmed_at'=>now(),
            'provider_reference'=>'partner-table-123']);
        \Illuminate\Support\Facades\Artisan::call('resavar:dining-arrival-reminders');
        $this->assertSame(1,$guest->fresh()->notifications()->count());
        \Illuminate\Support\Facades\Artisan::call('resavar:dining-arrival-reminders');
        $this->assertSame(1,$guest->fresh()->notifications()->count());
        $this->assertNotNull($item->fresh()->arrival_reminder_sent_at);
    }

    public function test_nearby_dining_recommendations_enforce_stay_ownership(): void
    {
        $guest=User::factory()->create(['email_verified_at'=>now()]);
        $stranger=User::factory()->create(['email_verified_at'=>now()]);
        $stay=Booking::factory()->create(['user_id'=>$guest->id,
            'property_latitude'=>6.5244,'property_longitude'=>3.3792]);
        $near=$this->partner();
        $far=$this->partner();
        $far->update(['name'=>'Far-away venue','latitude'=>9.0765,'longitude'=>7.3986]);
        $this->actingAs($guest)->get(route('user.dining.index',['near_stay'=>$stay->id]))
            ->assertOk()->assertSee($near->name)->assertDontSee('Far-away venue');
        $this->actingAs($stranger)->get(route('user.dining.index',['near_stay'=>$stay->id]))
            ->assertNotFound();
    }

    public function test_mobile_native_gate_fails_closed_without_measured_pwa_demand(): void
    {
        config()->set('travel.native_product_approved',true);
        $result=app(MobileDemandGate::class)->evaluate();
        $this->assertFalse($result['native_code_authorized']);
        $this->assertFalse($result['observed_cohort']);
        $this->assertSame(0,$result['pwa_attributed_paid_bookings']);
    }

    public function test_disabled_dining_routes_do_not_advertise_live_tables(): void
    {
        $guest=User::factory()->create(['email_verified_at'=>now()]);
        config()->set('travel.dining_enabled',false);
        $this->actingAs($guest)->get(route('user.dining.index'))->assertNotFound();
        $this->actingAs($guest)->post(route('user.dining.store'),[])->assertNotFound();
    }
}
