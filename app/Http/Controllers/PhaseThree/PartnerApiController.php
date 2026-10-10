<?php

namespace App\Http\Controllers\PhaseThree;

use App\Http\Controllers\Controller;
use App\Services\PhaseThree\PartnerAccessService;
use App\Services\Search\MarketplaceSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** V1 contracted partner discovery and redirect-only booking intent. No unaudited booking/payment path. */
final class PartnerApiController extends Controller
{
    private function filters(Request $request): array
    {
        return $request->validate([
            'check_in' => ['required','date','after_or_equal:today'],
            'check_out' => ['required','date','after:check_in'],
            'adults' => ['required','integer','min:1','max:12'],
            'children' => ['sometimes','integer','min:0','max:8'],
            'rooms' => ['sometimes','integer','min:1','max:5'],
            'destination' => ['sometimes','string','max:120'],
            'property_id' => ['sometimes','integer','min:1'],
            'page' => ['sometimes','integer','min:1','max:200'],
        ]);
    }

    public function search(Request $request, PartnerAccessService $access, MarketplaceSearchService $market): JsonResponse
    {
        $partner = $access->requireScope($request, 'stays.read');
        $filters = $this->filters($request);
        $results = $market->search($filters, false)['results'];
        return response()->json([
            'version' => '1', 'partner_id' => $partner->id, 'page' => $results->currentPage(),
            'has_more' => $results->hasMorePages(),
            'stays' => $results->getCollection()->take(30)->map(fn (array $row) => [
                'property_id' => $row['property']->id,
                'name' => $row['property']->name,
                'url' => route('properties.show', $row['property']),
                'currency' => $row['quote']['currency'],
                'total' => $row['quote']['total'],
                'check_in' => $filters['check_in'], 'check_out' => $filters['check_out'],
            ])->values(),
        ]);
    }

    public function intent(Request $request, PartnerAccessService $access, MarketplaceSearchService $market): JsonResponse
    {
        $partner = $access->requireScope($request, 'intents.create');
        $filters = $this->filters($request);
        $request->validate(['idempotency_key' => ['required','string','min:16','max:100']]);
        $key = $request->input('idempotency_key');
        $payload = collect($filters)->except('page')->sortKeys()->all();
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $intent = DB::transaction(function () use ($partner, $key, $hash, $payload, $market): object {
            // Database unique key makes retries idempotent, and the row lock
            // protects a different payload attempting reuse of the same key.
            DB::table('partner_clients')->where('id', $partner->id)->lockForUpdate()->first();
            $existing = DB::table('partner_booking_intents')
                ->where('partner_client_id', $partner->id)->where('idempotency_key', $key)
                ->lockForUpdate()->first();
            if ($existing) {
                if (strtotime($existing->expires_at) <= time()) {
                    throw ValidationException::withMessages(['idempotency_key' => 'Booking intent expired; start a new request with a new key.']);
                }
                if (! hash_equals($existing->payload_hash, $hash)) {
                    throw ValidationException::withMessages(['idempotency_key' => 'Key reused with different stay details.']);
                }
                return $existing;
            }
            $stays = $market->search($payload, false)['results']->getCollection();
            if ($stays->isEmpty()) {
                throw ValidationException::withMessages(['property_id' => 'No currently bookable stay matches this request.']);
            }
            $id = DB::table('partner_booking_intents')->insertGetId([
                'partner_client_id' => $partner->id,
                'idempotency_key' => $key,
                'payload_hash' => $hash,
                'filters' => json_encode($payload, JSON_THROW_ON_ERROR),
                'property_id' => $stays->first()['property']->id,
                'status' => 'pending_guest_checkout',
                'expires_at' => now()->addMinutes(15),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            return DB::table('partner_booking_intents')->where('id', $id)->first();
        }, 3);
        // An intent is NOT a reservation or payment authorization. Requote at checkout.
        return response()->json([
            'intent_id' => $intent->id, 'status' => $intent->status,
            'expires_at' => $intent->expires_at,
            'checkout_requires_guest_confirmation' => true,
            'property_url' => $intent->property_id
                ? route('properties.show', $intent->property_id)
                : null,
        ], 200);
    }
}
