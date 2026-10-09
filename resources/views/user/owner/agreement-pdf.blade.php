<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Resavar Property Listing Agreement</title>
<style>
@page { size: A4 portrait; margin: 20mm 18mm 23mm; }
body { font-family: DejaVu Sans, sans-serif; color: #052058; font-size: 11px; line-height: 1.65; }
.header { border-bottom: 2px solid #052058; padding-bottom: 16px; margin-bottom: 23px; }
.brand { color: #052058; font-size: 19px; font-weight: bold; letter-spacing: 1px; margin: 0; }
.tagline { margin: 3px 0 0; color: #526581; font-size: 10px; }
h1 { margin: 0 0 18px; font-size: 20px; line-height: 1.25; color: #052058; }
.meta { width: 100%; border-collapse: collapse; margin: 0 0 20px; page-break-inside: avoid; }
.meta td { padding: 9px 11px; border-bottom: 1px solid #DCE5F0; vertical-align: top; word-wrap: break-word; }
.meta td:first-child { width: 29%; color: #526581; font-weight: bold; }
.meta td:last-child { width: 71%; color: #052058; }
.meta tr:first-child td { border-top: 1px solid #DCE5F0; }
.agreement-text { white-space: pre-line; word-wrap: break-word; color: #233E64; }
.signature { margin-top: 30px; padding: 13px 0; border-top: 1px solid #B8C9DF; font-size: 10px; color: #405A7B; page-break-inside: avoid; }
.footer { position: fixed; bottom: -14mm; left: 0; right: 0; text-align: center; color: #526581; font-size: 8px; }
</style>
</head>
<body>
<header class="header">
    <p class="brand">RESAVAR</p>
    <p class="tagline">Exceptional Stays, Everywhere.</p>
</header>
<h1>Property Listing Agreement</h1>
<table class="meta" role="presentation">
    <tr><td>Agreement version</td><td>{{ $agreement->version }}</td></tr>
    <tr><td>Property owner</td><td>{{ $agreement->legal_name }}</td></tr>
    <tr><td>Date accepted</td><td>{{ $agreement->signed_at->format('j F Y, g:i a') }}</td></tr>
    <tr><td>Signature hash</td><td>{{ $agreement->signature_hash }}</td></tr>
</table>
<div class="agreement-text">{{ $agreement->agreement_text }}</div>
<div class="signature">
    Electronically accepted by <strong>{{ $agreement->legal_name }}</strong>.
    Originating IP: {{ $agreement->signed_ip ?: 'not recorded' }}.
</div>
<div class="footer">Resavar · Digitally recorded property listing agreement</div>
</body>
</html>
