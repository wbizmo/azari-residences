<!doctype html>
<html lang="en">
<body style="margin:0;padding:32px;background:#052058;font-family:Montserrat,Arial,sans-serif;color:#052058;line-height:1.6;">
    <div style="max-width:600px;margin:0 auto;padding:32px;background:#FFFFFF;border-radius:16px;border-top:4px solid #052058"><h2 style="margin-top:0;margin-bottom:16px;color:#052058;">Resavar account deletion request</h2>
    <p><strong>Reference:</strong> {{ $reference }}</p>
    <p><strong>Name:</strong> {{ $requesterName !== '' ? $requesterName : 'Not provided' }}</p>
    <p><strong>Account email:</strong> {{ $requesterEmail }}</p>
    <p><strong>Requested at:</strong> {{ $requestedAt }}</p>
    <p>The requester has asked for deletion of their Resavar account and associated personal data.</p>
    <p>Please verify account ownership before actioning the request. Delete or anonymize personal data that is not required for legitimate business, accounting, fraud-prevention, legal, or regulatory retention. Records that must be retained should be limited to the required scope and retention period.</p>
    </div>
</body>
</html>
