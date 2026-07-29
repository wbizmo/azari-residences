@extends('layouts.admin')
@section('title','Property owner listings')
@section('content')
<div class="az-admin-page-header"><div><h1>Property owner listings</h1><p>Review externally submitted properties before they enter inventory.</p></div></div>
<div class="az-admin-card"><form method="get" class="az-admin-filter-form"><label>Status<select name="status" onchange="this.form.submit()"><option value="">All</option>@foreach(['submitted','under_review','approved','declined'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ ucfirst(str_replace('_',' ',$status)) }}</option>@endforeach</select></label></form></div>
<div class="az-admin-card"><div class="az-admin-table-wrap"><table class="az-admin-table"><thead><tr><th>Reference</th><th>Owner</th><th>Property</th><th>Status</th><th>Submitted</th><th></th></tr></thead><tbody>
@forelse($listings as $listing)<tr><td>{{ $listing->reference }}</td><td>{{ $listing->user?->name }}</td><td>{{ data_get($listing->property_data,'name') }}</td><td>{{ str_replace('_',' ',$listing->status) }}</td><td>{{ optional($listing->submitted_at)->format('j M Y') }}</td><td><a class="az-admin-link" href="{{ route('azari.admin.owner-listings.show',$listing) }}">Review</a></td></tr>
@empty<tr><td colspan="6">No matching owner listings.</td></tr>@endforelse
</tbody></table></div>{{ $listings->links() }}</div>
@endsection
