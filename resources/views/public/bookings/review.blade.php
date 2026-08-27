<x-public-site.layout title="Review {{ $booking->reference }} | Azari Hotels & Residences">
<main class="site-container az-s56-page">
<header><span class="eyebrow">Step 2 of 3</span><h1>Review your booking</h1><p>Every adult must be Dojah verified before payment can continue.</p></header>

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
    <h2>Adult identity verification</h2>
    @foreach($booking->guests as $guest)
        @php($verified = $guest->type === 'child' ? true : ($guest->latestIdentityVerification?->isVerified() ?? false))
        <p>
            {{ ucfirst($guest->type) }} {{ $guest->position }}: {{ $guest->full_name }}
            @if($guest->type === 'child')
                · No identity verification required
            @elseif($verified)
                · <strong>Dojah verified</strong>
            @else
                · <strong>Dojah verification required</strong>
            @endif
        </p>
    @endforeach

    @if($booking->guests->where('type','adult')->contains(fn($guest) => !($guest->latestIdentityVerification?->isVerified() ?? false)))
        <p><a class="button" href="{{ route('user.guests.index') }}">Complete guest verification</a></p>
    @endif
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

    <form method="POST" action="{{ route('azari.booking.confirm',$booking->reference) }}">
        @csrf
        <button class="button button-primary">Continue to secure payment</button>
    </form>
</section>
</main>
</x-public-site.layout>
