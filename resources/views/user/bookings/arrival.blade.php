@extends('layouts.user')
@section('title','Arrival')
@section('kicker','Trip')
@section('page_title','Arrival and check-in')
@section('content')
<section class="az-user-panel"><header class="az-user-panel-header"><div><h2 class="az-user-panel-title">{{ $booking->property?->name }}</h2><p class="az-user-panel-subtitle">{{ $booking->check_in?->format('j M Y') }} to {{ $booking->check_out?->format('j M Y') }}</p></div></header><div class="az-user-panel-body">
@php
    $arrivalEditable = ! in_array($booking->status, ['cancelled', 'no_show', 'checked_out', 'completed'], true)
        && ! $booking->checked_out_at && ! $booking->completed_at;
    $showInstructions = in_array($booking->status, ['approved', 'confirmed', 'paid', 'checked_in'], true);
@endphp
<section aria-label="Your arrival information">
    <h3>Arrival information</h3>
    <p>Property-local time: {{ $booking->property_timezone ?: $booking->property?->timezone ?: config('localization.platform_timezone', 'UTC') }}</p>
    @if($booking->property?->check_in_time)
        <p>Check-in time: {{ $booking->property->check_in_time }}</p>
    @endif
    @if($booking->property?->check_out_time)
        <p>Check-out time: {{ $booking->property->check_out_time }}</p>
    @endif
    @if($showInstructions && filled($booking->property?->check_in_instructions))
        <p><strong>Property check-in instructions</strong></p>
        <div class="az-user-panel-subtitle">{!! nl2br(e($booking->property->check_in_instructions)) !!}</div>
    @elseif(! $showInstructions)
        <p>Full arrival instructions will appear after your reservation is confirmed.</p>
    @endif
</section>
@if($arrivalEditable)
<form method="POST" action="{{ route('user.bookings.phase2.arrival.update',$booking->reference) }}" class="az-form-grid">@csrf @method('PATCH')<label><span>Expected arrival time</span><input type="time" name="arrival_time" value="{{ old('arrival_time',$booking->arrival_time) }}"></label><label style="grid-column:1/-1"><span>Arrival note</span><textarea name="arrival_notes" maxlength="1000" rows="3">{{ old('arrival_notes',$booking->arrival_notes) }}</textarea></label><button class="az-user-button az-user-button--dark" type="submit">Save arrival details</button></form>
@else
<p class="az-notice" role="status">Arrival details can no longer be changed because this stay has ended or was cancelled.</p>
@endif
@if($booking->isCheckInEligible())<form method="POST" action="{{ route('user.bookings.phase2.arrival.check-in',$booking->reference) }}" style="margin-top:18px">@csrf<button class="az-user-button az-user-button--dark" type="submit">Check in</button></form>@else<div style="margin-top:18px"><strong>Online check-in is not available yet.</strong><ul>@foreach($booking->selfCheckInBlockers() as $reason)<li>{{ $reason }}</li>@endforeach</ul><p>Contact the property team if you need staffed check-in assistance.</p></div>@endif
</div></section>
@endsection
