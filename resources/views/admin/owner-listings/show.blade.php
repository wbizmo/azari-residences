@extends('layouts.admin')
@section('title','Review owner listing')
@section('content')
<div class="admin-page-header"><div><h1>{{ data_get($listing->property_data,'name','Owner listing') }}</h1><p>{{ $listing->reference }} · {{ $listing->user?->name }} · {{ str_replace('_',' ',$listing->status) }}</p></div><a href="{{ route('azari.admin.owner-listings.agreement',$listing) }}">Download signed agreement</a></div>
<div class="admin-card">@if($listing->cover_image)<img src="{{ Storage::disk('public')->url($listing->cover_image) }}" alt="" style="width:100%;max-height:420px;object-fit:cover">@endif
<dl>@foreach($listing->property_data as $key=>$value)@unless(is_array($value))<dt>{{ ucfirst(str_replace('_',' ',$key)) }}</dt><dd>{{ $value }}</dd>@endunless @endforeach</dl>
<p><strong>Amenities:</strong> {{ $amenities->join(', ') ?: 'None selected' }}</p>
@if($listing->decline_reason)<p><strong>Previous decline:</strong> {{ $listing->decline_reason }}</p>@endif
</div>
@if(in_array($listing->status,['submitted','under_review']))
<div class="admin-grid">
<form method="post" action="{{ route('azari.admin.owner-listings.approve',$listing) }}" class="admin-card">@csrf<h2>Approve listing</h2><label>Owner share percentage<input type="number" name="approved_owner_share_percentage" min="0" max="100" step="0.01" value="{{ old('approved_owner_share_percentage',$listing->proposed_owner_share_percentage) }}" required></label><label><input type="checkbox" name="publish_now" value="1"> Publish immediately</label><label><input type="checkbox" name="feature_now" value="1"> Feature immediately</label><label>Internal notes<textarea name="admin_notes"></textarea></label><button>Approve and create property</button></form>
<form method="post" action="{{ route('azari.admin.owner-listings.decline',$listing) }}" class="admin-card">@csrf<h2>Decline listing</h2><label>Reason visible to owner<textarea name="decline_reason" minlength="10" required></textarea></label><label>Internal notes<textarea name="admin_notes"></textarea></label><button>Decline with reason</button></form>
</div>
@endif
@endsection
