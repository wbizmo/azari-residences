@extends('layouts.public')

@section('content')
<main class="public-page-shell">
    <section class="site-container page-intro">
        <span class="eyebrow">Live inventory</span>
        <h1>Available residences</h1>
        <p>
            {{ \Carbon\Carbon::parse($filters['check_in'])->format('j M Y') }}
            to {{ \Carbon\Carbon::parse($filters['check_out'])->format('j M Y') }}
            · {{ (int) $filters['adults'] + (int) ($filters['children'] ?? 0) }} guests
        </p>

        <form class="availability-form" method="GET" action="{{ route('availability.results') }}">
            <input type="date" name="check_in" value="{{ $filters['check_in'] }}" required>
            <input type="date" name="check_out" value="{{ $filters['check_out'] }}" required>
            <input type="number" name="adults" min="1" value="{{ $filters['adults'] }}" required>
            <input type="number" name="children" min="0" value="{{ $filters['children'] ?? 0 }}">
            <select name="location_id">
                <option value="">All available locations</option>
                @foreach($locations as $location)
                    <option value="{{ $location->id }}" @selected((string)($filters['location_id'] ?? '') === (string)$location->id)>
                        {{ $location->name }}, {{ $location->city }}
                    </option>
                @endforeach
            </select>
            <select name="room_type_id">
                <option value="">Any category</option>
                @foreach($roomTypes as $roomType)
                    <option value="{{ $roomType->id }}" @selected((string)($filters['room_type_id'] ?? '') === (string)$roomType->id)>
                        {{ $roomType->name }}
                    </option>
                @endforeach
            </select>
            <input type="hidden" name="rooms" value="{{ $filters['rooms'] ?? 1 }}">
            <button class="button button-primary" type="submit">Update search</button>
        </form>

        @if($availableLocations->isNotEmpty())
            <div class="availability-location-summary">
                <strong>Available locations:</strong>
                {{ $availableLocations->map(fn($location) => $location->name)->join(', ') }}
            </div>
        @endif
    </section>

    <section class="site-container residences-grid">
        @forelse($results as $result)
            @php($property = $result['property'])
            @php($quote = $result['quote'])
            <article class="residence-card">
                @if($property->cover_image)
                    <img src="{{ Storage::url($property->cover_image) }}" alt="{{ $property->name }}">
                @endif
                <div class="residence-card__body">
                    <span class="eyebrow">
                        {{ $property->locationRecord?->name ?? $property->location }}
                        · {{ $property->roomType?->name ?? ucfirst($property->property_type) }}
                    </span>
                    <h2>{{ $property->name }}</h2>
                    <p>{{ $property->short_description }}</p>
                    <p>
                        Up to {{ $property->max_guests }} guests ·
                        {{ $property->bedrooms }} bedrooms ·
                        {{ $property->bathrooms }} bathrooms
                    </p>
                    <strong>
                        {{ $quote['currency'] }} {{ number_format($quote['total'], 2) }}
                        total for {{ $quote['nights'] }} night(s)
                    </strong>

                    <form method="POST" action="{{ route('azari.availability.hold', $property) }}">
                        @csrf
                        <input type="hidden" name="check_in" value="{{ $filters['check_in'] }}">
                        <input type="hidden" name="check_out" value="{{ $filters['check_out'] }}">
                        <input type="hidden" name="adults" value="{{ $filters['adults'] }}">
                        <input type="hidden" name="children" value="{{ $filters['children'] ?? 0 }}">
                        <input type="hidden" name="rooms" value="{{ $filters['rooms'] ?? 1 }}">
                        <button class="button button-brass" type="submit">Reserve this residence</button>
                    </form>
                </div>
            </article>
        @empty
            <div class="empty-state">
                <span class="material-symbols-outlined">event_busy</span>
                <h2>No exact match for those dates</h2>
                <p>Try another location or choose one of the next available date ranges below.</p>
            </div>
        @endforelse
    </section>

    @if($alternatives->isNotEmpty())
        <section class="site-container">
            <div class="section-heading">
                <span class="eyebrow">Next availability</span>
                <h2>Nearby available dates</h2>
            </div>

            <div class="residences-grid">
                @foreach($alternatives as $alternative)
                    @php($property = $alternative['property'])
                    <article class="residence-card">
                        <div class="residence-card__body">
                            <span class="eyebrow">{{ $property->locationRecord?->name ?? $property->location }}</span>
                            <h3>{{ $property->name }}</h3>
                            <p>
                                {{ $alternative['check_in']->format('j M Y') }}
                                to {{ $alternative['check_out']->format('j M Y') }}
                            </p>
                            <strong>
                                {{ $alternative['quote']['currency'] }}
                                {{ number_format($alternative['quote']['total'], 2) }}
                            </strong>
                            <form method="POST" action="{{ route('azari.availability.hold', $property) }}">
                                @csrf
                                <input type="hidden" name="check_in" value="{{ $alternative['check_in']->toDateString() }}">
                                <input type="hidden" name="check_out" value="{{ $alternative['check_out']->toDateString() }}">
                                <input type="hidden" name="adults" value="{{ $filters['adults'] }}">
                                <input type="hidden" name="children" value="{{ $filters['children'] ?? 0 }}">
                                <input type="hidden" name="rooms" value="{{ $filters['rooms'] ?? 1 }}">
                                <button class="button button-primary" type="submit">Choose these dates</button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
</main>
@endsection
