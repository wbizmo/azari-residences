<x-public-site.layout
    title="Available hotels & residences | Reserva"
    description="Review live Reserva inventory and refine your stay request."
>
    @php
        $checkIn = \Carbon\CarbonImmutable::parse($filters['check_in']);
        $checkOut = \Carbon\CarbonImmutable::parse($filters['check_out']);
        $nightCount = max(1, $checkIn->diffInDays($checkOut));
        $guestCount = (int) $filters['adults'] + (int) ($filters['children'] ?? 0);
    @endphp

    <main class="az-results-page">
        <section class="site-container az-results-shell">
            <header class="az-results-heading">
                <span class="sr-only">Your stay request</span>
                <div>
                    <span class="eyebrow">Live inventory</span>
                    <h1>Available hotels & residences</h1>
                    <p>
                        {{ $checkIn->format('j M Y') }} to {{ $checkOut->format('j M Y') }}
                        <span aria-hidden="true">·</span>
                        {{ $nightCount }} {{ \Illuminate\Support\Str::plural('night', $nightCount) }}
                        <span aria-hidden="true">·</span>
                        {{ $guestCount }} {{ \Illuminate\Support\Str::plural('guest', $guestCount) }}
                    </p>
                </div>
            </header>

            <form class="az-results-search" method="GET" action="{{ route('availability.results') }}">
                <label class="az-results-control">
                    <span>Check in</span>
                    <span class="az-results-input">
                        <span class="material-symbols-outlined" aria-hidden="true">calendar_today</span>
                        <input type="date" name="check_in" value="{{ $filters['check_in'] }}" min="{{ now()->toDateString() }}" required>
                    </span>
                </label>

                <label class="az-results-control">
                    <span>Check out</span>
                    <span class="az-results-input">
                        <span class="material-symbols-outlined" aria-hidden="true">event_available</span>
                        <input type="date" name="check_out" value="{{ $filters['check_out'] }}" min="{{ now()->addDay()->toDateString() }}" required>
                    </span>
                </label>

                <label class="az-results-control">
                    <span>Adults</span>
                    <span class="az-results-input">
                        <span class="material-symbols-outlined" aria-hidden="true">person</span>
                        <input type="number" name="adults" min="1" max="40" value="{{ $filters['adults'] }}" required>
                    </span>
                </label>

                <label class="az-results-control">
                    <span>Children</span>
                    <span class="az-results-input">
                        <span class="material-symbols-outlined" aria-hidden="true">child_care</span>
                        <input type="number" name="children" min="0" max="40" value="{{ $filters['children'] ?? 0 }}">
                    </span>
                </label>

                <label class="az-results-control">
                    <span>Location</span>
                    <span class="az-results-input az-results-select">
                        <span class="material-symbols-outlined" aria-hidden="true">location_on</span>
                        <select name="location_id">
                            <option value="">All available locations</option>
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}" @selected((string) ($filters['location_id'] ?? '') === (string) $location->id)>
                                    {{ $location->name }}, {{ $location->city }}
                                </option>
                            @endforeach
                        </select>
                    </span>
                </label>

                <label class="az-results-control">
                    <span>Category</span>
                    <span class="az-results-input az-results-select">
                        <span class="material-symbols-outlined" aria-hidden="true">apartment</span>
                        <select name="room_type_id">
                            <option value="">Any category</option>
                            @foreach($roomTypes as $roomType)
                                <option value="{{ $roomType->id }}" @selected((string) ($filters['room_type_id'] ?? '') === (string) $roomType->id)>
                                    {{ $roomType->name }}
                                </option>
                            @endforeach
                        </select>
                    </span>
                </label>

                <input type="hidden" name="rooms" value="{{ $filters['rooms'] ?? 1 }}">

                <button class="az-results-submit" type="submit">
                    <span class="material-symbols-outlined" aria-hidden="true">search</span>
                    <span>Update search</span>
                </button>
            </form>

            @if($availableLocations->isNotEmpty())
                <p class="az-results-locations">
                    <strong>Available locations:</strong>
                    {{ $availableLocations->map(fn ($location) => $location->name)->join(', ') }}
                </p>
            @endif

            <section class="az-results-grid" aria-label="Available hotels & residences">
                @forelse($results as $result)
                    @php($property = $result['property'])
                    @php($quote = $result['quote'])
                    <article class="az-results-card">
                        <div class="az-results-card__image">
                            @if($property->cover_image)
                                <img src="{{ Storage::url($property->cover_image) }}" alt="{{ $property->name }}" loading="lazy" decoding="async">
                            @else
                                <img src="{{ asset('images/azari-residence-fallback.png') }}" alt="{{ $property->name }}" loading="lazy" decoding="async">
                            @endif
                        </div>
                        <div class="az-results-card__body">
                            <span class="eyebrow">{{ $property->locationRecord?->name ?? $property->location }}</span>
                            <h2>{{ $property->name }}</h2>
                            <p>{{ $property->short_description }}</p>
                            <div class="az-results-meta">
                                <span><span class="material-symbols-outlined">group</span>Up to {{ $property->max_guests }}</span>
                                <span><span class="material-symbols-outlined">bed</span>{{ $property->bedrooms }} bedrooms</span>
                                <span><span class="material-symbols-outlined">bathtub</span>{{ $property->bathrooms }} bathrooms</span>
                            </div>
                            <div class="az-results-card__footer">
                                <div class="az-results-price">
                                    <small>Total for {{ $quote['nights'] }} {{ \Illuminate\Support\Str::plural('night', $quote['nights']) }}</small>
                                    <strong>${{ number_format((float) $quote['total'], 2) }} <span>USD</span></strong>
                                </div>
                                <form method="POST" action="{{ route('azari.availability.hold', $property) }}">
                                    @csrf
                                    <input type="hidden" name="check_in" value="{{ $filters['check_in'] }}">
                                    <input type="hidden" name="check_out" value="{{ $filters['check_out'] }}">
                                    <input type="hidden" name="adults" value="{{ $filters['adults'] }}">
                                    <input type="hidden" name="children" value="{{ $filters['children'] ?? 0 }}">
                                    <input type="hidden" name="rooms" value="{{ $filters['rooms'] ?? 1 }}">
                                    <button class="button button-brass" type="submit">Reserve residence</button>
                                </form>
                            </div>
                        </div>
                    </article>
                @empty
                    <article class="az-results-empty">
                        <span class="az-results-empty__icon material-symbols-outlined" aria-hidden="true">event_busy</span>
                        <div>
                            <span class="eyebrow">No exact availability</span>
                            <h2>No exact match for those dates</h2>
                            <p>Adjust your dates, location or guest count, or choose one of the next available ranges below.</p>
                        </div>
                    </article>
                @endforelse
            </section>

            @if($alternatives->isNotEmpty())
                <section class="az-results-alternatives">
                    <div class="section-heading">
                        <span class="eyebrow">Next availability</span>
                        <h2>Nearby available dates</h2>
                    </div>
                    <div class="az-results-grid">
                        @foreach($alternatives as $alternative)
                            @php($property = $alternative['property'])
                            <article class="az-results-card az-results-card--compact">
                                <div class="az-results-card__body">
                                    <span class="eyebrow">{{ $property->locationRecord?->name ?? $property->location }}</span>
                                    <h3>{{ $property->name }}</h3>
                                    <p>{{ $alternative['check_in']->format('j M Y') }} to {{ $alternative['check_out']->format('j M Y') }}</p>
                                    <strong class="az-results-alt-price">${{ number_format((float) $alternative['quote']['total'], 2) }} USD</strong>
                                    <form method="POST" action="{{ route('azari.availability.hold', $property) }}">
                                        @csrf
                                        <input type="hidden" name="check_in" value="{{ $alternative['check_in']->toDateString() }}">
                                        <input type="hidden" name="check_out" value="{{ $alternative['check_out']->toDateString() }}">
                                        <input type="hidden" name="adults" value="{{ $filters['adults'] }}">
                                        <input type="hidden" name="children" value="{{ $filters['children'] ?? 0 }}">
                                        <input type="hidden" name="rooms" value="{{ $filters['rooms'] ?? 1 }}">
                                        <button class="button button-brass" type="submit">Choose these dates</button>
                                    </form>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
        </section>
    </main>
</x-public-site.layout>
