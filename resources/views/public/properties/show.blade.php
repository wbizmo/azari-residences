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
                    <h2>About this residence</h2>
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
                    <strong>{{ $property->currency }} {{ number_format($property->nightly_rate) }}</strong>
                    <small>per night</small>
                    <a href="{{ route('availability.property', $property) }}" class="button button-primary button-block">Check availability</a>
                </aside>
            </div>
        </div>
    </section>
</x-public-site.layout>
