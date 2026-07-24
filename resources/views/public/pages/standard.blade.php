@extends('layouts.public')

@section('content')
<main class="public-page-shell">
    <section class="site-container page-intro">
        <span class="eyebrow">Azari Residences</span>
        <h1>{{ $title }}</h1>
        <p>{{ $intro }}</p>

        @if($key === 'contact' || $key === 'support')
            <div class="content-card">
                <h2>Guest and booking support</h2>
                <p>Use the contact details configured by the Azari administration team.</p>
                <a class="button button-primary" href="{{ route('availability.index') }}">Check availability</a>
            </div>
        @elseif(in_array($key, ['services', 'concierge', 'housekeeping', 'restaurant', 'airport-transfers'], true))
            <div class="content-card">
                <p>Service availability can depend on the selected residence and booking dates.</p>
                <a class="button button-primary" href="{{ route('availability.index') }}">Start a booking</a>
            </div>
        @endif
    </section>
</main>
@endsection
