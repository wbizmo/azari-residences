@extends('layouts.user')
@section('title', 'Archived records')
@section('kicker', 'Account history')
@section('page_title', 'Historical records')
@section('content')
<section class="az-user-panel" aria-labelledby="archive-heading">
    <header class="az-user-panel-header">
        <div>
            <h2 class="az-user-panel-title" id="archive-heading">Legacy travel requests</h2>
            <p class="az-user-panel-subtitle">Read-only records from before Resavar became an accommodation-only marketplace.</p>
        </div>
    </header>
    <div class="az-user-panel-body">
        <p>These are historical enquiries, not new travel services or evidence of a paid booking. For payment and receipt records, use your usual booking documents.</p>
        @forelse($requests as $historical)
            <article class="az-user-panel" style="margin-block:12px;padding:18px">
                <h3 style="margin:0 0 8px">
                    <a href="{{ route('user.archive.travel.show', $historical) }}">
                        {{ $historical->offer?->title ?? ($historical->quote_snapshot['title'] ?? 'Historical request') }}
                    </a>
                </h3>
                <p><strong>Reference:</strong> {{ $historical->id }}</p>
                <p><strong>Recorded status:</strong> {{ str_replace('_', ' ', ucfirst($historical->status)) }}</p>
                <p><strong>Created:</strong> {{ $historical->created_at?->format('j M Y, H:i') }}</p>
            </article>
        @empty
            <p>You have no archived travel requests.</p>
        @endforelse
        {{ $requests->links() }}
        <h3>Historical dining requests</h3>
        @forelse($diningRequests as $dining)
            <p><strong>{{ $dining->partner?->name ?? 'Former dining partner' }}</strong> ·
                {{ str_replace('_', ' ', $dining->status) }} ·
                {{ $dining->requested_for?->format('j M Y, H:i') ?? $dining->created_at?->format('j M Y, H:i') }}
            </p>
        @empty
            <p>No historical dining requests.</p>
        @endforelse
        {{ $diningRequests->links() }}
        <h3>Historical trip records</h3>
        @forelse($assemblies as $assembly)
            <p><strong>{{ $assembly->itinerary?->name ?? 'Archived trip plan' }}</strong> ·
                {{ str_replace('_', ' ', $assembly->status) }} · {{ $assembly->created_at?->format('j M Y') }}
            </p>
        @empty
            <p>No archived trip records.</p>
        @endforelse
        {{ $assemblies->links() }}
        <p><a href="{{ route('user.bookings.index') }}">Return to accommodation bookings</a></p>
    </div>
</section>
@endsection
