@extends('components.public.layout')
@section('title', 'Verify Booking')
@section('content')
<section class="site-container" style="padding:120px 0 80px">
    <div class="az-verification-card">
        <p class="az-eyebrow">Reservation verification</p>
        <h1>Verify a booking</h1>
        <p>Enter the booking number printed on the receipt, invoice or confirmation message.</p>
        <form method="POST" action="{{ route('booking.verify.submit') }}" class="az-inline-form">@csrf
            <input name="booking_number" value="{{ old('booking_number') }}" placeholder="Example: AZR-2026-000001" required>
            <button class="az-button">Verify booking</button>
        </form>
        @isset($searched)
            @if($booking)
                <div class="az-verify-success"><strong>Booking verified</strong><span>Reference: {{ $booking->booking_number }}</span></div>
            @else
                <div class="az-error">No booking matched that number.</div>
            @endif
        @endisset
    </div>
</section>
@endsection
