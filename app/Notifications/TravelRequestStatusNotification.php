<?php

namespace App\Notifications;

use App\Models\TravelRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class TravelRequestStatusNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly TravelRequest $travelRequest)
    {
    }

    public function via(object $notifiable): array
    {
        // In-app messaging only, by design. No SMS, WhatsApp or web push.
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Update on your travel request',
            'message' => match ($this->travelRequest->status) {
                'supplier_acknowledged' => 'Your supplier has acknowledged your enquiry. This is not a confirmed reservation or ticket.',
                'supplier_declined' => 'The supplier could not accept your travel request. Your accommodation is unaffected.',
                default => 'Your travel request needs assistance. Your accommodation remains unchanged.',
            },
            'url' => route('user.travel.show', $this->travelRequest),
            'travel_request_id' => $this->travelRequest->id,
            'status' => $this->travelRequest->status,
        ];
    }
}
