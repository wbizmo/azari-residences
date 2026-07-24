@extends('components.public.layout')
@section('title','Booking '.$booking->reference)
@section('content')<section class="az-booking-confirmation"><span>Reservation received</span><h1>{{ $booking->reference }}</h1><p>{{ $booking->guest_name }}</p><p>{{ $booking->check_in->format('d M Y') }} to {{ $booking->check_out->format('d M Y') }}</p><strong>{{ strtoupper($booking->status) }}</strong></section>@endsection
