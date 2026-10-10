<?php

namespace Tests\Feature\Architecture;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\TripItinerary;
use App\Models\TripAssemblyStep;
use App\Models\User;
use App\Services\Travel\TripAccountingService;
use App\Services\Travel\TripAssemblyService;
use App\Services\Travel\TripRecoveryService;
use App\Support\MinorMoney;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ResavarAuditRemediationTest extends TestCase
{
    use RefreshDatabase;

    public function test_minor_currency_units_are_exact_and_never_default_to_two_decimal_places(): void
    {
        $this->assertSame(125, MinorMoney::toMinor('125', 'JPY'));
        $this->assertSame('125', MinorMoney::display(125, 'JPY'));
        $this->assertSame(12525, MinorMoney::toMinor('125.25', 'USD'));
        $this->assertSame('125.25', MinorMoney::display(12525, 'USD'));
        $this->assertSame(125255, MinorMoney::toMinor('125.255', 'KWD'));
        $this->assertSame('125.255', MinorMoney::display(125255, 'KWD'));
        $this->expectException(ValidationException::class);
        MinorMoney::toMinor('125.256', 'USD');
    }

    public function test_trip_ledger_refunds_only_booking_allocated_payments_not_excess(): void
    {
        $guest=User::factory()->create();
        $trip=TripItinerary::query()->create(['user_id'=>$guest->id,'name'=>'Refund audit']);
        $stay=Booking::factory()->create(['user_id'=>$guest->id,
            'trip_itinerary_id'=>$trip->id,'currency'=>'USD','total'=>'100.00','status'=>'confirmed']);
        Payment::query()->create(['booking_id'=>$stay->id,'reference'=>'BOOKED-PAY-1',
            'provider'=>'manual','status'=>Payment::SUCCESSFUL,
            'amount'=>'100.00','currency'=>'USD','verified_at'=>now()]);
        $excess=Payment::query()->create(['booking_id'=>$stay->id,'reference'=>'BOOKED-PAY-EXCESS',
            'provider'=>'manual','status'=>'successful_excess',
            'amount'=>'20.00','currency'=>'USD','verified_at'=>now()]);
        Refund::query()->create(['booking_id'=>$stay->id,'payment_id'=>$excess->id,
            'reference'=>'RF-EXCESS-100','amount'=>'20.00','currency'=>'USD','status'=>'successful']);
        $this->assertEquals(0,$stay->successfulRefundsTotal());
        $row=app(TripAccountingService::class)->snapshot($trip)['totals']['USD'];
        $this->assertSame(10000,$row['collected_minor']);
        $this->assertSame(0,$row['refunded_minor']);
        $this->assertSame(10000,$row['net_collected_minor']);
    }

    public function test_legacy_booking_payment_proof_respects_existing_booking_decimal_precision(): void
    {
        $guest=User::factory()->create();
        $trip=TripItinerary::query()->create(['user_id'=>$guest->id,'name'=>'Legacy audit']);
        $stay=Booking::factory()->create(['user_id'=>$guest->id,
            'trip_itinerary_id'=>$trip->id,'currency'=>'KWD', 'total'=>'250.125',
            'status'=>'paid','paid_at'=>now(),'payment_reference'=>'LEGACY-CAPTURE-ONE']);
        $row=app(TripAccountingService::class)->snapshot($trip);
        $this->assertSame(250130,$row['totals']['KWD']['quoted_minor']);
        $this->assertSame(250130,$row['totals']['KWD']['collected_minor']);
        $this->assertSame('legacy_record',$row['items'][0]['collection_evidence']);
    }

    public function test_crashed_trip_check_can_only_be_reclaimed_after_lease_expiry_and_bounded_attempts(): void
    {
        $guest=User::factory()->create();
        $staff=User::factory()->create();
        $trip=TripItinerary::query()->create(['user_id'=>$guest->id,'name'=>'Recovery audit']);
        $stay=Booking::factory()->create(['user_id'=>$guest->id, 'trip_itinerary_id'=>$trip->id]);
        $assembly=app(TripAssemblyService::class)->prepare($guest,$trip,(string)Str::uuid());
        $service=app(TripRecoveryService::class);
        $service->prepare($assembly);
        $step=$assembly->steps()->firstOrFail();
        $this->assertSame('preexisting_stay',$step->status);
        $step->update(['status'=>'checking','last_checked_at'=>now()->subDay(),'attempts'=>1]);
        $this->assertSame('needs_provider',$service->reconcile($staff,$step)->status);
        $step=$step->fresh();
        $step->update(['status'=>'checking','last_checked_at'=>now(),'attempts'=>2]);
        try { $service->reconcile($staff,$step); $this->fail('Fresh lease reclaimed'); }
        catch(ValidationException $e) { $this->assertArrayHasKey('step',$e->errors()); }
        $step=$step->fresh();
        $step->update(['status'=>'checking','last_checked_at'=>now()->subHour(),'attempts'=>8]);
        try { $service->reconcile($staff,$step); $this->fail('Exceeded bounded retry ceiling'); }
        catch(ValidationException $e) { $this->assertArrayHasKey('step',$e->errors()); }
        $this->assertSame('confirmed',$stay->fresh()->status);
    }

    public function test_jsonld_does_not_allow_script_breakout(): void
    {
        foreach (['public/properties/show','public/destinations/show','partials/public-seo'] as $view) {
            $src=file_get_contents(resource_path('views/'.$view.'.blade.php'));
            $this->assertStringContainsString('JSON_HEX_TAG', $src);
            $this->assertStringContainsString('JSON_HEX_QUOT', $src);
        }
        $raw=json_encode(['name'=>'</script><script>alert(1)</script>'],
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $this->assertStringNotContainsString('</script>', $raw);
    }

    public function test_search_and_account_deletion_contrast_rules_do_not_put_navy_ink_on_navy(): void
    {
        $deletion=file_get_contents(resource_path('views/public/account-deletion.blade.php'));
        $this->assertStringNotContainsString('background:#052058;color:#052058', $deletion);
        foreach (['public/search/results','public/search-results','public/availability_results','public/availability-results'] as $view) {
            $src=file_get_contents(resource_path('views/'.$view.'.blade.php'));
            $this->assertStringContainsString('background:#F2F5FA;color:#052058', $src);
        }
        $pdf=file_get_contents(resource_path('views/documents/booking-pdf.blade.php'));
        $this->assertStringNotContainsString('background: #052058;\n    color: #052058;', $pdf);
    }

    public function test_release_script_is_fail_closed_for_legacy_domains(): void
    {
        $script=file_get_contents(base_path('deploy-hostinger-production.sh'));
        $this->assertStringContainsString('exit 2',$script);
        $this->assertStringNotContainsString('ssh -p', $script);
    }
}
