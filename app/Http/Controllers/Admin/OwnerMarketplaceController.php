<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use App\Models\AuditLog;
use App\Models\OwnerPayoutProfile;
use App\Models\Property;
use App\Models\PropertyListing;
use App\Models\PropertyPhotoModeration;
use App\Models\SiteSetting;
use App\Models\WithdrawalRequest;
use App\Services\Owners\ListingCompletenessService;
use App\Services\Owners\OwnerWithdrawalService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OwnerMarketplaceController extends Controller
{
    public function listings(Request $request): View
    {
        $query = PropertyListing::query()->with(['user', 'agreement', 'approvedProperty', 'reviewedBy'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        return view('admin.owner-listings.index', [
            'listings' => $query->paginate(10)->withQueryString(),
        ]);
    }

    public function showListing(
        PropertyListing $listing,
        ListingCompletenessService $completeness
    ): View {
        $listing->load(['user.ownerPayoutProfile', 'agreement', 'approvedProperty', 'reviewedBy', 'mediaReviewedBy']);
        $completion = $completeness->evaluate($listing);

        return view('admin.owner-listings.show', [
            'listing' => $listing,
            'completion' => $completion,
            'amenities' => Amenity::query()->whereIn('id', $listing->amenity_ids ?? [])->pluck('name'),
        ]);
    }

    public function markUnderReview(Request $request, PropertyListing $listing): RedirectResponse
    {
        abort_unless($listing->status === 'submitted', 422);

        $listing->update([
            'status' => 'under_review',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('status', 'Listing marked as under review.');
    }

    public function approveListing(
        Request $request,
        PropertyListing $listing,
        ListingCompletenessService $completeness
    ): RedirectResponse {
        $data = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:3000'],
            'publish_now' => ['nullable', 'boolean'],
            'feature_now' => ['nullable', 'boolean'],
            'media_reviewed' => ['required', 'accepted'],
            'media_review_note' => ['required', 'string', 'min:12', 'max:1500'],
            'photo_alt' => ['required', 'array'],
            'photo_alt.*' => ['required', 'string', 'min:8', 'max:300'],
            'photo_attribution' => ['required', 'array'],
            'photo_attribution.*' => ['required', 'string', 'min:3', 'max:300'],
        ]);

        DB::transaction(function () use ($listing, $data, $request, $completeness): void {
            // Recheck under the row lock: two administrators must never
            // publish duplicate properties from the same owner submission.
            $locked = PropertyListing::query()->whereKey($listing->id)
                ->lockForUpdate()->firstOrFail();
            abort_unless(in_array($locked->status, ['submitted', 'under_review'], true), 422,
                'This listing has already been reviewed.');

            $completion = $completeness->sync($locked->load('user.ownerPayoutProfile'));
            abort_unless(
                $completion['publishable'],
                422,
                'This listing is incomplete: '.implode(', ', $completion['blockers']).'.'
            );

            $files = collect([$locked->cover_image])
                ->merge($locked->gallery ?? [])
                ->filter()->values();
            abort_unless($files->isNotEmpty(), 422,
                'The owner must provide property imagery for moderation.');
            foreach ($files as $image) {
                abort_unless(is_string($image)
                    && Str::startsWith($image, 'owner-listings/')
                    && Storage::disk('public')->exists($image),
                    422, 'All owner-submitted photographs must still exist before approval.');
            }

            $payload = $locked->property_data;
            $payload['slug'] = Str::slug($payload['name']).'-'.Str::lower(Str::random(5));
            $payload['cover_image'] = $locked->cover_image;
            $payload['gallery'] = $locked->gallery ?? [];
            $payload['owner_id'] = $locked->user_id;
            $payload['owner_listing_id'] = $locked->id;
            $payload['owner_share_percentage'] = 100.00;
            $payload['managed_for_owner'] = true;
            $payload['currency'] = (string) config('azari.currency', 'USD');
            $payload['is_published'] = (bool) ($data['publish_now'] ?? false);
            $payload['is_featured'] = (bool) ($data['feature_now'] ?? false);
            $payload['status'] = ($data['publish_now'] ?? false) ? 'available' : 'draft';

            foreach ($files as $image) {
                $hash = PropertyPhotoModeration::pathHash($image);
                abort_unless(filled($data['photo_alt'][$hash] ?? null)
                    && filled($data['photo_attribution'][$hash] ?? null), 422,
                    'Every submitted photo requires its own description and attribution.');
            }

            $property = Property::query()->create($payload);
            $property->amenities()->sync($locked->amenity_ids ?? []);

            foreach ($files->unique() as $image) {
                $hash = PropertyPhotoModeration::pathHash($image);
                PropertyPhotoModeration::query()->create([
                    'property_id' => $property->id,
                    'path_hash' => $hash,
                    'path' => $image,
                    'status' => 'approved',
                    'alt_text' => trim($data['photo_alt'][$hash]),
                    'attribution' => trim($data['photo_attribution'][$hash]),
                    'review_note' => $data['media_review_note'],
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => now(),
                ]);
            }

            $previousStatus = $locked->status;
            $locked->update([
                'status' => 'approved',
                'approved_property_id' => $property->id,
                'approved_owner_share_percentage' => 100.00,
                'admin_notes' => $data['admin_notes'] ?? null,
                'decline_reason' => null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'media_reviewed_by' => $request->user()->id,
                'media_reviewed_at' => now(),
                'media_review_note' => $data['media_review_note'],
                'approved_at' => now(),
                'declined_at' => null,
            ]);

            AuditLog::record('owner_listing.media_reviewed_and_approved',
                $locked,
                ['status' => $previousStatus],
                ['status' => 'approved', 'property_id' => $property->id],
                ['media_count' => $files->count(), 'published' => (bool) ($data['publish_now'] ?? false)]
            );
        }, 3);

        return redirect()->route('azari.admin.owner-listings.show', $listing)
            ->with('status', 'Listing and its media reviewed and approved.');
    }

    public function declineListing(Request $request, PropertyListing $listing): RedirectResponse
    {
        abort_unless(in_array($listing->status, ['submitted', 'under_review'], true), 422);

        $data = $request->validate([
            'decline_reason' => ['required', 'string', 'min:10', 'max:3000'],
            'admin_notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $listing->update([
            'status' => 'declined',
            'decline_reason' => $data['decline_reason'],
            'admin_notes' => $data['admin_notes'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'declined_at' => now(),
            'approved_at' => null,
        ]);

        return back()->with('status', 'Listing declined with a reason visible to the owner.');
    }

    public function agreementDownload(PropertyListing $listing): BinaryFileResponse
    {
        $agreement = $listing->agreement;
        abort_unless($agreement !== null, 404);
        $disk = Storage::disk('private');
        $relativePath = 'agreements/'.$agreement->id.'.pdf';
        $path = $disk->path($relativePath);

        if (! $disk->exists($relativePath)) {
            $disk->makeDirectory('agreements');
            $options = new Options();
            $options->set('defaultFont', 'DejaVu Sans');
            $pdf = new Dompdf($options);
            $pdf->loadHtml(view('user.owner.agreement-pdf', compact('agreement'))->render());
            $pdf->setPaper('A4');
            $pdf->render();
            if (! $disk->put($relativePath, $pdf->output())) {
                throw new \RuntimeException('Unable to store the private agreement PDF.');
            }
        }

        return response()->download($path, 'owner-agreement-'.$listing->reference.'.pdf');
    }

    public function withdrawals(Request $request): View
    {
        $query = WithdrawalRequest::query()->with(['user.ownerPayoutProfile', 'processedBy'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        return view('admin.owner-withdrawals.index', [
            'withdrawals' => $query->paginate(10)->withQueryString(),
        ]);
    }

    public function showWithdrawal(WithdrawalRequest $withdrawal): View
    {
        $withdrawal->load(['user.ownerPayoutProfile', 'processedBy']);
        return view('admin.owner-withdrawals.show', compact('withdrawal'));
    }

    public function processWithdrawal(Request $request, WithdrawalRequest $withdrawal, OwnerWithdrawalService $withdrawals): RedirectResponse
    {
        $data = $request->validate(['admin_note' => ['nullable', 'string', 'max:3000']]);

        try {
            $processed = $withdrawals->process($withdrawal, $request->user(), $data['admin_note'] ?? null);
            return back()->with('status', $processed->status === 'processed'
                ? 'Withdrawal processed exactly once and recorded successfully.'
                : 'Withdrawal processing status updated.');
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['withdrawal' => $exception->getMessage()]);
        }
    }

    public function rejectWithdrawal(Request $request, WithdrawalRequest $withdrawal): RedirectResponse
    {
        abort_unless(in_array($withdrawal->status, ['pending', 'failed'], true), 422);
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'min:10', 'max:3000']]);
        $withdrawal->update([
            'status' => 'rejected',
            'rejection_reason' => $data['rejection_reason'],
            'processed_by' => $request->user()->id,
            'rejected_at' => now(),
        ]);
        return back()->with('status', 'Withdrawal rejected. The reserved amount is available to the owner again.');
    }

    public function verifyPayoutProfile(Request $request, OwnerPayoutProfile $profile): RedirectResponse
    {
        $data = $request->validate(['verification_note' => ['required', 'string', 'min:10', 'max:3000']]);
        $profile->update([
            'is_verified' => true,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'verification_note' => $data['verification_note'],
        ]);
        return back()->with('status', 'Payout destination verified. The owner can now request withdrawals.');
    }

    public function unverifyPayoutProfile(Request $request, OwnerPayoutProfile $profile): RedirectResponse
    {
        $data = $request->validate(['verification_note' => ['required', 'string', 'min:10', 'max:3000']]);
        $profile->update([
            'is_verified' => false,
            'verified_by' => $request->user()->id,
            'verified_at' => null,
            'verification_note' => $data['verification_note'],
        ]);
        return back()->with('status', 'Payout destination verification revoked.');
    }

    public function retryWithdrawal(Request $request, WithdrawalRequest $withdrawal, OwnerWithdrawalService $withdrawals): RedirectResponse
    {
        $data = $request->validate(['admin_note' => ['nullable', 'string', 'max:3000']]);
        try {
            $withdrawals->retryFailed($withdrawal, $request->user(), $data['admin_note'] ?? null);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['withdrawal' => $exception->getMessage()]);
        }
        return back()->with('status', 'Withdrawal returned to the pending queue for one safe retry.');
    }

    public function reconcileWithdrawalPaid(Request $request, WithdrawalRequest $withdrawal, OwnerWithdrawalService $withdrawals): RedirectResponse
    {
        $data = $request->validate([
            'provider_reference' => ['required', 'string', 'max:255'],
            'reconciliation_note' => ['required', 'string', 'min:10', 'max:3000'],
        ]);
        try {
            $withdrawals->reconcileAsPaid($withdrawal, $request->user(), $data['provider_reference'], $data['reconciliation_note']);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['withdrawal' => $exception->getMessage()]);
        }
        return back()->with('status', 'Withdrawal reconciled as paid and the debit was recorded exactly once.');
    }

    public function reconcileWithdrawalNotPaid(Request $request, WithdrawalRequest $withdrawal, OwnerWithdrawalService $withdrawals): RedirectResponse
    {
        $data = $request->validate(['reconciliation_note' => ['required', 'string', 'min:10', 'max:3000']]);
        try {
            $withdrawals->reconcileAsNotPaid($withdrawal, $request->user(), $data['reconciliation_note']);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['withdrawal' => $exception->getMessage()]);
        }
        return back()->with('status', 'Withdrawal reconciled as not paid. Reserved funds are available again.');
    }

    public function settings(): View
    {
        return view('admin.owner-settings.edit', [
            'settings' => collect([
                'owner_withdrawal_days',
                'owner_withdrawal_minimum',
                'owner_paypal_enabled',
                'owner_stripe_enabled',
                'owner_listing_agreement_version',
            ])->mapWithKeys(fn ($key) => [$key => SiteSetting::valueFor($key)])->all(),
            'currency' => (string) config('azari.currency', 'USD'),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'owner_withdrawal_days' => ['required', 'string', 'regex:/^[1-7](,[1-7])*$/'],
            'owner_withdrawal_minimum' => ['required', 'numeric', 'min:0'],
            'owner_listing_agreement_version' => ['required', 'string', 'max:40'],
        ]);

        $data['owner_paypal_enabled'] = $request->boolean('owner_paypal_enabled') ? '1' : '0';
        $data['owner_stripe_enabled'] = $request->boolean('owner_stripe_enabled') ? '1' : '0';
        $data['owner_withdrawal_currency'] = (string) config('azari.currency', 'USD');

        foreach ($data as $key => $value) {
            SiteSetting::put($key, $value, is_bool($value) ? 'boolean' : 'text', 'property_owners');
        }

        return back()->with('status', 'Property owner marketplace settings updated.');
    }
}
