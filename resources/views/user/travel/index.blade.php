@extends('layouts.user')
@section('title', 'Travel extras')
@section('kicker', 'Resavar journeys')
@section('page_title', 'Travel extras')
@section('content')
<nav aria-label="Travel categories" class="az-user-tabs">
    @foreach(['all' => 'All', 'transfer' => 'Airport transfers', 'experience' => 'Experiences', 'car' => 'Car rental', 'flight' => 'Flights'] as $slug => $label)
        <a class="az-user-tab {{ ($kind ?? 'all') === $slug ? 'is-active' : '' }}"
            href="{{ route('user.travel.index', $slug === 'all' ? [] : ['kind' => $slug]) }}">{{ $label }}</a>
    @endforeach
</nav>
<section class="az-user-panel" style="margin-bottom:18px">
    <header class="az-user-panel-header">
        <div>
            <h2 class="az-user-panel-title">Supplier-reviewed travel offers</h2>
            <p class="az-user-panel-subtitle">These are indicative supplier offers, not guaranteed live availability. Requests do not charge your card or confirm a flight, transfer, car or ticket.</p>
        </div>
        <a class="az-user-button az-user-button--light" href="{{ route('user.bookings.index', ['status' => 'all']) }}">My stays</a>
    </header>
    <div class="az-user-panel-body">
        @forelse($offers as $offer)
            <article class="az-user-panel" style="margin-bottom:16px">
                <div class="az-user-panel-body">
                    <h3>{{ $offer->title }}</h3>
                    <p>{{ ucfirst($offer->kind) }} · {{ $offer->supplier->name }}
                        @if($offer->origin) · {{ $offer->origin }} @endif
                        @if($offer->destination) → {{ $offer->destination }} @endif
                    </p>
                    <p><strong>{{ $offer->currency }} {{ number_format($offer->totalMinor(1) / 100, 2) }} per participant</strong>
                        · includes disclosed taxes and fees
                        @if($offer->deposit_minor)
                            · separate potential refundable deposit: {{ $offer->currency }} {{ number_format($offer->deposit_minor / 100, 2) }}
                        @endif
                    </p>
                    <details>
                        <summary>What's included, policies and request form</summary>
                        <p>{{ $offer->terms['included'] ?? '' }}</p>
                        <p><strong>Cancellation:</strong> {{ $offer->terms['cancellation'] ?? '' }}</p>
                        <p><strong>Supplier terms:</strong> {{ $offer->terms['disclosure'] ?? '' }}</p>
                        @if($offer->eligibility)
                            <p><strong>Eligibility:</strong> {{ implode('; ', array_map(fn ($item) => is_scalar($item) ? (string)$item : 'See supplier terms', $offer->eligibility)) }}</p>
                        @endif
                        <form method="POST" action="{{ route('user.travel.store') }}" class="az-form-grid">
                            @csrf
                            <input type="hidden" name="offer_id" value="{{ $offer->id }}">
                            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                            <label><span>Travellers (max {{ $offer->max_party }})</span>
                                <input type="number" name="party_size" min="1" max="{{ $offer->max_party }}" value="1" required>
                            </label>
                            @if($offer->kind === 'experience')
                                <label><span>Available activity time</span>
                                    <select name="slot_id" required>
                                        <option value="">Choose a time</option>
                                        @foreach($offer->slots as $slot)
                                            <option value="{{ $slot->id }}">
                                                {{ $slot->starts_at->setTimezone($offer->timezone)->format('j M Y, g:i A') }}
                                                ({{ $offer->timezone }})
                                            </option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif
                            <label><span>Associate with a travel itinerary (optional)</span>
                                <select name="trip_itinerary_id">
                                    <option value="">No itinerary</option>
                                    @foreach($itineraries as $itinerary)
                                        <option value="{{ $itinerary->id }}">{{ $itinerary->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label><span>Link to a stay (optional)</span>
                                <select name="booking_id">
                                    <option value="">No linked stay</option>
                                    @foreach($stays as $stay)
                                        <option value="{{ $stay->id }}">{{ $stay->reference }}</option>
                                    @endforeach
                                </select>
                            </label>
                            @if(in_array($offer->kind, ['transfer', 'car']))
                                <label><span>Bags</span><input type="number" name="bags" min="0" max="30" value="0"></label>
                                <label><input type="checkbox" name="accessible" value="1"> Accessible vehicle needed</label>
                            @endif
                            @if($offer->kind === 'transfer')
                                <label><span>Flight number (optional, for arrival planning)</span><input type="text" name="flight_number" maxlength="16"></label>
                            @endif
                            <label><input type="checkbox" name="data_share_consent" value="1">
                                I consent to sharing only the necessary contact and pickup details with the approved supplier after review.</label>
                            <button class="az-user-button az-user-button--dark" type="submit">
                                Request supplier availability, no payment
                            </button>
                        </form>
                    </details>
                </div>
            </article>
        @empty
            <div class="az-user-empty">
                <h3>No approved offers in this category yet</h3>
                <p>Resavar will not advertise unverified supplier inventory or pretend flight seats and cars are confirmed.</p>
            </div>
        @endforelse
        {{ $offers->links() }}
    </div>
</section>
<section class="az-user-panel">
    <header class="az-user-panel-header"><h2 class="az-user-panel-title">My travel requests</h2></header>
    <div class="az-user-panel-body az-user-list">
        @forelse($requests as $travel)
            <a class="az-user-list-item" href="{{ route('user.travel.show', $travel) }}">
                <div><h3>{{ $travel->offer?->title ?? ucfirst($travel->kind) }}</h3>
                    <p>{{ $travel->currency }} {{ number_format($travel->quoted_total_minor / 100, 2) }} indicative
                        · {{ str_replace('_', ' ', $travel->status) }}
                        @if($travel->itinerary) · {{ $travel->itinerary->name }} @endif
                    </p>
                </div><span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
            </a>
        @empty
            <p>You haven't requested any extras.</p>
        @endforelse
    </div>
</section>
@endsection
