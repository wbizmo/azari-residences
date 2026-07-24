@extends('admin.layouts.app')
@section('title','Bookings')
@section('content')
<section class="az-page-heading az-page-heading--image"><div><h1>Booking operations</h1><a class="az-button az-button--light" href="{{ route('azari.admin.bookings.calendar') }}">Open calendar</a></div><img src="{{ asset('images/azari-booking-suite.png') }}" alt="Refined Azari residence prepared for arrival"></section>
<div class="az-booking-grid">
@forelse($bookings as $booking)
<article class="az-booking-card"><span>{{ $booking->reference }}</span><h2>{{ $booking->guest_name }}</h2><p>{{ $booking->check_in->format('d M Y') }} to {{ $booking->check_out->format('d M Y') }}</p>
<label class="az-field"><span>New status</span><select id="booking-status-{{ $booking->id }}">@foreach(['pending','approved','confirmed','checked_in','checked_out','cancelled'] as $status)<option value="{{ $status }}" @selected($booking->status===$status)>{{ ucwords(str_replace('_',' ',$status)) }}</option>@endforeach</select></label>
<button type="button" class="az-button az-button--primary" data-az-booking-modal="booking-status-modal" data-select="booking-status-{{ $booking->id }}" data-action="{{ route('azari.admin.bookings.transition',$booking) }}" data-reference="{{ $booking->reference }}">Review status change</button>
</article>
@empty<div class="az-empty-state">No bookings yet.</div>@endforelse
</div>{{ $bookings->links() }}

<x-azari-confirm-modal id="booking-status-modal" />
@endsection
