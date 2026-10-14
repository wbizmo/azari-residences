@extends('layouts.user')
@section('title','My properties')
@section('kicker','Property Centre')
@section('page_title','My properties')
@section('content')
<section class="az-user-panel">
<header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Listing applications</h2><p class="az-user-panel-subtitle">Track approval, decline and resubmission decisions.</p></div><a class="az-user-button az-user-button--dark" href="{{ route('user.owner.listings.create') }}">Add property</a></header>
<div class="az-user-panel-body">
@forelse($listings as $listing)
<a class="az-user-list-item" href="{{ route('user.owner.listings.show',$listing) }}"><div><h3>{{ data_get($listing->property_data,'name','Property application') }}</h3><p>{{ $listing->reference }} · {{ data_get($listing->property_data,'location','Location pending') }}</p></div><span class="az-user-status {{ $listing->status==='declined'?'az-user-status--danger':($listing->status==='submitted'?'az-user-status--warning':'') }}">{{ str_replace('_',' ',$listing->status) }}</span></a>
@empty<div class="az-user-empty"><span class="material-symbols-outlined">apartment</span><h3>No applications</h3><p>Your submitted properties will appear here.</p></div>@endforelse
{{ $listings->links() }}
</div></section>
<section class="az-user-panel" style="margin-top:18px">
    <header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Admin-assigned properties</h2>
        <p class="az-user-panel-subtitle">Manage your assigned room types, unit inventory, pricing and calendar in the same Resavar system.</p></div></header>
    <div class="az-user-panel-body">
        @forelse($assignedProperties as $assigned)
            <a class="az-user-list-item" href="{{ route('user.owner.commercial.edit', $assigned) }}">
                <div><h3>{{ $assigned->name }}</h3><p>{{ $assigned->location }} · {{ $assigned->currency }}</p></div>
                <span class="az-user-status">{{ $assigned->is_published ? 'Published' : 'Draft' }}</span>
            </a>
        @empty
            <p>No properties have been assigned to you by an administrator.</p>
        @endforelse
        {{ $assignedProperties->links() }}
    </div>
</section>
@endsection
