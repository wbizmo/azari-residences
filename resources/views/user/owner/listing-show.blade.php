@extends('layouts.user')
@section('title','Listing '.$listing->reference)
@section('content')
<div class="az-premium-page-head"><div><span class="az-premium-kicker">{{ $listing->reference }}</span><h1>{{ data_get($listing->property_data,'name') }}</h1><p>{{ data_get($listing->property_data,'location') }} · {{ ucfirst($listing->status) }}</p></div>@if($listing->isEditable())<a class="az-premium-button" href="{{ route('user.owner.listings.edit',$listing) }}">Edit and resubmit</a>@endif</div>
@if($listing->status==='declined')<div class="az-alert az-alert--danger"><span class="material-symbols-outlined">error</span><div><strong>Declined</strong><p>{{ $listing->decline_reason }}</p></div></div>@endif
<div class="az-s78-grid">
<section class="az-premium-card"><div class="az-premium-card-head"><div><h2>Property details</h2><p>Submitted information awaiting or following review.</p></div></div><dl class="az-s78-detail">@foreach($listing->property_data as $key=>$value)@if(!is_array($value))<div><dt>{{ str($key)->replace('_',' ')->title() }}</dt><dd>{{ is_bool($value)?($value?'Yes':'No'):$value }}</dd></div>@endif @endforeach</dl></section>
<section class="az-premium-card"><div class="az-premium-card-head"><div><h2>Review record</h2></div></div><dl class="az-s78-detail"><div><dt>Status</dt><dd>{{ ucfirst($listing->status) }}</dd></div><div><dt>Proposed share</dt><dd>{{ number_format((float)$listing->proposed_owner_share_percentage,2) }}%</dd></div>@if($listing->approved_owner_share_percentage!==null)<div><dt>Approved share</dt><dd>{{ number_format((float)$listing->approved_owner_share_percentage,2) }}%</dd></div>@endif<div><dt>Submitted</dt><dd>{{ optional($listing->submitted_at)->format('d M Y H:i') }}</dd></div></dl><a class="az-premium-link" href="{{ route('user.owner.agreement.download') }}">Download listing agreement</a></section>
</div>
@endsection
