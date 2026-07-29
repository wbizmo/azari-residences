@extends('layouts.admin')
@section('title','Property owner settings')
@section('content')
<div class="az-admin-page-header"><div><h1>Property owner marketplace</h1><p>Control agreements, earnings share, withdrawal days and payout gateways.</p></div></div>
<form method="post" action="{{ route('azari.admin.owner-settings.update') }}" class="az-admin-form az-admin-card">@csrf @method('PUT')
<label>Default owner share (%)<input type="number" name="owner_default_share_percentage" min="0" max="100" step="0.01" value="{{ old('owner_default_share_percentage',$settings['owner_default_share_percentage']) }}" required></label>
<label>Withdrawal weekdays (ISO 1–7, comma-separated)<input name="owner_withdrawal_days" value="{{ old('owner_withdrawal_days',$settings['owner_withdrawal_days']) }}" required></label>
<label>Minimum withdrawal<input type="number" name="owner_withdrawal_minimum" min="0" step="0.01" value="{{ old('owner_withdrawal_minimum',$settings['owner_withdrawal_minimum']) }}" required></label>
<label>Withdrawal currency<input name="owner_withdrawal_currency" maxlength="3" value="{{ old('owner_withdrawal_currency',$settings['owner_withdrawal_currency']) }}" required></label>
<label>Agreement version<input name="owner_listing_agreement_version" value="{{ old('owner_listing_agreement_version',$settings['owner_listing_agreement_version']) }}" required></label>
<label><input type="checkbox" name="owner_paypal_enabled" value="1" @checked(filter_var($settings['owner_paypal_enabled'],FILTER_VALIDATE_BOOL))> Enable PayPal payouts</label>
<label><input type="checkbox" name="owner_stripe_enabled" value="1" @checked(filter_var($settings['owner_stripe_enabled'],FILTER_VALIDATE_BOOL))> Enable Stripe Connect payouts</label>
<button class="az-admin-button">Save owner marketplace settings</button>
</form>
@endsection
