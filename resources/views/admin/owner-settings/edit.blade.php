@extends('admin.layouts.app')
@section('title','Property owner settings')
@section('content')
<div class="az-admin-page-header"><div><h1>Property owner marketplace</h1><p>Control owner agreements and manual withdrawal request windows. Transfers are completed outside Resavar and recorded only after verified external evidence. Automated PayPal/Stripe owner payouts are disabled under the accommodation-only policy.</p></div></div>
<form method="post" action="{{ route('azari.admin.owner-settings.update') }}" class="az-admin-form az-admin-card">@csrf @method('PUT')
<label>Withdrawal weekdays (ISO 1–7, comma-separated)<input name="owner_withdrawal_days" value="{{ old('owner_withdrawal_days',$settings['owner_withdrawal_days']) }}" required></label>
<label>Minimum manual withdrawal in each eligible ledger currency<input type="number" name="owner_withdrawal_minimum" min="0" step="0.01" value="{{ old('owner_withdrawal_minimum',$settings['owner_withdrawal_minimum']) }}" required></label>
<p>Eligible currencies are determined by posted owner earnings. Three-decimal and zero-decimal money handling remains restricted pending currency precision issue #261.</p>
<label>Agreement version<input name="owner_listing_agreement_version" value="{{ old('owner_listing_agreement_version',$settings['owner_listing_agreement_version']) }}" required></label>
<button class="az-admin-button">Save owner marketplace settings</button>
</form>
@endsection
