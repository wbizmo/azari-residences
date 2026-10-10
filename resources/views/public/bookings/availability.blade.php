<x-public-site.layout
    title="Available stays — Resavar"
    description="Compare live Resavar inventory, policies, verified reviews and total stay prices."
>
    @php
        $checkIn = Carbon\CarbonImmutable::parse($filters['check_in']);
        $checkOut = Carbon\CarbonImmutable::parse($filters['check_out']);
        $nightCount = max(1, $checkIn->diffInDays($checkOut));
        $guestCount = (int) $filters['adults'] + (int) ($filters['children'] ?? 0);
        $queryWithoutPage = collect(request()->query())->except('page')->all();
    @endphp

    <main class="az-results-page">
        <section class="site-container az-results-shell">
            <header class="az-results-heading">
                <div>
                    <span class="eyebrow">Live inventory</span>
                    <h1>Available stays</h1>
                    <p>
                        {{ $checkIn->format('j M Y') }} to {{ $checkOut->format('j M Y') }}
                        <span aria-hidden="true">·</span>
                        {{ $nightCount }} {{ Str::plural('night', $nightCount) }}
                        <span aria-hidden="true">·</span>
                        {{ $guestCount }} {{ Str::plural('guest', $guestCount) }}
                        <span aria-hidden="true">·</span>
                        {{ $filters['rooms'] ?? 1 }} {{ Str::plural('room', $filters['rooms'] ?? 1) }}
                    </p>
                </div>

                @auth
                    <form method="POST" action="{{ route('user.saved-searches.store') }}">
                        @csrf
                        @foreach(collect($filters)->except(['page'])->filter(fn ($value) => !is_array($value) && $value !== null && $value !== '') as $name => $value)
                            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                        @endforeach
                        @foreach($filters['amenities'] ?? [] as $amenityId)
                            <input type="hidden" name="amenities[]" value="{{ $amenityId }}">
                        @endforeach
                        <button class="button button-secondary" type="submit">
                            <span class="material-symbols-outlined" aria-hidden="true">bookmark_add</span>
                            Save search
                        </button>
                    </form>
                @endauth
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
                        <input type="number" name="adults" min="1" max="12" value="{{ $filters['adults'] }}" required>
                    </span>
                </label>

                <label class="az-results-control">
                    <span>Children</span>
                    <span class="az-results-input">
                        <span class="material-symbols-outlined" aria-hidden="true">child_care</span>
                        <input type="number" name="children" min="0" max="8" value="{{ $filters['children'] ?? 0 }}">
                    </span>
                </label>

                <label class="az-results-control">
                    <span>Flexible dates</span>
                    <span class="az-results-input">
                        <span class="material-symbols-outlined" aria-hidden="true">date_range</span>
                        <select name="flex_days">
                            <option value="">Exact dates</option>
                            <option value="1" @selected(($filters['flex_days'] ?? null)==1)>±1 day</option>
                            <option value="3" @selected(($filters['flex_days'] ?? null)==3)>±3 days</option>
                            <option value="7" @selected(($filters['flex_days'] ?? null)==7)>±7 days</option>
                        </select>
                    </span>
                </label>

                <label class="az-results-control">
                    <span>Rooms</span>
                    <span class="az-results-input">
                        <span class="material-symbols-outlined" aria-hidden="true">meeting_room</span>
                        <input type="number" name="rooms" min="1" max="20" value="{{ $filters['rooms'] ?? 1 }}">
                    </span>
                </label>

                @if(!empty($filters['destination']))
                    <input type="hidden" name="destination" value="{{ $filters['destination'] }}">
                @endif
                @if(!empty($filters['destination_type']))
                    <input type="hidden" name="destination_type" value="{{ $filters['destination_type'] }}">
                @endif
                @if(!empty($filters['destination_id']))
                    <input type="hidden" name="destination_id" value="{{ $filters['destination_id'] }}">
                @endif
                @if(!empty($filters['location_id']))
                    <input type="hidden" name="location_id" value="{{ $filters['location_id'] }}">
                @endif

                <button class="az-results-submit" type="submit">
                    <span class="material-symbols-outlined" aria-hidden="true">search</span>
                    <span>Update search</span>
                </button>
            </form>

            @if($alternatives->isNotEmpty())
                <section class="reserva-flex-date-options" aria-label="Flexible dates and full-stay price examples">
                    <h2>Flexible-date price calendar</h2>
                    <p class="az-user-panel-subtitle">Totals are examples for one verified available stay, including known fees and taxes. A final quote is confirmed when you choose a property. Dates without a verified quote cannot be selected here.</p>
                    <div class="reserva-flex-date-calendar" role="list">
                        @foreach($alternatives as $alternative)
                            <div role="listitem">
                                @if($alternative['example_total'] !== null)
                                    <a class="reserva-flex-date-tile {{ !empty($alternative['current']) ? 'is-selected' : '' }}"
                                       @if(!empty($alternative['current'])) aria-current="true" @endif
                                       href="{{ route('availability.results', [...collect($queryWithoutPage)->except('flex_days')->all(), 'check_in'=>$alternative['check_in'], 'check_out'=>$alternative['check_out']]) }}">
                                        <time datetime="{{ $alternative['check_in'] }}">{{ Carbon\CarbonImmutable::parse($alternative['check_in'])->format('j M Y') }}</time>
                                        <span>to {{ Carbon\CarbonImmutable::parse($alternative['check_out'])->format('j M') }}</span>
                                        <strong>{{ $alternative['currency'] }} {{ number_format((float)$alternative['example_total'], 2) }}</strong>
                                        <small>{{ !empty($alternative['current']) ? 'Current dates' : 'View available stays' }}</small>
                                    </a>
                                @else
                                    <div class="reserva-flex-date-tile is-unavailable" aria-label="No verified quote for {{ $alternative['check_in'] }}">
                                        <time datetime="{{ $alternative['check_in'] }}">{{ Carbon\CarbonImmutable::parse($alternative['check_in'])->format('j M Y') }}</time>
                                        <span>to {{ Carbon\CarbonImmutable::parse($alternative['check_out'])->format('j M') }}</span>
                                        <strong>No verified quote</strong>
                                        <small>Not selectable</small>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            <div class="reserva-results-toolbar">
                <div>
                    <strong>{{ number_format($results->total()) }} {{ Str::plural('stay', $results->total()) }}</strong>
                    @if($availableLocations->isNotEmpty())
                        <span> in {{ $availableLocations->pluck('name')->unique()->join(', ') }}</span>
                    @endif
                </div>

                <div class="reserva-results-toolbar__actions">
                    <button
                        class="button button-secondary reserva-mobile-filter-trigger"
                        type="button"
                        data-drawer-open="reserva-search-filter-drawer"
                    >
                        <span class="material-symbols-outlined" aria-hidden="true">tune</span>
                        Filters
                    </button>

                    <label>
                        <span class="sr-only">Sort results</span>
                        <select name="sort" form="reserva-filter-form" data-search-sort>
                            @foreach([
                                'recommended' => 'Recommended',
                                'price_asc' => 'Price: low to high',
                                'price_desc' => 'Price: high to low',
                                'rating' => 'Guest rating',
                                'distance' => 'Distance',
                                'popularity' => 'Popularity',
                            ] as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['sort'] ?? 'recommended') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <div class="reserva-results-view-toggle" aria-label="Results view">
                        <button type="button" class="is-active" data-results-view="list" aria-pressed="true">List</button>
                        <button type="button" data-results-view="map" aria-pressed="false">Map</button>
                    </div>
                </div>
            </div>

            <div class="reserva-search-layout">
                <aside class="reserva-search-sidebar">
                    <form id="reserva-filter-form" method="GET" action="{{ route('availability.results') }}">
                        <h2>Filter stays</h2>
                        @include('public.bookings.partials.search-filters')
                    </form>
                </aside>

                <div>
                    <section data-results-pane="list" aria-label="Available stays">
                        @forelse($results as $result)
                            @php
                                $property = $result['property'];
                                $type = $result['accommodation_type'];
                                $ratePlan = $result['rate_plan'];
                                $remaining = $result['remaining'];
                                $quote = $result['quote'];
                                $review = $result['reviews'];
                            @endphp

                            <article class="reserva-results-card" data-property-card="{{ $property->id }}">
                                <div class="reserva-results-card__image">
                                    <a href="{{ route('properties.show', $property) }}" aria-label="View {{ $property->name }}">
                                        @if($property->cover_image)
                                            <img src="{{ Storage::url($property->cover_image) }}" alt="{{ $property->name }}" loading="lazy" decoding="async">
                                        @else
                                            <img src="{{ asset('images/azari-residence-fallback.png') }}" alt="{{ $property->name }}" loading="lazy" decoding="async">
                                        @endif
                                    </a>
                                </div>

                                <div class="reserva-results-card__body">
                                    <div class="reserva-results-card__top">
                                        <div>
                                            <span class="eyebrow">
                                                {{ Str::headline($property->property_type) }}
                                                · {{ $property->locationRecord?->name ?? $property->location }}
                                            </span>
                                            <h2><a href="{{ route('properties.show', $property) }}">{{ $property->name }}</a></h2>
                                            <p>{{ $property->short_description }}</p>
                                        </div>

                                        @if($review['count'] > 0)
                                            <div class="reserva-review-score" aria-label="{{ $review['overall'] }} out of 5 from {{ $review['count'] }} verified reviews">
                                                <span>{{ $review['count'] }} {{ Str::plural('review', $review['count']) }}</span>
                                                <strong>{{ number_format($review['overall'], 1) }}</strong>
                                            </div>
                                        @endif
                                    </div>

                                    <p class="az-results-accommodation">
                                        <strong>{{ $type->name }}</strong>
                                        @if($type->bed_configuration)
                                            <span aria-hidden="true">·</span> {{ $type->bed_configuration }}
                                        @endif
                                        <span aria-hidden="true">·</span> Sleeps {{ $type->max_guests }}
                                    </p>

                                    <div class="reserva-result-badges">
                                        @if($ratePlan?->is_refundable)
                                            <span class="is-positive">{{ $ratePlan->cancellationPolicy?->name ?? 'Refundable' }}</span>
                                        @else
                                            <span>Non-refundable</span>
                                        @endif

                                        @if($ratePlan?->paymentPolicy)
                                            <span>{{ $ratePlan->paymentPolicy->name }}</span>
                                        @endif

                                        @if(($quote['discount_total'] ?? 0) > 0)
                                            <span class="is-positive">Deal: save {{ $quote['currency'] }} {{ number_format((float) $quote['discount_total'], 2) }}</span>
                                        @endif

                                        @if($remaining <= 5)
                                            <span>{{ $remaining }} {{ Str::plural('unit', $remaining) }} left</span>
                                        @endif
                                    </div>

                                    <div class="reserva-result-amenities" aria-label="Top amenities">
                                        @foreach($property->amenities->take(5) as $amenity)
                                            <span>{{ $amenity->name }}</span>
                                        @endforeach
                                    </div>

                                    <div class="reserva-results-card__footer">
                                        <div class="reserva-results-price">
                                            <small>
                                                Total for {{ $quote['nights'] }} {{ Str::plural('night', $quote['nights']) }}
                                                @if(($quote['tax_total'] ?? 0) > 0 || ($quote['fee_total'] ?? 0) > 0)
                                                    · includes displayed taxes/fees
                                                @endif
                                            </small>
                                            <strong>{{ $quote['currency'] }} {{ number_format((float) $quote['total'], 2) }}</strong>
                                            @if(($quote['tax_total'] ?? 0) > 0 || ($quote['fee_total'] ?? 0) > 0)
                                                <small>Taxes {{ number_format((float) ($quote['tax_total'] ?? 0), 2) }} · Fees {{ number_format((float) ($quote['fee_total'] ?? 0), 2) }}</small>
                                            @endif
                                        </div>

                                        <div class="reserva-results-actions">
                                            @auth
                                                <form method="POST" action="{{ route('user.favourites.toggle', $property) }}">
                                                    @csrf
                                                    <button
                                                        class="reserva-favourite-button"
                                                        type="submit"
                                                        aria-label="{{ $result['is_favourite'] ? 'Remove from favourites' : 'Save to favourites' }}"
                                                    >
                                                        <span class="material-symbols-outlined" aria-hidden="true">
                                                            {{ $result['is_favourite'] ? 'favorite' : 'favorite_border' }}
                                                        </span>
                                                    </button>
                                                </form>
                                            @else
                                                <button
                                                    class="reserva-favourite-button resavar-local-favourite"
                                                    type="button"
                                                    aria-label="Save this stay on this device"
                                                    aria-pressed="false"
                                                    data-resavar-local-favourite="{{ $property->id }}"
                                                    data-property-name="{{ $property->name }}"
                                                    data-property-url="{{ route('properties.show', $property) }}"
                                                >
                                                    <span class="material-symbols-outlined" aria-hidden="true">favorite_border</span>
                                                </button>
                                            @endauth

                                            <button
                                                class="button button-secondary resavar-compare-button"
                                                type="button"
                                                aria-pressed="false"
                                                data-resavar-compare="{{ $property->id }}"
                                                data-property-name="{{ $property->name }}"
                                                data-property-url="{{ route('properties.show', $property) }}"
                                            >
                                                Compare
                                            </button>

                                            <a class="button button-secondary" href="{{ route('properties.show', $property) }}">View details</a>

                                            <form method="POST" action="{{ route('azari.availability.hold', $property) }}">
                                                @csrf
                                                <input type="hidden" name="check_in" value="{{ $filters['check_in'] }}">
                                                <input type="hidden" name="check_out" value="{{ $filters['check_out'] }}">
                                                <input type="hidden" name="adults" value="{{ $filters['adults'] }}">
                                                <input type="hidden" name="children" value="{{ $filters['children'] ?? 0 }}">
                                                <input type="hidden" name="rooms" value="{{ $filters['rooms'] ?? 1 }}">
                                                <input type="hidden" name="accommodation_type_id" value="{{ $type->id }}">
                                                @if($ratePlan)
                                                    <input type="hidden" name="rate_plan_id" value="{{ $ratePlan->id }}">
                                                @endif
                                                <button class="button button-primary" type="submit">Reserve</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        @empty
                            <article class="az-results-empty">
                                <span class="az-results-empty__icon material-symbols-outlined" aria-hidden="true">event_busy</span>
                                <div>
                                    <span class="eyebrow">No exact availability</span>
                                    <h2>No exact match for those dates and filters</h2>
                                    <p>Remove a filter, change your dates, or broaden the destination to see more stays.</p>
                                </div>
                            </article>
                        @endforelse

                        @if($results->hasPages())
                            <nav class="reserva-search-pagination" aria-label="Search result pages">
                                {{ $results->links() }}
                            </nav>
                        @endif
                    </section>

                    <section data-results-pane="map" hidden>
                        <div
                            class="reserva-map-shell"
                            data-reserva-results-map
                            data-map-endpoint="{{ route('availability.map-cursor') }}"
                            data-map-page="{{ $results->currentPage() }}"
                            data-map-pages="{{ $results->lastPage() }}"
                            role="region"
                            aria-label="Interactive geographic map of the current result page"
                        ></div>
                        <p class="az-user-panel-subtitle" data-map-status role="status">Pins initially show the current result page. Load more verified map batches to explore every matching stay. Pan, zoom, or select Search this area to update filters. The accessible list remains available.</p>
                        <button type="button" class="button button-secondary" data-map-load-more @if($results->lastPage() <= 1) hidden @endif>Load more matching stays on map</button>
                    </section>
                </div>
            </div>
        </section>
    </main>

    <div
        id="reserva-search-filter-drawer"
        class="reserva-filter-drawer"
        data-drawer
        hidden
        aria-hidden="true"
        aria-labelledby="reserva-filter-drawer-title"
    >
        <div class="reserva-filter-drawer__panel">
            <header>
                <h2 id="reserva-filter-drawer-title">Filter stays</h2>
                <button type="button" class="reserva-favourite-button" data-drawer-close aria-label="Close filters">
                    <span class="material-symbols-outlined" aria-hidden="true">close</span>
                </button>
            </header>
            <form method="GET" action="{{ route('availability.results') }}">
                <input type="hidden" name="sort" value="{{ $filters['sort'] ?? 'recommended' }}">
                @include('public.bookings.partials.search-filters')
            </form>
        </div>
    </div>

    <script type="application/json" data-map-points>@json($mapPoints)</script>
</x-public-site.layout>
