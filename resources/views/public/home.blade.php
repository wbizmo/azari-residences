

<x-public-site.layout
    title="Resavar | Home"
    :description="$content['hero_body'] ?? 'Luxury serviced apartments by Resavar.'"
>

    @include('public.partials.promotion-popup')

    <section class="azari-home-hero" aria-labelledby="azari-home-hero-title">
        <img
            src="{{ asset('images/resavar-hero.png') }}"
            alt="Luxury Resavar serviced apartment interior"
            class="azari-home-hero__image"
            width="2048"
            height="1152"
            fetchpriority="high"
        >

        <div class="azari-home-hero__overlay" aria-hidden="true"></div>

        <div class="site-container azari-home-hero__container">
            <div class="azari-home-hero__content">
                <span class="azari-home-hero__eyebrow">
                    {{ $content['hero_eyebrow'] ?? 'Premium serviced hotels & residences' }}
                </span>

                <h1 id="azari-home-hero-title">
                    {{ $content['hero_title'] ?? 'Exceptional stays, thoughtfully managed.' }}
                </h1>

                <p>
                    {{ $content['hero_body'] ?? 'Discover carefully selected serviced apartments and private hotels & residences designed around comfort, privacy and dependable hospitality.' }}
                </p>

                <div class="azari-home-hero__actions">
                    <a
                        href="{{ route('availability.index') }}"
                        class="button azari-home-hero__primary"
                    >
                        <span>Check availability</span>
                    </a>

                    <a
                        href="{{ route('bookings.verify') }}"
                        class="button azari-home-hero__secondary"
                    >
                        <span
                            class="material-symbols-outlined"
                            aria-hidden="true"
                        >verified</span>

                        <span>Verify a booking</span>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="availability-section" id="availability">
        <div class="site-container">
            <div class="availability-card">
                <div class="availability-heading">
                    <span class="eyebrow">Direct booking</span>
                    <h2>Find your residence</h2>
                    <p>
                        Search by dates, guests and residence type.
                    </p>
                </div>

                <form
                    class="availability-form"
                    method="GET"
                    action="{{ route('availability.results') }}"
                    data-availability-form
                    novalidate
                >

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
                                value="{{ session('azari_stay_search.check_in') }}"
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
                                value="{{ session('azari_stay_search.check_out') }}"
                                required
                            >
                        </div>

                        <span
                            class="field-error"
                            data-error-for="check_out"
                        ></span>
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
                                1 adult · 0 children
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
                                'adults' => ['Adults', 1],
                                'children' => ['Children', 0],
                            ] as $key => [$label, $count])
                                <div class="guest-row">
                                    <div>
                                        <strong>{{ $label }}</strong>
                                    </div>

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
                            value="{{ max(1, (int) session('azari_stay_search.adults', 1)) }}"
                            data-guest-input="adults"
                        >

                        <input
                            type="hidden"
                            name="children"
                            value="{{ max(0, (int) session('azari_stay_search.children', 0)) }}"
                            data-guest-input="children"
                        >
                    </div>

                    <div class="search-field">
                        <label for="location_id">Location</label>

                        <div class="input-shell select-shell">
                            <span class="material-symbols-outlined" aria-hidden="true">location_on</span>

                            <select id="location_id" name="location_id">
                                <option value="">All available locations</option>
                                @foreach($locations as $location)
                                    <option value="{{ $location->id }}">{{ $location->name }}, {{ $location->city }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="search-field">
                        <label for="room_type_id">Residence type</label>

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
                                    <option value="{{ $roomType->id }}">{{ $roomType->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <button
                        class="availability-submit"
                        type="submit"
                    >
                        <span>Search availability</span>

                        <span
                            class="material-symbols-outlined"
                            aria-hidden="true"
                        >search</span>
                    </button>
                </form>
            </div>
        </div>
    </section>

    <section class="intro-section" id="about">
        <div class="site-container intro-grid">
            <div class="intro-heading">
                <span class="eyebrow">Across Our Locations</span>

                <h2>
                    A considered collection of hotels & residences.
                </h2>
            </div>

            <div class="intro-copy">
                <p class="intro-lead">
                    Every property is carefully selected, professionally
                    prepared and continuously managed to a consistent
                    hospitality standard.
                </p>

                <p>
                    Each Resavar residence combines the privacy and comfort of a
                    personal home with the thoughtful service expected from
                    premium hospitality. From carefully furnished interiors and
                    reliable housekeeping to responsive guest support, every
                    detail is coordinated to make arrival and daily living feel
                    effortless.
                </p>

                <p>
                    Whether you are travelling for business, relocating,
                    planning an extended visit or simply seeking a refined place
                    to stay, our hotels & residences are prepared to provide comfort,
                    confidence and a dependable experience from check-in through
                    departure.
                </p>
            </div>
        </div>
    </section>

    <section class="residences-section" id="residences">
        <div class="site-container">
            <div class="section-heading azari-featured-heading">
                <div>
                    <span class="eyebrow">The collection</span>

                    <h2>
                        {{ $content['featured_title'] ?? 'Featured Hotels & Residences' }}
                    </h2>
                </div>

                <a
                    href="{{ url('/residences') }}"
                    class="azari-view-all-residences"
                >
                    <span>View all hotels & residences</span>

                    <span
                        class="material-symbols-outlined"
                        aria-hidden="true"
                    >arrow_forward</span>
                </a>
            </div>

            <div class="residence-grid">
                @forelse($featuredResidences as $property)
                    <article class="residence-card">
                        <a
                            href="{{ route('properties.show', $property) }}"
                            class="residence-image"
                        >
                            @if($property->cover_image)
                                <img
                                    src="{{ Storage::url($property->cover_image) }}"
                                    alt="{{ $property->name }}"
                                    loading="lazy"
                                >
                            @else
                                <div class="residence-image-placeholder">
                                    <span
                                        class="material-symbols-outlined"
                                        aria-hidden="true"
                                    >apartment</span>
                                </div>
                            @endif

                            <span class="residence-type">
                                {{ $property->property_type }}
                            </span>
                        </a>

                        <div class="residence-content">
                            <span class="residence-location">
                                <span
                                    class="material-symbols-outlined"
                                    aria-hidden="true"
                                >location_on</span>

                                {{ $property->location }},
                                {{ $property->country }}
                            </span>

                            <h3>{{ $property->name }}</h3>

                            <div class="property-card-facts">
                                <span>
                                    {{ $property->bedrooms }} bedrooms
                                </span>

                                <span>
                                    {{ $property->bathrooms }} bathrooms
                                </span>

                                <span>
                                    {{ $property->max_guests }} guests
                                </span>
                            </div>

                            <ul class="amenity-list">
                                @foreach($property->amenities->take(4) as $amenity)
                                    <li>
                                        <span
                                            class="material-symbols-outlined"
                                            aria-hidden="true"
                                        >{{ $amenity->icon }}</span>

                                        {{ $amenity->name }}
                                    </li>
                                @endforeach
                            </ul>

                            <div class="residence-footer">
                                <span>
                                    From

                                    <strong>
                                        {{ $property->currency }}
                                        {{ number_format($property->nightly_rate) }}
                                    </strong>

                                    / night
                                </span>

                                <a
                                    href="{{ route('properties.show', $property) }}"
                                    aria-label="View {{ $property->name }}"
                                >
                                    <span
                                        class="material-symbols-outlined"
                                        aria-hidden="true"
                                    >arrow_outward</span>
                                </a>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="production-empty-state">
                        No featured hotels & residences are currently published.
                    </div>
                @endforelse
            </div>

            <div class="azari-featured-mobile-action">
                <a
                    href="{{ route('public.apartments') }}"
                    class="azari-view-all-residences"
                >
                    <span>View all hotels & residences</span>

                    <span
                        class="material-symbols-outlined"
                        aria-hidden="true"
                    >arrow_forward</span>
                </a>
            </div>
        </div>
    </section>

    <section class="services-section" id="services">
        <div class="site-container">
            <div class="section-heading section-heading-light">
                <div>
                    <span class="eyebrow">Guest services</span>

                    <h2>
                        {{ $content['services_title'] ?? 'Thoughtful services for every stay.' }}
                    </h2>
                </div>
            </div>

            <div class="service-grid">
                @foreach([
                    [
                        'concierge',
                        'Private concierge',
                        'Personalised assistance before arrival, throughout your stay and until departure. Our concierge team can coordinate reservations, local recommendations and guest requests to ensure a seamless hospitality experience.',
                    ],
                    [
                        'cleaning_services',
                        'Housekeeping',
                        'Professional housekeeping services maintain every residence to hotel-quality standards. Fresh linens, meticulous cleaning and scheduled servicing help every stay remain comfortable from the first night to the last.',
                    ],
                    [
                        'restaurant',
                        'Dining reservations',
                        'Enjoy access to carefully selected restaurants and local dining experiences. Our team can assist with reservations, recommendations and special arrangements tailored to your preferences.',
                    ],
                    [
                        'airport_shuttle',
                        'Airport transfers',
                        'Reliable airport pickup and drop-off services arranged through trusted transportation partners, providing a comfortable journey between the airport and your residence.',
                    ],
                ] as [$icon, $title, $description])
                    <article class="service-card">
                        <span
                            class="material-symbols-outlined service-icon"
                            aria-hidden="true"
                        >{{ $icon }}</span>

                        <h3>{{ $title }}</h3>

                        <p>{{ $description }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <!-- RESAVAR APP DOWNLOAD START -->
    <section
        class="azari-app-section"
        aria-labelledby="azari-app-title"
    >
        <style>
            .azari-app-section {
                position: relative;
                overflow: hidden;
                padding: clamp(64px, 8vw, 112px) 0;
                background:
                    radial-gradient(
                        circle at 12% 18%,
                        rgba(5, 32, 88, .13),
                        transparent 34%
                    ),
                    radial-gradient(
                        circle at 92% 82%,
                        rgba(5, 32, 88, .12),
                        transparent 36%
                    ),
                    #FFFFFF;
            }

            .azari-app-card {
                position: relative;
                display: grid;
                grid-template-columns: minmax(0, 1fr) minmax(320px, .9fr);
                align-items: center;
                min-height: 510px;
                overflow: hidden;
                border: 1px solid rgba(5, 32, 88, .12);
                border-radius: clamp(24px, 3vw, 38px);
                background: #FFFFFF;
                box-shadow:
                    0 30px 80px rgba(0, 0, 0, .10),
                    0 8px 24px rgba(0, 0, 0, .05);
            }

            .azari-app-copy {
                position: relative;
                z-index: 2;
                padding: clamp(36px, 6vw, 76px);
            }

            .azari-app-kicker {
                display: inline-flex;
                align-items: center;
                gap: 9px;
                margin-bottom: 20px;
                color: #052058;
                font-size: 12px;
                font-weight: 700;
                letter-spacing: .16em;
                text-transform: uppercase;
            }

            .azari-app-kicker::before {
                content: "";
                width: 28px;
                height: 1px;
                background: currentColor;
            }

            .azari-app-title {
                max-width: 720px;
                margin: 0;
                color: #000000;
                font-size: clamp(34px, 4.7vw, 62px);
                line-height: 1.03;
                letter-spacing: -.035em;
            }

            .azari-app-description {
                max-width: 620px;
                margin: 24px 0 0;
                color: #052058;
                font-size: clamp(16px, 1.5vw, 19px);
                line-height: 1.75;
            }

            .azari-app-devices {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                margin: 28px 0 30px;
            }

            .azari-app-device {
                display: inline-flex;
                align-items: center;
                gap: 7px;
                padding: 9px 13px;
                border: 1px solid rgba(5, 32, 88, .12);
                border-radius: 999px;
                background: rgba(255, 255, 255, .72);
                color: #052058;
                font-size: 13px;
                font-weight: 600;
            }

            .azari-app-device .material-symbols-outlined {
                font-size: 18px;
            }

            .azari-app-actions {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 14px;
            }

            .azari-google-play-link {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                flex: 0 0 auto;
                border-radius: 10px;
                text-decoration: none;
                transition:
                    transform .2s ease,
                    opacity .2s ease;
            }

            .azari-google-play-link:hover {
                transform: translateY(-2px);
            }

            .azari-google-play-link:focus-visible {
                outline: 3px solid rgba(5, 32, 88, .42);
                outline-offset: 4px;
            }

            .azari-google-play-link img {
                display: block;
                width: auto;
                height: 58px;
                object-fit: contain;
            }

            .azari-app-note {
                display: block;
                margin-top: 14px;
                color: #052058;
                font-size: 12px;
                line-height: 1.55;
            }

            .azari-app-visual {
                position: relative;
                align-self: stretch;
                min-height: 510px;
                overflow: hidden;
            }

            .azari-app-visual::before {
                content: "";
                position: absolute;
                z-index: 1;
                inset: 0;
                background:
                    linear-gradient(
                        90deg,
                        #FFFFFF 0%,
                        rgba(255, 255, 255, .75) 13%,
                        rgba(255, 255, 255, 0) 38%
                    );
                pointer-events: none;
            }

            .azari-app-visual img {
                width: 100%;
                height: 100%;
                min-height: 510px;
                object-fit: cover;
                object-position: center;
                display: block;
            }

            .azari-app-floating {
                position: absolute;
                z-index: 2;
                right: 24px;
                bottom: 24px;
                display: flex;
                align-items: center;
                gap: 12px;
                max-width: calc(100% - 48px);
                padding: 14px 16px;
                border: 1px solid rgba(255, 255, 255, .44);
                border-radius: 18px;
                background: rgba(0, 0, 0, .78);
                color: #FFFFFF;
                box-shadow: 0 15px 45px rgba(0, 0, 0, .22);
                backdrop-filter: blur(15px);
                -webkit-backdrop-filter: blur(15px);
            }

            .azari-app-floating .material-symbols-outlined {
                font-size: 25px;
            }

            .azari-app-floating strong {
                display: block;
                font-size: 13px;
                line-height: 1.2;
            }

            .azari-app-floating span:last-child {
                display: block;
                margin-top: 3px;
                color: rgba(255, 255, 255, .73);
                font-size: 11px;
            }

            @media (max-width: 900px) {
                .azari-app-card {
                    grid-template-columns: 1fr;
                }

                .azari-app-visual {
                    min-height: 390px;
                    order: -1;
                }

                .azari-app-visual img {
                    min-height: 390px;
                }

                .azari-app-visual::before {
                    background:
                        linear-gradient(
                            0deg,
                            #FFFFFF 0%,
                            rgba(255, 255, 255, .82) 10%,
                            rgba(255, 255, 255, 0) 36%
                        );
                }
            }

            @media (max-width: 600px) {
                .azari-app-section {
                    padding: 48px 0;
                }

                .azari-app-card {
                    border-radius: 24px;
                }

                .azari-app-copy {
                    padding: 30px 24px 34px;
                }

                .azari-app-visual,
                .azari-app-visual img {
                    min-height: 320px;
                }

                .azari-app-actions {
                    align-items: flex-start;
                    flex-direction: column;
                }

                .azari-google-play-link img {
                    height: 56px;
                }
            }
        </style>

        <div class="site-container">
            <div class="azari-app-card">
                <div class="azari-app-copy">
                    <span class="azari-app-kicker">
                        The Resavar App
                    </span>

                    <h2
                        class="azari-app-title"
                        id="azari-app-title"
                    >
                        Your Resavar experience, wherever you are.
                    </h2>

                    <p class="azari-app-description">
                        Download the Resavar app on Google Play
                        for convenient access to your bookings, guest account
                        and Resavar experience on Android.
                    </p>

                    <div
                        class="azari-app-devices"
                        aria-label="Available platform"
                    >
                        <span class="azari-app-device">
                            <span
                                class="material-symbols-outlined"
                                aria-hidden="true"
                            >android</span>
                            Android
                        </span>
                    </div>

                    <div class="azari-app-actions">
                        <a
                            href="https://play.google.com/store/apps/details?id=com.azariresidences.app"
                            class="azari-google-play-link"
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label="Get Resavar on Google Play"
                        >
                            <img
                                src="{{ asset('images/google-play-badge.png') }}"
                                alt="Get it on Google Play"
                                width="646"
                                height="250"
                                loading="lazy"
                                decoding="async"
                            >
                        </a>
                    </div>

                    <span class="azari-app-note">
                        Available now on Google Play for Android.
                    </span>
                </div>

                <div class="azari-app-visual">
                    <img
                        src="{{ asset('images/azari-hospitality-welcome.png') }}"
                        alt="Resavar hospitality experience"
                        loading="lazy"
                        decoding="async"
                    >

                    <div class="azari-app-floating">
                        <span
                            class="material-symbols-outlined"
                            aria-hidden="true"
                        >devices</span>

                        <div>
                            <strong>Resavar on Android.</strong>
                            <span>Available on Google Play</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- RESAVAR APP DOWNLOAD END -->

</x-public-site.layout>
