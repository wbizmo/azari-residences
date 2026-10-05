<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;color:#052058;font-size:12px;line-height:1.65}.brand{font-size:22px;font-weight:700}.meta{margin:20px 0;padding:14px;border:1px solid #052058}.signature{margin-top:35px;border-top:1px solid #052058;padding-top:12px}</style></head><body>
<div class="brand">RESERVA</div><p>Exceptional Stays, Everywhere.</p><h1>Property Listing Agreement</h1>
<div class="meta"><strong>Version:</strong> {{ $agreement->version }}<br><strong>Owner:</strong> {{ $agreement->legal_name }}<br><strong>Signed:</strong> {{ $agreement->signed_at->format('j F Y, g:i a') }}<br><strong>Signature hash:</strong> {{ $agreement->signature_hash }}</div>
<div style="white-space:pre-line">{{ $agreement->agreement_text }}</div>
<div class="signature">Electronically accepted by {{ $agreement->legal_name }} from IP {{ $agreement->signed_ip ?: 'not recorded' }}.</div>
</body></html>
