@extends('layouts.user')
@section('title', 'Dining and concierge')
@section('kicker', 'Resavar journeys')
@section('page_title', 'Dining and concierge')
@section('content')
<section class="az-user-panel" style="margin-bottom:18px">
    <header class="az-user-panel-header"><div>
        <h2 class="az-user-panel-title">Verified dining recommendations</h2>
        <p class="az-user-panel-subtitle">Curated information, not live table availability. Concierge requests are not confirmed reservations or charges.</p>
    </div></header>
    <div class="az-user-panel-body">
        <form method="GET" class="az-form-grid">
            <label>City <input name="city" value="{{ request('city') }}" maxlength="100"></label>
            <label>Approximate area near a stay
                <select name="near_stay"><option value="">All verified locations</option>
                @foreach($stays as $stay)
                    <option value="{{ $stay->id }}" @selected((string)request('near_stay')===(string)$stay->id)>{{ $stay->reference }}</option>
                @endforeach
                </select>
            </label>
            <button type="submit" class="az-user-button az-user-button--dark">Find dining</button>
        </form>
        @forelse($partners as $partner)
            <article class="az-user-panel" style="margin:16px 0"><div class="az-user-panel-body">
                <h3>{{ $partner->name }}</h3>
                <p>{{ $partner->address }}, {{ $partner->city }} · {{ $partner->timezone }}</p>
                <p>{{ $partner->disclosures }}</p>
                @if($partner->dietary_options)<p><strong>Dietary:</strong> {{ implode(', ', $partner->dietary_options) }}</p>@endif
                @if($partner->accessibility)<p><strong>Accessibility:</strong> {{ implode(', ', $partner->accessibility) }}</p>@endif
                <p><small>Details last checked {{ $partner->details_verified_at?->format('j M Y') }}. Confirm hours directly with the restaurant.</small></p>
                @if($partner->website)<a href="{{ $partner->website }}" target="_blank" rel="noopener noreferrer">Restaurant website</a>@endif
                <details><summary>Ask Resavar concierge about a table</summary>
                    <form method="POST" action="{{ route('user.dining.store') }}" class="az-form-grid">
                        @csrf
                        <input type="hidden" name="dining_partner_id" value="{{ $partner->id }}">
                        <input type="hidden" name="idempotency_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}">
                        <label>Guests <input type="number" name="party_size" min="1" max="12" value="2" required></label>
                        <label>Preferred time (UTC). Restaurant operates in {{ $partner->timezone }}.
                            <input type="datetime-local" name="requested_for" required></label>
                        <label>Trip (optional) <select name="trip_itinerary_id"><option value="">No trip</option>
                            @foreach($itineraries as $trip)<option value="{{ $trip->id }}">{{ $trip->name }}</option>@endforeach
                        </select></label>
                        <label>Dietary or accessibility notes (private unless consent given)
                            <textarea name="dietary_notes" maxlength="750"></textarea></label>
                        <label><input type="checkbox" name="supplier_share_consent" value="1"> I consent to sharing necessary request notes with the approved dining partner after review.</label>
                        <button type="submit" class="az-user-button az-user-button--dark">Send concierge enquiry (no payment)</button>
                    </form>
                </details>
            </div></article>
        @empty
            <p>No recently verified dining partners for this search.</p>
        @endforelse
        {{ $partners->links() }}
    </div>
</section>
<section class="az-user-panel"><header class="az-user-panel-header">
    <h2 class="az-user-panel-title">My dining requests</h2>
</header><div class="az-user-panel-body az-user-list">
    @forelse($diningRequests as $item)
        <article class="az-user-list-item"><div>
            <h3>{{ $item->partner?->name ?? 'Dining concierge' }}</h3>
            <p>{{ $item->requested_for?->setTimezone($item->partner?->timezone ?? 'UTC')->format('j M Y, g:i A') }} · {{ $item->party_size }} guests</p>
            <p><strong>{{ str_replace('_',' ',ucfirst($item->status)) }}</strong>
                @if($item->status==='pending_concierge') · Not a reserved table @endif</p>
        </div>
        @if(!in_array($item->status,['cancelled','cancellation_requested'],true))
            <form method="POST" action="{{ route('user.dining.cancel',$item) }}">
                @csrf
                <button class="az-user-button az-user-button--light" type="submit">Cancel this request</button>
            </form>
        @endif
        </article>
    @empty
        <p>No dining enquiries yet.</p>
    @endforelse
</div></section>
@endsection
