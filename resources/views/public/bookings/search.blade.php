@extends('layouts.public')

@section('content')
<main class="public-page-shell">
    <section class="site-container page-intro">
        <span class="eyebrow">Direct booking</span>
        <h1>Check availability</h1>
        <p>Search live inventory using dates, guests, location and residence category.</p>

        <form class="availability-form" method="GET" action="{{ route('availability.results') }}">
            <div class="search-field">
                <label for="check_in">Check in</label>
                <div class="input-shell">
                    <span class="material-symbols-outlined">calendar_today</span>
                    <input id="check_in" name="check_in" type="date"
                           min="{{ now()->toDateString() }}"
                           value="{{ old('check_in', request('check_in')) }}" required>
                </div>
            </div>

            <div class="search-field">
                <label for="check_out">Check out</label>
                <div class="input-shell">
                    <span class="material-symbols-outlined">event_available</span>
                    <input id="check_out" name="check_out" type="date"
                           min="{{ now()->addDay()->toDateString() }}"
                           value="{{ old('check_out', request('check_out')) }}" required>
                </div>
            </div>

            <div class="search-field">
                <label for="adults">Adults</label>
                <div class="input-shell">
                    <span class="material-symbols-outlined">person</span>
                    <input id="adults" name="adults" type="number" min="1" max="40"
                           value="{{ old('adults', request('adults', 1)) }}" required>
                </div>
            </div>

            <div class="search-field">
                <label for="children">Children</label>
                <div class="input-shell">
                    <span class="material-symbols-outlined">child_care</span>
                    <input id="children" name="children" type="number" min="0" max="40"
                           value="{{ old('children', request('children', 0)) }}">
                </div>
            </div>

            <div class="search-field">
                <label for="location_id">Location</label>
                <div class="input-shell select-shell">
                    <span class="material-symbols-outlined">location_on</span>
                    <select id="location_id" name="location_id">
                        <option value="">All available locations</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @selected((string) request('location_id') === (string) $location->id)>
                                {{ $location->name }}, {{ $location->city }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="search-field">
                <label for="room_type_id">Residence category</label>
                <div class="input-shell select-shell">
                    <span class="material-symbols-outlined">apartment</span>
                    <select id="room_type_id" name="room_type_id">
                        <option value="">Any category</option>
                        @foreach($roomTypes as $roomType)
                            <option value="{{ $roomType->id }}" @selected((string) request('room_type_id') === (string) $roomType->id)>
                                {{ $roomType->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <input type="hidden" name="rooms" value="1">

            <button class="availability-submit" type="submit">
                <span>Search live availability</span>
                <span class="material-symbols-outlined">search</span>
            </button>
        </form>
    </section>
</main>
@endsection
