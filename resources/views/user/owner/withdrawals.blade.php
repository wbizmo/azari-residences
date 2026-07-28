@extends('layouts.user')
@section('title','Owner Withdrawals')
@section('content')
<div class="az-premium-page-head"><div><span class="az-premium-kicker">Property Centre</span><h1>Balance and withdrawals</h1><p>Available balance: <strong>{{ $currency }} {{ number_format($available,2) }}</strong>. Minimum withdrawal: {{ $currency }} {{ number_format($minimum,2) }}.</p></div></div>
@if(!$withdrawalOpen)<div class="az-s78-note">Withdrawal requests are closed today. Admin-configured processing days apply.</div>@endif
<div class="az-s78-grid">
<form class="az-premium-card az-s78-form" method="POST" action="{{ route('user.owner.payout-profile.update') }}">@csrf @method('PUT')
<div class="az-premium-card-head"><div><h2>Payout destination</h2><p>Stripe is for a connected Stripe account; PayPal uses Payouts recipient details.</p></div></div>
<label class="az-s78-field"><span>Preferred gateway</span><select name="preferred_gateway"><option value="paypal" @selected(old('preferred_gateway',$profile->preferred_gateway)==='paypal') @disabled(!$paypalEnabled)>PayPal</option><option value="stripe" @selected(old('preferred_gateway',$profile->preferred_gateway)==='stripe') @disabled(!$stripeEnabled)>Stripe Connect</option></select></label>
<label class="az-s78-field"><span>PayPal recipient</span><input name="paypal_recipient" value="{{ old('paypal_recipient',$profile->paypal_recipient) }}" placeholder="Email, phone or payer ID"></label>
<label class="az-s78-field"><span>PayPal recipient type</span><select name="paypal_recipient_type">@foreach(['EMAIL','PHONE','PAYPAL_ID'] as $type)<option @selected(old('paypal_recipient_type',$profile->paypal_recipient_type)===$type)>{{ $type }}</option>@endforeach</select></label>
<label class="az-s78-field"><span>Stripe connected account ID</span><input name="stripe_connected_account_id" value="{{ old('stripe_connected_account_id',$profile->stripe_connected_account_id) }}" placeholder="acct_..."></label>
<button class="az-premium-button">Save payout destination</button></form>
<form class="az-premium-card az-s78-form" method="POST" action="{{ route('user.owner.withdrawals.store') }}">@csrf
<div class="az-premium-card-head"><div><h2>Request withdrawal</h2><p>Requests reserve the amount until processed, rejected or failed.</p></div></div>
<label class="az-s78-field"><span>Amount ({{ $currency }})</span><input type="number" step=".01" min="{{ $minimum }}" max="{{ $available }}" name="amount" required></label>
<label class="az-s78-field"><span>Note</span><textarea name="owner_note"></textarea></label>
<button class="az-premium-button" @disabled(!$withdrawalOpen||!$profile->exists||$available<$minimum)>Submit withdrawal</button></form>
</div>
<section class="az-premium-card"><div class="az-premium-card-head"><div><h2>Withdrawal history</h2></div></div><div class="az-responsive-table-shell"><table class="az-s78-table"><thead><tr><th>Reference</th><th>Gateway</th><th>Amount</th><th>Status</th><th>Requested</th></tr></thead><tbody>@forelse($withdrawals as $withdrawal)<tr><td>{{ $withdrawal->reference }}</td><td>{{ ucfirst($withdrawal->gateway) }}</td><td>{{ $withdrawal->currency }} {{ number_format($withdrawal->amount,2) }}</td><td><span class="az-s78-badge {{ in_array($withdrawal->status,['failed','rejected'])?'is-danger':($withdrawal->status==='processed'?'':'is-warning') }}">{{ ucfirst($withdrawal->status) }}</span></td><td>{{ $withdrawal->requested_at->format('d M Y H:i') }}</td></tr>@empty<tr><td colspan="5"><div class="az-s78-empty">No withdrawal requests yet.</div></td></tr>@endforelse</tbody></table></div><div class="az-user-pagination">{{ $withdrawals->links('vendor.pagination.azari') }}</div></section>
@endsection
