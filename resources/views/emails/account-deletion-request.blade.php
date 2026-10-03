<!doctype html>
<html lang="en">
<body style="font-family: Arial, sans-serif; color:#18231f; line-height:1.6;">
    <h2 style="margin-bottom:16px;">Resavar account deletion request</h2>
    <p><strong>Reference:</strong> {{ $reference }}</p>
    <p><strong>Name:</strong> {{ $requesterName !== '' ? $requesterName : 'Not provided' }}</p>
    <p><strong>Account email:</strong> {{ $requesterEmail }}</p>
    <p><strong>Requested at:</strong> {{ $requestedAt }}</p>
    <p>The requester has asked for deletion of their Resavar account and associated personal data.</p>
    <p>Please verify account ownership before actioning the request. Delete or anonymize personal data that is not required for legitimate business, accounting, fraud-prevention, legal, or regulatory retention. Records that must be retained should be limited to the required scope and retention period.</p>
</body>
</html>
