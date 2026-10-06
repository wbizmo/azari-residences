@extends('layouts.user')
@section('title','Property application')
@section('kicker','Property Centre')
@section('page_title',data_get($listing->property_data,'name','Property application'))
@section('content')
<section class="az-user-panel">
<header class="az-user-panel-header"><div><h2 class="az-user-panel-title">{{ $listing->reference }}</h2><p class="az-user-panel-subtitle">Submitted {{ optional($listing->submitted_at)->format('j M Y, g:i a') ?? 'Not submitted' }}</p></div><span class="az-user-status">{{ str_replace('_',' ',$listing->status) }}</span></header>
<div class="az-user-panel-body">
<div class="az-user-alert {{ $completion['publishable'] ? '' : 'az-user-alert--danger' }}">
    <strong>Listing completeness: {{ $completion['score'] }}%</strong>
    @if($completion['publishable'])
        <p>All required publication checks are complete.</p>
    @else
        <p>Complete these items before the listing can be published: {{ collect($completion['blockers'])->map(fn($item)=>Str::headline($item))->join(', ') }}.</p>
    @endif
</div>
@if($listing->cover_image)<img class="az-owner-cover" src="{{ Storage::disk('public')->url($listing->cover_image) }}" alt="{{ data_get($listing->property_data,'name','Property cover') }}">@endif
<div class="az-user-detail-grid">
@foreach(['location'=>'Location','property_type'=>'Property type','bedrooms'=>'Bedrooms','bathrooms'=>'Bathrooms','max_guests'=>'Maximum guests','nightly_rate'=>'Nightly rate','currency'=>'Currency'] as $key=>$label)
<div><small>{{ $label }}</small><strong>{{ data_get($listing->property_data,$key,'—') }}</strong></div>
@endforeach
</div>
@if($listing->decline_reason)<div class="az-user-alert az-user-alert--danger"><strong>Reason for decline</strong><p>{{ $listing->decline_reason }}</p></div>@endif
@if($listing->isEditable())
    <a class="az-user-button az-user-button--dark" href="{{ route('user.owner.listings.edit',$listing) }}">Edit and resubmit</a>
@endif
@if($listing->status === 'approved' && $listing->approvedProperty)
    <a class="az-user-button az-user-button--dark" href="{{ route('user.owner.commercial.edit',$listing->approvedProperty) }}">
        Manage accommodation & rates
    </a>
@endif
</div></section>
@endsection
