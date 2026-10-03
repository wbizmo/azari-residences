<x-public-site.layout title="Verify email | Resavar">
<main class="site-container az-s56-page">
<header>
    <span class="eyebrow">Secure account setup</span>
    <h1>Verify your email</h1>
    <p>Your booking details are saved. Confirm {{ $email }} to continue without restarting.</p>
</header>

<section class="az-panel">
    <div class="az-notice">
        <strong>We sent a six-digit code.</strong>
        Enter it below. The booking hold stays attached to this Resavar account while you complete email and Dojah verification.
    </div>

    <form method="POST" action="{{ route('azari.booking.onboarding.email.verify', $hold->token) }}" style="margin-top:18px">
        @csrf

        <label>
            <span>Verification code</span>
            <input
                name="code"
                inputmode="numeric"
                pattern="[0-9]{6}"
                minlength="6"
                maxlength="6"
                autocomplete="one-time-code"
                required
            >
        </label>

        <button class="button button-primary" type="submit" style="margin-top:14px">
            Verify email and continue
        </button>
    </form>

    <form method="POST" action="{{ route('azari.booking.onboarding.email.send', $hold->token) }}" style="margin-top:12px">
        @csrf
        <button class="button" type="submit">Send a new code</button>
    </form>
</section>
</main>
</x-public-site.layout>
