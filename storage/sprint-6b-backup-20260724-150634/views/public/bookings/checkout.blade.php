<x-public-site.layout title="Complete Booking | Azari Residences">
<main class="site-container az-s56-page"><header><span class="eyebrow">Secure reservation hold</span><h1>Complete your booking</h1><p>{{ $hold->property->name }} · {{ $hold->check_in->format('d M Y') }} to {{ $hold->check_out->format('d M Y') }}</p></header>
<div class="az-s56-checkout"><form method="POST" action="{{ route('azari.booking.store') }}" class="az-s56-form">@csrf
<input type="hidden" name="hold_token" value="{{ $hold->token }}">
<label><span>Full name</span><input name="guest_name" value="{{ old('guest_name',auth()->user()?->name) }}" required></label>
<label><span>Email</span><input name="guest_email" type="email" value="{{ old('guest_email',auth()->user()?->email) }}" required></label>
<label><span>Phone</span><input name="guest_phone" value="{{ old('guest_phone') }}"></label>
<label><span>Notes</span><textarea name="guest_notes">{{ old('guest_notes') }}</textarea></label>
@if($addOns->isNotEmpty())<fieldset><legend>Add-ons</legend>@foreach($addOns as $a)<label><span>{{ $a->name }} · {{ number_format($a->price,2) }}</span><input type="number" min="0" max="20" name="add_ons[{{ $a->id }}]" value="0"></label>@endforeach</fieldset>@endif
<label><input type="checkbox" name="terms" value="1" required><span>Booking information is accurate.</span></label>
<button class="button button-primary">Create booking</button></form>
<aside class="az-s56-summary"><h2>Summary</h2><p>{{ $quote['nights'] }} nights</p><p>Subtotal: {{ $quote['currency'] }} {{ number_format($quote['subtotal'],2) }}</p><p>Fees: {{ $quote['currency'] }} {{ number_format($quote['fee_total'],2) }}</p><p>Tax: {{ $quote['currency'] }} {{ number_format($quote['tax_total'],2) }}</p><strong>Total: {{ $quote['currency'] }} {{ number_format($quote['total'],2) }}</strong></aside></div>
</main></x-public-site.layout>
