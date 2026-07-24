@extends('components.public.layout')
@section('title', 'Booking Verified')
@section('content')
<section class="site-container" style="padding:120px 0 80px">
<div class="az-verification-card"><p class="az-eyebrow">QR verification result</p><h1>Booking verified</h1><p>Reference: <strong>{{ $booking->booking_number }}</strong></p></div>
</section>
@endsection
