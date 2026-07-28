@extends('admin.layouts.app')
@section('content')
<div class="az-premium-page-head"><div><span class="az-premium-kicker">Property owners</span><h1>Marketplace settings</h1><p>Configure default splits, withdrawal windows and enabled payout gateways.</p></div></div>
<form class="az-premium-card az-s78-form" method="POST" action="{{ route('azari.admin.owner-settings.update') }}">@csrf @method('PUT')
<div class="az-s78-form-grid">
<label class="az-s78-field"><span>Default owner share (%)</span><input type="number" step=".01" min="0" max="100" name="owner_default_share_percentage" value="{{ $settings['owner_default_share_percentage'] }}" required></label>
<label class="az-s78-field"><span>Withdrawal days (ISO 1-7)</span><input name="owner_withdrawal_days" value="{{ $settings['owner_withdrawal_days'] }}" required><small>Example: 1,2,3,4,5 for Monday-Friday.</small></label>
<label class="az-s78-field"><span>Minimum withdrawal</span><input type="number" step=".01" min="0" name="owner_withdrawal_minimum" value="{{ $settings['owner_withdrawal_minimum'] }}" required></label>
<label class="az-s78-field"><span>Withdrawal currency</span><input maxlength="3" name="owner_withdrawal_currency" value="{{ $settings['owner_withdrawal_currency'] }}" required></label>
<label class="az-s78-field"><span>Agreement version</span><input name="owner_listing_agreement_version" value="{{ $settings['owner_listing_agreement_version'] }}" required></label>
<label class="az-s78-check"><input type="checkbox" name="owner_paypal_enabled" value="1" @checked(filter_var($settings['owner_paypal_enabled'],FILTER_VALIDATE_BOOL))><span>Enable PayPal withdrawals</span></label>
<label class="az-s78-check"><input type="checkbox" name="owner_stripe_enabled" value="1" @checked(filter_var($settings['owner_stripe_enabled'],FILTER_VALIDATE_BOOL))><span>Enable Stripe Connect withdrawals</span></label>
</div><button class="az-premium-button">Save marketplace settings</button></form>
@endsection
