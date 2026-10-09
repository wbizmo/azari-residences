@php
    $images = collect([$property->cover_image])
        ->merge($property->gallery ?? [])
        ->filter()
        ->unique()
        ->values();

    $mapUrl = null;
    if ($property->latitude !== null && $property->longitude !== null) {
        $lat = (float) $property->latitude;
        $lng = (float) $property->longitude;
        $delta = 0.012;
        $mapUrl = 'https://www.openstreetmap.org/export/embed.html?'.http_build_query([
            'bbox' => ($lng - $delta).','.($lat - $delta).','.($lng + $delta).','.($lat + $delta),
            'layer' => 'mapnik',
            'marker' => $lat.','.$lng,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'LodgingBusiness',
        'name' => $property->name,
        'description' => $property->short_description ?: $property->description,
        'url' => route('properties.show', $property),
        'address' => array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => $property->formatted_address ?: $property->address_line_1,
            'addressLocality' => $property->address_city ?: $property->location,
            'addressRegion' => $property->address_region,
            'postalCode' => $property->address_postal_code,
            'addressCountry' => $property->address_country_code ?: $property->country,
        ]),
        'currenciesAccepted' => $property->currency,
        'checkinTime' => $property->check_in_time,
        'checkoutTime' => $property->check_out_time,
        'image' => $images->map(fn ($image) => \App\Support\ResponsiveImage::originalUrl($image))->values()->all(),
        'amenityFeature' => $property->amenities->map(fn ($amenity) => ['@type'=>'LocationFeatureSpecification','name'=>$amenity->name,'value'=>true])->values()->all(),
        // Only issue price/availability offers after canonical stay dates and
        // occupancy are quoted. A base nightly rate is not a bookable offer.
    ];

    if ($property->latitude !== null && $property->longitude !== null) {
        $schema['geo'] = [
            '@type' => 'GeoCoordinates',
            'latitude' => (float) $property->latitude,
            'longitude' => (float) $property->longitude,
        ];
    }

    if (($reviewSummary['count'] ?? 0) > 0) {
        $schema['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => $reviewSummary['overall'],
            'reviewCount' => $reviewSummary['count'],
            'bestRating' => 5,
            'worstRating' => 1,
        ];
    }
@endphp

<x-public-site.layout :title="$property->name" :description="$property->short_description">
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>

    <main
        class="reserva-property-page"
        data-resavar-property="{{ $property->id }}"
        data-property-name="{{ $property->name }}"
        data-property-url="{{ route('properties.show', $property) }}"
        data-property-location="{{ $property->locationRecord?->name ?? $property->location }}"
        data-property-image="{{ $property->cover_image ? Storage::url($property->cover_image) : asset('images/azari-residence-fallback.png') }}"
    >
        <div class="site-container">
            <nav class="reserva-property-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('home') }}">Home</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('availability.index') }}">Stays</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page">{{ $property->name }}</span>
            </nav>

            <header class="reserva-property-heading">
                <div>
                    <span class="eyebrow">
                        {{ Str::headline($property->property_type) }}
                        · {{ $property->locationRecord?->name ?? $property->location }}
                    </span>
                    <h1>{{ $property->name }}</h1>
                    <p>{{ $property->short_description }}</p>

                    @if(($reviewSummary['count'] ?? 0) > 0)
                        <div class="reserva-review-score">
                            <strong>{{ number_format((float) $reviewSummary['overall'], 1) }}</strong>
                            <span>{{ $reviewSummary['count'] }} verified {{ Str::plural('review', $reviewSummary['count']) }}</span>
                        </div>
                    @endif
                </div>

                <div class="reserva-property-heading__actions">
                    @auth
                        <form method="POST" action="{{ route('user.favourites.toggle', $property) }}">
                            @csrf
                            <button class="button button-secondary" type="submit">
                                <span class="material-symbols-outlined" aria-hidden="true">{{ $isFavourite ? 'favorite' : 'favorite_border' }}</span>
                                {{ $isFavourite ? 'Saved' : 'Save' }}
                            </button>
                        </form>
                    @else
                        <button
                            class="button button-secondary resavar-local-favourite"
                            type="button"
                            aria-pressed="false"
                            data-resavar-local-favourite="{{ $property->id }}"
                            data-property-name="{{ $property->name }}"
                            data-property-url="{{ route('properties.show', $property) }}"
                        >
                            <span class="material-symbols-outlined" aria-hidden="true">favorite_border</span>
                            <span data-favourite-label>Save</span>
                        </button>
                    @endauth

                    <button
                        class="button button-secondary"
                        type="button"
                        data-reserva-share="{{ route('properties.show', $property) }}"
                        data-share-title="{{ $property->name }}"
                    >
                        <span class="material-symbols-outlined" aria-hidden="true">share</span>
                        Share
                    </button>
                </div>
            </header>

            @if($images->isNotEmpty())
                <section class="reserva-property-gallery" aria-label="{{ $property->name }} photo gallery">
                    @foreach($images->take(5) as $index => $image)
                        <button type="button" data-modal-open="reserva-property-gallery-modal" aria-label="Open full photo gallery">
                            <x-public.responsive-image :path="$image" :alt="$property->name.' photo '.($index + 1)" :priority="$index === 0" width="1200" height="800" sizes="(max-width: 760px) 100vw, 60vw" />
                            @if($index === min(4, $images->count() - 1))
                                <span class="reserva-gallery-count">{{ $images->count() }} photos</span>
                            @endif
                        </button>
                    @endforeach
                </section>
            @endif

            <div class="reserva-property-content">
                <div class="reserva-property-main">
                    <section class="reserva-property-section">
                        <h2>Stay highlights</h2>
                        <div class="reserva-property-highlights">
                            <div class="reserva-highlight"><span class="material-symbols-outlined" aria-hidden="true">group</span><span>Up to {{ $property->max_guests }} guests</span></div>
                            <div class="reserva-highlight"><span class="material-symbols-outlined" aria-hidden="true">bed</span><span>{{ $property->bedrooms }} {{ Str::plural('bedroom', $property->bedrooms) }}</span></div>
                            <div class="reserva-highlight"><span class="material-symbols-outlined" aria-hidden="true">bathtub</span><span>{{ $property->bathrooms }} {{ Str::plural('bathroom', $property->bathrooms) }}</span></div>
                            @if($property->bed_configuration)
                                <div class="reserva-highlight"><span class="material-symbols-outlined" aria-hidden="true">king_bed</span><span>{{ $property->bed_configuration }}</span></div>
                            @endif
                        </div>
                    </section>

                    <section class="reserva-property-section">
                        <h2>About this stay</h2>
                        <div class="prose">{!! nl2br(e($property->description)) !!}</div>
                    </section>

                    @if($property->publicVerifiedClaims->isNotEmpty())
                        <section class="reserva-property-section" aria-label="Staff-verified property facts">
                            <h2>Independently verified details</h2>
                            <p>These specific facts have been checked by Resavar staff within their verification period. Other property details remain property-supplied.</p>
                            <ul class="reserva-verified-property-claims">
                                @foreach($property->publicVerifiedClaims as $verifiedClaim)
                                    <li>{{ \App\Models\PropertyVerifiedClaim::TYPES[$verifiedClaim->claim_type] ?? 'Verified property detail' }}
                                        <small>Checked {{ $verifiedClaim->verified_at->format('M Y') }}</small>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    @if($property->amenities->isNotEmpty())
                        <section class="reserva-property-section">
                            <h2>Amenities</h2>
                            <p class="reserva-property-fact-disclosure">These amenities are listed by the property. Details that are not independently verified should be confirmed before booking.</p>
                            <div class="reserva-amenity-list">
                                @foreach($property->amenities as $amenity)
                                    <span>
                                        <span class="material-symbols-outlined" aria-hidden="true">{{ $amenity->icon ?: 'check_circle' }}</span>
                                        {{ $amenity->name }}
                                    </span>
                                @endforeach
                            </div>

                            @if($property->accessibility_notes)
                                <div class="reserva-policy-card" style="margin-top:16px">
                                    <strong>Accessibility</strong>
                                    <p>{{ $property->accessibility_notes }}</p>
                                </div>
                            @endif
                        </section>
                    @endif

                    <section class="reserva-property-section" id="rates">
                        <div class="section-heading">
                            <div>
                                <span class="eyebrow">{{ $hasStayDates ? 'Live availability' : 'Rates' }}</span>
                                <h2>{{ $hasStayDates ? 'Choose your accommodation and rate' : 'Accommodation and rate options' }}</h2>
                            </div>
                            @if($hasStayDates)
                                <p>{{ Carbon\CarbonImmutable::parse($searchState['check_in'])->format('j M Y') }} to {{ Carbon\CarbonImmutable::parse($searchState['check_out'])->format('j M Y') }}</p>
                            @endif
                        </div>

                        @if($rateOptions->isNotEmpty())
                            <div class="reserva-rate-table">
                                @foreach($rateOptions as $option)
                                    @php
                                        $type = $option['type'];
                                        $plan = $option['plan'];
                                        $quote = $option['quote'] ?? null;
                                    @endphp

                                    <article class="reserva-rate-row">
                                        <div class="reserva-rate-row__name">
                                            <strong>{{ $type->name }}</strong>
                                            <small>{{ $plan->name }}</small>
                                            @if($type->bed_configuration)
                                                <small>{{ $type->bed_configuration }}</small>
                                            @endif
                                        </div>

                                        <div>
                                            <strong>Sleeps {{ $type->max_guests }}</strong>
                                            <small>{{ $type->bedrooms }} {{ Str::plural('bedroom', $type->bedrooms) }}</small>
                                        </div>

                                        <div>
                                            <strong>{{ $plan->is_refundable ? 'Refundable' : 'Non-refundable' }}</strong>
                                            <small>{{ $plan->cancellationPolicy?->name ?? 'Rate terms apply' }}</small>
                                        </div>

                                        <div>
                                            <strong>{{ $plan->paymentPolicy?->name ?? 'Standard payment' }}</strong>
                                            @if($plan->meal_plan)
                                                <small>{{ $plan->meal_plan }}</small>
                                            @endif
                                            @if($plan->inclusions)
                                                <small>{{ collect($plan->inclusions)->take(2)->join(', ') }}</small>
                                            @endif
                                        </div>

                                        <div class="reserva-rate-row__price">
                                            @if($quote)
                                                <strong>{{ \App\Support\Money::format($quote['total'], $quote['currency']) }}</strong>
                                                <small>Total for {{ $quote['nights'] }} {{ Str::plural('night', $quote['nights']) }} · {{ $quote['quantity'] ?? 1 }} {{ Str::plural('room', $quote['quantity'] ?? 1) }}</small>
                                                @if(($quote['discount_total'] ?? 0) > 0)
                                                    <small>Discount included: −{{ \App\Support\Money::format($quote['discount_total'], $quote['currency']) }}</small>
                                                @endif
                                                @if(($quote['fee_total'] ?? 0) > 0)
                                                    <small>Includes fees: {{ \App\Support\Money::format($quote['fee_total'], $quote['currency']) }}</small>
                                                @endif
                                                @if(($quote['tax_total'] ?? 0) > 0)
                                                    <small>Includes taxes: {{ \App\Support\Money::format($quote['tax_total'], $quote['currency']) }}</small>
                                                @endif
                                                @if(($quote['security_deposit'] ?? 0) > 0)
                                                    <small>Security deposit (separate from booking total; check property's collection terms): {{ \App\Support\Money::format($quote['security_deposit'], $quote['currency']) }}</small>
                                                @endif
                                                @if(($quote['policy']['cancellation']['free_cancel_hours'] ?? null) !== null && $plan->is_refundable)
                                                    <small>Free cancellation up to {{ (int) $quote['policy']['cancellation']['free_cancel_hours'] }} hours before arrival, subject to the full policy</small>
                                                @endif
                                                @if(($option['remaining'] ?? null) !== null && $option['remaining'] <= 5)
                                                    <small>{{ $option['remaining'] }} {{ Str::plural('unit', $option['remaining']) }} left</small>
                                                @endif
                                            @else
                                                <strong>{{ \App\Support\Money::format(($option['from_rate'] ?? $type->base_rate), $type->currency) }}</strong>
                                                <small>From per night</small>
                                            @endif
                                        </div>

                                        <div>
                                            @if($quote)
                                                <form method="POST" action="{{ route('azari.availability.hold', $property) }}">
                                                    @csrf
                                                    <input type="hidden" name="check_in" value="{{ $searchState['check_in'] }}">
                                                    <input type="hidden" name="check_out" value="{{ $searchState['check_out'] }}">
                                                    <input type="hidden" name="adults" value="{{ $searchState['adults'] ?? 1 }}">
                                                    <input type="hidden" name="children" value="{{ $searchState['children'] ?? 0 }}">
                                                    <input type="hidden" name="rooms" value="{{ $searchState['rooms'] ?? 1 }}">
                                                    <input type="hidden" name="accommodation_type_id" value="{{ $type->id }}">
                                                    <input type="hidden" name="rate_plan_id" value="{{ $plan->id }}">
                                                    <button class="button button-primary" type="submit">Choose</button>
                                                </form>
                                            @else
                                                <a
                                                    class="button button-primary"
                                                    href="{{ route('availability.property', [
                                                        'property' => $property,
                                                        'accommodation_type_id' => $type->id,
                                                        'rate_plan_id' => $plan->id,
                                                    ]) }}"
                                                >
                                                    Check dates
                                                </a>
                                            @endif
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        @else
                            <div class="az-results-empty">
                                <div>
                                    <h3>No rate is available for the selected stay</h3>
                                    <p>Try different dates or guest counts.</p>
                                    <a class="button button-secondary" href="{{ route('availability.property', $property) }}">Change dates</a>
                                </div>
                            </div>
                        @endif
                    </section>

                    <section class="reserva-property-section">
                        <h2>House rules and policies</h2>
                        <div class="reserva-policy-grid">
                            <div class="reserva-policy-card">
                                <strong>Check-in</strong>
                                <p>{{ $property->check_in_time ?: 'See booking confirmation' }}</p>
                                @if($property->check_in_instructions)<small>{{ $property->check_in_instructions }}</small>@endif
                            </div>
                            <div class="reserva-policy-card">
                                <strong>Check-out</strong>
                                <p>{{ $property->check_out_time ?: 'See booking confirmation' }}</p>
                                @if($property->check_out_instructions)<small>{{ $property->check_out_instructions }}</small>@endif
                            </div>
                            @foreach([
                                'Children' => $property->children_policy,
                                'Pets' => $property->pet_policy,
                                'Smoking' => $property->smoking_policy,
                                'Parties' => $property->party_policy,
                            ] as $label => $policy)
                                @if($policy)
                                    <div class="reserva-policy-card">
                                        <strong>{{ $label }}</strong>
                                        <p>{{ $policy }}</p>
                                    </div>
                                @endif
                            @endforeach
                        </div>

                        @if($property->house_rules)
                            <ul>
                                @foreach($property->house_rules as $rule)
                                    <li>{{ is_array($rule) ? ($rule['text'] ?? '') : $rule }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </section>

                    <section class="reserva-property-section">
                        <h2>Location</h2>
                        <p>{{ $property->formatted_address ?: collect([$property->location, $property->country])->filter()->join(', ') }}</p>

                        @if($mapUrl)
                            <div class="reserva-property-map">
                                <iframe
                                    src="{{ $mapUrl }}"
                                    title="Map showing {{ $property->name }}"
                                    loading="lazy"
                                    referrerpolicy="no-referrer"
                                ></iframe>
                            </div>
                        @endif

                        @if($property->pointsOfInterest->isNotEmpty())
                            <h3>Nearby</h3>
                            <div class="reserva-poi-grid">
                                @foreach($property->pointsOfInterest as $poi)
                                    <div class="reserva-poi-card">
                                        <strong>{{ $poi->name }}</strong>
                                        <span>{{ $poi->category }}</span>
                                        @if($poi->distance_km !== null)
                                            <small>{{ number_format((float) $poi->distance_km, 1) }} km away</small>
                                        @elseif($poi->walking_minutes)
                                            <small>{{ $poi->walking_minutes }} min walk</small>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </section>

                    @if($property->host_name || $property->host_description)
                        <section class="reserva-property-section">
                            <h2>Host or property manager</h2>
                            @if($property->host_name)<strong>{{ $property->host_name }}</strong>@endif
                            @if($property->host_description)<p>{{ $property->host_description }}</p>@endif
                        </section>
                    @endif

                    @if(($reviewSummary['count'] ?? 0) > 0)
                        <section class="reserva-property-section" id="reviews">
                            <h2>Verified guest reviews</h2>
                            <div class="reserva-review-summary">
                                <div class="reserva-review-summary__score">{{ number_format((float) $reviewSummary['overall'], 1) }}</div>
                                <div>
                                    <strong>{{ $reviewSummary['count'] }} verified {{ Str::plural('stay review', $reviewSummary['count']) }}</strong>
                                    <p>Scores come only from eligible completed stays.</p>
                                </div>
                            </div>

                            @if(($reviewSummary['categories'] ?? []) !== [])
                                <div class="reserva-review-categories">
                                    @foreach($reviewSummary['categories'] as $category => $score)
                                        <div class="reserva-review-category">
                                            <span>{{ Str::headline($category) }}</span>
                                            <strong>{{ number_format((float) $score, 1) }}</strong>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <div class="reserva-review-list">
                                @foreach($property->reviews as $review)
                                    <article class="reserva-review-card">
                                        <div class="reserva-review-card__meta">
                                            <strong>{{ $review->user?->name ? Str::before($review->user->name, ' ') : 'Verified guest' }}</strong>
                                            <span>{{ $review->created_at->format('M Y') }} · {{ $review->rating }}/5</span>
                                        </div>
                                        @if($review->title)<h3>{{ $review->title }}</h3>@endif
                                        @if($review->positive_feedback)<p><strong>Liked:</strong> {{ $review->positive_feedback }}</p>@endif
                                        @if($review->negative_feedback)<p><strong>Could be better:</strong> {{ $review->negative_feedback }}</p>@endif
                                        <p>{{ $review->body }}</p>
                    <div class="reserva-review-helpful">
                        <span>{{ number_format((int) ($review->helpful_votes_count ?? 0)) }} found this helpful</span>
                        @if(auth()->check() && auth()->user()->hasVerifiedEmail()
                            && (int) auth()->id() !== (int) $review->user_id
                            && (int) auth()->id() !== (int) $property->owner_id)
                            <form method="POST" action="{{ route('user.reviews.helpful', $review) }}">
                                @csrf
                                <button class="button button-secondary" type="submit" aria-label="Mark review as helpful">Helpful</button>
                            </form>
                        @endif
                    </div>
                                        @if($review->admin_reply)
                                            <div class="reserva-management-reply">
                                                <strong>Resavar response</strong>
                                                <p>{{ $review->admin_reply }}</p>
                                            </div>
                                        @endif
                                        @if($review->owner_reply && $review->owner_reply_status === 'approved')
                                            <div class="reserva-management-reply">
                                                <strong>Property response</strong>
                                                <p>{{ $review->owner_reply }}</p>
                                                @if($review->owner_replied_at)<small>{{ $review->owner_replied_at->format('M Y') }}</small>@endif
                                            </div>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                            <p style="margin-top:16px">
                                <a class="button button-secondary" href="{{ route('properties.reviews', $property) }}">
                                    Browse all {{ number_format($reviewSummary['count']) }} verified guest reviews
                                </a>
                            </p>
                        </section>
                    @endif

                    @if($property->faqs)
                        <section class="reserva-property-section">
                            <h2>Frequently asked questions</h2>
                            <div class="reserva-faq-list">
                                @foreach($property->faqs as $faq)
                                    @if(is_array($faq) && filled($faq['question'] ?? null))
                                        <details>
                                            <summary>{{ $faq['question'] }}</summary>
                                            <p>{{ $faq['answer'] ?? '' }}</p>
                                        </details>
                                    @endif
                                @endforeach
                            </div>
                        </section>
                    @endif
                </div>

                <aside class="reserva-property-aside">
                    @if($rateOptions->isNotEmpty())
                        @php
                            $pricedOptions = $rateOptions->filter(fn ($option) => !empty($option['quote']));
                            $lowestExact = $pricedOptions->sortBy(fn ($option) => $option['quote']['total'] ?? PHP_FLOAT_MAX)->first();
                            $lowestFrom = $rateOptions->sortBy(fn ($option) => $option['from_rate'] ?? PHP_FLOAT_MAX)->first();
                        @endphp
                        <span>{{ $hasStayDates ? 'Best total from' : 'Rates from' }}</span>
                        @if($lowestExact)
                            <strong>{{ $lowestExact['quote']['currency'] }} {{ number_format((float) $lowestExact['quote']['total'], 2) }}</strong>
                            <small>Total stay price for your selected dates</small>
                        @elseif($lowestFrom)
                            <strong>{{ $lowestFrom['type']->currency }} {{ number_format((float) $lowestFrom['from_rate'], 2) }}</strong>
                            <small>per night before date-specific pricing</small>
                        @endif
                    @else
                        <span>Rates</span>
                        <strong>{{ $property->currency }} {{ number_format((float) $property->nightly_rate, 2) }}</strong>
                    @endif

                    <a href="#rates" class="button button-primary button-block">Choose a rate</a>

                    @if($property->security_deposit > 0)
                        <small>Security deposit: {{ $property->currency }} {{ number_format((float) $property->security_deposit, 2) }}</small>
                    @endif
                    @if($property->cleaning_fee > 0)
                        <small>Cleaning fee: {{ $property->currency }} {{ number_format((float) $property->cleaning_fee, 2) }}</small>
                    @endif
                </aside>
            </div>
        </div>
    </main>

    @if($images->isNotEmpty())
        <div
            id="reserva-property-gallery-modal"
            class="az-modal"
            data-modal
            hidden
            aria-hidden="true"
            role="dialog"
            aria-modal="true"
            aria-labelledby="reserva-gallery-title"
        >
            <div class="reserva-gallery-modal__panel">
                <header class="reserva-property-heading">
                    <h2 id="reserva-gallery-title">{{ $property->name }} photos</h2>
                    <button type="button" class="reserva-favourite-button" data-modal-close aria-label="Close gallery">
                        <span class="material-symbols-outlined" aria-hidden="true">close</span>
                    </button>
                </header>
                <div class="reserva-gallery-modal__grid">
                    @foreach($images as $index => $image)
                        <img src="{{ Storage::url($image) }}" alt="{{ $property->name }} photo {{ $index + 1 }}" loading="lazy" decoding="async">
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</x-public-site.layout>
