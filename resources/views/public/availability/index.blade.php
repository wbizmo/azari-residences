<x-public-site.layout
    title="Book your stay | Azari Residences"
    description="Search live availability across Azari Residences."
>
    <main class="az-book-page">
        <section class="az-book-hero" aria-labelledby="az-book-title">
            <div class="site-container">
                <div class="az-book-intro">
                    <span class="eyebrow">Direct booking</span>

                    <h1 id="az-book-title">
                        Find your next residence.
                    </h1>

                    <p>
                        Select your dates, guests, preferred location and
                        residence category. We will show only currently
                        available stays.
                    </p>
                </div>

                <div class="az-book-grid">
                    <div class="az-book-media">
                        <img
                            src="{{ asset('images/azari-hero.png') }}"
                            alt="Refined interior at an Azari Residence"
                            width="1200"
                            height="1500"
                        >

                        <div class="az-book-media__overlay" aria-hidden="true"></div>

                        <div class="az-book-media__copy">
                            <span>Azari Residences</span>

                            <strong>
                                Thoughtfully managed stays across our locations.
                            </strong>
                        </div>
                    </div>

                    <div class="az-book-panel">
                        <div class="az-book-panel__heading">
                            <span class="eyebrow">Check availability</span>

                            <h2>Plan your stay</h2>

                            <p>
                                Complete the search below to view matching
                                residences and current rates.
                            </p>
                        </div>

                        <form
                            class="az-book-form"
                            method="GET"
                            action="{{ route('availability.results') }}"
                            data-availability-form
                            novalidate
                        >
                            <div class="az-book-form__dates">
                                <div class="search-field">
                                    <label for="check_in">Check in</label>

                                    <div class="input-shell">
                                        <span
                                            class="material-symbols-outlined"
                                            aria-hidden="true"
                                        >calendar_today</span>

                                        <input
                                            id="check_in"
                                            name="check_in"
                                            type="date"
                                            value="{{ request('check_in') }}"
                                            min="{{ now()->toDateString() }}"
                                            required
                                        >
                                    </div>

                                    <span
                                        class="field-error"
                                        data-error-for="check_in"
                                    ></span>
                                </div>

                                <div class="search-field">
                                    <label for="check_out">Check out</label>

                                    <div class="input-shell">
                                        <span
                                            class="material-symbols-outlined"
                                            aria-hidden="true"
                                        >event_available</span>

                                        <input
                                            id="check_out"
                                            name="check_out"
                                            type="date"
                                            value="{{ request('check_out') }}"
                                            min="{{ now()->addDay()->toDateString() }}"
                                            required
                                        >
                                    </div>

                                    <span
                                        class="field-error"
                                        data-error-for="check_out"
                                    ></span>
                                </div>
                            </div>

                            <div
                                class="search-field guest-selector"
                                data-guest-selector
                            >
                                <span class="field-label">Guests</span>

                                <button
                                    class="input-shell guest-selector-trigger"
                                    type="button"
                                    data-guest-trigger
                                    aria-expanded="false"
                                >
                                    <span
                                        class="material-symbols-outlined"
                                        aria-hidden="true"
                                    >group</span>

                                    <span data-guest-summary>
                                        {{ max(1, (int) request('adults', 1)) }}
                                        {{ (int) request('adults', 1) === 1 ? 'adult' : 'adults' }}
                                        路
                                        {{ max(0, (int) request('children', 0)) }}
                                        {{ (int) request('children', 0) === 1 ? 'child' : 'children' }}
                                    </span>

                                    <span
                                        class="material-symbols-outlined guest-chevron"
                                        aria-hidden="true"
                                    >keyboard_arrow_down</span>
                                </button>

                                <div
                                    class="guest-selector-panel"
                                    data-guest-panel
                                    hidden
                                >
                                    @foreach([
                                        'adults' => [
                                            'Adults',
                                            max(1, (int) request('adults', 1)),
                                        ],
                                        'children' => [
                                            'Children',
                                            max(0, (int) request('children', 0)),
                                        ],
                                    ] as $key => [$label, $count])
                                        <div class="guest-row">
                                            <strong>{{ $label }}</strong>

                                            <div class="stepper">
                                                <button
                                                    type="button"
                                                    data-stepper="{{ $key }}"
                                                    data-direction="-1"
                                                    aria-label="Reduce {{ strtolower($label) }}"
                                                >
                                                    <span
                                                        class="material-symbols-outlined"
                                                        aria-hidden="true"
                                                    >remove</span>
                                                </button>

                                                <output data-count-for="{{ $key }}">
                                                    {{ $count }}
                                                </output>

                                                <button
                                                    type="button"
                                                    data-stepper="{{ $key }}"
                                                    data-direction="1"
                                                    aria-label="Increase {{ strtolower($label) }}"
                                                >
                                                    <span
                                                        class="material-symbols-outlined"
                                                        aria-hidden="true"
                                                    >add</span>
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach

                                    <button
                                        class="button button-primary button-block"
                                        type="button"
                                        data-guest-done
                                    >
                                        Done
                                    </button>
                                </div>

                                <input
                                    type="hidden"
                                    name="adults"
                                    value="{{ max(1, (int) request('adults', 1)) }}"
                                    data-guest-input="adults"
                                >

                                <input
                                    type="hidden"
                                    name="children"
                                    value="{{ max(0, (int) request('children', 0)) }}"
                                    data-guest-input="children"
                                >
                            </div>

                            <div class="search-field">
                                <label for="location_id">Location</label>

                                <div class="input-shell select-shell">
                                    <span
                                        class="material-symbols-outlined"
                                        aria-hidden="true"
                                    >location_on</span>

                                    <select id="location_id" name="location_id">
                                        <option value="">
                                            All available locations
                                        </option>

                                        @foreach($locations as $location)
                                            <option
                                                value="{{ $location->id }}"
                                                @selected(
                                                    (string) request('location_id') ===
                                                    (string) $location->id
                                                )
                                            >
                                                {{ $location->name }},
                                                {{ $location->city }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="search-field">
                                <label for="room_type_id">
                                    Residence category
                                </label>

                                <div class="input-shell select-shell">
                                    <span
                                        class="material-symbols-outlined"
                                        aria-hidden="true"
                                    >apartment</span>

                                    <select
                                        id="room_type_id"
                                        name="room_type_id"
                                    >
                                        <option value="">Any category</option>

                                        @foreach($roomTypes as $roomType)
                                            <option
                                                value="{{ $roomType->id }}"
                                                @selected(
                                                    (string) request('room_type_id') ===
                                                    (string) $roomType->id
                                                )
                                            >
                                                {{ $roomType->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <button
                                class="availability-submit az-book-submit"
                                type="submit"
                            >
                                <span>Search live availability</span>

                                <span
                                    class="material-symbols-outlined"
                                    aria-hidden="true"
                                >search</span>
                            </button>
                        </form>

                        <div class="az-book-assurance">
                            <span class="material-symbols-outlined" aria-hidden="true">
                                verified_user
                            </span>

                            <p>
                                Live inventory, direct rates and secure booking
                                through Azari Residences.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
</x-public-site.layout>
