<!doctype html>
<html>
<body style="font-family:Arial,sans-serif;color:#17211d;line-height:1.6">
    <h2>You're invited to verify for an Azari booking</h2>

    <p>Hello {{ $guest->first_name }},</p>

    <p>
        {{ $booking->guest_name }} added you as an adult guest on booking
        <strong>{{ $booking->reference }}</strong>
        @if($booking->property)
            for {{ $booking->property->name }}
        @endif.
    </p>

    <p>
        Every adult verifies their own identity. Use the private page below. The URL contains only the booking reference and your adult position; Azari will still require an email code before showing your details or launching identity verification.
    </p>

    <p><a href="{{ $verificationUrl }}">{{ $verificationUrl }}</a></p>

    <p>
        You may verify only for this booking, or optionally create/link your own Azari account during the process for future bookings.
    </p>

    <p>Resavar</p>
</body>
</html>
