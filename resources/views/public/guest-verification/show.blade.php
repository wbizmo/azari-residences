<x-public-site.layout title="Guest verification | Reserva">
<main class="site-container az-s56-page">
<header>
    <span class="eyebrow">Adult guest verification</span>
    <h1>Verify for booking {{ $booking->reference }}</h1>
    <p>This secure page is for adult guest {{ $position }} on the booking.</p>
</header>

@if(session('success'))
    <section class="az-panel"><div class="az-notice"><strong>{{ session('success') }}</strong></div></section>
@endif

@if($errors->any())
    <section class="az-panel">
        <div class="az-notice">
            <strong>Please check the details below.</strong>
            <ul>
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    </section>
@endif

@if($stage === 'email')
<section class="az-panel">
    <h2>Confirm this invitation</h2>
    <p>
        For privacy, Reserva does not show the invited guest's name, email or identity status from the booking reference alone.
        A one-time code will be sent to the email address the booker supplied for this adult.
    </p>

    <form method="POST" action="{{ route('guest-verification.code.send', [$booking->reference,$position]) }}" style="margin-top:18px">
        @csrf
        <button class="button button-primary" type="submit">Send verification code</button>
    </form>

    <form method="POST" action="{{ route('guest-verification.code.verify', [$booking->reference,$position]) }}" style="margin-top:18px">
        @csrf
        <label>
            <span>Six-digit code</span>
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
        <button class="button" type="submit" style="margin-top:12px">Confirm email</button>
    </form>
</section>
@else
@php($verified = $verification?->isVerified() ?? false)

<section class="az-panel">
    <div class="az-detail-grid">
        <div>
            <h2>{{ $guest->full_name }}</h2>
            <p>{{ $guest->email }}</p>
        </div>
        <div>
            <strong>Identity status</strong>
            <p>{{ $verified ? 'Dojah verified' : ucfirst(str_replace('_',' ',$verification->status)) }}</p>
        </div>
    </div>

    @if($verified)
        <div class="az-notice" style="margin-top:18px">
            <strong>Your identity is verified for this booking.</strong>
            The person who made the booking can now see this adult as verified.
            @if($allAdultsVerified)
                <strong> All adults on this booking are now verified and payment can continue.</strong>
            @else
                Other adults may still need to complete their own verification.
            @endif
        </div>
    @elseif(!$widgetConfigured)
        <div class="az-notice" style="margin-top:18px">
            <strong>Identity verification is temporarily unavailable.</strong>
            Protected booking steps stay locked until Dojah is available.
        </div>
    @else
        <div class="az-notice" style="margin-top:18px">
            <strong>Complete your own Dojah verification.</strong>
            The secure Dojah flow opens in a new tab. Return here and refresh after completion. Reserva only marks you verified after the signed Dojah result is received.
        </div>

        <div style="margin-top:18px">
            <a class="button button-primary" href="{{ $widget['launch_url'] }}" target="_blank" rel="noopener noreferrer">
                Start secure verification
            </a>
            <a class="button" href="{{ route('guest-verification.show', [$booking->reference,$position]) }}">
                Refresh status
            </a>
        </div>
    @endif
</section>

<section class="az-panel">
    <h2>Optional Reserva account</h2>

    @if($accountState === 'linked')
        <div class="az-notice">
            <strong>This guest is linked to a Reserva account.</strong>
            A successful Dojah result on this guest can also establish the account's verified identity, so you can book for yourself later without repeating setup.
        </div>
    @elseif($accountState === 'existing')
        <p>A Reserva account already uses this email. Sign in to link this guest verification to that account.</p>

        <form method="POST" action="{{ route('guest-verification.account.link', [$booking->reference,$position]) }}">
            @csrf
            <button class="button" type="submit">Sign in and link my account</button>
        </form>
    @else
        <p>
            You can complete verification only for this booking, or create your own Reserva account now so the verified identity can be linked to you for future bookings.
        </p>

        <form method="POST" action="{{ route('guest-verification.account.create', [$booking->reference,$position]) }}" style="margin-top:16px">
            @csrf

            <div class="az-form-grid">
                <label>
                    <span>Create password</span>
                    <input type="password" name="password" minlength="8" autocomplete="new-password" required>
                </label>

                <label>
                    <span>Confirm password</span>
                    <input type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required>
                </label>
            </div>

            <button class="button" type="submit" style="margin-top:12px">Create my Reserva account</button>
        </form>

        <p style="margin-top:12px">Creating an account is optional and is not required to verify for this booking.</p>
    @endif
</section>
@endif

@if($stage === 'identity' && !$verified && $widgetConfigured)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const statusUrl = @json(route('guest-verification.status', [$booking->reference,$position]));
    let stopped = false;

    async function checkGuestStatus() {
        if (stopped || document.hidden) {
            return;
        }

        try {
            const response = await fetch(statusUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                cache: 'no-store'
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            if (data.verified) {
                stopped = true;
                window.location.reload();
            }
        } catch (_) {
            // Keep the verification page usable through temporary network loss.
        }
    }

    const timer = window.setInterval(checkGuestStatus, 3500);

    window.addEventListener('beforeunload', function () {
        stopped = true;
        window.clearInterval(timer);
    });

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            checkGuestStatus();
        }
    });
});
</script>
@endif

</main>
</x-public-site.layout>
