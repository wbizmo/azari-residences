<!doctype html>
<html>
<body style="font-family:Arial,sans-serif;color:#17211d;line-height:1.6">
    <h2>Continue your Azari booking</h2>

    <p>Hello {{ $user->name }},</p>

    <p>
        Use this six-digit code to verify your email and continue the booking you already started:
    </p>

    <p style="font-size:28px;font-weight:700;letter-spacing:6px">{{ $code }}</p>

    <p>This code expires in 10 minutes. Your booking details remain attached to your secure hold while you continue.</p>

    <p>Azari Hotels & Residences</p>
</body>
</html>
