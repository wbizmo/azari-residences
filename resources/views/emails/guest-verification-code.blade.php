<!doctype html>
<html>
<body style="margin:0;padding:32px;background:#052058;font-family:Montserrat,Arial,sans-serif;color:#052058;line-height:1.6">
    <div style="max-width:600px;margin:0 auto;padding:32px;background:#FFFFFF;border-radius:16px;border-top:4px solid #052058">
        <h2 style="margin-top:0;color:#052058">Your Resarva verification code</h2>
        <p>Use this six-digit code to confirm that you control the guest email for booking <strong>{{ $booking->reference }}</strong>:</p>
        <p style="font-size:28px;font-weight:700;letter-spacing:6px;color:#052058">{{ $code }}</p>
        <p>This code expires in 10 minutes. Do not share it with anyone.</p>
        <p style="margin-bottom:0;font-weight:700;color:#052058">Resarva</p>
    </div>
</body>
</html>
