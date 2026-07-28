@extends('layouts.admin')
@section('title','Owner withdrawals')
@section('content')
<div class="az-admin-page-header"><div><h1>Owner withdrawals</h1><p>Process each payout exactly once and reconcile ambiguous provider outcomes.</p></div></div>
<div class="az-admin-card"><form method="get" class="az-admin-filter-form"><label>Status<select name="status" onchange="this.form.submit()"><option value="">All</option>@foreach(['pending','processing','provider_sent','processed','failed','reconciliation_required','rejected'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ ucfirst(str_replace('_',' ',$status)) }}</option>@endforeach</select></label></form></div>
<div class="az-admin-card"><div class="az-admin-table-wrap"><table class="az-admin-table"><thead><tr><th>Reference</th><th>Owner</th><th>Gateway</th><th>Amount</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($withdrawals as $withdrawal)<tr><td>{{ $withdrawal->reference }}</td><td>{{ $withdrawal->user?->name }}</td><td>{{ ucfirst($withdrawal->gateway) }}</td><td>{{ $withdrawal->currency }} {{ number_format((float)$withdrawal->amount,2) }}</td><td>{{ str_replace('_',' ',$withdrawal->status) }}</td><td><a class="az-admin-link" href="{{ route('azari.admin.owner-withdrawals.show',$withdrawal) }}">Open</a></td></tr>
@empty<tr><td colspan="6">No matching withdrawals.</td></tr>@endforelse
</tbody></table></div>{{ $withdrawals->links() }}</div>
@endsection
