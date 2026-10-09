@extends('layouts.user')
@section('title','Arrival')
@section('kicker','Trip')
@section('page_title','Arrival and check-in')
@section('content')
<section class="az-user-panel"><header class="az-user-panel-header"><div><h2 class="az-user-panel-title">{{ $booking->property?->name }}</h2><p class="az-user-panel-subtitle">{{ $booking->check_in?->format('j M Y') }} to {{ $booking->check_out?->format('j M Y') }}</p></div></header><div class="az-user-panel-body">
<h3>Pre-arrival checklist</h3>
<div class="az-user-list" style="margin-bottom:18px">
    <div class="az-user-list-item"><div><strong>Payment</strong><p>{{ $booking->balanceDue() <= 0 ? 'Complete' : $booking->currency.' '.number_format($booking->balanceDue(),2).' remaining' }}</p></div><span>{{ $booking->balanceDue() <= 0 ? 'Ready' : 'Action needed' }}</span></div>
    <div class="az-user-list-item"><div><strong>Adult identity checks</strong><p>{{ $booking->allRequiredGuestsVerified() ? 'Required adult guest verification is complete.' : 'One or more adult guest checks are still incomplete.' }}</p></div><span>{{ $booking->allRequiredGuestsVerified() ? 'Ready' : 'Action needed' }}</span></div>
    <div class="az-user-list-item"><div><strong>Room readiness</strong><p>{{ $booking->room_ready_at ? 'The property team released the room for arrival.' : 'The property team has not released the room yet.' }}</p></div><span>{{ $booking->room_ready_at ? 'Ready' : 'Pending' }}</span></div>
    <div class="az-user-list-item"><div><strong>Stay date</strong><p>Self check-in opens on {{ $booking->check_in?->format('j M Y') }} in the property timezone.</p></div></div>
</div>
<form method="POST" action="{{ route('user.bookings.phase2.arrival.update',$booking->reference) }}" class="az-form-grid">@csrf @method('PATCH')<label><span>Expected arrival time</span><input type="time" name="arrival_time" value="{{ old('arrival_time',$booking->arrival_time) }}"></label><label style="grid-column:1/-1"><span>Arrival note</span><textarea name="arrival_notes" maxlength="1000" rows="3">{{ old('arrival_notes',$booking->arrival_notes) }}</textarea></label><button class="az-user-button az-user-button--dark" type="submit">Save arrival details</button></form>
@if($booking->isSelfCheckInEligible())<form method="POST" action="{{ route('user.bookings.phase2.arrival.check-in',$booking->reference) }}" style="margin-top:18px">@csrf<button class="az-user-button az-user-button--dark" type="submit">Check in</button></form>@else<p style="margin-top:18px">Self check-in is not available yet. Payment, dates, identity requirements and stay status are checked by the server before check-in is allowed.</p>@endif
</div></section>
@endsection
