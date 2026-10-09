@extends('layouts.user')
@section('title','Arrival')
@section('kicker','Trip')
@section('page_title','Arrival and check-in')
@section('content')
<section class="az-user-panel"><header class="az-user-panel-header"><div><h2 class="az-user-panel-title">{{ $booking->property?->name }}</h2><p class="az-user-panel-subtitle">{{ $booking->check_in?->format('j M Y') }} to {{ $booking->check_out?->format('j M Y') }}</p></div></header><div class="az-user-panel-body">
<form method="POST" action="{{ route('user.bookings.phase2.arrival.update',$booking->reference) }}" class="az-form-grid">@csrf @method('PATCH')<label><span>Expected arrival time</span><input type="time" name="arrival_time" value="{{ old('arrival_time',$booking->arrival_time) }}"></label><label style="grid-column:1/-1"><span>Arrival note</span><textarea name="arrival_notes" maxlength="1000" rows="3">{{ old('arrival_notes',$booking->arrival_notes) }}</textarea></label><button class="az-user-button az-user-button--dark" type="submit">Save arrival details</button></form>
@if($booking->isCheckInEligible())<form method="POST" action="{{ route('user.bookings.phase2.arrival.check-in',$booking->reference) }}" style="margin-top:18px">@csrf<button class="az-user-button az-user-button--dark" type="submit">Check in</button></form>@else<div style="margin-top:18px"><strong>Online check-in is not available yet.</strong><ul>@foreach($booking->selfCheckInBlockers() as $reason)<li>{{ $reason }}</li>@endforeach</ul><p>Contact the property team if you need staffed check-in assistance.</p></div>@endif
</div></section>
@endsection
