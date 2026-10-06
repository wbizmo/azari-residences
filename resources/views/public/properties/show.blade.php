@php
    $searchState = array_filter(
        (array) session('azari_stay_search', []),
        fn ($value) => $value !== null && $value !== ''
    );
@endphp

<x-public-site.layout :title="$property->name" :description="$property->short_description">
    <section class="property-detail-hero">
        <div class="site-container">
            <span class="eyebrow">{{ $property->location }}, {{ $property->country }}</span>
            <h1>{{ $property->name }}</h1>
            <p>{{ $property->short_description }}</p>
        </div>
    </section>

    <section class="property-gallery-section">
        <div class="site-container">
            <div class="property-gallery">
                @if($property->cover_image)
                    <img class="property-gallery-main" src="{{ Storage::url($property->cover_image) }}" alt="{{ $property->name }}">
                @endif
                @foreach($property->gallery ?? [] as $image)
                    <img src="{{ Storage::url($image) }}" alt="{{ $property->name }} gallery image">
                @endforeach
            </div>

            <div class="property-detail-grid">
                <article>
                    <h2>About this stay</h2>
                    <p>{{ $property->description }}</p>
                    <ul class="property-facts">
                        <li>{{ $property->bedrooms }} bedrooms</li>
                        <li>{{ $property->bathrooms }} bathrooms</li>
                        <li>Up to {{ $property->max_guests }} guests</li>
                    </ul>
                    <div class="property-amenities">
                        @foreach($property->amenities as $amenity)
                            <span><span class="material-symbols-outlined">{{ $amenity->icon }}</span>{{ $amenity->name }}</span>
                        @endforeach
                    </div>
                </article>
                <aside>
                    <span>From</span>
                    <strong>{{ $property->currency }} {{ number_format((float) $property->nightly_rate, 2) }}</strong>
                    <small>per night</small>
                    <a href="{{ route(
                        'availability.property',
                        array_merge(['property' => $property], $searchState)
                    ) }}" class="button button-primary button-block">Check availability</a>
                </aside>
            </div>
        </div>
    </section>

    @if($property->publicAccommodationTypes->isNotEmpty())
        <section class="reserva-rate-section" aria-labelledby="reserva-rate-heading">
            <div class="site-container">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">Choose your stay</span>
                        <h2 id="reserva-rate-heading">Accommodation and rate options</h2>
                    </div>
                    <p>Choose the accommodation type and booking terms that fit your trip. Final totals are recalculated for your dates before checkout.</p>
                </div>

                <div class="reserva-rate-grid">
                    @foreach($property->publicAccommodationTypes as $type)
                        @foreach($type->ratePlans->where('is_active', true)->where('is_public', true) as $plan)
                            @php
                                $baseRate = (float) $type->base_rate;
                                $adjustment = (float) $plan->pricing_adjustment;
                                $fromRate = $baseRate;

                                if ($plan->pricing_adjustment_type === 'percentage') {
                                    $fromRate += $baseRate * ($adjustment / 100);
                                } elseif ($plan->pricing_adjustment_type === 'fixed') {
                                    $fromRate += $adjustment;
                                }

                                $fromRate = max(0, $fromRate);
                            @endphp

                            <article class="reserva-rate-card">
                                <div class="reserva-rate-card__heading">
                                    <div>
                                        <span>{{ $type->name }}</span>
                                        <h3>{{ $plan->publicLabel() }}</h3>
                                    </div>
                                    <strong>{{ $type->currency }} {{ number_format($fromRate, 2) }}</strong>
                                </div>

                                <div class="reserva-rate-card__facts">
                                    <span>Up to {{ $type->max_guests }} guests</span>
                                    <span>{{ $type->bedrooms }} {{ Str::plural('bedroom', $type->bedrooms) }}</span>
                                    @if($type->bed_configuration)
                                        <span>{{ $type->bed_configuration }}</span>
                                    @endif
                                    @if($plan->meal_plan)
                                        <span>{{ $plan->meal_plan }}</span>
                                    @endif
                                </div>

                                <ul class="reserva-rate-card__terms">
                                    <li>
                                        {{ $plan->is_refundable
                                            ? ($plan->cancellationPolicy?->name ?? 'Refundable rate')
                                            : 'Non-refundable rate' }}
                                    </li>
                                    @if($plan->paymentPolicy)
                                        <li>{{ $plan->paymentPolicy->name }}</li>
                                    @endif
                                    @foreach(array_slice($plan->inclusions ?? [], 0, 3) as $inclusion)
                                        <li>{{ $inclusion }}</li>
                                    @endforeach
                                </ul>

                                <a
                                    class="button button-secondary button-block"
                                    href="{{ route('availability.property', array_merge(
                                        [
                                            'property' => $property,
                                            'accommodation_type_id' => $type->id,
                                            'rate_plan_id' => $plan->id,
                                        ],
                                        $searchState
                                    )) }}"
                                >
                                    Check this rate
                                </a>
                            </article>
                        @endforeach
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</x-public-site.layout>
