<?php

namespace Tests\Feature\PhaseThree;

use App\Models\AccommodationType;
use App\Models\AnalyticsEvent;
use App\Models\CommunicationPreference;
use App\Models\OwnerLedgerEntry;
use App\Models\Property;
use App\Models\RecentlyViewedProperty;
use App\Models\User;
use App\Services\PhaseThree\CommissionAgreementService;
use App\Services\PhaseThree\LifecycleCampaignService;
use App\Services\PhaseThree\MarketplaceFunnelInsights;
use App\Services\PhaseThree\OwnerStatementService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PhaseThreeFinalFiveTest extends TestCase
{
    use RefreshDatabase;

    private function stay(): array
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['owner_id' => $owner->id, 'currency'=>'USD',
            'status'=>'active', 'is_published'=>true]);
        $type = AccommodationType::query()->create([
            'property_id'=>$property->id, 'name'=>'Garden Suite','slug'=>'garden-suite',
            'code'=>'P3LAST-'.$property->id,'adult_capacity'=>2,'child_capacity'=>0,
            'max_guests'=>2,'total_inventory'=>3,'base_rate'=>120,'currency'=>'USD',
            'is_active'=>true,'is_published'=>true,
        ]);
        return [$owner, $property, $type];
    }

    private function partner(array $scopes = ['stays.read','intents.create'], bool $active = true): string
    {
        $key = Str::random(50);
        DB::table('partner_clients')->insert([
            'name'=>'Approved test client', 'key_hash'=>hash('sha256',$key),
            'scopes'=>json_encode($scopes), 'is_active'=>$active,
            'created_at'=>now(), 'updated_at'=>now(),
        ]);
        return $key;
    }

    private function searchPayload(?Property $property = null): array
    {
        $data = [
            'check_in'=>now()->addDays(14)->toDateString(),
            'check_out'=>now()->addDays(16)->toDateString(),
            'adults'=>2, 'rooms'=>1,
        ];
        if ($property) $data['property_id'] = $property->id;
        return $data;
    }

    public function test_partner_api_rejects_unapproved_missing_or_wrong_scope_keys(): void
    {
        $payload = $this->searchPayload();
        $this->getJson(route('partner.v1.stays', $payload))->assertUnauthorized();
        $inactive = $this->partner(['stays.read'], false);
        $this->withToken($inactive)->getJson(route('partner.v1.stays', $payload))->assertUnauthorized();
        $scoped = $this->partner(['intents.create']);
        $this->withToken($scoped)->getJson(route('partner.v1.stays', $payload))->assertForbidden();
    }

    public function test_partner_only_receives_public_quote_without_guest_pii_or_secret(): void
    {
        [, $property] = $this->stay();
        $key = $this->partner();
        $response = $this->withToken($key)->getJson(route('partner.v1.stays', $this->searchPayload($property)));
        $response->assertOk()->assertJsonStructure(['stays']);
        $body = $response->json();
        $this->assertArrayNotHasKey('owner_id', $body['stays'][0] ?? []);
        $this->assertStringNotContainsString($key, $response->getContent());
        $this->assertSame('1', $body['version']);
    }

    public function test_partner_intent_is_idempotent_and_never_claims_booking_confirmed(): void
    {
        [, $property] = $this->stay();
        $key = $this->partner();
        $payload = $this->searchPayload($property) + ['idempotency_key'=>'tested-retry-key-123'];
        $first = $this->withToken($key)->postJson(route('partner.v1.intents'), $payload);
        $first->assertOk()->assertJsonPath('status', 'pending_guest_checkout');
        $this->withToken($key)->postJson(route('partner.v1.intents'), $payload)
            ->assertOk()->assertJsonPath('intent_id', $first->json('intent_id'));
        $this->withToken($key)->postJson(route('partner.v1.intents'),
            [...$payload, 'rooms'=>2])->assertUnprocessable();
        $this->assertDatabaseCount('partner_booking_intents', 1);
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_campaign_requires_positive_consent_and_suppresses_unsubscribe(): void
    {
        $guest = User::factory()->create(['marketing_consent'=>true]);
        CommunicationPreference::query()->create(['user_id'=>$guest->id,'email_marketing'=>true]);
        $id = DB::table('marketing_campaigns')->insertGetId([
            'name'=>'Weekend escapes','template_key'=>'approved-weekend',
            'subject'=>'Available stays','status'=>'approved',
            'approved_at'=>now(),'created_at'=>now(),'updated_at'=>now(),
        ]);
        $service = app(LifecycleCampaignService::class);
        $this->assertTrue($service->reserve($guest,$id,'saved-search:123'));
        $this->assertFalse($service->reserve($guest,$id,'saved-search:123'));
        $this->assertFalse($service->reserve($guest,$id,'saved-search:456'));
        $this->assertSame(1,$service->metrics($id)['reserved']);
        $this->assertSame(1,$service->suppressOnOptOut($guest));
        $guest->forceFill(['marketing_consent'=>false])->save();
        $this->assertFalse($service->reserve($guest,$id,'saved-search:789'));
        $this->assertSame(1,$service->metrics($id)['suppressed']);
    }

    public function test_unapproved_marketing_does_not_enqueue_messages(): void
    {
        $guest=User::factory()->create(['marketing_consent'=>true]);
        CommunicationPreference::query()->create(['user_id'=>$guest->id,'email_marketing'=>true]);
        $id=DB::table('marketing_campaigns')->insertGetId([
            'name'=>'Draft','template_key'=>'draft','subject'=>'Not ready',
            'status'=>'draft','created_at'=>now(),'updated_at'=>now(),
        ]);
        $this->assertFalse(app(LifecycleCampaignService::class)->reserve($guest,$id,'event-1234'));
        $this->assertDatabaseCount('marketing_deliveries',0);
    }

    public function test_experiment_assignment_is_stable_and_funnel_is_versioned(): void
    {
        $insights=app(MarketplaceFunnelInsights::class);
        $this->assertSame($insights->assignExperiment('relevance-v1','guest:2'),
            $insights->assignExperiment('relevance-v1','guest:2'));
        $report=$insights->funnel(CarbonImmutable::today()->subDay(),CarbonImmutable::today()->endOfDay());
        $this->assertSame(1,$report['schema_version']);
        $this->assertSame(0,$report['verified_paid_bookings']);
        $this->assertArrayHasKey('checkout_started',$report['events']);
    }

    public function test_statement_is_owner_scoped_and_separates_currencies(): void
    {
        [$owner,$property]=$this->stay();
        $other=User::factory()->create();
        $entry=[
            'property_id'=>$property->id,'type'=>'booking_earning','direction'=>'credit',
            'amount'=>'37.25','currency'=>'USD','reference'=>'P3-LEDGER-'.Str::random(8),
            'description'=>'Verified owner earning',
        ];
        OwnerLedgerEntry::query()->create($entry+['user_id'=>$owner->id]);
        OwnerLedgerEntry::query()->create([...$entry,'user_id'=>$other->id,
            'reference'=>'P3-OTHER-'.Str::random(8), 'amount'=>'50.00']);
        $from=CarbonImmutable::today()->startOfDay();$through=CarbonImmutable::today()->endOfDay();
        $service=app(OwnerStatementService::class);
        $this->assertSame(3725,$service->totals($owner,'USD',$from,$through)['net_minor']);
        $this->assertSame(5000,$service->totals($other,'USD',$from,$through)['net_minor']);
        $this->assertSame(0,$service->totals($owner,'NGN',$from,$through)['entries']);
        $this->actingAs($owner)->get(route('user.owner-statement.csv',[
            'from'=>$from->toDateString(),'to'=>$through->toDateString(),'currency'=>'USD',
        ]))->assertOk()->assertHeader('content-type','text/csv; charset=UTF-8');
    }

    public function test_commission_contract_versions_are_future_effective_and_owner_bound(): void
    {
        [$owner,$property]=$this->stay();
        $staff=User::factory()->create();
        $service=app(CommissionAgreementService::class);
        $first=$service->approve($property,$staff,300,CarbonImmutable::today()->addDays(7));
        $this->assertSame(1,(int) $first->version);
        $second=$service->approve($property,$staff,400,CarbonImmutable::today()->addDays(14));
        $this->assertSame(2,(int) $second->version);
        $this->assertSame($owner->id,(int) $second->owner_id);
        try {
            $service->approve($property,$staff,700,CarbonImmutable::today()->addDays(10));
            $this->fail('Retroactive version should be rejected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('effective_from',$e->errors());
        }
    }

    public function test_recent_property_tracking_is_opt_out_aware_and_guest_can_clear(): void
    {
        [$owner,$property]=$this->stay();
        $guest=User::factory()->create();
        $this->actingAs($guest)->get(route('properties.show',$property))->assertOk();
        $this->assertDatabaseHas('recently_viewed_properties',['user_id'=>$guest->id,'property_id'=>$property->id]);
        $this->actingAs($guest)->patch(route('user.recommendations.preferences'),[
            'personalization_opt_out'=>1,
        ])->assertRedirect();
        $this->assertDatabaseMissing('recently_viewed_properties',['user_id'=>$guest->id]);
        $this->actingAs($guest)->get(route('properties.show',$property))->assertOk();
        $this->assertDatabaseMissing('recently_viewed_properties',['user_id'=>$guest->id]);
        $this->actingAs($owner)->delete(route('user.recommendations.clear'))->assertRedirect();
    }

    public function test_recommendations_are_guest_scoped_and_use_canonical_available_quotes(): void
    {
        [, $property]=$this->stay();
        $guest=User::factory()->create();
        $other=User::factory()->create();
        RecentlyViewedProperty::query()->create([
            'user_id'=>$guest->id,'property_id'=>$property->id,'viewed_at'=>now(),
        ]);
        $filters=$this->searchPayload();unset($filters['property_id']);
        $first=$this->actingAs($guest)->getJson(route('user.recommendations.index',$filters));
        $first->assertOk()->assertJsonPath('personalized',true);
        $otherReply=$this->actingAs($other)->getJson(route('user.recommendations.index',$filters));
        $otherReply->assertOk()->assertJsonPath('personalized',true);
        $guestItems=$first->json('items');
        $this->assertContains('Recently viewed by you',array_column($guestItems,'reason'));
        $this->assertNotContains('Recently viewed by you',array_column($otherReply->json('items'),'reason'));
    }
}
