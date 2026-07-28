@extends('layouts.user')
@section('title','Owner Earnings')
@section('content')
<div class="az-premium-page-head"><div><span class="az-premium-kicker">Property Centre</span><h1>Earnings statement</h1><p>Every successful payment is credited once using the approved property split.</p></div></div>
<section class="az-premium-card"><div class="az-responsive-table-shell"><table class="az-s78-table"><thead><tr><th>Date</th><th>Reference</th><th>Description</th><th>Direction</th><th>Amount</th></tr></thead><tbody>@forelse($entries as $entry)<tr><td>{{ $entry->created_at->format('d M Y H:i') }}</td><td>{{ $entry->reference }}</td><td>{{ $entry->description }}</td><td>{{ ucfirst($entry->direction) }}</td><td>{{ $entry->currency }} {{ number_format($entry->amount,2) }}</td></tr>@empty<tr><td colspan="5"><div class="az-s78-empty">No ledger entries yet.</div></td></tr>@endforelse</tbody></table></div><div class="az-user-pagination">{{ $entries->links('vendor.pagination.azari') }}</div></section>
@endsection
