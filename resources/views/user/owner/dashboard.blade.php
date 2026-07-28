@extends('layouts.user')
@section('title','Property Centre')
@section('kicker','Owner marketplace')
@section('page_title','Property Centre')
@section('content')
<section class="az-user-stat-grid">
    @foreach([
        ['label'=>'Account balance','value'=>$currency.' '.number_format($balance,2),'icon'=>'account_balance_wallet'],
        ['label'=>'Reserved withdrawals','value'=>$currency.' '.number_format($pending,2),'icon'=>'schedule'],
        ['label'=>'Available to withdraw','value'=>$currency.' '.number_format($available,2),'icon'=>'payments'],
        ['label'=>'Listing applications','value'=>$listings->count(),'icon'=>'apartment'],
    ] as $item)
        <article class="az-user-stat"><span class="material-symbols-outlined">{{ $item['icon'] }}</span><div><small>{{ $item['label'] }}</small><strong>{{ $item['value'] }}</strong></div></article>
    @endforeach
</section>

<section class="az-user-panel">
    <header class="az-user-panel-header">
        <div><h2 class="az-user-panel-title">Your property portfolio</h2><p class="az-user-panel-subtitle">Submit, monitor and manage properties offered through Azari.</p></div>
        <a class="az-user-button az-user-button--dark" href="{{ route('user.owner.listings.create') }}">Add property</a>
    </header>
    <div class="az-user-panel-body">
        @forelse($listings as $listing)
            <a class="az-user-list-item" href="{{ route('user.owner.listings.show',$listing) }}">
                <div><h3>{{ data_get($listing->property_data,'name','Property application') }}</h3><p>{{ $listing->reference }} · Submitted {{ optional($listing->submitted_at)->format('j M Y') ?? 'Not submitted' }}</p></div>
                <span class="az-user-status {{ $listing->status==='declined'?'az-user-status--danger':($listing->status==='submitted'?'az-user-status--warning':'') }}">{{ str_replace('_',' ',$listing->status) }}</span>
            </a>
        @empty
            <div class="az-user-empty"><span class="material-symbols-outlined">holiday_village</span><h3>No property applications yet</h3><p>Sign the owner agreement and submit your first property for review.</p></div>
        @endforelse
    </div>
</section>

<section class="az-user-panel">
    <header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Recent account activity</h2><p class="az-user-panel-subtitle">Credits and completed withdrawal debits.</p></div><a href="{{ route('user.owner.earnings') }}">View statement</a></header>
    <div class="az-user-panel-body">
        @forelse($recentEntries as $entry)
            <div class="az-user-list-item"><div><h3>{{ $entry->description }}</h3><p>{{ $entry->created_at->format('j M Y, g:i a') }} · {{ $entry->reference }}</p></div><strong>{{ $entry->direction==='credit'?'+':'-' }}{{ $entry->currency }} {{ number_format((float)$entry->amount,2) }}</strong></div>
        @empty
            <div class="az-user-empty"><p>No owner ledger activity yet.</p></div>
        @endforelse
    </div>
</section>
@endsection
