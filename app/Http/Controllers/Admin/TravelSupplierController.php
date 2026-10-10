<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\TravelOffer;
use App\Models\TravelRequest;
use App\Models\TravelSupplier;
use App\Services\Travel\TravelRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class TravelSupplierController extends Controller
{
    /** Staff-only operations; no supplier secrets or private traveler documents in responses. */
    public function index(Request $request): JsonResponse|View
    {
        if (! $request->expectsJson()) {
            return view('admin.travel.index', [
                'suppliers' => TravelSupplier::query()->latest()->limit(100)->get(),
                'offers' => TravelOffer::query()->with('supplier')->latest()->limit(100)->get(),
                'pendingRequests' => TravelRequest::query()
                    ->whereIn('status', ['requested', 'supplier_acknowledged'])
                    ->where('expires_at', '>', now())
                    ->with('offer:id,title')->latest()->limit(50)->get(),
            ]);
        }

        return response()->json([
            'suppliers' => TravelSupplier::query()
                ->select('id', 'kind', 'name', 'status', 'contract_verified_at', 'safety_verified_at')
                ->latest()->paginate(30),
            'pending_requests' => TravelRequest::query()
                ->whereIn('status', ['requested', 'supplier_acknowledged'])
                ->where('expires_at', '>', now())
                ->with('offer:id,title')->latest()->limit(30)
                ->get(['id','travel_offer_id','kind','status','expires_at']),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['transfer', 'experience', 'car', 'flight'])],
            'name' => ['required', 'string', 'min:3', 'max:160'],
            'support_email' => ['required', 'email', 'max:180'],
            'terms_url' => ['required', 'url', 'starts_with:https://', 'max:500'],
            'approved_regions' => ['required', 'array', 'min:1', 'max:50'],
            'approved_regions.*' => ['required', 'string', 'max:100'],
        ]);
        $supplier = TravelSupplier::query()->create($data + ['status' => 'pending']);

        return $this->respond($request, ['id' => $supplier->id, 'status' => 'pending'], 201);
    }

    public function approve(Request $request, TravelSupplier $supplier): JsonResponse|RedirectResponse
    {
        $evidence = $request->validate([
            'contract_reference' => ['required', 'string', 'min:8', 'max:160'],
            'licence_reference' => ['required', 'string', 'min:8', 'max:160'],
            'insurance_reference' => ['required', 'string', 'min:8', 'max:160'],
            'operating_jurisdiction' => ['required', 'string', 'min:2', 'max:120'],
            'review_attestation' => ['required', Rule::in(['CONTRACT_AND_SAFETY_VERIFIED'])],
        ]);

        DB::transaction(function () use ($supplier, $request, $evidence): void {
            $locked = TravelSupplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['supplier' => 'Supplier must be pending review.']);
            }
            $locked->update([
                'compliance_evidence' => $evidence,
                'contract_verified_at' => now(),
                'safety_verified_at' => now(),
                'approved_by' => $request->user()->id,
                'status' => 'approved',
            ]);
            AuditLog::record('travel_supplier.approved', $locked,
                ['status' => 'pending'], ['status' => 'approved']);
        }, 3);

        return $this->respond($request, ['id' => $supplier->id, 'status' => 'approved']);
    }

    public function storeOffer(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'travel_supplier_id' => ['required', 'integer', 'exists:travel_suppliers,id'],
            'title' => ['required', 'string', 'min:5', 'max:180'],
            'origin' => ['nullable', 'string', 'max:160'],
            'destination' => ['nullable', 'string', 'max:160'],
            'timezone' => ['required', 'timezone'],
            'max_party' => ['required', 'integer', 'min:1', 'max:12'],
            'currency' => ['required', Rule::in(['USD','EUR','GBP','NGN','CAD'])],
            'base_minor' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'tax_minor' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'fee_minor' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'deposit_minor' => ['sometimes', 'integer', 'min:0', 'max:1000000000'],
            'starts_at' => ['nullable', 'date', 'after:now'],
            'expires_at' => ['required', 'date', 'after:now'],
            'terms' => ['required', 'array'],
            'terms.cancellation' => ['required', 'string', 'max:2500'],
            'terms.included' => ['required', 'string', 'max:2500'],
            'terms.disclosure' => ['required', 'string', 'max:2500'],
            'eligibility' => ['nullable', 'array', 'max:12'],
        ]);
        $supplier = TravelSupplier::query()->findOrFail($data['travel_supplier_id']);
        if (! $supplier->isApproved()) {
            throw ValidationException::withMessages(['travel_supplier_id' => 'Supplier must pass contract and safety review first.']);
        }
        $data['kind'] = $supplier->kind;
        $data['deposit_minor'] = $data['deposit_minor'] ?? 0;
        $offer = TravelOffer::query()->create($data);
        AuditLog::record('travel_offer.created', $offer, [], ['kind' => $offer->kind]);

        return $this->respond($request, ['id' => $offer->id, 'status' => 'draft'], 201);
    }

    public function publish(Request $request, TravelOffer $offer): JsonResponse|RedirectResponse
    {
        DB::transaction(function () use ($offer): void {
            $locked = TravelOffer::query()->with('supplier')
                ->whereKey($offer->id)->lockForUpdate()->firstOrFail();
            if (! $locked->supplier->isApproved() || $locked->expires_at->lte(now())) {
                throw ValidationException::withMessages(['offer' => 'Approved and unexpired supplier offers only.']);
            }
            if ($locked->kind === 'flight'
                && ! app(\App\Services\Travel\TravelPartnerGateway::class)->supports($locked->supplier)) {
                throw ValidationException::withMessages(['offer' => 'A certified airline distribution adapter is required before publication.']);
            }
            $locked->update(['published_at' => now()]);
            AuditLog::record('travel_offer.published', $locked, [],
                ['published_at' => $locked->published_at?->toIso8601String()]);
        }, 3);

        return $this->respond($request, ['id' => $offer->id, 'status' => 'published']);
    }


    public function pause(Request $request, TravelSupplier $supplier): JsonResponse|RedirectResponse
    {
        DB::transaction(function () use ($supplier): void {
            $locked = TravelSupplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'paused') {
                $before = $locked->status;
                $locked->update(['status' => 'paused']);
                AuditLog::record('travel_supplier.paused', $locked,
                    ['status' => $before], ['status' => 'paused']);
            }
        }, 3);

        return $this->respond($request, ['id' => $supplier->id, 'status' => 'paused']);
    }

    public function unpublish(Request $request, TravelOffer $offer): JsonResponse|RedirectResponse
    {
        DB::transaction(function () use ($offer): void {
            $locked = TravelOffer::query()->whereKey($offer->id)->lockForUpdate()->firstOrFail();
            if ($locked->published_at) {
                $locked->update(['published_at' => null]);
                AuditLog::record('travel_offer.unpublished', $locked,
                    ['published' => true], ['published' => false]);
            }
        }, 3);

        return $this->respond($request, ['id' => $offer->id, 'status' => 'unpublished']);
    }

    public function storeSlot(Request $request, TravelOffer $offer): JsonResponse|RedirectResponse
    {
        abort_unless($offer->kind === 'experience', 404);
        $data = $request->validate([
            'starts_at' => ['required', 'date', 'after:now'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);
        $slot = $offer->slots()->create($data);
        return $this->respond($request, ['id' => $slot->id, 'status' => 'slot recorded'], 201);
    }

    public function review(Request $request, TravelRequest $travelRequest, TravelRequestService $service): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['acknowledge', 'decline'])],
            'supplier_reference' => ['required_if:decision,acknowledge', 'nullable', 'string', 'min:5', 'max:160'],
        ]);

        $result = $service->review(
            $travelRequest,
            $request->user(),
            $data['decision'] === 'acknowledge',
            $data['supplier_reference'] ?? null
        );

        return $this->respond($request, ['id' => $result->id, 'status' => $result->status,
            'confirmation' => null, 'payment_collected' => false]);
    }
    private function respond(Request $request, array $data, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json($data, $status);
        }

        return back()->with('success',
            'Travel supplier operation recorded: '.($data['status'] ?? 'updated').'. No travel payment was taken.');
    }
}
