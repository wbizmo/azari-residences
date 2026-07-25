<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Receipt {{ $booking->reference }}</title>
<style>
body{margin:0;background:#f3f0e9;color:#17231f;font-family:Arial,sans-serif}.receipt{width:min(820px,calc(100% - 32px));margin:32px auto;background:#fff;padding:42px;box-sizing:border-box;border:1px solid #ddd7ca}.head{display:flex;justify-content:space-between;gap:24px;border-bottom:2px solid #173d33;padding-bottom:22px}.brand{font-size:26px;font-weight:800}.muted{color:#66716d}.grid{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin:28px 0}.box{border:1px solid #e2ddd2;padding:18px}.rows{width:100%;border-collapse:collapse}.rows td{padding:11px 0;border-bottom:1px solid #eee9df}.rows td:last-child{text-align:right;font-weight:700}.total td{font-size:18px;border-top:2px solid #173d33;border-bottom:0}.actions{width:min(820px,calc(100% - 32px));margin:22px auto;display:flex;gap:10px}.actions button{border:0;background:#173d33;color:#fff;padding:13px 18px;border-radius:8px;font-weight:700;cursor:pointer}@media(max-width:650px){.receipt{padding:24px}.head,.grid{grid-template-columns:1fr;display:grid}}@media print{body{background:#fff}.actions{display:none}.receipt{width:100%;margin:0;border:0;padding:18mm;box-shadow:none}}
</style>
</head>
<body>
<div class="actions"><button type="button" onclick="window.print()">Print or save as PDF</button></div>
<main class="receipt">
    <header class="head"><div><div class="brand">Azari Residences</div><div class="muted">Payment receipt</div></div><div><strong>{{ $payment->receipt_number ?: $payment->reference }}</strong><br><span class="muted">{{ optional($payment->paid_at ?: $payment->created_at)->format('d M Y, H:i') }}</span></div></header>
    <section class="grid"><div class="box"><strong>Guest</strong><p>{{ $booking->guest_name }}<br>{{ $booking->guest_email }}<br>{{ $booking->guest_phone }}</p></div><div class="box"><strong>Booking</strong><p>{{ $booking->reference }}<br>{{ $booking->property?->name }}<br>{{ $booking->check_in?->format('d M Y') }} to {{ $booking->check_out?->format('d M Y') }}</p></div></section>
    <table class="rows"><tr><td>Payment provider</td><td>{{ ucfirst($payment->provider) }}</td></tr><tr><td>Transaction reference</td><td>{{ $payment->provider_reference ?: $payment->reference }}</td></tr><tr><td>Booking total</td><td>{{ $booking->currency }} {{ number_format((float)$booking->total,2) }}</td></tr><tr><td>Amount paid</td><td>{{ $payment->currency }} {{ number_format((float)$payment->amount,2) }}</td></tr><tr><td>Status</td><td>{{ ucwords(str_replace('_',' ',$payment->status)) }}</td></tr><tr class="total"><td>Receipt total</td><td>{{ $payment->currency }} {{ number_format((float)$payment->amount,2) }}</td></tr></table>
</main>
</body>
</html>
