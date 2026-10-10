@php
    $documentType = $documentType ?? 'Invoice';
    $documentReference = $invoiceNumber ?? ($booking->reference ?? '');
    $documentTotal = $total ?? (($booking->currency ?? 'NGN').' '.number_format(
        $documentType === 'Receipt' && isset($booking) ? (float) $booking->netPaidTotal() : (float) ($booking->total ?? 0), 2));
    $documentFooter = $footer ?? 'Generated from the current Resavar booking record.';
@endphp
@push('head')
<style>
.resavar-doc-preview{max-width:900px;margin:16px auto 28px;padding:clamp(24px,5vw,44px);background:#fff;border:1px solid #D8E2EE;color:#052058}
.resavar-doc-head{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-start;gap:24px;padding-bottom:20px;border-bottom:2px solid #052058}
.resavar-doc-logo{max-width:185px;max-height:52px;object-fit:contain}
.resavar-doc-brand{font-size:20px;font-weight:800;color:#052058}
.resavar-doc-type{text-align:right;min-width:140px}
.resavar-doc-type h1{margin:0 0 7px;color:#052058;font-size:23px;letter-spacing:.02em}
.resavar-doc-type small{color:#526581;overflow-wrap:anywhere;word-break:break-word}
.resavar-doc-data{width:100%;border-collapse:collapse;margin-top:24px}
.resavar-doc-data th,.resavar-doc-data td{padding:12px 10px;text-align:left;border-bottom:1px solid #DCE5F0;vertical-align:top;overflow-wrap:anywhere}
.resavar-doc-data th{width:38%;white-space:normal;color:#526581;font-weight:600}
.resavar-doc-data td{color:#052058;font-weight:700}
.resavar-doc-total{margin-top:24px;display:flex;flex-wrap:wrap;justify-content:space-between;gap:10px;border-top:2px solid #052058;padding-top:20px;font-size:18px;font-weight:800}
.resavar-doc-footer{margin-top:30px;padding-top:14px;border-top:1px solid #DCE5F0;color:#526581;font-size:12px}
.resavar-doc-print-button{display:inline-flex;margin:0 0 14px;padding:10px 18px;border-radius:9px;background:#052058;color:#fff;cursor:pointer;border:0}
@media(max-width:600px){.resavar-doc-head{display:block}.resavar-doc-data th{width:43%}.resavar-doc-type{text-align:left}.resavar-doc-preview{padding:20px}.resavar-doc-total{font-size:15px}}
@media print{
    @page{size:A4 portrait;margin:16mm}
    body.az-admin-body{background:#fff!important;color:#052058!important}
    .az-admin-sidebar,.az-admin-topbar,.az-skip-link,.resavar-doc-print-button{display:none!important}
    .az-admin-app,.az-admin-main,.az-admin-content{display:block!important;width:100%!important;min-height:0!important;margin:0!important;padding:0!important;background:#fff!important}
    .resavar-doc-preview{max-width:none;border:0;margin:0;padding:0;box-shadow:none}.resavar-doc-data{page-break-inside:auto}.resavar-doc-data tr{page-break-inside:avoid;break-inside:avoid}
    .resavar-doc-head,.resavar-doc-total{break-inside:avoid}
}
</style>
@endpush
<div class="resavar-doc-preview">
    <button type="button" class="resavar-doc-print-button" onclick="window.print()">Print or save as PDF</button>
    <header class="resavar-doc-head">
        <div>
            @if(!empty($logo))
                <img class="resavar-doc-logo" src="{{ $logo }}" alt="Resavar">
            @else
                <img class="resavar-doc-logo" src="{{ asset('images/logo-light.png') }}" alt="Resavar">
            @endif
            <p class="resavar-doc-brand">Resavar</p>
        </div>
        <div class="resavar-doc-type">
            <h1>{{ $documentType }}</h1>
            <small>{{ $documentReference }}</small>
        </div>
    </header>
    <table class="resavar-doc-data">
        <tr><th>Booking reference</th><td>{{ $booking->reference ?? $booking->booking_number ?? 'Not supplied' }}</td></tr>
        @if(!empty($booking->guest_name))
            <tr><th>Guest</th><td>{{ $booking->guest_name }}</td></tr>
        @endif
        @if(!empty($booking->property))
            <tr><th>Stay</th><td>{{ $booking->property?->name ?? 'Not assigned' }}</td></tr>
        @endif
    </table>
    <div class="resavar-doc-total">
        <span>{{ $documentType === 'Receipt' ? 'Amount received' : 'Total' }}</span>
        <span>{{ $documentTotal }}</span>
    </div>
    <p class="resavar-doc-footer">{{ $documentFooter }}</p>
</div>
