<x-public-site.layout title="Complete payment | Azari Residences">
<main class="site-container az-checkout-page">
    <section class="az-checkout-shell">
        <div class="az-checkout-main">
            <a class="az-checkout-back" href="{{ route('bookings.show', $booking->reference) }}"><span class="material-symbols-outlined">arrow_back</span> Back to booking</a>
            <div class="az-checkout-heading">
                <span class="eyebrow">Secure checkout</span>
                <h1>Complete your booking payment</h1>
                <p>Select a payment provider to continue. Your booking is confirmed only after the transaction is verified.</p>
            </div>

            @if(session('error'))<div class="az-alert az-alert--danger">{{ session('error') }}</div>@endif
            @if(session('warning'))<div class="az-alert">{{ session('warning') }}</div>@endif

            <section class="az-checkout-panel">
                <div class="az-checkout-panel-head">
                    <div><h2>Payment method</h2><p>Choose one of the available secure providers.</p></div>
                    <span class="material-symbols-outlined az-checkout-seal">verified_user</span>
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
                                <div class="az-provider-mark"><span class="material-symbols-outlined">account_balance_wallet</span></div>
                                <div class="az-provider-copy"><h3>{{ $label }}</h3><p>Secure {{ strtolower($provider->mode()) }} checkout</p></div>
                                <button class="az-checkout-provider-button" type="submit">Continue <span class="material-symbols-outlined">arrow_forward</span></button>
                            </form>
                        @endforeach
                    </div>
                @endif
            </section>

            <div class="az-checkout-trust-row">
                <div><span class="material-symbols-outlined">lock</span><span>Encrypted checkout</span></div>
                <div><span class="material-symbols-outlined">fact_check</span><span>Server-verified payment</span></div>
                <div><span class="material-symbols-outlined">shield</span><span>Protected booking reference</span></div>
            </div>
        </div>

        <aside class="az-checkout-summary">
            <div class="az-checkout-summary-card">
                <span class="eyebrow">Booking summary</span>
                <h2>{{ $booking->property?->name ?? 'Azari Residence' }}</h2>
                <p class="az-checkout-reference">{{ $booking->reference }}</p>
                <dl>
                    <div><dt>Check-in</dt><dd>{{ $booking->check_in?->format('d M Y') }}</dd></div>
                    <div><dt>Check-out</dt><dd>{{ $booking->check_out?->format('d M Y') }}</dd></div>
                    <div><dt>Guests</dt><dd>{{ $booking->adults }} adult{{ $booking->adults == 1 ? '' : 's' }}@if($booking->children), {{ $booking->children }} child{{ $booking->children == 1 ? '' : 'ren' }}@endif</dd></div>
                    <div><dt>Booking total</dt><dd>{{ $booking->currency }} {{ number_format((float) $booking->total, 2) }}</dd></div>
                    <div><dt>Paid</dt><dd>{{ $booking->currency }} {{ number_format($booking->successfulPaymentsTotal(), 2) }}</dd></div>
                </dl>
                <div class="az-checkout-balance"><span>Amount due</span><strong>{{ $booking->currency }} {{ number_format($booking->balanceDue(), 2) }}</strong></div>
            </div>
        </aside>
    </section>
</main>
</x-public-site.layout>
