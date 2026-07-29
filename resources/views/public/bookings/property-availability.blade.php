@extends('layouts.public')

@section('title', 'Availability — '.$property->name.' | Azari Residences')

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
            <p>{{ $property->locationRecord?->name ?? 'Azari Residences' }} · {{ $property->roomType?->name ?? 'Private residence' }}</p>
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
                <select name="adults">@for($i=1;$i<=10;$i++)<option value="{{ $i }}" @selected((int)request('adults',1)===$i)>{{ $i }}</option>@endfor</select>
            </label>
            <label>Children
                <select name="children">@for($i=0;$i<=8;$i++)<option value="{{ $i }}" @selected((int)request('children',0)===$i)>{{ $i }}</option>@endfor</select>
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

<style>
.az-inventory{background:#f5f1e9;color:#153b34;min-height:100vh;padding:48px 5vw 80px}.az-inventory-hero{display:grid;grid-template-columns:minmax(260px,480px) 1fr;max-width:1180px;margin:auto;background:#153b34;color:#fff}.az-inventory-hero img{width:100%;height:330px;object-fit:cover}.az-inventory-hero>div{padding:42px;display:flex;flex-direction:column;justify-content:center}.az-inventory-hero a{color:#d7c7a4;margin-bottom:32px}.az-inventory-hero h1{font:500 clamp(2rem,5vw,4rem)/1.05 Georgia,serif;margin:10px 0}.eyebrow{text-transform:uppercase;letter-spacing:.18em;font-size:.72rem}.az-inventory-panel{max-width:1180px;margin:24px auto 0;background:#fff;padding:36px}.az-inventory-copy{max-width:680px}.az-inventory-copy h2{font:500 2rem Georgia,serif;margin:8px 0}.az-inventory-form{display:grid;grid-template-columns:repeat(4,1fr) auto;gap:12px;margin:28px 0}.az-inventory-form label{font-size:.76rem;text-transform:uppercase;letter-spacing:.1em}.az-inventory-form input,.az-inventory-form select{display:block;width:100%;box-sizing:border-box;border:1px solid #cbc5b8;background:#fff;padding:13px;margin-top:7px}.az-inventory-form button{align-self:end;border:0;background:#153b34;color:#fff;padding:14px 22px;min-height:45px}.az-calendar-key{display:flex;gap:22px;margin:24px 0 12px}.az-calendar-key span{display:flex;gap:7px;align-items:center;font-size:.82rem}.az-calendar-key i{width:12px;height:12px;border-radius:50%}.az-calendar-key .open{background:#317c63}.az-calendar-key .blocked{background:#d8d3c9}.az-calendar{display:grid;grid-template-columns:repeat(10,1fr);gap:8px}.az-calendar-day{border:1px solid #ded8cc;background:#f8f6f0;min-height:76px;padding:7px;color:#153b34}.az-calendar-day small,.az-calendar-day span{display:block;font-size:.64rem;text-transform:uppercase}.az-calendar-day strong{display:block;font-size:1.2rem;margin:3px}.az-calendar-day.is-open{cursor:pointer;border-color:#98b9aa;background:#edf5f1}.az-calendar-day.is-open:hover,.az-calendar-day.is-selected{background:#153b34;color:#fff}.az-calendar-day.is-blocked{opacity:.48;text-decoration:line-through}.az-calendar-day:disabled{cursor:not-allowed}@media(max-width:900px){.az-inventory-hero{grid-template-columns:1fr}.az-inventory-form{grid-template-columns:1fr 1fr}.az-inventory-form button{grid-column:1/-1}.az-calendar{grid-template-columns:repeat(7,1fr)}}@media(max-width:560px){.az-inventory{padding:20px 14px 50px}.az-inventory-panel{padding:22px 14px}.az-inventory-form{grid-template-columns:1fr}.az-calendar{grid-template-columns:repeat(4,1fr)}}
</style>

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
