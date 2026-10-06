<x-public-site.layout
    title="Book your stay | Reserva"
    description="Search live availability and book directly with Reserva."
>
    <main class="az-book-page">
        <section
            class="az-book-hero"
            aria-labelledby="az-book-title"
        >
            <div class="site-container">
                <header class="az-book-intro">
                    <span class="eyebrow">Direct booking</span>

                    <h1 id="az-book-title">
                        Find your next residence.
                    </h1>

                    <p>
                        Select your dates, guests, preferred location and
                        residence category. We will show currently available
                        stays that match your requirements.
                    </p>
                </header>

                <div class="az-book-grid">
                    <figure class="az-book-media">
                        <img
                            src="{{ asset('images/azari-hospitality-welcome.png') }}"
                            alt="Reserva hospitality interior"
                            width="1200"
                            height="1500"
                        >

                        <span
                            class="az-book-media__overlay"
                            aria-hidden="true"
                        ></span>

                        <figcaption class="az-book-media__copy">
                            <span>Reserva</span>

                            <strong>
                                Thoughtfully managed stays across our locations.
                            </strong>
                        </figcaption>
                    </figure>

                    <section
                        class="az-book-panel"
                        aria-labelledby="az-book-form-title"
                    >
                        <header class="az-book-panel__heading">
                            <span class="eyebrow">Check availability</span>

                            <h2 id="az-book-form-title">
                                Plan your stay
                            </h2>

                            <p>
                                Enter your stay details to view matching
                                hotels & residences and current direct-booking rates.
                            </p>
                        </header>

                        <form
                            class="availability-form az-book-form"
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
                                            min="{{ now()->toDateString() }}"
                                            value="{{ request('check_in', session('azari_stay_search.check_in')) }}"
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
                                            min="{{ now()->addDay()->toDateString() }}"
                                            value="{{ request('check_out', session('azari_stay_search.check_out')) }}"
                                            required
                                        >
                                    </div>

                                    <span
                                        class="field-error"
                                        data-error-for="check_out"
                                    ></span>
                                </div>
                            </div>

                            <div class="search-field">
                                <label for="adults">Adults</label>

                                <div class="input-shell">
                                    <span
                                        class="material-symbols-outlined"
                                        aria-hidden="true"
                                    >person</span>

                                    <input
                                        id="adults"
                                        name="adults"
                                        type="number"
                                        min="1"
                                        max="40"
                                        value="{{ max(1, (int) request('adults', session('azari_stay_search.adults', 1))) }}"
                                        required
                                    >
                                </div>
                            </div>

                            <div class="search-field">
                                <label for="children">Children</label>

                                <div class="input-shell">
                                    <span
                                        class="material-symbols-outlined"
                                        aria-hidden="true"
                                    >child_care</span>

                                    <input
                                        id="children"
                                        name="children"
                                        type="number"
                                        min="0"
                                        max="40"
                                        value="{{ max(0, (int) request('children', session('azari_stay_search.children', 0))) }}"
                                    >
                                </div>
                            </div>

                            <div class="search-field">
                                <label for="location_id">Location</label>

                                <div class="input-shell select-shell">
                                    <span
                                        class="material-symbols-outlined"
                                        aria-hidden="true"
                                    >location_on</span>

                                    <select
                                        id="location_id"
                                        name="location_id"
                                    >
                                        <option value="">
                                            All available locations
                                        </option>

                                        @foreach($locations as $location)
                                            <option
                                                value="{{ $location->id }}"
                                                @selected(
                                                    (string) request('location_id', session('azari_stay_search.location_id')) ===
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
                                                    (string) request('room_type_id', session('azari_stay_search.room_type_id')) ===
                                                    (string) $roomType->id
                                                )
                                            >
                                                {{ $roomType->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <input
                                type="hidden"
                                name="rooms"
                                value="{{ max(1, (int) request('rooms', session('azari_stay_search.rooms', 1))) }}"
                            >

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

                        <footer class="az-book-assurance">
                            <span
                                class="material-symbols-outlined"
                                aria-hidden="true"
                            >verified_user</span>

                            <p>
                                Live inventory, direct rates and secure booking
                                through Reserva.
                            </p>
                        </footer>
                    </section>
                </div>
            </div>
        </section>
    </main>
</x-public-site.layout>
