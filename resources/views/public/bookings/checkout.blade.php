<x-public-site.layout title="Guest details | Azari Hotels & Residences">
<main class="site-container az-s56-page">
<header><span class="eyebrow">Step 1 of 3</span><h1>Guest information</h1><p>{{ $hold->property->name }} · {{ $hold->check_in->format('d M Y') }} to {{ $hold->check_out->format('d M Y') }}</p></header>

<form method="POST" action="{{ route('azari.booking.store') }}" class="az-s6b-form">
@csrf
<input type="hidden" name="hold_token" value="{{ $hold->token }}">

<section class="az-panel">
    <h2>Lead guest</h2>
    <div class="az-notice"><strong>Your Azari account has passed Dojah identity verification.</strong> Identity-file uploads are no longer used for verification.</div>
    <div class="az-form-grid">
        <label><span>First name</span><input name="first_name" value="{{ old('first_name',auth()->user()?->name ? Str::before(auth()->user()->name,' ') : '') }}" required></label>
        <label><span>Last name</span><input name="last_name" value="{{ old('last_name',auth()->user()?->name ? Str::after(auth()->user()->name,' ') : '') }}" required></label>
        <label><span>Email</span><input type="email" name="guest_email" value="{{ old('guest_email',auth()->user()?->email) }}" required></label>
        <label><span>Phone</span><input name="guest_phone" value="{{ old('guest_phone',auth()->user()?->phone) }}" required></label>
        <label><span>Nationality</span><input name="nationality" value="{{ old('nationality') }}" required></label>
        <label><span>Arrival time</span><input type="time" name="arrival_time" value="{{ old('arrival_time') }}"></label>
        <label class="wide"><span>Address</span><input name="address" value="{{ old('address') }}" required></label>
        <label><span>City</span><input name="city" value="{{ old('city') }}" required></label>
        <label><span>Country</span><input name="country" value="{{ old('country') }}" required></label>
        <label class="wide"><span>Special requests</span><textarea name="guest_notes">{{ old('guest_notes') }}</textarea></label>
    </div>
</section>

<section class="az-panel">
    <h2>Adult guests</h2>
    @if($hold->adults > 1)
        <div class="az-notice">After these guest details are saved, every additional adult must complete their own Dojah verification before payment can continue.</div>
    @endif
    @for($i=0;$i<$hold->adults;$i++)
        <article class="az-guest-card">
            <h3>Adult {{ $i+1 }}{{ $i===0?' · Lead guest':'' }}</h3>
            <div class="az-form-grid">
                <label><span>First name</span><input name="adults[{{ $i }}][first_name]" value="{{ old("adults.$i.first_name",$i===0?old('first_name',auth()->user()?->name ? Str::before(auth()->user()->name,' ') : ''):'') }}" required></label>
                <label><span>Last name</span><input name="adults[{{ $i }}][last_name]" value="{{ old("adults.$i.last_name",$i===0?old('last_name',auth()->user()?->name ? Str::after(auth()->user()->name,' ') : ''):'') }}" required></label>
            </div>
        </article>
    @endfor
</section>

@if($hold->children>0)
<section class="az-panel">
    <h2>Children</h2>
    <p>Children do not require identity verification under the current Azari rules.</p>
    @for($i=0;$i<$hold->children;$i++)
        <article class="az-guest-card">
            <h3>Child {{ $i+1 }}</h3>
            <div class="az-form-grid">
                <label><span>First name</span><input name="children[{{ $i }}][first_name]" value="{{ old("children.$i.first_name") }}" required></label>
                <label><span>Last name</span><input name="children[{{ $i }}][last_name]" value="{{ old("children.$i.last_name") }}" required></label>
            </div>
        </article>
    @endfor
</section>
@endif

<section class="az-panel az-booking-total">
    <h2>Booking total</h2>
    <p>{{ $quote['nights'] }} nights · {{ $quote['currency'] }} {{ number_format($quote['total'],2) }}</p>
    <label class="az-check"><input type="checkbox" name="terms" value="1" required><span>I confirm that the guest information is accurate.</span></label>
    <button class="button button-primary" type="submit">Review booking</button>
</section>
</form>
</main>
</x-public-site.layout>
