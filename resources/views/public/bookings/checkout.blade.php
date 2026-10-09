<x-public-site.layout title="Guest details | Resavar">
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
            Enter the booking details first. Resavar will create your account as part of this booking and verify your email without making you restart.
        @else
            Your signed-in account will stay attached to this hold while you complete the booking.
        @endif
    </div>
</section>

<form method="POST" action="{{ route('azari.booking.onboarding.begin', $hold->token) }}" class="az-s6b-form">
@csrf
<input type="hidden" name="hold_token" value="{{ $hold->token }}">
@if($creatingAccount)
<input type="hidden" name="_auth_form_token" value="{{ $authFormToken }}">
<div aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden">
    <label for="company_website">Company website</label>
    <input id="company_website" name="company_website" type="text" tabindex="-1" autocomplete="off">
    <label for="contact_fax">Fax</label>
    <input id="contact_fax" name="contact_fax" type="text" tabindex="-1" autocomplete="off">
</div>
@endif

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
                <small>The booking uses the email on your signed-in Resavar account.</small>
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
    <h2>Secure your Resavar access</h2>
    <p>
        If this email is new to Resavar, choose a password and the account is created inside this booking.
        If the email already belongs to an Resavar account, leave the password fields blank if you prefer; Resavar will preserve the booking and ask you to sign in instead of creating a duplicate.
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
        If this email already has an Resavar account, your details will remain saved and you will be asked to sign in instead of creating a duplicate.
    </p>
</section>
@endif

<section class="az-panel">
    <h2>Adult guests</h2>

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

            </div>
        </article>
    @endfor
</section>

@if($hold->children>0)
<section class="az-panel">
    <h2>Children</h2>

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

    <dl class="az-user-detail-list" aria-label="Server-verified booking price breakdown">
        <div class="az-user-detail-row"><dt>Stay before discounts</dt><dd>{{ \App\Support\Money::format($quote['subtotal_before_discount'], $quote['currency']) }}</dd></div>
        @if(($quote['discount_total'] ?? 0) > 0)
            <div class="az-user-detail-row"><dt>Discount</dt><dd>−{{ \App\Support\Money::format($quote['discount_total'], $quote['currency']) }}</dd></div>
        @endif
        @foreach(($quote['fee_breakdown'] ?? []) as $fee)
            <div class="az-user-detail-row"><dt>{{ $fee['name'] }}</dt><dd>{{ \App\Support\Money::format($fee['amount'], $quote['currency']) }}</dd></div>
        @endforeach
        @foreach(($quote['add_ons'] ?? []) as $addon)
            <div class="az-user-detail-row"><dt>{{ $addon['name'] ?? 'Optional add-on' }}</dt><dd>{{ \App\Support\Money::format($addon['line_total'] ?? 0, $quote['currency']) }}</dd></div>
        @endforeach
        <div class="az-user-detail-row"><dt>Taxes ({{ $quote['tax_rate'] }}%)</dt><dd>{{ \App\Support\Money::format($quote['tax_total'], $quote['currency']) }}</dd></div>
        <div class="az-user-detail-row"><dt><strong>Total for this booking</strong></dt><dd><strong>{{ \App\Support\Money::format($quote['total'], $quote['currency']) }}</strong></dd></div>
    </dl>
    @if(($quote['security_deposit'] ?? 0) > 0)
        <p class="az-notice">A separate security deposit of <strong>{{ \App\Support\Money::format($quote['security_deposit'], $quote['currency']) }}</strong> may be collected under the property's policy. It is not included in the booking total above.</p>
    @endif

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
