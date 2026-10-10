@extends('layouts.user')
@section('title', $itinerary->name.' | Trips')
@section('kicker', 'Your travel plans')
@section('page_title', $itinerary->name)
@section('content')

<nav aria-label="Trip navigation" style="margin-bottom:16px">
    <a class="az-user-button az-user-button--light" href="{{ route('user.bookings.index', ['status' => 'all']) }}">
        All bookings
    </a>
</nav>

<section class="az-user-panel">
    <header class="az-user-panel-header">
        <div>
            <h2 class="az-user-panel-title">{{ $itinerary->name }}</h2>
            <p class="az-user-panel-subtitle">
                A private grouping of your Resavar stays. Each booking retains its own payment,
                cancellation terms, documents and support conversation.
            </p>
        </div>
    </header>
    <div class="az-user-panel-body">
        <div class="az-user-list">
            @forelse($bookings as $booking)
                <a class="az-user-list-item" href="{{ route('user.bookings.show', $booking->reference) }}">
                    <div>
                        <h3>{{ $booking->property_name_snapshot ?: ($booking->property?->name ?? 'Resavar stay') }}</h3>
                        <p>
                            {{ $booking->check_in?->format('j M Y') }}
                            to {{ $booking->check_out?->format('j M Y') }}
                            · {{ $booking->accommodation_type_name_snapshot ?: ($booking->accommodationType?->name ?? 'Accommodation') }}
                        </p>
                        <p>{{ $booking->currency }} {{ number_format((float) $booking->total, 2) }} · {{ Str::headline($booking->status) }}</p>
                    </div>
                    <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
                </a>
            @empty
                <div class="az-user-empty">
                    <h3>No stays in this itinerary</h3>
                    <p>Open an individual booking and choose this itinerary to add it.</p>
                    <a href="{{ route('user.bookings.index', ['status' => 'all']) }}">Browse all bookings</a>
                </div>
            @endforelse
        </div>
        {{ $bookings->links() }}
    </div>
</section>


<section class="az-user-panel" style="margin-top:18px">
    <header class="az-user-panel-header">
        <div>
            <h2 class="az-user-panel-title">Travel extras linked to this itinerary</h2>
            <p class="az-user-panel-subtitle">Travel supplier enquiries are separate from stays and cannot change accommodation payments.</p>
        </div>
        @if(config('travel.requests_enabled'))
            <a class="az-user-button az-user-button--dark" href="{{ route('user.travel.index') }}">Explore extras</a>
        @endif
    </header>
    <div class="az-user-panel-body az-user-list">
        @forelse($travelRequests as $travel)
            <div class="az-user-list-item">
                <div>
                    <h3>{{ $travel->offer?->title ?? ucfirst($travel->kind) }}</h3>
                    <p>{{ $travel->currency }} {{ number_format($travel->quoted_total_minor / 100, 2) }} indicative · {{ str_replace('_', ' ', $travel->status) }}</p>
                </div>
                @if(config('travel.requests_enabled'))
                    <a href="{{ route('user.travel.show', $travel) }}">View request</a>
                @endif
            </div>
        @empty
            <p>No travel extras attached yet.</p>
        @endforelse
        {{ $travelRequests->links() }}
    </div>
</section>

<section class="az-user-panel" style="margin-top:18px">
    <div class="az-user-panel-body">
        <form method="POST" action="{{ route('user.itineraries.destroy', $itinerary) }}">
            @csrf
            @method('DELETE')
            <p>Removing this itinerary will not cancel, refund or delete any booking.</p>
            <button class="az-user-button az-user-button--light" type="submit">
                Remove itinerary grouping
            </button>
        </form>
    </div>
</section>
@endsection
