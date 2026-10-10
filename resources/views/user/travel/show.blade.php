@extends('layouts.user')
@section('title', 'Travel request')
@section('kicker', 'Resavar travel')
@section('page_title', 'Your travel request')
@section('content')
<nav style="margin-bottom:16px"><a class="az-user-button az-user-button--light" href="{{ route('user.travel.index') }}">Travel extras</a></nav>
<section class="az-user-panel">
    <header class="az-user-panel-header"><div>
        <h2 class="az-user-panel-title">{{ $travelRequest->quote_snapshot['title'] ?? ucfirst($travelRequest->kind) }}</h2>
        <p class="az-user-panel-subtitle">{{ ucfirst($travelRequest->kind) }} · {{ $travelRequest->supplier?->name }}</p>
    </div></header>
    <div class="az-user-panel-body">
        <p><strong>State:</strong> {{ in_array($travelRequest->status, ['requested','supplier_acknowledged']) && $travelRequest->expires_at->isPast() ? 'Expired' : str_replace('_', ' ', ucfirst($travelRequest->status)) }}</p>
        <p><strong>Indicative total:</strong> {{ $travelRequest->currency }} {{ number_format($travelRequest->quoted_total_minor / 100, 2) }} for {{ $travelRequest->party_size }} traveller(s), including tax and fees.</p>
        @if(($travelRequest->quote_snapshot['refundable_deposit_minor'] ?? 0) > 0)
            <p>Possible separate refundable deposit: {{ $travelRequest->currency }} {{ number_format($travelRequest->quote_snapshot['refundable_deposit_minor'] / 100, 2) }} per traveller.</p>
        @endif
        <p><strong>Offer valid until:</strong> {{ $travelRequest->expires_at->format('j M Y, H:i T') }}</p>
        <p><strong>Cancellation terms:</strong> {{ $travelRequest->quote_snapshot['terms']['cancellation'] ?? 'Refer to supplier policy' }}</p>
        <p><strong>Supplier terms:</strong> {{ $travelRequest->quote_snapshot['terms']['disclosure'] ?? '' }}</p>
        @if($travelRequest->itinerary)<p>Travel plan: {{ $travelRequest->itinerary->name }}</p>@endif
        @if($travelRequest->supplier_acknowledged_at)
            <p>The supplier has acknowledged the enquiry. This is not a completed reservation or ticket.</p>
        @endif
        <p><strong>No payment has been collected by this travel-request flow.</strong> Fulfilment, tickets, confirmed pickups and independently accounted refunds will only be available after supplier and payment integration approval.</p>
        @if(in_array($travelRequest->status, ['requested', 'supplier_acknowledged', 'expired']))
            <form method="POST" action="{{ route('user.travel.cancel', $travelRequest) }}">
                @csrf
                <button class="az-user-button az-user-button--light" type="submit">Cancel travel request only</button>
            </form>
        @endif
        <h3>Request history</h3>
        <ul>
            @foreach($travelRequest->events as $event)
                <li>{{ $event->created_at->format('j M Y, H:i') }} · {{ str_replace('_', ' ', $event->event_type) }}</li>
            @endforeach
        </ul>
    </div>
</section>
@endsection
