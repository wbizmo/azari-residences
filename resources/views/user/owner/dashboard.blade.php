@extends('layouts.user')
@section('title', 'Property Centre')
@section('content')
<div class="az-premium-page-head">
    <div>
        <span class="az-premium-kicker"><span class="material-symbols-outlined">real_estate_agent</span>Property Centre</span>
        <h1>List, manage and earn from your property.</h1>
        <p>Your normal Azari account now includes property listing, approval tracking, earnings and withdrawals.</p>
    </div>
    <a class="az-premium-button" href="{{ route('user.owner.listings.create') }}"><span class="material-symbols-outlined">add_home</span>List a property</a>
</div>

<div class="az-s78-stat-grid">
    <article class="az-s78-stat"><span>Account balance</span><strong>{{ $currency }} {{ number_format($balance,2) }}</strong></article>
    <article class="az-s78-stat"><span>Available</span><strong>{{ $currency }} {{ number_format($available,2) }}</strong></article>
    <article class="az-s78-stat"><span>Pending withdrawals</span><strong>{{ $currency }} {{ number_format($pending,2) }}</strong></article>
</div>

<div class="az-s78-grid">
    <section class="az-premium-card">
        <div class="az-premium-card-head"><div><h2>Recent listings</h2><p>Approval progress for properties you submitted.</p></div><a class="az-premium-link" href="{{ route('user.owner.listings.index') }}">View all</a></div>
        <div class="az-service-history">
            @forelse($listings as $listing)
                <a class="az-service-history-card" href="{{ route('user.owner.listings.show',$listing) }}">
                    <span class="az-service-history-icon"><span class="material-symbols-outlined">apartment</span></span>
                    <div><h3>{{ data_get($listing->property_data,'name') }}</h3><p>{{ $listing->reference }} · {{ optional($listing->submitted_at)->format('d M Y') }}</p></div>
                    <span class="az-premium-status {{ $listing->status==='declined'?'is-danger':($listing->status==='approved'?'':'is-warning') }}">{{ str_replace('_',' ',$listing->status) }}</span>
                </a>
            @empty
                <div class="az-premium-empty"><span class="material-symbols-outlined">holiday_village</span>No properties submitted yet.</div>
            @endforelse
        </div>
    </section>

    <section class="az-premium-card">
        <div class="az-premium-card-head"><div><h2>Recent earnings</h2><p>Automatic owner credits from successful guest payments.</p></div><a class="az-premium-link" href="{{ route('user.owner.earnings') }}">Statement</a></div>
        <div class="az-user-list">
            @forelse($recentEntries as $entry)
                <div class="az-user-list-item"><div><h3>{{ $entry->description }}</h3><p>{{ $entry->created_at->format('d M Y, H:i') }}</p></div><strong>{{ $entry->direction==='credit'?'+':'-' }}{{ $entry->currency }} {{ number_format($entry->amount,2) }}</strong></div>
            @empty
                <div class="az-premium-empty">No owner earnings yet.</div>
            @endforelse
        </div>
    </section>
</div>
@endsection
