<x-public-site.layout title="Review {{ $booking->reference }} | Resavar">
<main class="site-container az-s56-page">
<header>
    <span class="eyebrow">Step 2 of 3</span>
    <h1>Review your booking</h1>
    <p>{{ $booking->isAwaitingApproval() ? 'Your request has been submitted. Review the details below while the owner or Resavar team responds.' : 'Check the stay and guest details below, then continue to payment.' }}</p>
</header>

<div class="az-detail-grid">
    <section class="az-panel">
        <h2>Stay</h2>
        <p>{{ $booking->property->name }}</p>
        <p>{{ $booking->check_in->format('d M Y') }} to {{ $booking->check_out->format('d M Y') }}</p>
        <p>{{ $booking->nights }} nights · {{ $booking->adults }} adults · {{ $booking->children }} children</p>
    </section>

    <section class="az-panel">
        <h2>Lead guest</h2>
        <p>{{ $booking->guest_name }}</p>
        <p>{{ $booking->guest_email }} · {{ $booking->guest_phone }}</p>
        <p>{{ $booking->address }}, {{ $booking->city }}, {{ $booking->country }}</p>
    </section>
</div>

<section class="az-panel">
    <h2>Guests</h2>
    @foreach($booking->guests as $guest)
        <p>{{ ucfirst($guest->type) }} {{ $guest->position }}: {{ $guest->full_name }}</p>
    @endforeach
</section>

<section class="az-panel az-booking-total">
    <h2>Voucher</h2>

    @if($booking->voucher_code)
        <p><strong>{{ $booking->voucher_code }}</strong> saved you {{ $booking->currency }} {{ number_format((float)$booking->discount_total,2) }}</p>
        <form method="POST" action="{{ route('azari.booking.voucher.destroy',$booking->reference) }}">
            @csrf
            @method('DELETE')
            <button type="submit">Remove voucher</button>
        </form>
    @else
        <form method="POST" action="{{ route('azari.booking.voucher.store',$booking->reference) }}">
            @csrf
            <label for="voucher_code">Discount code</label>
            <input id="voucher_code" name="voucher_code" maxlength="64" required>
            <button type="submit">Apply voucher</button>
        </form>
    @endif

    <p>Taxes: {{ $booking->currency }} {{ number_format($booking->tax_total,2) }}</p>
    <strong>Total: {{ $booking->currency }} {{ number_format($booking->total,2) }}</strong>

    @if($booking->isAwaitingApproval())
        <p role="status"><strong>{{ $booking->approval?->status === 'under_review' ? 'Under review' : 'Awaiting approval' }}.</strong>
            This is a request, not a paid or confirmed reservation. Payment is unavailable until approval.</p>
        <p>Review deadline: {{ $booking->expires_at?->format('j M Y, H:i') }}</p>
        <a class="button button-primary" href="{{ route('user.bookings.show', $booking->reference) }}">Track request</a>
    @elseif($booking->approval && $booking->approval->status === 'declined')
        <p role="status">The owner declined your request. {{ $booking->approval->review_note }}</p>
        <a class="button" href="{{ route('availability.index') }}">Search other dates</a>
    @else
        <form method="POST" action="{{ route('azari.booking.confirm',$booking->reference) }}">
            @csrf
            <button class="button button-primary">Continue to secure payment</button>
        </form>
    @endif
</section>
</main>
</x-public-site.layout>
