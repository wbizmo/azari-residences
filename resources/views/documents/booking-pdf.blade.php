<!doctype html>

<html>
<head>
    <meta charset="utf-8">
    
<style>
    @page {
        margin: 24mm 18mm;
    }

    body {
        font-family: DejaVu Sans, sans-serif;
        color: #17231e;
        font-size: 11px;
        line-height: 1.5;
    }

    .head {
        display: table;
        width: 100%;
        border-bottom: 2px solid #b68a4a;
        padding-bottom: 14px;
    }

    .head-brand,
    .head-meta {
        display: table-cell;
        vertical-align: top;
        width: 50%;
    }

    .head-meta {
        text-align: right;
    }

    .logo {
        display: block;
        max-width: 220px;
        max-height: 58px;
        width: auto;
        height: auto;
        margin-bottom: 8px;
    }

    .brand-name {
        margin: 0;
        font-weight: bold;
        font-size: 13px;
    }

    .title {
        margin: 0 0 8px;
        text-transform: uppercase;
        letter-spacing: 2px;
        font-size: 22px;
    }

    .meta-line {
        margin: 3px 0;
    }

    h2 {
        margin-top: 24px;
        margin-bottom: 8px;
        color: #17231e;
        font-size: 14px;
    }

    .grid {
        width: 100%;
        border-collapse: collapse;
        margin-top: 8px;
    }

    .grid td {
        padding: 8px;
        border-bottom: 1px solid #ddd;
        vertical-align: top;
    }

    .grid td:first-child {
        width: 42%;
        font-weight: bold;
        color: #44524c;
    }

    .money {
        text-align: right;
    }

    .total td {
        border-top: 2px solid #17231e;
        font-size: 16px;
        font-weight: bold;
    }

    .payment-status {
        display: inline-block;
        padding: 3px 8px;
        border: 1px solid #2f6f52;
        border-radius: 10px;
        color: #2f6f52;
        font-size: 9px;
        font-weight: bold;
        text-transform: uppercase;
    }

    .qr {
        margin-top: 20px;
        page-break-inside: avoid;
    }

    .qr-image {
        display: block;
        width: 135px;
        height: 135px;
        padding: 8px;
        border: 1px solid #d8c39e;
        background: #fff;
    }

    .qr-fallback {
        width: 135px;
        min-height: 105px;
        padding: 15px 10px;
        border: 2px dashed #b68a4a;
        background: #f7f4ee;
        color: #44524c;
        text-align: center;
        font-size: 9px;
    }

    .qr p {
        margin-top: 5px;
        color: #667;
        font-size: 9px;
    }

    .notice {
        margin-top: 18px;
        padding: 10px 12px;
        border-left: 3px solid #b68a4a;
        background: #f7f4ee;
        color: #44524c;
    }

    .foot {
        position: fixed;
        bottom: -10mm;
        left: 0;
        right: 0;
        text-align: center;
        font-size: 9px;
        color: #667;
    }


/* Premium document verification panel */
.verification-panel {
    width: 100%;
    margin-top: 24px;
    border: 1px solid #d8c39e;
    background: #f8f6f1;
    border-collapse: collapse;
    page-break-inside: avoid;
}

.verification-panel td {
    vertical-align: middle;
}

.verification-copy {
    width: 68%;
    padding: 22px 24px;
    border-right: 1px solid #e3d6bd;
    background: #f8f6f1;
}

.verification-eyebrow {
    margin: 0 0 7px;
    color: #a77b3f;
    font-size: 8px;
    font-weight: 700;
    letter-spacing: 1.5px;
    text-transform: uppercase;
}

.verification-title {
    margin: 0 0 8px;
    color: #173b31;
    font-size: 16px;
    font-weight: 700;
}

.verification-note {
    margin: 0 0 12px;
    color: #53615c;
    font-size: 9.5px;
    line-height: 1.55;
}

.verification-reference {
    display: inline-block;
    margin-top: 2px;
    padding: 6px 10px;
    border: 1px solid #d6c29d;
    background: #ffffff;
    color: #173b31;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: .65px;
}

.verification-security {
    margin: 11px 0 0;
    color: #7c6748;
    font-size: 8px;
    line-height: 1.45;
}

.verification-url {
    margin: 10px 0 0;
    color: #7b817e;
    font-size: 6.8px;
    line-height: 1.35;
    word-break: break-all;
}

.verification-qr-cell {
    width: 32%;
    padding: 18px;
    text-align: right;
    background: #ffffff;
}

.verification-qr-card {
    display: inline-block;
    width: 146px;
    padding: 9px;
    border: 1px solid #c9a96e;
    background: #ffffff;
    text-align: center;
}

.verification-qr-card .qr-image {
    display: block;
    width: 128px;
    height: 128px;
    margin: 0 auto;
    padding: 0;
    border: 0;
    background: #ffffff;
}

.verification-qr-card .qr-fallback {
    width: 110px;
    min-height: 88px;
    margin: 0 auto;
    padding: 18px 8px;
    border: 2px dashed #b68a4a;
    background: #f7f4ee;
    color: #44524c;
    text-align: center;
    font-size: 8px;
    line-height: 1.45;
}

.verification-scan-label {
    margin: 8px 0 2px;
    color: #173b31;
    font-size: 8px;
    font-weight: 700;
    letter-spacing: .7px;
    text-transform: uppercase;
}

.verification-scan-help {
    margin: 0;
    color: #7b817e;
    font-size: 7px;
    line-height: 1.35;
}

</style>

</head>

<body>
    @php
        $documentTitle = ucfirst($type);

    $paymentReference = $payment?->provider_reference
        ?: $payment?->reference
        ?: $booking->payment_reference;

    $receiptNumber = $payment?->receipt_number
        ?: $booking->receipt_number;

    $paymentDate = $payment?->paid_at
        ?: $payment?->verified_at
        ?: $booking->paid_at;

    $paymentAmount = $payment?->amount
        ?? ($booking->paid_at ? $booking->total : null);

    $paymentCurrency = $payment?->currency
        ?: $booking->currency;

    $paymentProvider = $payment?->provider
        ?: 'manual';

    $paymentMethod = $payment?->payment_method
        ?: 'Recorded payment';

    $isReceipt = $type === 'receipt';
    $isInvoice = $type === 'invoice';
@endphp

<div class="head">
    <div class="head-brand">
        @if(!empty($logoDataUri))
            <img
                class="logo"
                src="{{ $logoDataUri }}"
                alt="{{ config('app.name', 'The Azari Hotels & Residences') }}"
            >
        @endif

        <p class="brand-name">
            {{ config('app.name', 'The Azari Hotels & Residences') }}
        </p>
    </div>

    <div class="head-meta">
        <h1 class="title">{{ $documentTitle }}</h1>

        <p class="meta-line">
            {{ strtoupper($type) }}-{{ $booking->reference }}
        </p>

        @if($isReceipt && $receiptNumber)
            <p class="meta-line">
                Receipt number: {{ $receiptNumber }}
            </p>
        @endif

        <p class="meta-line">
            Issued
            {{ now(config('azari.timezone', 'Africa/Lagos'))->format('j F Y, g:i A') }}
        </p>
    </div>
</div>

<h2>Booking</h2>

<table class="grid">
    <tr>
        <td>Reference</td>
        <td>{{ $booking->reference }}</td>
    </tr>

    <tr>
        <td>Status</td>
        <td>{{ ucwords(str_replace('_', ' ', $booking->status)) }}</td>
    </tr>

    <tr>
        <td>Verification status</td>
        <td>{{ ucwords(str_replace('_', ' ', $booking->verification_status)) }}</td>
    </tr>

    <tr>
        <td>Guest</td>
        <td>{{ $booking->guest_name }}</td>
    </tr>

    <tr>
        <td>Email</td>
        <td>{{ $booking->guest_email }}</td>
    </tr>

    @if($booking->guest_phone)
        <tr>
            <td>Phone</td>
            <td>{{ $booking->guest_phone }}</td>
        </tr>
    @endif

    <tr>
        <td>Residence</td>
        <td>{{ $booking->property?->name ?: 'Not assigned' }}</td>
    </tr>

    <tr>
        <td>Stay</td>
        <td>
            {{ $booking->check_in?->format('j F Y') ?: 'Not available' }}
            –
            {{ $booking->check_out?->format('j F Y') ?: 'Not available' }}
        </td>
    </tr>

    <tr>
        <td>Guests</td>
        <td>
            {{ (int) $booking->adults }} adult(s),
            {{ (int) $booking->children }} child(ren)
        </td>
    </tr>

    <tr>
        <td>Rooms</td>
        <td>{{ (int) $booking->rooms }}</td>
    </tr>
</table>

<h2>Charges</h2>

<table class="grid">
    <tr>
        <td>Subtotal</td>
        <td class="money">
            {{ $booking->currency }}
            {{ number_format((float) $booking->subtotal, 2) }}
        </td>
    </tr>

    <tr>
        <td>Add-ons</td>
        <td class="money">
            {{ $booking->currency }}
            {{ number_format((float) $booking->add_on_total, 2) }}
        </td>
    </tr>

    <tr>
        <td>Fees</td>
        <td class="money">
            {{ $booking->currency }}
            {{ number_format((float) $booking->fee_total, 2) }}
        </td>
    </tr>

    <tr>
        <td>Tax</td>
        <td class="money">
            {{ $booking->currency }}
            {{ number_format((float) $booking->tax_total, 2) }}
        </td>
    </tr>

    <tr class="total">
        <td>Total</td>
        <td class="money">
            {{ $booking->currency }}
            {{ number_format((float) $booking->total, 2) }}
        </td>
    </tr>
</table>

@if($payment || $booking->paid_at)
    <h2>Payment</h2>

    <table class="grid">
        <tr>
            <td>Payment status</td>
            <td>
                <span class="payment-status">
                    {{ ucwords(str_replace('_', ' ', $payment?->status ?: 'successful')) }}
                </span>
            </td>
        </tr>

        <tr>
            <td>Payment provider</td>
            <td>{{ ucfirst($paymentProvider) }}</td>
        </tr>

        <tr>
            <td>Payment method</td>
            <td>{{ $paymentMethod }}</td>
        </tr>

        @if($paymentReference)
            <tr>
                <td>Payment reference</td>
                <td>{{ $paymentReference }}</td>
            </tr>
        @endif

        @if($receiptNumber)
            <tr>
                <td>Receipt number</td>
                <td>{{ $receiptNumber }}</td>
            </tr>
        @endif

        @if($paymentDate)
            <tr>
                <td>Payment date</td>
                <td>
                    {{ $paymentDate instanceof \Carbon\CarbonInterface
                        ? $paymentDate->format('j F Y, g:i A')
                        : \Illuminate\Support\Carbon::parse($paymentDate)->format('j F Y, g:i A') }}
                </td>
            </tr>
        @endif

        @if($paymentAmount !== null)
            <tr>
                <td>Amount paid</td>
                <td class="money">
                    {{ $paymentCurrency }}
                    {{ number_format((float) $paymentAmount, 2) }}
                </td>
            </tr>
        @endif
    </table>
@elseif($isInvoice)
    <div class="notice">
        This invoice has not yet been marked as paid.
    </div>
@endif


@php
    $documentKind = strtolower(str_replace(['_', ' '], '-', $type));

    $verificationContent = match ($documentKind) {
        'receipt' => [
            'eyebrow' => 'Payment authenticated',
            'title' => 'This receipt is digitally verifiable',
            'note' => 'This receipt confirms that payment has been recorded successfully against the booking shown above. Scan the code to verify the booking reference and confirm its current status directly from The Azari Hotels & Residences.',
            'security' => 'For your protection, validate this receipt before relying on printed or forwarded copies.',
        ],

        'invoice' => [
            'eyebrow' => 'Secure invoice verification',
            'title' => 'Confirm this invoice before payment',
            'note' => 'Scan the code to confirm that this invoice belongs to the stated booking and to review its latest booking and payment status. Always verify the reference before completing any payment.',
            'security' => 'Payment status may change after this document is issued. The online booking record remains authoritative.',
        ],

        'booking-confirmation', 'confirmation' => [
            'eyebrow' => 'Stay confirmation',
            'title' => 'Verify your reservation instantly',
            'note' => 'Scan the code to confirm the reservation reference, residence, stay dates and current booking status. Keep this confirmation available for arrival and check-in assistance.',
            'security' => 'Guests may be asked to present a valid identity document matching the booking record.',
        ],

        default => [
            'eyebrow' => 'Document verification',
            'title' => 'Verify this booking document',
            'note' => 'Scan the code to open the official booking verification page and confirm the reference and current status shown in The Azari Hotels & Residences records.',
            'security' => 'The live booking record remains the authoritative source for this document.',
        ],
    };
@endphp

<table class="verification-panel" role="presentation">
    <tr>
        <td class="verification-copy">
            <p class="verification-eyebrow">
                {{ $verificationContent['eyebrow'] }}
            </p>

            <h3 class="verification-title">
                {{ $verificationContent['title'] }}
            </h3>

            <p class="verification-note">
                {{ $verificationContent['note'] }}
            </p>

            <div class="verification-reference">
                BOOKING REFERENCE: {{ $booking->reference }}
            </div>

            <p class="verification-security">
                {{ $verificationContent['security'] }}
            </p>

            <p class="verification-url">
                {{ $verificationUrl }}
            </p>
        </td>

        <td class="verification-qr-cell">
            <div class="verification-qr-card">
                @if(!empty($qrDataUri))
                    <img
                        class="qr-image"
                        src="{{ $qrDataUri }}"
                        alt="Booking verification QR code"
                    >
                @elseif(!empty($qrFallbackUrls))
                    <img
                        class="qr-image"
                        src="{{ $qrFallbackUrls[0] }}"
                        alt="Booking verification QR code"
                    >
                @else
                    <div class="qr-fallback">
                        Could not generate the QR code.<br>
                        Please verify the booking details manually.
                    </div>
                @endif

                <p class="verification-scan-label">
                    Scan to verify
                </p>

                <p class="verification-scan-help">
                    Opens the official booking record
                </p>
            </div>
        </td>
    </tr>
</table>


<div class="foot">
    Times use {{ config('azari.timezone', 'Africa/Lagos') }}.
    This document was generated from Azari's current authoritative booking record.
</div>

</body>
</html>
