@extends('layouts.user')
@section('title','Earnings and statement')
@section('kicker','Property Centre')
@section('page_title','Earnings and statement')
@section('content')
<section class="az-user-panel"><header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Owner ledger</h2><p class="az-user-panel-subtitle">An immutable record of booking earnings and completed withdrawals.</p></div></header><div class="az-user-panel-body">
@forelse($entries as $entry)<div class="az-user-list-item"><div><h3>{{ $entry->description }}</h3><p>{{ $entry->reference }} · {{ $entry->created_at->format('j M Y, g:i a') }}</p></div><strong>{{ $entry->direction==='credit'?'+':'-' }}{{ $entry->currency }} {{ number_format((float)$entry->amount,2) }}</strong></div>
@empty<div class="az-user-empty"><span class="material-symbols-outlined">receipt_long</span><h3>No transactions yet</h3><p>Verified booking earnings will appear automatically.</p></div>@endforelse
{{ $entries->links() }}
</div></section>
@endsection
