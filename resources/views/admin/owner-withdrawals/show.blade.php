@extends('layouts.admin')
@section('title','Owner withdrawal')
@section('content')
<div class="admin-page-header"><div><h1>{{ $withdrawal->reference }}</h1><p>{{ $withdrawal->user?->name }} · {{ $withdrawal->currency }} {{ number_format((float)$withdrawal->amount,2) }} · {{ str_replace('_',' ',$withdrawal->status) }}</p></div></div>
@if($withdrawal->status==='reconciliation_required')<div class="admin-alert admin-alert--danger"><strong>Manual reconciliation required.</strong> Do not retry this payout. Check the provider using reference {{ $withdrawal->provider_reference ?: $withdrawal->reference }}.</div>@endif
<div class="admin-card"><dl><dt>Gateway</dt><dd>{{ ucfirst($withdrawal->gateway) }}</dd><dt>Requested</dt><dd>{{ optional($withdrawal->requested_at)->format('j M Y, g:i a') }}</dd><dt>Provider reference</dt><dd>{{ $withdrawal->provider_reference ?: 'Not issued' }}</dd><dt>Destination snapshot</dt><dd><pre>{{ json_encode($withdrawal->destination_snapshot,JSON_PRETTY_PRINT) }}</pre></dd>@if($withdrawal->last_error)<dt>Last error</dt><dd>{{ $withdrawal->last_error }}</dd>@endif</dl></div>
@if($withdrawal->status==='pending')<form method="post" action="{{ route('azari.admin.owner-withdrawals.process',$withdrawal) }}" class="admin-card">@csrf<label>Processing note<textarea name="admin_note"></textarea></label><button>Process once</button></form>@endif
@if(in_array($withdrawal->status,['pending','failed']))<form method="post" action="{{ route('azari.admin.owner-withdrawals.reject',$withdrawal) }}" class="admin-card">@csrf<label>Rejection reason<textarea name="rejection_reason" minlength="10" required></textarea></label><button>Reject and release reserved funds</button></form>@endif
@endsection
