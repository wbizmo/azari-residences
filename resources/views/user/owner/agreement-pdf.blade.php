<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;color:#17251f;font-size:12px;line-height:1.65}h1{font-family:DejaVu Serif,serif;color:#103328;font-size:28px}.meta{background:#f3efe6;padding:14px;margin:20px 0}.signature{margin-top:40px;border-top:1px solid #333;padding-top:12px}</style></head><body>
<h1>Azari Residences Property Listing Agreement</h1>
<div class="meta"><strong>Version:</strong> {{ $agreement->version }}<br><strong>Owner:</strong> {{ $agreement->legal_name }}<br><strong>Signed:</strong> {{ $agreement->signed_at->format('d F Y, H:i T') }}<br><strong>Agreement reference:</strong> AGR-{{ str_pad($agreement->id,8,'0',STR_PAD_LEFT) }}</div>
<p style="white-space:pre-line">{{ $agreement->agreement_text }}</p>
<div class="signature"><strong>Electronic signature:</strong> {{ $agreement->legal_name }}<br><small>Signature integrity hash: {{ $agreement->signature_hash }}</small></div>
</body></html>
