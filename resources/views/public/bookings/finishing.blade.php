<x-public-site.layout title="Finishing booking | Resavar">
<main class="site-container az-s56-page">
<header>
    <span class="eyebrow">Booking secured</span>
    <h1>Finishing your booking</h1>
    <p>Your account, email and identity are verified. We are attaching the saved guest details to this reservation.</p>
</header>

<section class="az-panel">
    <div class="az-notice">
        <strong>Your booking details were preserved.</strong>
        You do not need to enter them again.
    </div>

    <form id="azari-finish-booking" method="POST" action="{{ route('azari.booking.onboarding.complete', $hold->token) }}" style="margin-top:18px">
        @csrf
        <button class="button button-primary" type="submit">Continue to booking review</button>
    </form>

    <noscript>
        <p style="margin-top:12px">JavaScript is off. Use the button above to continue.</p>
    </noscript>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('azari-finish-booking');
    if (form && !form.dataset.submitted) {
        form.dataset.submitted = '1';
        form.requestSubmit();
    }
});
</script>
</main>
</x-public-site.layout>
