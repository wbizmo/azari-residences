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
                        $balance = max(0, round((float)$booking->total - $paid, 2));
                    @endphp
                    <a class="az-user-list-item" href="{{ route('user.bookings.show',$booking->reference) }}">
                        <div>
                            <h3>{{ $booking->property?->name ?? $booking->property_name_snapshot ?? 'Resarva stay' }}</h3>
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
