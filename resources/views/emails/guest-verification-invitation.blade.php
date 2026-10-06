<!doctype html>
<html>
<body style="margin:0;padding:32px;background:#052058;font-family:Montserrat,Arial,sans-serif;color:#052058;line-height:1.6">
    <div style="max-width:600px;margin:0 auto;padding:32px;background:#FFFFFF;border-radius:16px;border-top:4px solid #052058">
        <h2 style="margin-top:0;color:#052058">You're invited to verify for a Resarva booking</h2>
        <p>Hello {{ $guest->first_name }},</p>
        <p>
            {{ $booking->guest_name }} added you as an adult guest on booking
            <strong>{{ $booking->reference }}</strong>
            @if($booking->property)
                for {{ $booking->property->name }}
            @endif.
        </p>
        <p>
            Every adult verifies their own identity. Use the private page below. The URL contains only the booking reference and your adult position; Resarva will still require an email code before showing your details or launching identity verification.
        </p>
        <p><a style="color:#052058;font-weight:700" href="{{ $verificationUrl }}">{{ $verificationUrl }}</a></p>
        <p>
            You may verify only for this booking, or optionally create/link your own Resarva account during the process for future bookings.
        </p>
        <p style="margin-bottom:0;font-weight:700;color:#052058">Resarva</p>
    </div>
</body>
</html>
