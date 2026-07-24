@extends('layouts.public')

@section('content')
<main class="public-page-shell">
    <section class="site-container page-intro">
        <span class="eyebrow">Azari collection</span>
        <h1>{{ $title }}</h1>
        <p>Published residences are loaded directly from the inventory managed by Azari administrators.</p>
    </section>

    <section class="site-container residences-grid">
        @forelse($properties as $property)
            <article class="residence-card">
                @if($property->cover_image)
                    <img src="{{ Storage::url($property->cover_image) }}" alt="{{ $property->name }}">
                @endif
                <div class="residence-card__body">
                    <span class="eyebrow">{{ $property->locationRecord?->name ?? $property->location }}</span>
                    <h2>{{ $property->name }}</h2>
                    <p>{{ $property->short_description }}</p>
                    <div class="residence-card__actions">
                        <a class="button button-secondary" href="{{ route('properties.show', $property) }}">View residence</a>
                        <a class="button button-brass" href="{{ route('availability.index', ['room_type_id' => $property->room_type_id]) }}">Book now</a>
                    </div>
                </div>
            </article>
        @empty
            <div class="empty-state">
                <h2>No published {{ strtolower($title) }} yet</h2>
                <p>Published inventory will appear here automatically.</p>
            </div>
        @endforelse
    </section>

    <div class="site-container">{{ $properties->links('vendor.pagination.azari') }}</div>
</main>
@endsection
