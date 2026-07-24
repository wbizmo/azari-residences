@extends('admin.layouts.app')
@section('title','Booking calendar')
@section('content')
<section class="az-page-heading az-page-heading--image"><div><h1>Booking calendar</h1></div><img src="{{ asset('images/azari-booking-calendar.png') }}" alt="Hospitality planning calendar"></section>
<div class="az-calendar-board">@forelse($bookings as $booking)<article><time>{{ $booking->check_in->format('d M') }}</time><strong>{{ $booking->guest_name }}</strong><span>{{ $booking->reference }} · {{ $booking->status }}</span></article>@empty<div>No active bookings.</div>@endforelse</div>
@endsection
