@extends('layouts.public')

@section('title', 'Availability — '.$property->name.' | Reserva')

@section('content')
@php
    $image = $property->featured_image ?: $property->cover_image ?: $property->image ?: 'images/azari-residence-fallback.png';
    $imageUrl = \Illuminate\Support\Str::startsWith($image, ['http://', 'https://', '/']) ? $image : asset($image);
    $sameDay = (bool) ($property->same_day_booking ?? config('azari.booking.same_day_booking'));
@endphp
<main class="az-inventory">
    <section class="az-inventory-hero">
        <img src="{{ $imageUrl }}" alt="{{ $property->name }}">
        <div>
            <a href="{{ route('properties.show', $property) }}">← Back to residence</a>
            <span class="eyebrow">Live 90-day inventory</span>
            <h1>{{ $property->name }}</h1>
            <p>{{ $property->locationRecord?->name ?? 'Reserva' }} · {{ $property->roomType?->name ?? 'Private residence' }}</p>
        </div>
    </section>

    <section class="az-inventory-panel">
        <div class="az-inventory-copy">
            <span class="eyebrow">Choose your stay</span>
            <h2>Search this residence only</h2>
            <p>Unavailable dates already have a confirmed stay, an active booking hold, or scheduled maintenance. Inventory updates from the database on every request.</p>
        </div>

        <form method="GET" action="{{ route('availability.results') }}" class="az-inventory-form">
            <input type="hidden" name="property_id" value="{{ $property->getKey() }}">
            <label>Check-in
                <input id="property-check-in" type="date" name="check_in"
                    min="{{ $sameDay ? $start->toDateString() : $start->addDay()->toDateString() }}"
                    value="{{ request('check_in', $sameDay ? $start->toDateString() : $start->addDay()->toDateString()) }}" required>
            </label>
            <label>Check-out
                <input id="property-check-out" type="date" name="check_out"
                    min="{{ $start->addDay()->toDateString() }}"
                    value="{{ request('check_out', $start->addDays(2)->toDateString()) }}" required>
            </label>
            <label>Adults
                <input type="number" name="adults" min="1" step="1" inputmode="numeric" value="{{ max(1, (int) request('adults', 1)) }}" required>
            </label>
            <label>Children
                <input type="number" name="children" min="0" step="1" inputmode="numeric" value="{{ max(0, (int) request('children', 0)) }}" required>
            </label>
            <input type="hidden" name="rooms" value="1">
            <button type="submit">Check these dates</button>
        </form>

        <div class="az-calendar-key"><span><i class="open"></i>Available</span><span><i class="blocked"></i>Unavailable</span></div>
        <div class="az-calendar" aria-label="90-day availability calendar">
            @foreach($calendar as $day)
                <button type="button"
                    class="az-calendar-day {{ $day['available'] ? 'is-open' : 'is-blocked' }}"
                    data-date="{{ $day['date']->toDateString() }}"
                    @disabled(!$day['available'])
                    aria-label="{{ $day['date']->format('l j F Y') }}: {{ $day['available'] ? 'available' : 'unavailable' }}">
                    <small>{{ $day['date']->format('D') }}</small>
                    <strong>{{ $day['date']->format('j') }}</strong>
                    <span>{{ $day['date']->format('M') }}</span>
                </button>
            @endforeach
        </div>
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const openDays = Array.from(document.querySelectorAll('.az-calendar-day.is-open'));
    const checkIn = document.getElementById('property-check-in');
    const checkOut = document.getElementById('property-check-out');
    let selectingCheckout = false;

    openDays.forEach(function (day) {
        day.addEventListener('click', function () {
            if (!selectingCheckout || day.dataset.date <= checkIn.value) {
                checkIn.value = day.dataset.date;
                const next = new Date(day.dataset.date + 'T12:00:00');
                next.setDate(next.getDate() + 1);
                checkOut.min = next.toISOString().slice(0, 10);
                checkOut.value = next.toISOString().slice(0, 10);
                selectingCheckout = true;
            } else {
                checkOut.value = day.dataset.date;
                selectingCheckout = false;
            }
            openDays.forEach(function (item) { item.classList.remove('is-selected'); });
            day.classList.add('is-selected');
        });
    });

    checkIn.addEventListener('change', function () {
        const next = new Date(checkIn.value + 'T12:00:00');
        next.setDate(next.getDate() + 1);
        checkOut.min = next.toISOString().slice(0, 10);
        if (!checkOut.value || checkOut.value <= checkIn.value) checkOut.value = checkOut.min;
    });
});
</script>
@endsection
