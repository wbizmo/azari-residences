<x-public-site.layout title="Available Residences | Azari Residences">
<main class="site-container az-s56-page">
<header><span class="eyebrow">Available residences</span><h1>Choose where you would like to stay</h1></header>
<form method="GET" action="{{ route('azari.availability.results') }}" class="az-s56-filter">
<label><span>Check in</span><input name="check_in" type="date" value="{{ $filters['check_in'] }}" required></label>
<label><span>Check out</span><input name="check_out" type="date" value="{{ $filters['check_out'] }}" required></label>
<label><span>Available location</span><select name="location"><option value="">All locations</option>@foreach($locations as $location)<option value="{{ $location }}" @selected(($filters['location'] ?? '')===$location)>{{ $location }}</option>@endforeach</select></label>
<label><span>Residence type</span><select name="property_type"><option value="">Any type</option>@foreach(['apartment','room','studio','penthouse'] as $type)<option value="{{ $type }}" @selected(($filters['property_type'] ?? '')===$type)>{{ ucfirst($type) }}</option>@endforeach</select></label>
<input type="hidden" name="adults" value="{{ $filters['adults'] ?? 1 }}"><input type="hidden" name="children" value="{{ $filters['children'] ?? 0 }}"><input type="hidden" name="rooms" value="{{ $filters['rooms'] ?? 1 }}">
<button class="button button-primary" type="submit">Update search</button></form>
<section class="az-s56-results">@forelse($results as $result) @php($property=$result['property']) @php($quote=$result['quote'])
<article class="az-s56-card"><div><span class="eyebrow">{{ $property->location }}</span><h2>{{ $property->name }}</h2><p>{{ $property->max_guests }} guests · {{ $quote['nights'] }} nights</p></div>
<strong>{{ $quote['currency'] }} {{ number_format($quote['total'],2) }}</strong>
<form method="POST" action="{{ route('azari.availability.hold',$property) }}">@csrf
<input type="hidden" name="check_in" value="{{ $filters['check_in'] }}"><input type="hidden" name="check_out" value="{{ $filters['check_out'] }}"><input type="hidden" name="adults" value="{{ $filters['adults'] ?? 1 }}"><input type="hidden" name="children" value="{{ $filters['children'] ?? 0 }}"><input type="hidden" name="rooms" value="{{ $filters['rooms'] ?? 1 }}">
<button class="button button-primary">Reserve</button></form></article>
@empty <div class="production-empty-state">No residences are available for these dates.</div>@endforelse</section>
</main></x-public-site.layout>
