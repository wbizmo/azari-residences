@extends('admin.layouts.app')
@section('title','Bookings')
@section('content')
<section class="az-page-heading"><div><span class="eyebrow">Reservations</span><h1>Bookings</h1><p>Search by booking reference or guest information, then open the complete record.</p></div></section>
<form method="GET" class="az-filter-bar" action="{{ route('azari.admin.bookings.index') }}">
    <label class="az-field"><span>Booking search</span><input name="search" value="{{ $search }}" placeholder="Booking number, guest, email or phone"></label>
    <label class="az-field"><span>Status</span><select name="status"><option value="">All statuses</option>@foreach(['pending','confirmed','checked_in','checked_out','cancelled'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ ucwords(str_replace('_',' ',$status)) }}</option>@endforeach</select></label>
    <button class="az-button az-button--primary" type="submit">Search</button>
</form>
<div class="az-booking-grid">
@forelse($bookings as $booking)
<article class="az-booking-card"><span>{{ $booking->reference }}</span><h2>{{ $booking->guest_name }}</h2><p>{{ $booking->property?->name }}</p><p>{{ $booking->check_in->format('d M Y') }} to {{ $booking->check_out->format('d M Y') }}</p><p>{{ ucfirst(str_replace('_',' ',$booking->status)) }}</p><a class="az-button az-button--primary" href="{{ route('azari.admin.bookings.show',$booking) }}">View booking</a></article>
@empty<div class="az-empty-state">No bookings matched your search.</div>@endforelse
</div>
{{ $bookings->links() }}
@endsection
