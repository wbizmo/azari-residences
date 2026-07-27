@include('emails.premium', [
    'title' => 'New service request',
    'preheader' => 'A new booking-linked service request requires attention.',
    'lines' => [
        'Request reference: '.$sr->reference,
        'Booking: '.$booking->reference,
        'Type: '.ucwords(str_replace('_', ' ', $sr->type)),
        $sr->notes ?: 'No additional notes were supplied.',
    ],
    'actionLabel' => null,
    'actionUrl' => null,
])
