<x-public-site.layout title="Complete Flutterwave payment | Azari Residences">
<main class="site-container az-checkout-page">
    <section class="az-checkout-shell">
        <div class="az-checkout-main">
            <a class="az-checkout-back" href="{{ route('public.payment.select', $booking->reference) }}">
                <span class="material-symbols-outlined">arrow_back</span>
                Back to payment options
            </a>

            <div class="az-checkout-heading">
                <span class="eyebrow">Flutterwave API v4</span>
                <h1>Complete your payment</h1>
                <p>Use the instruction below on the phone connected to your bank account. Azari will confirm the booking only after Flutterwave verifies the charge.</p>
            </div>

            <section class="az-checkout-panel">
                <div class="az-checkout-panel-head">
                    <div>
                        <h2>Payment instruction</h2>
                        <p>Do not share your PIN, OTP or banking password with Azari.</p>
                    </div>
                    <span class="material-symbols-outlined az-checkout-seal">dialpad</span>
                </div>

                <div class="az-flw-instruction">
                    <span class="material-symbols-outlined">phone_in_talk</span>
                    <strong>{{ $instruction }}</strong>
                </div>

                <div class="az-flw-instruction-actions">
                    <a class="az-checkout-provider-button" href="{{ route('payments.callback', ['provider' => 'flutterwave', 'payment' => $payment->reference, 'charge_id' => $payment->provider_reference]) }}">
                        Verify payment
                        <span class="material-symbols-outlined">refresh</span>
                    </a>
                    <a class="az-checkout-back" href="{{ route('public.payment.receipt', [$booking->reference, $payment->reference]) }}">
                        View current payment status
                    </a>
                </div>
            </section>
        </div>

        <aside class="az-checkout-summary">
            <div class="az-checkout-summary-card">
                <span class="eyebrow">Payment summary</span>
                <h2>{{ $booking->property?->name ?? 'Azari Residence' }}</h2>
                <p class="az-checkout-reference">{{ $payment->reference }}</p>
                <dl>
                    <div><dt>Provider</dt><dd>Flutterwave</dd></div>
                    <div><dt>Status</dt><dd>{{ ucfirst($payment->status) }}</dd></div>
                    <div><dt>Amount</dt><dd>{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</dd></div>
                </dl>
            </div>
        </aside>
    </section>
</main>

<style>
    .az-flw-instruction {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin: 1.25rem;
        padding: 1.25rem;
        color: var(--green-950, #0c2b24);
        background: var(--ivory, #f8f5ef);
        border: 1px solid var(--line, #e7e1d7);
        border-radius: 1rem;
    }

    .az-flw-instruction .material-symbols-outlined {
        color: var(--brass, #b58a4a);
        font-size: 2rem;
    }

    .az-flw-instruction-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 1rem;
        padding: 0 1.25rem 1.25rem;
    }
</style>
</x-public-site.layout>
