@extends('layouts.user')
@section('title', 'Archived request')
@section('kicker', 'Account history')
@section('page_title', 'Historical request details')
@section('content')
<nav style="margin-bottom:16px">
    <a class="az-user-button az-user-button--light" href="{{ route('user.archive.travel.index') }}">Back to historical records</a>
</nav>
<section class="az-user-panel" aria-labelledby="historical-record">
    <header class="az-user-panel-header">
        <div>
            <h2 class="az-user-panel-title" id="historical-record">
                {{ $travelRequest->offer?->title ?? ($travelRequest->quote_snapshot['title'] ?? 'Historical request') }}
            </h2>
            <p class="az-user-panel-subtitle">Archived enquiry · Read-only</p>
        </div>
    </header>
    <div class="az-user-panel-body">
        <p><strong>Reference:</strong> {{ $travelRequest->id }}</p>
        <p><strong>Category at creation:</strong> {{ ucfirst($travelRequest->kind) }}</p>
        <p><strong>Recorded status:</strong> {{ str_replace('_', ' ', ucfirst($travelRequest->status)) }}</p>
        <p><strong>Former supplier:</strong> {{ $travelRequest->supplier?->name ?? 'Unavailable' }}</p>
        <p><strong>Created:</strong> {{ $travelRequest->created_at?->format('j M Y, H:i') }}</p>
        <p><strong>Indicative historical quote:</strong>
            {{ $travelRequest->currency }} {{ number_format(((int) $travelRequest->quoted_total_minor) / 100, 2) }}
        </p>
        <p><strong>Party size:</strong> {{ $travelRequest->party_size }}</p>
        <p>This quote is not proof of payment, a valid ticket, or a current reservation. Current stays and verified payment documents remain in your accommodation booking history.</p>

        <h3>Recorded request events</h3>
        <ul>
            @forelse($travelRequest->events as $event)
                <li>{{ $event->created_at?->format('j M Y, H:i') }}:
                    {{ str_replace('_', ' ', $event->event_type) }}
                    @if($event->new_status)
                        ({{ str_replace('_', ' ', $event->new_status) }})
                    @endif
                </li>
            @empty
                <li>No change events were recorded.</li>
            @endforelse
        </ul>
        <p>For corrections, refunds or disputes, contact Resavar support. Historical requests cannot be rebooked here.</p>
        <a href="{{ route('user.support.index') }}">Contact support</a>
    </div>
</section>
@endsection
