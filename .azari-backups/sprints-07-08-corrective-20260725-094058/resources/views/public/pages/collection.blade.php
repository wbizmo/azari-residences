@extends('components.public.layout')

@section('title', $title.' | Azari Residences')

@section('content')
<main class="az-editorial-page az-collection-page">
    <section class="az-editorial-hero">
        <div class="site-container az-editorial-hero__grid">
            <div class="az-editorial-hero__copy">
                <span class="eyebrow">The Azari collection</span>
                <h1>{{ $title }}</h1>
                <p>{{ $intro }}</p>
                <div class="az-editorial-actions">
                    <a class="button button-primary" href="{{ route('availability.index', ['room_type_id' => request('room_type_id')]) }}">Check availability</a>
                    <a class="az-text-link" href="#collection">Explore the collection <span class="material-symbols-outlined">south</span></a>
                </div>
            </div>
            <figure class="az-editorial-hero__media">
                <img src="{{ asset('images/'.$image) }}" alt="Azari {{ strtolower($title) }} experience">
            </figure>
        </div>
    </section>

    <section class="az-collection-section" id="collection">
        <div class="site-container">
            <header class="az-section-heading az-section-heading--split">
                <div><span class="eyebrow">Available residences</span><h2>Designed around the way you stay.</h2></div>
                <p>Every published residence below is loaded directly from Azari's managed inventory.</p>
            </header>

            <div class="az-luxury-property-grid">
                @forelse($properties as $property)
                    <article class="az-luxury-property-card">
                        <a class="az-luxury-property-card__media" href="{{ route('properties.show', $property) }}">
                            <img src="{{ $property->cover_image ? Storage::url($property->cover_image) : asset('images/azari-residence-fallback.png') }}" alt="{{ $property->name }}">
                            <span>{{ $property->property_type }}</span>
                        </a>
                        <div class="az-luxury-property-card__body">
                            <span class="eyebrow">{{ $property->locationRecord?->name ?? $property->location }}</span>
                            <h2>{{ $property->name }}</h2>
                            <p>{{ $property->short_description ?: 'A carefully prepared Azari residence with dependable guest support.' }}</p>
                            <div class="az-property-facts">
                                <span><span class="material-symbols-outlined">bed</span>{{ $property->bedrooms }} bedrooms</span>
                                <span><span class="material-symbols-outlined">bathtub</span>{{ $property->bathrooms }} bathrooms</span>
                                <span><span class="material-symbols-outlined">group</span>{{ $property->max_guests }} guests</span>
                            </div>
                            <div class="az-luxury-property-card__footer">
                                <strong>{{ $property->currency }} {{ number_format($property->nightly_rate) }} <small>/ night</small></strong>
                                <a href="{{ route('properties.show', $property) }}" aria-label="View {{ $property->name }}"><span class="material-symbols-outlined">arrow_outward</span></a>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="az-visual-empty-state">
                        <img src="{{ asset('images/azari-availability-empty.png') }}" alt="No published residences">
                        <div><span class="eyebrow">Collection update</span><h2>No published {{ strtolower($title) }} yet.</h2><p>New inventory will appear here automatically when it is published.</p><a class="button button-primary" href="{{ route('availability.index') }}">Search all availability</a></div>
                    </div>
                @endforelse
            </div>

            <div class="az-pagination-wrap">{{ $properties->links('vendor.pagination.azari') }}</div>
        </div>
    </section>
</main>
@endsection
