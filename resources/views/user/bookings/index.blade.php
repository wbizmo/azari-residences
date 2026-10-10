@extends('layouts.user')
@section('title','Trips')
@section('kicker','Your stays')
@section('page_title','Trips')
@section('content')
<nav class="az-user-tabs" aria-label="Trip filters">
    @foreach([
        'current' => 'Current',
        'upcoming' => 'Upcoming',
        'past' => 'Past',
        'cancelled' => 'Cancelled',
        'pending-payment' => 'Payment due',
        'all' => 'All',
    ] as $key => $label)
        <a class="az-user-tab {{ $status===$key?'is-active':'' }}" href="{{ route('user.bookings.index',['status'=>$key]) }}">{{ $label }}</a>
    @endforeach
</nav>


<section class="az-user-panel" aria-label="Your itineraries" style="margin-bottom:18px">
    <header class="az-user-panel-header">
        <div>
            <h2 class="az-user-panel-title">Your itineraries</h2>
            <p class="az-user-panel-subtitle">Organize multiple stays into one private travel plan without combining payments or changing reservations.</p>
        </div>
    </header>
    <div class="az-user-panel-body">
        <form method="POST" action="{{ route('user.itineraries.store') }}" class="az-form-grid">
            @csrf
            <label><span>New itinerary name</span>
                <input type="text" name="name" maxlength="120" minlength="2"
                    placeholder="e.g. October holiday" required>
            </label>
            <button class="az-user-button az-user-button--dark" type="submit">Create itinerary</button>
        </form>
        @if($tripItineraries->isNotEmpty())
            <nav aria-label="Saved itineraries" class="az-user-list" style="margin-top:16px">
                @foreach($tripItineraries as $itinerary)
                    <a class="az-user-list-item" href="{{ route('user.itineraries.show', $itinerary) }}">
                        <span>{{ $itinerary->name }}</span>
                        <span>{{ $itinerary->bookings_count }} {{ Str::plural('stay', $itinerary->bookings_count) }}</span>
                    </a>
                @endforeach
            </nav>
        @endif
    </div>
</section>

<section class="az-user-panel">
    <header class="az-user-panel-header">
        <div>
            <h2 class="az-user-panel-title">{{ Str::headline(str_replace('-',' ',$status)) }} trips</h2>
            <p class="az-user-panel-subtitle">Property, accommodation, dates, payment state and pending changes in one place.</p>
        </div>
        <a class="az-user-button az-user-button--dark" href="{{ route('availability.index') }}">Book another stay</a>
    </header>

    <div class="az-user-panel-body">
        @if($bookings->isEmpty())
            <div class="az-user-empty">
                <span class="material-symbols-outlined">luggage</span>
                <h3>No trips in this view</h3>
                <p>Change the filter or search for another stay.</p>
            </div>
        @else
            <div class="az-user-list">
                @foreach($bookings as $booking)
                    @php
                        $paid = (float) $booking->payments->where('status','successful')->sum('amount');
                        // Mirror the legacy-only payment fallback without
                        // triggering an extra SQL query per booking card.
                        $legacyPaid = $paid <= 0
                            && ! $booking->isCancelled()
                            && in_array($booking->status, ['paid','confirmed','check_in','checked_in','checked_out','completed'], true)
                            && $booking->paid_at !== null
                            && (filled($booking->payment_reference) || filled($booking->receipt_number));
                        if ($legacyPaid) $paid = (float) $booking->total;
                        $balance = max(0, round((float) $booking->total - $paid, 2));
                    @endphp
                    <a class="az-user-list-item" href="{{ route('user.bookings.show',$booking->reference) }}">
                        <div>
                            <h3>{{ $booking->property?->name ?? $booking->property_name_snapshot ?? 'Resavar stay' }}</h3>
                            <p>
                                {{ $booking->check_in?->format('j M Y') }} to {{ $booking->check_out?->format('j M Y') }}
                                · {{ $booking->accommodationType?->name ?? $booking->accommodation_type_name_snapshot ?? 'Accommodation' }}
                                @if($booking->ratePlan?->name || $booking->rate_plan_name_snapshot)
                                    · {{ $booking->ratePlan?->name ?? $booking->rate_plan_name_snapshot }}
                                @endif
                            </p>
                            <p>
                                {{ $booking->currency }} {{ number_format((float)$booking->total,2) }}
                                · Paid {{ number_format($paid,2) }}
                                @if($balance > 0) · Balance {{ number_format($balance,2) }} @endif
                                @if($booking->modificationRequests->isNotEmpty()) · Change request pending @endif
                                @if($booking->tripItinerary) · Itinerary: {{ $booking->tripItinerary->name }} @endif
                            </p>
                        </div>
                        <span class="az-user-status {{ in_array($booking->status,['pending','pending_payment'])?'az-user-status--warning':($booking->status==='cancelled'?'az-user-status--danger':'') }}">
                            {{ str_replace('_',' ',$booking->status) }}
                        </span>
                    </a>
                @endforeach
            </div>
            {{ $bookings->links() }}
        @endif
    </div>
</section>
@endsection
