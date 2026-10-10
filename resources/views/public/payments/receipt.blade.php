<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Payment receipt {{ $payment->reference }}</title>
<style>*{box-sizing:border-box}body{margin:0;background:#F2F5FA;color:#052058;font-family:Arial,sans-serif}.toolbar,.receipt{width:min(820px,calc(100% - 32px));margin:22px auto}.toolbar{display:flex;justify-content:flex-end}.toolbar button{border:0;border-radius:10px;background:#052058;color:#FFFFFF;padding:13px 18px;font-weight:800}.receipt{background:#FFFFFF;border:1px solid #D8E2EE;padding:42px}.head{display:flex;flex-wrap:wrap;justify-content:space-between;gap:20px;border-bottom:2px solid #052058;padding-bottom:22px}.eyebrow{font-size:11px;font-weight:800;letter-spacing:.15em;text-transform:uppercase;color:#052058}h1{margin:7px 0 0}.status{font-weight:800;text-transform:uppercase}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin-top:24px}.card{border:1px solid #D8E2EE;padding:17px;min-width:0}.card span{display:block;color:#526581;font-size:12px;margin-bottom:7px}.card strong{overflow-wrap:anywhere}.amount{margin-top:22px;padding:22px;background:#052058;color:#FFFFFF;display:flex;flex-wrap:wrap;gap:10px;justify-content:space-between;align-items:center;break-inside:avoid}.amount strong{font-size:24px;color:#FFFFFF;overflow-wrap:anywhere}.note{margin-top:24px;padding:17px;border-left:4px solid #052058;background:#EEF3FA;color:#052058;line-height:1.6}.foot{margin-top:28px;color:#052058;font-size:12px}@media(max-width:620px){.receipt{padding:24px}.head,.grid{display:grid;grid-template-columns:1fr}}@media print{@page{size:A4;margin:13mm}body{background:#FFFFFF;-webkit-print-color-adjust:exact;print-color-adjust:exact}.toolbar{display:none}.receipt{border:0;width:100%;margin:0;padding:0}.head,.card,.amount,.note{break-inside:avoid}.amount{background:#FFFFFF!important;color:#052058!important;border:2px solid #052058}.amount strong,.amount span{color:#052058!important}}</style>
<style>
/* Resavar A4 document safeguards: no clipped references or broken rows. */
.invoice,.receipt{overflow-wrap:anywhere}
.head>*{min-width:0}
.card,.meta,.amount,.verify{min-width:0}
@media print {
  .head,.verify,.amount{break-inside:avoid;page-break-inside:avoid}
  table{width:100%;border-collapse:collapse}
  tr{break-inside:avoid;page-break-inside:avoid}
  .invoice,.receipt{max-width:none;box-shadow:none}
  a{color:#052058;text-decoration:underline}
}
</style>
</head><body>
<div class="toolbar"><button onclick="window.print()">Print or save PDF</button></div>
<main class="receipt">
<header class="head"><div><img src="{{ asset('images/logo-light.png') }}" alt="Resavar" style="display:block;max-width:170px;max-height:48px;width:auto;margin-bottom:12px"><div class="eyebrow">Resavar</div><h1>Payment receipt</h1></div><div class="status">{{ ucwords(str_replace('_',' ',$payment->status)) }}</div></header>
@if($fullAccess)
<section class="grid"><div class="card"><span>Booking reference</span><strong>{{ $booking->reference }}</strong></div><div class="card"><span>Payment reference</span><strong>{{ $payment->reference }}</strong></div><div class="card"><span>Stay</span><strong>{{ $booking->property?->name ?? 'Resavar Stay' }}</strong></div><div class="card"><span>Payment date</span><strong>{{ ($payment->paid_at ?: $payment->created_at)?->format('d M Y, H:i') }}</strong></div></section>
@else
<section class="grid"><div class="card"><span>Booking reference</span><strong>{{ $booking->reference }}</strong></div><div class="card"><span>Payment reference</span><strong>{{ $payment->reference }}</strong></div></section>
@endif
<div class="amount"><span>Amount {{ $payment->isSuccessful() ? 'paid' : 'recorded' }}</span><strong>{{ $payment->currency }} {{ number_format((float)$payment->amount,2) }}</strong></div>
@unless($fullAccess)<div class="note">This limited receipt is shown because the payment was made for another person's booking or the payer was not signed in to the account that owns the booking. It confirms the payment reference and amount without exposing private reservation information.</div>@endunless
<footer class="foot">Generated {{ now()->format('d M Y, H:i') }}</footer>
</main></body></html>
