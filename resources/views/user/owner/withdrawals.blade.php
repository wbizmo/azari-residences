@extends('layouts.user')
@section('title','Withdrawals')
@section('kicker','Property Centre')
@section('page_title','Withdrawals')
@section('content')
@if((int) session('auth.password_confirmed_at', 0) < now()->subMinutes(15)->timestamp)
<div class="az-user-panel" role="status" style="padding:1rem;background:#F0F4FA;color:#052058">
    Changing your payout destination or requesting a withdrawal requires a recent password confirmation.
    <a href="{{ route('password.confirm') }}" style="color:#052058;text-decoration:underline">Confirm password</a>
    first, then return here to submit.
</div>
@endif

<section class="az-user-stat-grid">
<article class="az-user-stat"><span class="material-symbols-outlined">account_balance_wallet</span><div><small>Available</small><strong>{{ $currency }} {{ number_format($available,2) }}</strong></div></article>
<article class="az-user-stat"><span class="material-symbols-outlined">payments</span><div><small>Minimum</small><strong>{{ $currency }} {{ number_format($minimum,2) }}</strong></div></article>
<article class="az-user-stat"><span class="material-symbols-outlined">event_available</span><div><small>Requests today</small><strong>{{ $withdrawalOpen?'Open':'Closed' }}</strong></div></article>
</section>


<section class="az-user-panel"><header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Payout destination</h2><p class="az-user-panel-subtitle">Changes require verification before production payout.</p></div></header><div class="az-user-panel-body">
<form method="post" action="{{ route('user.owner.payout-profile.update') }}" class="az-user-form">@csrf @method('PUT')
<label>Gateway<select name="preferred_gateway"><option value="paypal" @selected(old('preferred_gateway',$profile->preferred_gateway)==='paypal') @disabled(!$paypalEnabled)>PayPal</option><option value="stripe" @selected(old('preferred_gateway',$profile->preferred_gateway)==='stripe') @disabled(!$stripeEnabled)>Stripe Connect</option></select></label>
<label>PayPal recipient<input name="paypal_recipient" value="{{ old('paypal_recipient',$profile->paypal_recipient) }}"></label>
<label>PayPal recipient type<select name="paypal_recipient_type">@foreach(['EMAIL','PHONE','PAYPAL_ID'] as $type)<option @selected(old('paypal_recipient_type',$profile->paypal_recipient_type)===$type)>{{ $type }}</option>@endforeach</select></label>
<label>Stripe connected account ID<input name="stripe_connected_account_id" value="{{ old('stripe_connected_account_id',$profile->stripe_connected_account_id) }}" placeholder="acct_..."></label>
<button class="az-user-button az-user-button--dark">Save payout destination</button>
</form></div></section>

<section class="az-user-panel"><header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Request withdrawal</h2><p class="az-user-panel-subtitle">Submitted funds are reserved immediately to prevent duplicate withdrawals. Owner payouts are processed in {{ $currency }}.</p></div></header><div class="az-user-panel-body">
<form method="post" action="{{ route('user.owner.withdrawals.store') }}" class="az-user-form az-user-form--withdrawal">@csrf
<div class="az-user-withdrawal-amount-field">
    <label for="owner-withdrawal-amount">Amount ({{ $currency }})</label>
    <input id="owner-withdrawal-amount" type="number" name="amount" inputmode="decimal" min="{{ number_format((float)$minimum, 2, '.', '') }}" max="{{ number_format((float)$available, 2, '.', '') }}" step="0.01" value="{{ old('amount') }}" aria-describedby="owner-withdrawal-hint" required>
    <small id="owner-withdrawal-hint">Available: {{ $currency }} {{ number_format($available,2) }} · Minimum: {{ $currency }} {{ number_format($minimum,2) }}</small>
    @error('amount')<small class="az-user-form-error" role="alert">{{ $message }}</small>@enderror
</div>
<label for="owner-withdrawal-note">Note<textarea id="owner-withdrawal-note" name="owner_note" rows="3" maxlength="3000">{{ old('owner_note') }}</textarea></label>
<button type="submit" class="az-user-button az-user-button--dark" @disabled(!$withdrawalOpen || $available<$minimum)>Submit withdrawal</button>
</form></div></section>

<section class="az-user-panel"><header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Withdrawal history</h2><p class="az-user-panel-subtitle">Reconciliation-required items must not be resubmitted.</p></div></header><div class="az-user-panel-body">
@forelse($withdrawals as $withdrawal)<div class="az-user-list-item"><div><h3>{{ $withdrawal->reference }}</h3><p>{{ ucfirst($withdrawal->gateway) }} · {{ optional($withdrawal->requested_at)->format('j M Y, g:i a') }}</p>@if($withdrawal->rejection_reason)<p>{{ $withdrawal->rejection_reason }}</p>@endif</div><div><strong>{{ $withdrawal->currency }} {{ number_format((float)$withdrawal->amount,2) }}</strong><span class="az-user-status">{{ str_replace('_',' ',$withdrawal->status) }}</span></div></div>
@empty<div class="az-user-empty"><p>No withdrawal requests yet.</p></div>@endforelse
{{ $withdrawals->links() }}
</div></section>
@endsection
