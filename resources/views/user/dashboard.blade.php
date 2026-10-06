@extends('layouts.user')
@section('title','Trips')
@section('kicker','Guest dashboard')
@section('page_title','Trips')
@section('content')
@php
    $featured = $currentStay ?: $nextBooking;
    $image = $featured?->property?->cover_image ? Storage::url($featured->property->cover_image) : asset('images/azari-residence-fallback.png');
    $payment = $featured?->payments?->firstWhere('status','successful') ?: $featured?->payments?->first();
@endphp

@if(!$identityVerified)
<section class="az-user-panel" style="margin-bottom:18px">
    <div class="az-user-panel-body">
        <div class="az-user-alert az-user-alert--danger">
            <strong>Identity verification required.</strong>
            <p>Your account is currently unverified. Complete Dojah verification before bookings, payments, property listings or other KYC-protected actions can continue.</p>
        </div>
        <div class="az-user-actions" style="margin-top:14px">
            <a class="az-user-button az-user-button--dark" href="{{ route('user.identity.index') }}">Verify with Dojah</a>
        </div>
    </div>
</section>
@endif

<section class="az-user-hero-grid">
    <article class="az-user-welcome">
        <div class="az-user-eyebrow"><span class="material-symbols-outlined">{{ $identityVerified ? 'verified' : 'shield' }}</span> Welcome back</div>
        <h2>{{ $currentStay ? 'Your stay is now underway.' : ($nextBooking ? 'Your next stay is beautifully arranged.' : 'Your next Resavar stay begins here.') }}</h2>
        <p>{{ $featured ? 'Review your reservation, payment status, identities and documents from one private guest area.' : 'Browse available stays, choose your dates and complete a new booking whenever you are ready.' }}</p>
        <div class="az-user-actions">
            @if($featured)<a class="az-user-button az-user-button--primary" href="{{ route('user.bookings.show',$featured->reference) }}"><span class="material-symbols-outlined">calendar_month</span>View booking</a>@endif
            <a class="az-user-button az-user-button--ghost" href="{{ route('availability.index') }}"><span class="material-symbols-outlined">search</span>Book another stay</a>
        </div>
    </article>
    <aside class="az-user-timezone-card">
        <div><div class="az-user-timezone-icon"><span class="material-symbols-outlined">schedule</span></div><h3>Your local time</h3><p>Dates and times are converted for clarity. Resavar's official operational timezone remains {{ config('localization.platform_timezone','UTC') }}.</p></div>
        <div class="az-user-timezone-value"><span>Showing times in</span><strong data-user-timezone>{{ auth()->user()->timezone ?: config('localization.platform_timezone','UTC') }}</strong></div>
    </aside>
</section>

<section class="az-user-summary-grid" aria-label="Trip summary">
    <article class="az-user-summary-card"><div class="az-user-summary-icon"><span class="material-symbols-outlined">hotel</span></div><div class="az-user-summary-value">{{ $tripCounts['current'] }}</div><div class="az-user-summary-label">Current {{ Str::plural('stay',$tripCounts['current']) }}</div></article>
    <article class="az-user-summary-card"><div class="az-user-summary-icon"><span class="material-symbols-outlined">calendar_today</span></div><div class="az-user-summary-value">{{ $tripCounts['upcoming'] }}</div><div class="az-user-summary-label">Upcoming {{ Str::plural('trip',$tripCounts['upcoming']) }}</div></article>
    <article class="az-user-summary-card"><div class="az-user-summary-icon"><span class="material-symbols-outlined">account_balance_wallet</span></div><div class="az-user-summary-value">{{ $pendingPaymentCount }}</div><div class="az-user-summary-label">Bookings with balance due</div></article>
    <article class="az-user-summary-card"><div class="az-user-summary-icon"><span class="material-symbols-outlined">history</span></div><div class="az-user-summary-value">{{ $tripCounts['past'] }}</div><div class="az-user-summary-label">Past {{ Str::plural('trip',$tripCounts['past']) }}</div></article>
</section>

<section class="az-user-dashboard-grid">
    <div class="az-user-panel">
        <header class="az-user-panel-header"><div><h2 class="az-user-panel-title">{{ $currentStay ? 'Current stay' : 'Next booking' }}</h2><p class="az-user-panel-subtitle">Live reservation and payment status</p></div><a class="az-user-button az-user-button--light" href="{{ route('user.bookings.index') }}">All bookings</a></header>
        @if($featured)
        <article class="az-user-booking-card">
            <div class="az-user-booking-image" style="--az-booking-image:url('{{ $image }}')"><span class="az-user-booking-status"><span class="material-symbols-outlined">check_circle</span>{{ str_replace('_',' ',$featured->status) }}</span><span class="az-user-booking-reference">{{ $featured->reference }}</span><div class="az-user-booking-image-copy"><h3>{{ $featured->property?->name ?? 'Resavar Stay' }}</h3><p>{{ $featured->property?->location ?? $featured->property?->locationRecord?->name ?? 'Resavar' }}</p></div></div>
            <div class="az-user-booking-body"><div class="az-user-date-grid"><div><div class="az-user-data-label">{{ $featured->checked_in_at ? 'Checked in at' : 'Scheduled arrival' }}</div><div class="az-user-data-value">@if($featured->checked_in_at)<x-user-local-time :value="$featured->checked_in_at" />@else{{ $featured->check_in?->format('j F Y') }}@endif</div></div><span class="material-symbols-outlined">east</span><div><div class="az-user-data-label">Departure</div><div class="az-user-data-value">{{ $featured->check_out?->format('j F Y') }}</div></div></div>
            <div class="az-user-meta-grid"><div class="az-user-meta-item"><span>Guests</span><strong>{{ $featured->adults }} adults · {{ $featured->children }} children</strong></div><div class="az-user-meta-item"><span>Payment</span><strong>{{ $payment ? ucfirst($payment->status).' · '.ucfirst($payment->provider) : 'No payment yet' }}</strong></div><div class="az-user-meta-item"><span>Total</span><strong>{{ $featured->currency }} {{ number_format((float)$featured->total,2) }}</strong></div></div>
            <div class="az-user-actions"><a class="az-user-button az-user-button--dark" href="{{ route('user.bookings.show',$featured->reference) }}"><span class="material-symbols-outlined">visibility</span>View details</a>@if($featured->balanceDue()>0)<a class="az-user-button az-user-button--primary" href="{{ route('public.payment.select',$featured->reference) }}"><span class="material-symbols-outlined">account_balance_wallet</span>Pay balance</a>@endif</div></div>
        </article>
        @else
        <div class="az-user-empty"><span class="material-symbols-outlined">hotel</span><h3>No upcoming booking</h3><p>Your future reservations will appear here.</p><a class="az-user-button az-user-button--dark" href="{{ route('availability.index') }}">Search stays</a></div>
        @endif
    </div>

    <aside class="az-user-panel"><header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Guest essentials</h2><p class="az-user-panel-subtitle">Complete the items needed for a smooth stay</p></div></header><div class="az-user-panel-body az-user-list">
        <a class="az-user-list-item" href="{{ route('user.identity.index') }}"><div><h3>My identity</h3><p>{{ $identityVerified ? 'Your Resavar account is verified by Dojah.' : 'Dojah verification is required.' }}</p></div><span class="material-symbols-outlined">chevron_right</span></a>
        <a class="az-user-list-item" href="{{ route('user.guests.index') }}"><div><h3>Additional adult guests</h3><p>Every additional adult completes their own Dojah verification.</p></div><span class="material-symbols-outlined">chevron_right</span></a>
        <a class="az-user-list-item" href="{{ route('user.contact') }}"><div><h3>Contact Resavar</h3><p>See the dynamically configured phone, email and support hours.</p></div><span class="material-symbols-outlined">chevron_right</span></a>
    </div></aside>
</section>

<section class="az-user-panel"><header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Recent receipts</h2><p class="az-user-panel-subtitle">Successful payments associated with your bookings</p></div><a class="az-user-button az-user-button--light" href="{{ route('user.documents.index') }}">Document centre</a></header><div class="az-user-panel-body">
@if($recentPayments->isEmpty())<div class="az-user-empty"><span class="material-symbols-outlined">receipt_long</span><p>Receipts will appear after a verified successful payment.</p></div>@else<div class="az-user-list">@foreach($recentPayments as $recent)<a class="az-user-list-item" href="{{ route('user.payments.show',$recent) }}"><div><h3>{{ $recent->receipt_number ?? $recent->reference }}</h3><p>{{ $recent->booking?->property?->name }} · {{ $recent->currency }} {{ number_format((float)$recent->amount,2) }}</p></div><span class="az-user-status">{{ ucfirst($recent->provider) }}</span></a>@endforeach</div>@endif
</div></section>

<section class="az-user-dashboard-grid" style="margin-top:18px">
    <div class="az-user-panel">
        <header class="az-user-panel-header">
            <div><h2 class="az-user-panel-title">Saved stays</h2><p class="az-user-panel-subtitle">Your favourite Resavar properties</p></div>
        </header>
        <div class="az-user-panel-body az-user-list">
            @forelse($favourites as $favourite)
                @if($favourite->property)
                    <a class="az-user-list-item" href="{{ route('properties.show',$favourite->property) }}">
                        <div><h3>{{ $favourite->property->name }}</h3><p>{{ $favourite->property->locationRecord?->name ?? $favourite->property->location }}</p></div>
                        <span class="material-symbols-outlined">favorite</span>
                    </a>
                @endif
            @empty
                <div class="az-user-empty"><p>No saved stays yet.</p></div>
            @endforelse
        </div>
    </div>

    <div class="az-user-panel">
        <header class="az-user-panel-header">
            <div><h2 class="az-user-panel-title">Saved searches</h2><p class="az-user-panel-subtitle">Continue a previous search with the same filters</p></div>
        </header>
        <div class="az-user-panel-body az-user-list">
            @forelse($savedSearches as $saved)
                <div class="az-user-list-item">
                    <div>
                        <h3>{{ $saved->name ?: ($saved->parameters['destination'] ?? 'Saved search') }}</h3>
                        <p>{{ $saved->parameters['check_in'] ?? 'Dates' }} to {{ $saved->parameters['check_out'] ?? 'flexible' }}</p>
                    </div>
                    <div class="az-user-actions">
                        <a class="az-user-button az-user-button--light" href="{{ route('availability.results',$saved->parameters) }}">Continue</a>
                        <form method="POST" action="{{ route('user.saved-searches.destroy',$saved) }}">@csrf @method('DELETE')<button class="az-user-button az-user-button--light" type="submit">Remove</button></form>
                    </div>
                </div>
            @empty
                <div class="az-user-empty"><p>No saved searches yet.</p></div>
            @endforelse
        </div>
    </div>
</section>

@if($recentlyViewed->isNotEmpty())
<section class="az-user-panel" style="margin-top:18px">
    <header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Recently viewed</h2><p class="az-user-panel-subtitle">Continue exploring stays you opened recently</p></div></header>
    <div class="az-user-panel-body az-user-list">
        @foreach($recentlyViewed as $recent)
            @if($recent->property)
                <a class="az-user-list-item" href="{{ route('properties.show',$recent->property) }}">
                    <div><h3>{{ $recent->property->name }}</h3><p>{{ $recent->property->locationRecord?->name ?? $recent->property->location }}</p></div>
                    <span class="material-symbols-outlined">history</span>
                </a>
            @endif
        @endforeach
    </div>
</section>
@endif

@if($pendingModifications->isNotEmpty())
<section class="az-user-panel" style="margin-top:18px">
    <header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Pending trip changes</h2><p class="az-user-panel-subtitle">Requests awaiting review</p></div></header>
    <div class="az-user-panel-body az-user-list">
        @foreach($pendingModifications as $modification)
            <a class="az-user-list-item" href="{{ route('user.bookings.show',$modification->booking->reference) }}">
                <div><h3>{{ Str::headline($modification->type) }}</h3><p>{{ $modification->booking?->property?->name }} · {{ $modification->reference }}</p></div>
                <span class="az-user-status az-user-status--warning">Pending</span>
            </a>
        @endforeach
    </div>
</section>
@endif
@endsection
