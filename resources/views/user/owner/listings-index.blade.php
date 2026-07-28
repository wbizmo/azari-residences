@extends('layouts.user')
@section('title','My Property Listings')
@section('content')
<div class="az-premium-page-head"><div><span class="az-premium-kicker">Property Centre</span><h1>My property listings</h1><p>Track approvals, review decline reasons and resubmit corrected properties.</p></div><a class="az-premium-button" href="{{ route('user.owner.listings.create') }}">Add property</a></div>
<section class="az-premium-card">
<div class="az-responsive-table-shell"><table class="az-s78-table"><thead><tr><th>Property</th><th>Reference</th><th>Status</th><th>Submitted</th><th></th></tr></thead><tbody>
@forelse($listings as $listing)<tr><td><strong>{{ data_get($listing->property_data,'name') }}</strong><small>{{ data_get($listing->property_data,'location') }}</small></td><td>{{ $listing->reference }}</td><td><span class="az-s78-badge {{ $listing->status==='declined'?'is-danger':($listing->status==='approved'?'':'is-warning') }}">{{ str_replace('_',' ',$listing->status) }}</span></td><td>{{ optional($listing->submitted_at)->format('d M Y') ?: 'Draft' }}</td><td><a class="az-premium-link" href="{{ route('user.owner.listings.show',$listing) }}">Open</a></td></tr>
@empty<tr><td colspan="5"><div class="az-s78-empty">No listings yet.</div></td></tr>@endforelse
</tbody></table></div>
<div class="az-user-pagination">{{ $listings->links('vendor.pagination.azari') }}</div>
</section>
@endsection
