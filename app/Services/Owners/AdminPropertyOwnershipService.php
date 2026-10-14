<?php

namespace App\Services\Owners;

use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\IdentityVerification;
use App\Models\PropertyListingControlDocument;
use App\Models\ListingAgreement;
use App\Models\Property;
use App\Models\PropertyPhotoModeration;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Single policy for admin-created and owner-submitted properties. Account
 * verification and a signed owner agreement are not payout-profile checks.
 */
final class AdminPropertyOwnershipService
{
    public function eligibleOwner(int $ownerId): array
    {
        $owner = User::query()->find($ownerId);

        // A person can be identity-verified through the existing provider or
        // via Resavar staff's manual approval of signed legal-control proof.
        // Do not force Dojah when verified documentary evidence already exists.
        $hasManualProof = PropertyListingControlDocument::query()
            ->whereHas('listing', fn ($query) => $query
                ->where('user_id', $ownerId)->where('status', 'approved'))
            ->where('status', 'verified')
            ->where('reviewed_at', '>=', now()->subYear())
            ->exists();

        if (! $owner || $owner->isStaff() || $owner->isSuspended()
            || ! $owner->hasVerifiedEmail()
            || (! IdentityVerification::userIsVerified($ownerId) && ! $hasManualProof)) {
            throw ValidationException::withMessages([
                'owner_id' => 'Choose a verified, active guest account, not a staff or unverified account.',
            ]);
        }

        $agreement = ListingAgreement::query()
            ->where('user_id', $ownerId)
            ->where('version', (string) SiteSetting::valueFor('owner_listing_agreement_version', '1.0'))
            ->whereNotNull('signed_at')
            ->whereNotNull('signature_hash')
            ->latest('signed_at')
            ->first();

        if (! $agreement) {
            throw ValidationException::withMessages([
                'owner_id' => 'The owner must accept the current property management agreement first.',
            ]);
        }

        return [$owner, $agreement];
    }

    public function apply(array $data, ?Property $previous, User $actor): array
    {
        $ownerId = filled($data['owner_id'] ?? null) ? (int) $data['owner_id'] : null;
        $wasOwner = $previous?->owner_id !== null ? (int) $previous->owner_id : null;
        $ownershipChanged = $previous && $wasOwner !== $ownerId;

        if ($ownerId !== null || $ownershipChanged) {
            abort_unless($actor->isAdministrator(), 403);
        }

        if ($ownershipChanged) {
            if ($previous->owner_listing_id !== null
                || Booking::query()->where('property_id', $previous->getKey())->exists()
                || BookingHold::query()->where('property_id', $previous->getKey())->exists()) {
                throw ValidationException::withMessages([
                    'owner_id' => 'Ownership cannot change after an owner submission or reservation exists. Use a separately approved financial transfer.',
                ]);
            }
        }

        if ($ownerId === null) {
            return array_merge($data, [
                'owner_id' => null,
                'owner_agreement_id' => null,
                'ownership_type' => 'azari',
                'managed_for_owner' => false,
                'owner_share_percentage' => 100,
                'is_featured' => ! empty($data['is_published']) && ! empty($data['is_featured']),
                'status' => ! empty($data['is_published']) ? 'available' : 'draft',
            ]);
        }

        // On every fresh owner assignment validate both identity and contract.
        // An unchanged owner can retain a historic agreement until a new
        // publication decision, but no new account can bypass the gate.
        $agreementId = $previous?->owner_agreement_id;
        if ($wasOwner !== $ownerId || ($data['is_published'] ?? false) || ! $agreementId) {
            [, $agreement] = $this->eligibleOwner($ownerId);
            $agreementId = $agreement->id;
        }

        $defaultShare = (float) config('azari.owners.default_owner_share_percentage', 88);
        $share = isset($data['owner_share_percentage']) && $data['owner_share_percentage'] !== ''
            ? (float) $data['owner_share_percentage']
            : ($wasOwner === $ownerId && $previous?->owner_share_percentage !== null
                ? (float) $previous->owner_share_percentage : $defaultShare);

        if ($share <= 0 || $share > 100) {
            throw ValidationException::withMessages([
                'owner_share_percentage' => 'Owner share must be greater than zero and at most 100%.',
            ]);
        }

        if ($previous && (float) $previous->owner_share_percentage !== $share
            && Booking::query()->where('property_id', $previous->getKey())->exists()) {
            throw ValidationException::withMessages([
                'owner_share_percentage' => 'Existing bookings have locked commercial terms. Changing commission requires a separate audited financial agreement.',
            ]);
        }

        // A new owner-managed property is always a draft, even if an admin
        // submitted is_published=1. A later edit explicitly approves release.
        $publish = $previous ? (bool) ($data['is_published'] ?? false) : false;

        return array_merge($data, [
            'owner_id' => $ownerId,
            'owner_agreement_id' => $agreementId,
            'ownership_type' => 'third_party',
            'managed_for_owner' => true,
            'owner_share_percentage' => $share,
            'is_published' => $publish,
            'is_featured' => $publish && (bool) ($data['is_featured'] ?? false),
            'status' => $publish ? 'available' : 'draft',
        ]);
    }

    public function assertPublishable(Property $property): void
    {
        if (! $property->owner_id) {
            return;
        }

        // Coordinates and payout profiles are optional for accommodation.
        // Publication still requires listing completeness and approved media.
        $result = app(ListingCompletenessService::class)->propertyPublishability($property);
        $needed = array_diff($result['blockers'], ['coordinates']);
        if ($needed !== []) {
            throw ValidationException::withMessages([
                'is_published' => 'Complete the listing before publishing: '.implode(', ', $needed).'.',
            ]);
        }

        $paths = collect([$property->cover_image])->merge($property->gallery ?? [])->filter()->unique();
        if ($paths->isEmpty()) {
            throw ValidationException::withMessages(['is_published' => 'Upload photos for review before publishing.']);
        }

        $approvals = PropertyPhotoModeration::query()->where('property_id', $property->getKey())
            ->whereIn('path', $paths->all())
            ->where('status', 'approved')
            ->whereNotNull('alt_text')
            ->whereNotNull('attribution')
            ->pluck('path');

        if ($approvals->count() !== $paths->count()) {
            throw ValidationException::withMessages([
                'is_published' => 'Every property photo needs staff approval, description and attribution.',
            ]);
        }
    }
}
