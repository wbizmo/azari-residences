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
            @if($errors->any())
                <div class="az-alert az-alert--danger">
                    {{ $errors->first() }}
                </div>
            @endif

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

                            @if($key === 'flutterwave')
                                <form method="POST" action="{{ route('public.payment.initialise', $booking->reference) }}" class="az-checkout-provider az-flw-v4-provider" id="flutterwaveV4Form">
                                    @csrf
                                    <input type="hidden" name="provider" value="flutterwave">
                                    <div class="az-provider-mark"><span class="material-symbols-outlined">payments</span></div>
                                    <div class="az-provider-copy">
                                        <h3>Flutterwave</h3>
                                        <p>Secure {{ strtolower($provider->mode()) }} API v4 payment</p>

                                        <div class="az-flw-v4-fields">
                                            <label for="flutterwavePaymentMethod">Payment option</label>
                                            <select id="flutterwavePaymentMethod" name="flutterwave_payment_method" required>
                                                @foreach($flutterwaveMethods as $method)
                                                    <option value="{{ $method }}" @selected(old('flutterwave_payment_method', $flutterwaveMethods[0] ?? 'opay') === $method)>
                                                        {{ $method === 'opay' ? 'OPay' : 'USSD' }}
                                                    </option>
                                                @endforeach
                                            </select>

                                            <div id="flutterwaveBankField" hidden>
                                                <label for="flutterwaveUssdBank">Bank</label>
                                                <select id="flutterwaveUssdBank" name="flutterwave_ussd_bank">
                                                    <option value="">Select bank</option>
                                                    @foreach($flutterwaveBanks as $bank)
                                                        <option value="{{ $bank['code'] }}" @selected(old('flutterwave_ussd_bank') === $bank['code'])>
                                                            {{ $bank['name'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <button class="az-checkout-provider-button" type="submit">Continue <span class="material-symbols-outlined">arrow_forward</span></button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('public.payment.initialise', $booking->reference) }}" class="az-checkout-provider">
                                    @csrf
                                    <input type="hidden" name="provider" value="{{ $key }}">
                                    <div class="az-provider-mark"><span class="material-symbols-outlined">payments</span></div>
                                    <div class="az-provider-copy"><h3>{{ $label }}</h3><p>Secure {{ strtolower($provider->mode()) }} checkout</p></div>
                                    <button class="az-checkout-provider-button" type="submit">Pay now <span class="material-symbols-outlined">arrow_forward</span></button>
                                </form>
                            @endif
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
                        ((float)$booking->discount_total > 0)<div><dt>Voucher {{ $booking->voucher_code }}</dt><dd>-{{ $booking->currency }} {{ number_format((float)$booking->discount_total, 2) }}</dd></div>((float)$booking->discount_total > 0)<div><dt>Voucher {{ $booking->voucher_code }}</dt><dd>-{{ $booking->currency }} {{ number_format((float)$booking->discount_total, 2) }}</dd></div>((float)$booking->discount_total > 0)<div><dt>Voucher {{ $booking->voucher_code }}</dt><dd>-{{ $booking->currency }} {{ number_format((float)$booking->discount_total, 2) }}</dd></div>((float)$booking->discount_total > 0)<div><dt>Voucher {{ $booking->voucher_code }}</dt><dd>-{{ $booking->currency }} {{ number_format((float)$booking->discount_total, 2) }}</dd></div>((float)$booking->discount_total > 0)<div><dt>Voucher {{ $booking->voucher_code }}</dt><dd>-{{ $booking->currency }} {{ number_format((float)$booking->discount_total, 2) }}</dd></div>((float)$booking->discount_total > 0)<div><dt>Voucher {{ $booking->voucher_code }}</dt><dd>-{{ $booking->currency }} {{ number_format((float)$booking->discount_total, 2) }}</dd></div>((float)$booking->discount_total > 0)<div><dt>Voucher {{ $booking->voucher_code }}</dt><dd>-{{ $booking->currency }} {{ number_format((float)$booking->discount_total, 2) }}</dd></div>((float)$booking->discount_total > 0)<div><dt>Voucher {{ $booking->voucher_code }}</dt><dd>-{{ $booking->currency }} {{ number_format((float)$booking->discount_total, 2) }}</dd></div><div><dt>Booking total</dt><dd>{{ $booking->currency }} {{ number_format((float) $booking->total, 2) }}</dd></div>
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

<style>
    .az-flw-v4-provider {
        align-items: flex-start;
    }

    .az-flw-v4-fields {
        display: grid;
        gap: .55rem;
        margin-top: .85rem;
        max-width: 24rem;
    }

    .az-flw-v4-fields label {
        color: var(--muted, #6e7a75);
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .az-flw-v4-fields select {
        min-height: 2.85rem;
        width: 100%;
        padding: 0 .9rem;
        color: var(--ink, #18231f);
        background: var(--paper, #fffdf9);
        border: 1px solid var(--line, #e7e1d7);
        border-radius: .8rem;
        outline: 0;
    }

    .az-flw-v4-fields select:focus-visible {
        border-color: var(--brass, #b58a4a);
        box-shadow: 0 0 0 3px rgba(181, 138, 74, .18);
    }

    #flutterwaveBankField {
        display: grid;
        gap: .55rem;
    }

    #flutterwaveBankField[hidden] {
        display: none;
    }
</style>

@if(array_key_exists('flutterwave', $providers))
<script>
    (() => {
        const method = document.getElementById('flutterwavePaymentMethod');
        const bankField = document.getElementById('flutterwaveBankField');
        const bank = document.getElementById('flutterwaveUssdBank');

        function syncFlutterwaveMethod() {
            const needsBank = method?.value === 'ussd';
            if (bankField) bankField.hidden = !needsBank;
            if (bank) bank.required = needsBank;
        }

        method?.addEventListener('change', syncFlutterwaveMethod);
        syncFlutterwaveMethod();
    })();
</script>
@endif
</x-public-site.layout>
