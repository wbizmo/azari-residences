<?php

namespace App\Notifications;

use App\Models\PropertyListing;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class OwnerListingStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $reference,
        public readonly string $status,
        public readonly string $message,
        public readonly string $url,
    ) {}

    public static function forListing(PropertyListing $listing, string $message): self
    {
        return new self(
            $listing->reference,
            $listing->status,
            $message,
            route('user.owner.listings.show', $listing),
        );
    }

    public function via(object $notifiable): array
    {
        // Persistent in-app delivery is authoritative. Submission/review must
        // never fail because an optional email transport is unavailable.
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'owner_listing_status',
            'title' => 'Property application '.str_replace('_', ' ', $this->status),
            'reference' => $this->reference,
            'status' => $this->status,
            'message' => $this->message,
            'url' => $this->url,
        ];
    }
}
