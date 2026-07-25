@extends('layouts.user')
@section('title', 'Booking '.$booking->reference)
@section('content')
@php
    $successful = $booking->payments->firstWhere('status', 'successful');
    $latestPayment = $booking->payments->sortByDesc('created_at')->first();
@endphp

<div class="az-user-actions" style="margin-bottom:18px">
    <a class="az-user-button az-user-button--dark" href="{{ route('user.bookings.receipt', $booking->reference) }}" target="_blank">
        <span class="material-symbols-outlined">description</span> Print invoice
    </a>
    @if($booking->balanceDue() > 0)
        <a class="az-user-button az-user-button--primary" href="{{ route('public.payment.select', $booking->reference) }}">Pay securely</a>
    @endif
</div>

<div class="az-user-actions" style="margin-bottom:18px"><a class="az-user-button az-user-button--light" target="_blank" href="{{ route('user.bookings.documents',[$booking->reference,'confirmation']) }}">Booking confirmation</a><a class="az-user-button az-user-button--light" target="_blank" href="{{ route('user.bookings.documents',[$booking->reference,'invoice']) }}">Invoice PDF</a><a class="az-user-button az-user-button--light" target="_blank" href="{{ route('user.bookings.documents',[$booking->reference,'receipt']) }}">Receipt PDF</a>@if($booking->isCheckInEligible())<form method="post" action="{{ route('user.bookings.check-in',$booking->reference) }}">@csrf<button class="az-user-button az-user-button--primary">Check in</button></form>@endif</div><section class="az-user-detail-grid">
    <div class="az-user-panel">
        <header class="az-user-panel-header">
            <div><h2 class="az-user-panel-title">Reservation</h2><p class="az-user-panel-subtitle">{{ $booking->property?->name ?? 'Azari Residence' }}</p></div>
            <span class="az-user-status">{{ str_replace('_', ' ', $booking->status) }}</span>
        </header>
        <div class="az-user-panel-body">
            <dl class="az-user-detail-list">
                <div class="az-user-detail-row"><dt>Booking reference</dt><dd>{{ $booking->reference }}</dd></div>
                <div class="az-user-detail-row"><dt>Property</dt><dd>{{ $booking->property?->name }}</dd></div>
                <div class="az-user-detail-row"><dt>Unit or room</dt><dd>{{ $booking->property?->unit_number ?: $booking->property?->code }}</dd></div>
                <div class="az-user-detail-row"><dt>Adults / children</dt><dd>{{ $booking->adults }} / {{ $booking->children }}</dd></div>
                <div class="az-user-detail-row"><dt>Number of nights</dt><dd>{{ $booking->nights }}</dd></div>
                @if($booking->checked_in_at)
                    <div class="az-user-detail-row"><dt>Checked in at</dt><dd><x-user-local-time :value="$booking->checked_in_at" /></dd></div>
                @else
                    <div class="az-user-detail-row"><dt>Scheduled arrival</dt><dd>{{ $booking->check_in?->format('j F Y') }}</dd></div>
                @endif
                @if($booking->completed_at)
                    <div class="az-user-detail-row"><dt>Checked out at</dt><dd><x-user-local-time :value="$booking->completed_at" /></dd></div>
                @else
                    <div class="az-user-detail-row"><dt>Scheduled departure</dt><dd>{{ $booking->check_out?->format('j F Y') }}</dd></div>
                @endif
            </dl>
        </div>
    </div>

    <div class="az-user-panel">
        <header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Charges and payment</h2></div></header>
        <div class="az-user-panel-body">
            <dl class="az-user-detail-list">
                <div class="az-user-detail-row"><dt>Subtotal</dt><dd>{{ $booking->currency }} {{ number_format((float) $booking->subtotal, 2) }}</dd></div>
                <div class="az-user-detail-row"><dt>Add-ons</dt><dd>{{ $booking->currency }} {{ number_format((float) $booking->add_on_total, 2) }}</dd></div>
                <div class="az-user-detail-row"><dt>Fees</dt><dd>{{ $booking->currency }} {{ number_format((float) $booking->fee_total, 2) }}</dd></div>
                <div class="az-user-detail-row"><dt>Tax</dt><dd>{{ $booking->currency }} {{ number_format((float) $booking->tax_total, 2) }}</dd></div>
                <div class="az-user-detail-row"><dt>Total</dt><dd>{{ $booking->currency }} {{ number_format((float) $booking->total, 2) }}</dd></div>
                <div class="az-user-detail-row"><dt>Paid</dt><dd>{{ $booking->currency }} {{ number_format($booking->successfulPaymentsTotal(), 2) }}</dd></div>
                <div class="az-user-detail-row"><dt>Balance</dt><dd>{{ $booking->currency }} {{ number_format($booking->balanceDue(), 2) }}</dd></div>
                <div class="az-user-detail-row"><dt>Payment status</dt><dd>{{ str_replace('_', ' ', $latestPayment?->status ?? 'not started') }}</dd></div>
                <div class="az-user-detail-row"><dt>Gateway</dt><dd>{{ $latestPayment ? ucfirst($latestPayment->provider) : 'Not selected' }}</dd></div>
            </dl>
        </div>
    </div>
</section>

<section class="az-user-detail-grid" style="margin-top:18px">
    <div class="az-user-panel">
        <header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Guests and identities</h2></div></header>
        <div class="az-user-panel-body"><div class="az-user-list">
            @forelse($booking->guests as $guest)
                @php($identity = $guest->identityLink?->userIdentityDocument ?: $guest->identityDocument)
                <div class="az-user-list-item">
                    <div><h3>{{ $guest->full_name }} {{ $guest->is_lead ? '(booking owner)' : '' }}</h3><p>{{ ucfirst($guest->type) }} · {{ $identity ? 'Identity attached' : 'Identity pending' }}</p></div>
                    <span class="az-user-status {{ !$identity && $guest->type === 'adult' ? 'az-user-status--warning' : '' }}">{{ $identity ? 'Ready' : 'Pending' }}</span>
                </div>
            @empty
                <div class="az-user-list-item"><div><h3>No additional guests</h3></div></div>
            @endforelse
        </div></div>
    </div>

    <div class="az-user-panel">
        <header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Booking links</h2></div></header>
        <div class="az-user-panel-body az-user-list">
            <a class="az-user-list-item" href="{{ route('user.payments.index') }}"><div><h3>Payments</h3><p>{{ $booking->payments_count }} payment record(s)</p></div><span class="material-symbols-outlined">chevron_right</span></a>
            <a class="az-user-list-item" href="{{ route('user.documents.index') }}"><div><h3>Receipts and invoices</h3><p>{{ $successful ? 'Receipt available' : 'Available after successful payment' }}</p></div><span class="material-symbols-outlined">chevron_right</span></a>
            <a class="az-user-list-item" href="{{ route('user.contact') }}"><div><h3>Contact Azari</h3><p>Get help with this booking</p></div><span class="material-symbols-outlined">chevron_right</span></a>
        </div>
    </div>
</section>

<section class="az-user-panel" style="margin-top:18px">
    <header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Booking timeline</h2></div></header>
    <div class="az-user-panel-body az-user-list">
        @forelse($timeline as $event)
            <div class="az-user-list-item"><div><h3>{{ str_replace('_', ' ', $event->status ?? $event->to_status ?? 'Booking updated') }}</h3><p><x-user-local-time :value="$event->created_at" /></p></div></div>
        @empty
            <div class="az-user-list-item"><div><h3>Booking created</h3><p><x-user-local-time :value="$booking->created_at" /></p></div></div>
        @endforelse
    </div>
    <div class="az-user-pagination"><span>Page {{ $timeline->currentPage() }} of {{ $timeline->lastPage() }}</span>{{ $timeline->links() }}</div>
</section>
@endsection
