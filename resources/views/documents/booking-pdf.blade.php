<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 24mm 18mm; }
        body { font-family: DejaVu Sans; color: #17231e; font-size: 11px; }
        .head { display: table; width: 100%; border-bottom: 2px solid #b68a4a; padding-bottom: 14px; }
        .head-brand, .head-meta { display: table-cell; vertical-align: top; width: 50%; }
        .head-meta { text-align: right; }
        .logo { display: block; max-width: 220px; max-height: 58px; width: auto; height: auto; margin-bottom: 8px; }
        .title { text-transform: uppercase; letter-spacing: 2px; }
        .grid { width: 100%; border-collapse: collapse; margin-top: 18px; }
        .grid td { padding: 8px; border-bottom: 1px solid #ddd; }
        .money { text-align: right; }
        .total { font-size: 16px; font-weight: bold; }
        .qr { margin-top: 20px; }
        .foot { position: fixed; bottom: -10mm; left: 0; right: 0; text-align: center; font-size: 9px; color: #667; }
        h2 { margin-top: 24px; }
    </style>
</head>
<body>
    <div class="head">
        <div class="head-brand">
            @if($logoDataUri)
                <img class="logo" src="{{ $logoDataUri }}" alt="Azari Residences">
            @endif
            <p>{{ config('app.name') }}</p>
        </div>

        <div class="head-meta">
            <h1 class="title">{{ ucfirst($type) }}</h1>
            <p>{{ strtoupper($type) }}-{{ $booking->reference }}</p>
            <p>Issued {{ now(config('azari.timezone', 'Africa/Lagos'))->format('j F Y, g:i A') }}</p>
        </div>
    </div>

    <h2>Booking</h2>
    <table class="grid">
        <tr><td>Reference</td><td>{{ $booking->reference }}</td></tr>
        <tr><td>Status</td><td>{{ ucwords(str_replace('_', ' ', $booking->status)) }}</td></tr>
        <tr><td>Guest</td><td>{{ $booking->guest_name }}</td></tr>
        <tr><td>Residence</td><td>{{ $booking->property?->name }}</td></tr>
        <tr><td>Stay</td><td>{{ $booking->check_in?->format('j F Y') }} – {{ $booking->check_out?->format('j F Y') }}</td></tr>
        <tr><td>Guests</td><td>{{ $booking->adults }} adult(s), {{ $booking->children }} child(ren)</td></tr>
    </table>

    <h2>Charges</h2>
    <table class="grid">
        <tr><td>Subtotal</td><td class="money">{{ $booking->currency }} {{ number_format((float) $booking->subtotal, 2) }}</td></tr>
        <tr><td>Add-ons</td><td class="money">{{ $booking->currency }} {{ number_format((float) $booking->add_on_total, 2) }}</td></tr>
        <tr><td>Fees</td><td class="money">{{ $booking->currency }} {{ number_format((float) $booking->fee_total, 2) }}</td></tr>
        <tr><td>Tax</td><td class="money">{{ $booking->currency }} {{ number_format((float) $booking->tax_total, 2) }}</td></tr>
        <tr class="total"><td>Total</td><td class="money">{{ $booking->currency }} {{ number_format((float) $booking->total, 2) }}</td></tr>
        @if($payment)
            <tr><td>Payment gateway</td><td class="money">{{ ucfirst($payment->provider) }}</td></tr>
            <tr><td>Amount paid</td><td class="money">{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</td></tr>
        @endif
    </table>

    @if($qr)
        <div class="qr">
            {!! $qr !!}
            <p>Scan to verify this booking.</p>
        </div>
    @endif

    <div class="foot">Times use {{ config('azari.timezone', 'Africa/Lagos') }}. This document was generated from Azari's current authoritative booking record.</div>
</body>
</html>
