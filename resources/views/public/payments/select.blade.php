<x-public-site.layout title="Complete payment | Azari Residences">
<main class="site-container az-checkout-page">
    <section class="az-checkout-shell">
        <div class="az-checkout-main">
            @if($fullAccess)
                <a class="az-checkout-back" href="{{ route('bookings.show', $booking->reference) }}"><span class="material-symbols-outlined">arrow_back</span> Back to booking</a>
            @endif
            <div class="az-checkout-heading">
                <span class="eyebrow">Secure checkout</span>
                <h1>Complete payment</h1>
                <p>Choose an available payment provider. The booking is updated only after the provider transaction is verified.</p>
            </div>

            @if(session('error'))<div class="az-alert az-alert--danger">{{ session('error') }}</div>@endif
            @if(session('warning'))<div class="az-alert">{{ session('warning') }}</div>@endif

            <section class="az-checkout-panel">
                <div class="az-checkout-panel-head">
                    <div><h2>Payment method</h2><p>Continue with one of the secure providers below.</p></div>
                    <span class="material-symbols-outlined az-checkout-seal">shield_lock</span>
                </div>

                @if(empty($providers))
                    <div class="az-empty-state"><span class="material-symbols-outlined">credit_card_off</span><h3>Online payment is unavailable</h3><p>Please contact Azari and quote {{ $booking->reference }}.</p></div>
                @else
                    <div class="az-checkout-provider-list">
                        @foreach($providers as $key => $provider)
                            @php($label = $key === 'intouch' ? 'InTouch' : ucfirst($key))
                            <form method="POST" action="{{ route('public.payment.initialise', $booking->reference) }}" class="az-checkout-provider">
                                @csrf
                                <input type="hidden" name="provider" value="{{ $key }}">
                                <div class="az-provider-mark"><span class="material-symbols-outlined">payments</span></div>
                                <div class="az-provider-copy"><h3>{{ $label }}</h3><p>Secure {{ strtolower($provider->mode()) }} checkout</p></div>
                                <button class="az-checkout-provider-button" type="submit">Pay now <span class="material-symbols-outlined">arrow_forward</span></button>
                            </form>
                        @endforeach
                    </div>
                @endif
            </section>

            <div class="az-checkout-trust-row">
                <div><span class="material-symbols-outlined">lock</span><span>Encrypted checkout</span></div>
                <div><span class="material-symbols-outlined">fact_check</span><span>Server-verified payment</span></div>
                <div><span class="material-symbols-outlined">shield</span><span>Protected booking details</span></div>
            </div>
        </div>

        <aside class="az-checkout-summary">
            <div class="az-checkout-summary-card">
                <span class="eyebrow">Payment summary</span>
                @if($fullAccess)
                    <h2>{{ $booking->property?->name ?? 'Azari Residence' }}</h2>
                @else
                    <h2>Booking payment</h2>
                @endif
                <p class="az-checkout-reference">{{ $booking->reference }}</p>
                <dl>
                    <div><dt>Booking date</dt><dd>{{ $booking->created_at?->format('d M Y') }}</dd></div>
                    @if($fullAccess)
                        <div><dt>Check-in</dt><dd>{{ $booking->check_in?->format('d M Y') }}</dd></div>
                        <div><dt>Check-out</dt><dd>{{ $booking->check_out?->format('d M Y') }}</dd></div>
                        <div><dt>Guests</dt><dd>{{ $booking->adults }} adult{{ $booking->adults == 1 ? '' : 's' }}@if($booking->children), {{ $booking->children }} child{{ $booking->children == 1 ? '' : 'ren' }}@endif</dd></div>
                        <div><dt>Booking total</dt><dd>{{ $booking->currency }} {{ number_format((float) $booking->total, 2) }}</dd></div>
                        <div><dt>Paid</dt><dd>{{ $booking->currency }} {{ number_format($booking->successfulPaymentsTotal(), 2) }}</dd></div>
                    @endif
                </dl>
                <div class="az-checkout-balance"><span>Amount due</span><strong>{{ $booking->currency }} {{ number_format($booking->balanceDue(), 2) }}</strong></div>
                @unless($fullAccess)
                    <p class="az-checkout-privacy-note">This payment link does not reveal the guest, residence, dates or occupancy details.</p>
                @endunless
            </div>
        </aside>
    </section>
</main>
</x-public-site.layout>
