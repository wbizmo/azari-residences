@extends('admin.layouts.app')
@section('title', 'Booking '.$booking->reference)
@section('content')
<section class="az-page-heading">
    <div><span class="eyebrow">Booking record</span><h1>{{ $booking->reference }}</h1><p>{{ $booking->property?->name }}</p></div>
    <div class="az-actions">
        @if($booking->payments->contains('status', 'successful'))
            <a class="az-button" href="{{ route('azari.admin.bookings.receipt', $booking) }}" target="_blank">Print or save receipt</a>
        @endif
        <a class="az-button" href="{{ route('azari.admin.bookings.index') }}">Back to bookings</a>
    </div>
</section>

<div class="az-detail-grid">
    <section class="az-panel"><h2>Stay details</h2><dl class="az-definition-list"><dt>Residence</dt><dd>{{ $booking->property?->name }}</dd><dt>Dates</dt><dd>{{ $booking->check_in?->format('d M Y') }} to {{ $booking->check_out?->format('d M Y') }}</dd><dt>Occupancy</dt><dd>{{ $booking->adults }} adults · {{ $booking->children }} children</dd><dt>Status</dt><dd>{{ ucwords(str_replace('_', ' ', $booking->status)) }}</dd><dt>Total</dt><dd>{{ $booking->currency }} {{ number_format((float) $booking->total, 2) }}</dd></dl></section>
    <section class="az-panel"><h2>Lead guest</h2><dl class="az-definition-list"><dt>Name</dt><dd>{{ $booking->guest_name }}</dd><dt>Email</dt><dd>{{ $booking->guest_email }}</dd><dt>Phone</dt><dd>{{ $booking->guest_phone ?: 'Not supplied' }}</dd><dt>Nationality</dt><dd>{{ $booking->nationality ?: 'Not supplied' }}</dd><dt>Address</dt><dd>{{ collect([$booking->address, $booking->city, $booking->country])->filter()->implode(', ') ?: 'Not supplied' }}</dd><dt>Arrival time</dt><dd>{{ $booking->arrival_time ?: 'Not supplied' }}</dd><dt>Special requests</dt><dd>{{ $booking->guest_notes ?: 'None' }}</dd></dl></section>
</div>

<section class="az-panel"><h2>Guests and identity documents</h2><div class="az-booking-grid">@forelse($booking->guests as $guest)<article class="az-booking-card"><span>{{ ucfirst($guest->type) }} {{ $guest->position }}</span><h3>{{ $guest->full_name }}</h3>@if($guest->identityDocument)<p>{{ ucwords(str_replace('_', ' ', $guest->identityDocument->document_type)) }}</p><a class="az-button" href="{{ route('azari.admin.bookings.document', [$booking, $guest->identityDocument]) }}">Open identity document</a>@elseif($guest->type === 'adult')<p>No identity document stored.</p>@else<p>No identity document attached.</p>@endif</article>@empty<p>No additional guests.</p>@endforelse</div></section>

@if(!in_array($booking->status, ['cancelled', 'completed', 'checked_out'], true))
<section class="az-panel az-cancellation-panel">
    <h2>Cancel booking</h2>
    <form method="POST" action="{{ route('azari.admin.bookings.cancel', $booking) }}" class="az-form-grid az-contained-form">
        @csrf
        @method('PUT')
        <label class="az-field az-span-2"><span>Cancellation reason</span><textarea name="reason" required maxlength="1000"></textarea></label>
        <div class="az-form-actions az-span-2"><button class="az-button az-button--danger" type="submit">Cancel booking</button></div>
    </form>
</section>
@endif
@endsection
