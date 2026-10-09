@extends('admin.layouts.app')
@section('title','Owner withdrawal')
@section('content')
<div class="az-admin-page-header"><div><h1>{{ $withdrawal->reference }}</h1><p>{{ $withdrawal->user?->name }} · {{ $withdrawal->currency }} {{ number_format((float)$withdrawal->amount,2) }} · {{ str_replace('_',' ',$withdrawal->status) }}</p></div></div>

@if((int) session('auth.password_confirmed_at', 0) < now()->subMinutes(15)->timestamp)
<div class="az-admin-alert" role="status">
    <strong>Additional verification required.</strong>
    <a href="{{ route('password.confirm') }}" class="underline">Confirm your password</a>
    before processing, reconciling or changing a payout destination. Return here to resubmit.
</div>
@endif
@if($errors->any())<div class="az-admin-alert az-az-admin-alert--danger"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if($withdrawal->status==='reconciliation_required')<div class="az-admin-alert az-az-admin-alert--danger"><strong>Manual reconciliation required.</strong> Do not retry. Check the provider using {{ $withdrawal->provider_reference ?: $withdrawal->reference }}.</div>@endif

<div class="az-admin-card">
    <dl class="az-admin-detail-list">
        <dt>Gateway</dt><dd>{{ ucfirst($withdrawal->gateway) }}</dd>
        <dt>Requested</dt><dd>{{ optional($withdrawal->requested_at)->format('j M Y, g:i a') }}</dd>
        <dt>Provider reference</dt><dd>{{ $withdrawal->provider_reference ?: 'Not issued' }}</dd>
        <dt>Retry count</dt><dd>{{ (int)$withdrawal->retry_count }}</dd>
        <dt>Destination snapshot</dt><dd><pre class="az-admin-code">{{ json_encode($withdrawal->destination_snapshot,JSON_PRETTY_PRINT) }}</pre></dd>
        @if($withdrawal->last_error)<dt>Last error</dt><dd>{{ $withdrawal->last_error }}</dd>@endif
        @if($withdrawal->reconciliation_note)<dt>Reconciliation note</dt><dd>{{ $withdrawal->reconciliation_note }}</dd>@endif
    </dl>
</div>

@php($profile=$withdrawal->user?->ownerPayoutProfile)
@if($profile)
<div class="az-admin-card">
    <h2>Payout destination</h2>
    <p>{{ ucfirst($profile->preferred_gateway) }} · {{ $profile->destinationLabel() }}</p>
    <p>Status: <strong>{{ $profile->is_verified?'Verified':'Awaiting verification' }}</strong></p>
    @if($profile->verification_note)<p>{{ $profile->verification_note }}</p>@endif
    @if(!$profile->is_verified)
        <form method="post" action="{{ route('azari.admin.owner-payout-profiles.verify',$profile) }}" class="az-admin-form">@csrf<label>Verification evidence/note<textarea name="verification_note" minlength="10" required></textarea></label><button class="az-admin-button">Verify payout destination</button></form>
    @else
        <form method="post" action="{{ route('azari.admin.owner-payout-profiles.unverify',$profile) }}" class="az-admin-form">@csrf<label>Reason for revocation<textarea name="verification_note" minlength="10" required></textarea></label><button class="az-admin-button">Revoke verification</button></form>
    @endif
</div>
@endif

@if($withdrawal->status==='pending')
<form method="post" action="{{ route('azari.admin.owner-withdrawals.process',$withdrawal) }}" class="az-admin-form az-admin-card">@csrf<label>Processing note<textarea name="admin_note"></textarea></label><button class="az-admin-button">Process once</button></form>
@endif

@if($withdrawal->isSafelyRetryable())
<form method="post" action="{{ route('azari.admin.owner-withdrawals.retry',$withdrawal) }}" class="az-admin-form az-admin-card">@csrf<label>Retry note<textarea name="admin_note"></textarea></label><button class="az-admin-button">Return to pending for safe retry</button></form>
@endif

@if($withdrawal->status==='reconciliation_required')
<div class="az-admin-grid">
    <form method="post" action="{{ route('azari.admin.owner-withdrawals.reconcile-paid',$withdrawal) }}" class="az-admin-form az-admin-card">@csrf
        <h2>Provider confirms paid</h2>
        <label>Provider reference<input name="provider_reference" value="{{ old('provider_reference',$withdrawal->provider_reference) }}" required></label>
        <label>Evidence and reconciliation note<textarea name="reconciliation_note" minlength="10" required></textarea></label>
        <button class="az-admin-button">Confirm paid and record debit</button>
    </form>
    <form method="post" action="{{ route('azari.admin.owner-withdrawals.reconcile-not-paid',$withdrawal) }}" class="az-admin-form az-admin-card">@csrf
        <h2>Provider confirms not paid</h2>
        <label>Evidence and reconciliation note<textarea name="reconciliation_note" minlength="10" required></textarea></label>
        <button class="az-admin-button">Release reservation as failed</button>
    </form>
</div>
@endif

@if(in_array($withdrawal->status,['pending','failed']))
<form method="post" action="{{ route('azari.admin.owner-withdrawals.reject',$withdrawal) }}" class="az-admin-form az-admin-card">@csrf<label>Rejection reason<textarea name="rejection_reason" minlength="10" required></textarea></label><button class="az-admin-button">Reject and release reserved funds</button></form>
@endif
@endsection
