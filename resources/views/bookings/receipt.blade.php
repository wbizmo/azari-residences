<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Booking invoice {{ $booking->reference }}</title>
<style>
:root{--ink:#17231f;--green:#173d33;--gold:#b98b3b;--muted:#69736f;--line:#ddd8ce;--paper:#fff;--wash:#f5f2ec}*{box-sizing:border-box}body{margin:0;background:var(--wash);color:var(--ink);font-family:Arial,sans-serif}.toolbar{width:min(940px,calc(100% - 32px));margin:22px auto;display:flex;gap:10px;justify-content:flex-end}.toolbar button{border:0;background:var(--green);color:#fff;padding:13px 18px;border-radius:8px;font-weight:700;cursor:pointer}.invoice{width:min(940px,calc(100% - 32px));margin:0 auto 32px;background:var(--paper);padding:44px;border:1px solid var(--line)}.head{display:flex;justify-content:space-between;gap:28px;padding-bottom:24px;border-bottom:2px solid var(--green)}.brand{font-size:28px;font-weight:800;letter-spacing:-.04em}.kicker{text-transform:uppercase;letter-spacing:.14em;font-size:11px;color:var(--gold);font-weight:800}.status{display:inline-block;margin-top:8px;padding:7px 11px;border-radius:99px;background:#e9f0ed;font-size:12px;font-weight:800;text-transform:uppercase}.meta{text-align:right}.meta strong{font-size:18px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin:24px 0}.card{border:1px solid var(--line);padding:18px}.card h2,.section h2{font-size:13px;text-transform:uppercase;letter-spacing:.09em;margin:0 0 13px;color:var(--muted)}.card p{line-height:1.65;margin:0}.table{width:100%;border-collapse:collapse}.table th,.table td{padding:11px 0;border-bottom:1px solid #eeeae3;text-align:left;vertical-align:top}.table th{font-size:12px;color:var(--muted);font-weight:700}.table td:last-child,.table th:last-child{text-align:right}.total td{font-size:18px;font-weight:800;border-top:2px solid var(--green);border-bottom:0}.section{margin-top:24px}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}.stat{padding:15px;border:1px solid var(--line)}.stat span{display:block;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.08em}.stat strong{display:block;margin-top:6px;font-size:16px}.verify{display:flex;justify-content:space-between;align-items:center;gap:20px;margin-top:28px;padding:18px;background:#f4f7f5}.verify img{width:118px;height:118px}.verify p{margin:6px 0;color:var(--muted);font-size:13px;line-height:1.5}.foot{margin-top:28px;padding-top:18px;border-top:1px solid var(--line);font-size:11px;color:var(--muted)}@media(max-width:700px){.invoice{padding:24px}.head,.grid{display:grid;grid-template-columns:1fr}.meta{text-align:left}.stats{grid-template-columns:1fr 1fr}.verify{align-items:flex-start}.verify img{width:96px;height:96px}}@media print{body{background:#fff}.toolbar{display:none}.invoice{width:100%;margin:0;border:0;padding:13mm}.verify{break-inside:avoid}}
</style>
</head>
<body>
<div class="toolbar"><button type="button" onclick="window.print()">Print or save as PDF</button></div>
<main class="invoice">
    <header class="head">
        <div><div class="kicker">Resavar</div><div class="brand">Booking invoice</div><span class="status">{{ ucwords(str_replace('_', ' ', $booking->status)) }}</span></div>
        <div class="meta"><strong>{{ $booking->reference }}</strong><br><span>{{ $booking->created_at?->format('d M Y, H:i') }}</span></div>
    </header>

    <section class="grid">
        <div class="card"><h2>Guest</h2><p><strong>{{ $booking->guest_name }}</strong><br>{{ $booking->guest_email }}<br>{{ $booking->guest_phone ?: 'Phone not supplied' }}<br>{{ collect([$booking->address, $booking->city, $booking->country])->filter()->implode(', ') }}</p></div>
        <div class="card"><h2>Stay</h2><p><strong>{{ $booking->property?->name ?? 'Resavar Residence' }}</strong><br>{{ $booking->property?->unit_number ?: $booking->property?->code }}<br>{{ $booking->check_in?->format('d M Y') }} to {{ $booking->check_out?->format('d M Y') }}<br>{{ $booking->nights }} night{{ $booking->nights == 1 ? '' : 's' }}</p></div>
    </section>

    <section class="section"><h2>Booking statistics</h2><div class="stats"><div class="stat"><span>Adults</span><strong>{{ $booking->adults }}</strong></div><div class="stat"><span>Children</span><strong>{{ $booking->children }}</strong></div><div class="stat"><span>Rooms</span><strong>{{ $booking->rooms }}</strong></div><div class="stat"><span>Status</span><strong>{{ ucwords(str_replace('_', ' ', $booking->status)) }}</strong></div></div></section>

    <section class="section"><h2>Charges</h2><table class="table"><tbody>
        <tr><td>Accommodation subtotal</td><td>{{ $booking->currency }} {{ number_format((float) $booking->subtotal, 2) }}</td></tr>
        <tr><td>Add-ons</td><td>{{ $booking->currency }} {{ number_format((float) $booking->add_on_total, 2) }}</td></tr>
        <tr><td>Fees</td><td>{{ $booking->currency }} {{ number_format((float) $booking->fee_total, 2) }}</td></tr>
        <tr><td>Tax</td><td>{{ $booking->currency }} {{ number_format((float) $booking->tax_total, 2) }}</td></tr>
        <tr><td>Verified payments</td><td>{{ $booking->currency }} {{ number_format($booking->successfulPaymentsTotal(), 2) }}</td></tr>
        <tr class="total"><td>Outstanding balance</td><td>{{ $booking->currency }} {{ number_format($booking->balanceDue(), 2) }}</td></tr>
    </tbody></table></section>

    <section class="section"><h2>Latest payment</h2><table class="table"><tbody>
        <tr><td>Payment reference</td><td>{{ $payment?->provider_reference ?: $payment?->reference ?: 'No payment recorded' }}</td></tr>
        <tr><td>Provider</td><td>{{ $payment ? ucfirst($payment->provider) : 'Not selected' }}</td></tr>
        <tr><td>Payment status</td><td>{{ $payment ? ucwords(str_replace('_', ' ', $payment->status)) : 'Not started' }}</td></tr>
        <tr><td>Amount</td><td>{{ $payment ? $payment->currency.' '.number_format((float) $payment->amount, 2) : $booking->currency.' 0.00' }}</td></tr>
    </tbody></table></section>

    @php($verificationUrl = route('bookings.verify', ['reference' => $booking->reference]))
    <section class="verify"><div><div class="kicker">Booking verification</div><strong>{{ $booking->reference }}</strong><p>Scan the QR code or enter the reference on the Resavar verification page. Public verification reveals only the booking reference and current status.</p></div><img alt="Verification QR code" src="https://quickchart.io/qr?size=220&margin=1&text={{ urlencode($verificationUrl) }}"></section>
    <footer class="foot">Generated {{ now()->format('d M Y, H:i') }} · This invoice reflects the booking record and payment information available at the time of generation.</footer>
</main>
</body>
</html>
