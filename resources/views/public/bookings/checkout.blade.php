<x-public-site.layout title="Guest details | Resarva">
<main class="site-container az-s56-page">
<header>
    <span class="eyebrow">Step 1 of 3</span>
    <h1>Your booking details</h1>
    <p>
        {{ $hold->property->name }}
        @if($hold->accommodationType)
            · {{ $hold->accommodationType->name }}
        @endif
        @if($hold->ratePlan)
            · {{ $hold->ratePlan->name }}
        @endif
        · {{ $hold->check_in->format('d M Y') }} to {{ $hold->check_out->format('d M Y') }}
    </p>
</header>

<section class="az-panel">
    <div class="az-notice">
        <strong>Your place is being held while you finish.</strong>
        @if($creatingAccount)
            Enter the booking details first. Resarva will create your account as part of this booking, verify your email, then send you through secure Dojah identity verification without making you restart.
        @else
            Your signed-in account will stay attached to this hold. If identity verification is needed, Resarva will return you to this same booking afterward.
        @endif
    </div>
</section>

<form method="POST" action="{{ route('azari.booking.onboarding.begin', $hold->token) }}" class="az-s6b-form">
@csrf
<input type="hidden" name="hold_token" value="{{ $hold->token }}">

<section class="az-panel">
    <h2>Lead guest</h2>
    <div class="az-form-grid">
        <label>
            <span>First name</span>
            <input
                name="first_name"
                value="{{ old('first_name', data_get($draft,'first_name', auth()->user()?->name ? Str::before(auth()->user()->name,' ') : '')) }}"
                required
            >
        </label>

        <label>
            <span>Last name</span>
            <input
                name="last_name"
                value="{{ old('last_name', data_get($draft,'last_name', auth()->user()?->name ? Str::after(auth()->user()->name,' ') : '')) }}"
                required
            >
        </label>

        <label>
            <span>Email</span>
            <input
                type="email"
                name="guest_email"
                value="{{ old('guest_email', data_get($draft,'guest_email',auth()->user()?->email)) }}"
                @if(auth()->check()) readonly @endif
                required
            >
            @if(auth()->check())
                <small>The booking uses the email on your signed-in Resarva account.</small>
            @endif
        </label>

        <label>
            <span>Phone</span>
            <input
                name="guest_phone"
                value="{{ old('guest_phone', data_get($draft,'guest_phone',auth()->user()?->phone)) }}"
                required
            >
        </label>

        <label>
            <span>Nationality</span>
            <input name="nationality" value="{{ old('nationality', data_get($draft,'nationality')) }}" required>
        </label>

        <label>
            <span>Arrival time</span>
            <input type="time" name="arrival_time" value="{{ old('arrival_time', data_get($draft,'arrival_time')) }}">
        </label>

        <label class="wide">
            <span>Address</span>
            <input name="address" value="{{ old('address', data_get($draft,'address')) }}" required>
        </label>

        <label>
            <span>City</span>
            <input name="city" value="{{ old('city', data_get($draft,'city')) }}" required>
        </label>

        <label>
            <span>Country</span>
            <input name="country" value="{{ old('country', data_get($draft,'country')) }}" required>
        </label>

        <label class="wide">
            <span>Special requests</span>
            <textarea name="guest_notes">{{ old('guest_notes', data_get($draft,'guest_notes')) }}</textarea>
        </label>
    </div>
</section>

@if($creatingAccount)
<section class="az-panel">
    <h2>Secure your Resarva access</h2>
    <p>
        If this email is new to Resarva, choose a password and the account is created inside this booking.
        If the email already belongs to an Resarva account, leave the password fields blank if you prefer; Resarva will preserve the booking and ask you to sign in instead of creating a duplicate.
    </p>

    <div class="az-form-grid">
        <label>
            <span>Password</span>
            <input type="password" name="password" minlength="8" autocomplete="new-password">
        </label>

        <label>
            <span>Confirm password</span>
            <input type="password" name="password_confirmation" minlength="8" autocomplete="new-password">
        </label>
    </div>

    <p class="az-user-panel-subtitle">
        If this email already has an Resarva account, your details will remain saved and you will be asked to sign in instead of creating a duplicate.
    </p>
</section>
@endif

<section class="az-panel">
    <h2>Adult guests</h2>

    @if($hold->adults > 1)
        <div class="az-notice">
            <strong>Each additional adult verifies themselves.</strong>
            Add a separate email address for every additional adult. After the booking is created, Resarva emails each person their own private verification link. You can also resend or copy those links from your account.
        </div>
    @endif

    @for($i=0;$i<$hold->adults;$i++)
        <article class="az-guest-card">
            <h3>Adult {{ $i+1 }}{{ $i===0?' · Lead guest':'' }}</h3>

            <div class="az-form-grid">
                <label>
                    <span>First name</span>
                    <input
                        name="adults[{{ $i }}][first_name]"
                        value="{{ old("adults.$i.first_name", data_get($draft,"adults.$i.first_name", $i===0 ? data_get($draft,'first_name',auth()->user()?->name ? Str::before(auth()->user()->name,' ') : '') : '')) }}"
                        required
                    >
                </label>

                <label>
                    <span>Last name</span>
                    <input
                        name="adults[{{ $i }}][last_name]"
                        value="{{ old("adults.$i.last_name", data_get($draft,"adults.$i.last_name", $i===0 ? data_get($draft,'last_name',auth()->user()?->name ? Str::after(auth()->user()->name,' ') : '') : '')) }}"
                        required
                    >
                </label>

                @if($i > 0)
                    <label class="wide">
                        <span>Email for this adult</span>
                        <input
                            type="email"
                            name="adults[{{ $i }}][email]"
                            value="{{ old("adults.$i.email", data_get($draft,"adults.$i.email")) }}"
                            required
                        >
                        <small>This address receives only this person's verification link. It cannot be the lead guest's email or another adult's email.</small>
                    </label>
                @endif
            </div>
        </article>
    @endfor
</section>

@if($hold->children>0)
<section class="az-panel">
    <h2>Children</h2>
    <p>Children do not require identity verification under the current Resarva rules.</p>

    @for($i=0;$i<$hold->children;$i++)
        <article class="az-guest-card">
            <h3>Child {{ $i+1 }}</h3>

            <div class="az-form-grid">
                <label>
                    <span>First name</span>
                    <input
                        name="children[{{ $i }}][first_name]"
                        value="{{ old("children.$i.first_name", data_get($draft,"children.$i.first_name")) }}"
                        required
                    >
                </label>

                <label>
                    <span>Last name</span>
                    <input
                        name="children[{{ $i }}][last_name]"
                        value="{{ old("children.$i.last_name", data_get($draft,"children.$i.last_name")) }}"
                        required
                    >
                </label>
            </div>
        </article>
    @endfor
</section>
@endif

<section class="az-panel az-booking-total">
    <h2>Booking total</h2>
    <p>
        {{ $quote['nights'] }} nights
        · {{ $hold->rooms }} {{ Str::plural('unit', $hold->rooms) }}
        · {{ $quote['currency'] }} {{ number_format($quote['total'],2) }}
    </p>

    @if($hold->ratePlan)
        <div class="az-notice">
            <strong>{{ $hold->ratePlan->name }}</strong>
            @if(data_get($quote, 'policy.rate_plan.is_refundable') === false)
                <span> · Non-refundable</span>
            @elseif(data_get($quote, 'policy.cancellation.name'))
                <span> · {{ data_get($quote, 'policy.cancellation.name') }}</span>
            @endif

            @if(data_get($quote, 'policy.payment.name'))
                <span> · {{ data_get($quote, 'policy.payment.name') }}</span>
            @endif
        </div>
    @endif

    <label class="az-check">
        <input type="checkbox" name="terms" value="1" @checked(old('terms')) required>
        <span>I confirm that the guest information is accurate.</span>
    </label>

    <button class="button button-primary" type="submit">
        {{ $creatingAccount ? 'Continue securely' : 'Continue booking' }}
    </button>
</section>
</form>
</main>
</x-public-site.layout>
