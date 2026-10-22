@extends('layouts.user')
@section('title','Withdrawals')
@section('kicker','Property Centre')
@section('page_title','Withdrawals')
@section('content')
@if((int) session('auth.password_confirmed_at', 0) < now()->subMinutes(15)->timestamp)
<div class="az-user-panel" role="status" style="padding:1rem;background:#F0F4FA;color:#052058">
    Manual withdrawal requests require a recent password confirmation.
    <a href="{{ route('password.confirm') }}" style="text-decoration:underline">Confirm password</a>, then return here to submit.
</div>
@endif

<section class="az-user-stat-grid">
    <article class="az-user-stat"><span class="material-symbols-outlined">account_balance_wallet</span><div><small>Available</small><strong>{{ $currency }} {{ number_format($available,2) }}</strong></div></article>
    <article class="az-user-stat"><span class="material-symbols-outlined">payments</span><div><small>Minimum</small><strong>{{ $currency }} {{ number_format($minimum,2) }}</strong></div></article>
    <article class="az-user-stat"><span class="material-symbols-outlined">event_available</span><div><small>Request window</small><strong>{{ $withdrawalOpen?'Open':'Closed' }}</strong></div></article>
</section>

<section class="az-user-panel">
    <header class="az-user-panel-header"><div>
        <h2 class="az-user-panel-title">Request a manual withdrawal</h2>
        <p class="az-user-panel-subtitle">Choose a currency with posted owner earnings, enter receiving instructions and submit for staff approval. Funds are reserved on submission, but no transfer is sent automatically.</p>
    </div></header>
    <div class="az-user-panel-body">
        @if($errors->any())<div class="az-user-form-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form method="post" action="{{ route('user.owner.withdrawals.store') }}" class="az-user-form az-user-form--withdrawal">
            @csrf
            <input type="hidden" name="request_token" value="{{ old('request_token',$requestToken) }}">
            <label for="withdraw-currency">Withdrawal currency
                <select id="withdraw-currency" name="currency" required>
                    @foreach($availableCurrencies as $code => $sum)
                        <option value="{{ $code }}" @selected(old('currency',$currency)===$code)>{{ $code }} — {{ number_format($sum,2) }} available</option>
                    @endforeach
                </select>
            </label>
            <label for="withdraw-amount">Amount
                <input id="withdraw-amount" type="text" inputmode="decimal" name="amount" value="{{ old('amount') }}" placeholder="100.00" pattern="[0-9]+(\.[0-9]{1,2})?" aria-describedby="withdraw-hint" required>
                <small id="withdraw-hint">Minimum {{ number_format($minimum,2) }} in the selected currency. Previously reserved and disputed earnings are excluded from available funds.</small>
            </label>
            <label for="withdraw-receiving">Receiving account details
                <textarea id="withdraw-receiving" name="receiving_details" rows="5" minlength="10" maxlength="2000" autocomplete="off" required placeholder="Receiving institution, beneficiary name, account number, country and any other transfer instructions"></textarea>
                <small>Details are encrypted and shown only to authorized finance staff after password confirmation. Re-enter them for each request.</small>
            </label>
            <label for="withdraw-note">Optional note
                <textarea id="withdraw-note" name="owner_note" rows="3" maxlength="1000">{{ old('owner_note') }}</textarea>
            </label>
            <button type="submit" class="az-user-button az-user-button--dark" @disabled(!$withdrawalOpen || empty($availableCurrencies) || max($availableCurrencies)<$minimum)>Submit for review</button>
        </form>
        @if(empty($availableCurrencies))<p>No settled withdrawable owner balances are available yet.</p>@endif
    </div>
</section>

<section class="az-user-panel">
    <header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Withdrawal history</h2><p class="az-user-panel-subtitle">Pending and approved requests remain reserved. “Paid” means staff recorded an independently verified transfer.</p></div></header>
    <div class="az-user-panel-body">
        @forelse($withdrawals as $withdrawal)
            <div class="az-user-list-item"><div>
                <h3>{{ $withdrawal->reference }}</h3>
                <p>{{ $withdrawal->gateway === 'manual' ? 'Manual bank transfer' : 'Historical '.ucfirst($withdrawal->gateway) }} · {{ optional($withdrawal->requested_at)->format('j M Y, g:i a') }}</p>
                @if($withdrawal->rejection_reason)<p>{{ $withdrawal->rejection_reason }}</p>@endif
            </div><div>
                <strong>{{ $withdrawal->currency }} {{ number_format((float)$withdrawal->amount,2) }}</strong>
                <span class="az-user-status">{{ $withdrawal->gateway === 'manual' && $withdrawal->status === 'processed' ? 'Paid' : ucfirst(str_replace('_',' ',$withdrawal->status)) }}</span>
            </div></div>
        @empty
            <div class="az-user-empty"><p>No withdrawal requests yet.</p></div>
        @endforelse
        {{ $withdrawals->links() }}
    </div>
</section>
@endsection
