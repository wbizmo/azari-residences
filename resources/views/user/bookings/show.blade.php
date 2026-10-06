@extends('layouts.user')
@section('title', 'Booking '.$booking->reference)
@section('content')
@php
    $successful = $booking->successfulPayment();
    $latestPayment = $booking->payments->sortByDesc('created_at')->first();
    $isPaid = $booking->isPaid();
    $canPaySecurely = $booking->canAcceptPayment();
    $receiptAvailable = $booking->receiptAvailable();
    $showLocation = in_array($booking->status, ['confirmed','paid','check_in','checked_in','checked_out','completed'], true);
    $directionsUrl = $showLocation ? $booking->directionsUrl() : null;
    $dojahEnabled = (bool) config('azari.identity.dojah.enabled', false);
@endphp

<div class="az-user-actions" style="margin-bottom:18px">
    <a class="az-user-button az-user-button--dark" href="{{ route('user.bookings.receipt', $booking->reference) }}" target="_blank">
        <span class="material-symbols-outlined">description</span> Print invoice
    </a>
    @if($canPaySecurely)<a class="az-user-button az-user-button--primary" href="{{ route('public.payment.select', $booking->reference) }}">Pay securely</a>@endif
    @if($directionsUrl)<a class="az-user-button az-user-button--primary" href="{{ $directionsUrl }}" target="_blank" rel="noopener noreferrer"><span class="material-symbols-outlined">directions</span> Get directions</a>@endif
</div>

<div class="az-user-actions" style="margin-bottom:18px">
    <a class="az-user-button az-user-button--light" target="_blank" href="{{ route('user.bookings.documents', [$booking->reference, 'confirmation']) }}">Booking confirmation</a>
    <a class="az-user-button az-user-button--light" target="_blank" href="{{ route('user.bookings.documents', [$booking->reference, 'invoice']) }}">Invoice PDF</a>
    @if($receiptAvailable)<a class="az-user-button az-user-button--light" target="_blank" href="{{ route('user.bookings.documents', [$booking->reference, 'receipt']) }}">Receipt PDF</a>@endif
    @if($booking->isCheckInEligible())
        <form method="post" action="{{ route('user.bookings.check-in', $booking->reference) }}">@csrf<button class="az-user-button az-user-button--primary">Check in</button></form>
    @endif
</div>

<section class="az-user-detail-grid">
    <div class="az-user-panel">
        <header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Reservation</h2><p class="az-user-panel-subtitle">{{ $booking->property_name_snapshot ?: ($booking->property?->name ?? 'Resarva Residence') }}</p></div><span class="az-user-status">{{ str_replace('_', ' ', $booking->status) }}</span></header>
        <div class="az-user-panel-body"><dl class="az-user-detail-list">
            <div class="az-user-detail-row"><dt>Booking reference</dt><dd>{{ $booking->reference }}</dd></div>
            <div class="az-user-detail-row"><dt>Property</dt><dd>{{ $booking->property_name_snapshot ?: $booking->property?->name }}</dd></div>
            <div class="az-user-detail-row"><dt>Accommodation</dt><dd>{{ $booking->accommodationType?->name ?? $booking->accommodation_type_name_snapshot ?? 'Accommodation' }}</dd></div>
            <div class="az-user-detail-row"><dt>Rate plan</dt><dd>{{ $booking->ratePlan?->name ?? $booking->rate_plan_name_snapshot ?? 'Standard' }}</dd></div>
            <div class="az-user-detail-row"><dt>Unit or room</dt><dd>{{ $booking->property?->unit_number ?: $booking->property?->code }}</dd></div>
            @if($showLocation && $booking->property_formatted_address)<div class="az-user-detail-row"><dt>Property address</dt><dd>{{ $booking->property_formatted_address }}</dd></div>@endif
            <div class="az-user-detail-row"><dt>Adults / children</dt><dd>{{ $booking->adults }} / {{ $booking->children }}</dd></div>
            <div class="az-user-detail-row"><dt>Number of nights</dt><dd>{{ $booking->nights }}</dd></div>
            @if($booking->checked_in_at)<div class="az-user-detail-row"><dt>Checked in at</dt><dd><x-user-local-time :value="$booking->checked_in_at" /></dd></div>@else<div class="az-user-detail-row"><dt>Scheduled arrival</dt><dd>{{ $booking->check_in?->format('j F Y') }}</dd></div>@endif
            @if($booking->completed_at)<div class="az-user-detail-row"><dt>Checked out at</dt><dd><x-user-local-time :value="$booking->completed_at" /></dd></div>@else<div class="az-user-detail-row"><dt>Scheduled departure</dt><dd>{{ $booking->check_out?->format('j F Y') }}</dd></div>@endif
        </dl></div>
    </div>

    <div class="az-user-panel">
        <header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Charges and payment</h2></div></header>
        <div class="az-user-panel-body"><dl class="az-user-detail-list">
            <div class="az-user-detail-row"><dt>Subtotal</dt><dd>{{ $booking->currency }} {{ number_format((float) $booking->subtotal, 2) }}</dd></div>
            <div class="az-user-detail-row"><dt>Add-ons</dt><dd>{{ $booking->currency }} {{ number_format((float) $booking->add_on_total, 2) }}</dd></div>
            <div class="az-user-detail-row"><dt>Fees</dt><dd>{{ $booking->currency }} {{ number_format((float) $booking->fee_total, 2) }}</dd></div>
            <div class="az-user-detail-row"><dt>Tax</dt><dd>{{ $booking->currency }} {{ number_format((float) $booking->tax_total, 2) }}</dd></div>
            @if((float)$booking->discount_total > 0)<div class="az-user-detail-row"><dt>Voucher {{ $booking->voucher_code }}</dt><dd>-{{ $booking->currency }} {{ number_format((float)$booking->discount_total,2) }}</dd></div>@endif
            <div class="az-user-detail-row"><dt>Total</dt><dd>{{ $booking->currency }} {{ number_format((float) $booking->total, 2) }}</dd></div>
            <div class="az-user-detail-row"><dt>Paid</dt><dd>{{ $booking->currency }} {{ number_format($booking->successfulPaymentsTotal(), 2) }}</dd></div>
            <div class="az-user-detail-row"><dt>Refunded</dt><dd>{{ $booking->currency }} {{ number_format($booking->successfulRefundsTotal(), 2) }}</dd></div>
            <div class="az-user-detail-row"><dt>Balance</dt><dd>{{ $booking->currency }} {{ number_format($schedule['balance'], 2) }}</dd></div>
            <div class="az-user-detail-row"><dt>Payment terms</dt><dd>{{ Str::headline($schedule['payment_type']) }}@if($schedule['due_on']) · due {{ Carbon\CarbonImmutable::parse($schedule['due_on'])->format('j M Y') }}@endif</dd></div>
            <div class="az-user-detail-row"><dt>Payment status</dt><dd>{{ str_replace('_', ' ', $latestPayment?->status ?? 'not started') }}</dd></div>
            <div class="az-user-detail-row"><dt>Gateway</dt><dd>{{ $latestPayment ? ucfirst($latestPayment->provider) : 'Not selected' }}</dd></div>
        </dl></div>
    </div>
</section>

<section class="az-user-detail-grid" style="margin-top:18px">
    <div class="az-user-panel">
        <header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Guests and identities</h2></div></header>
        <div class="az-user-panel-body"><div class="az-user-list">
        @forelse($booking->guests as $guest)
            @php
                $identity = $guest->identityLink?->userIdentityDocument ?: $guest->identityDocument;
                $verification = $guest->latestIdentityVerification;
                $ready = $guest->type !== 'adult' || ($dojahEnabled ? $verification?->isVerified() : (bool) $identity);
            @endphp
            <div class="az-user-list-item">
                <div><h3>{{ $guest->full_name }} {{ $guest->is_lead ? '(booking owner)' : '' }}</h3><p>{{ ucfirst($guest->type) }} · {{ $guest->type !== 'adult' ? 'No adult ID requirement' : ($dojahEnabled ? 'Dojah '.str_replace('_',' ',$verification?->status ?? 'required') : ($identity ? 'Identity attached' : 'Identity pending')) }}</p></div>
                <span class="az-user-status {{ !$ready ? 'az-user-status--warning' : '' }}">{{ $ready ? 'Ready' : 'Pending' }}</span>
            </div>
        @empty
            <div class="az-user-list-item"><div><h3>No additional guests</h3></div></div>
        @endforelse
        </div></div>
    </div>

    <div class="az-user-panel">
        <header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Booking links</h2></div></header>
        <div class="az-user-panel-body az-user-list">
            @if($directionsUrl)<a class="az-user-list-item" href="{{ $directionsUrl }}" target="_blank" rel="noopener noreferrer"><div><h3>Google Maps directions</h3><p>{{ $booking->property_formatted_address }}</p></div><span class="material-symbols-outlined">directions</span></a>@endif
            <a class="az-user-list-item" href="{{ route('user.payments.index') }}"><div><h3>Payments</h3><p>{{ $booking->payments_count }} payment record(s)</p></div><span class="material-symbols-outlined">chevron_right</span></a>
            <a class="az-user-list-item" href="{{ route('user.documents.index') }}"><div><h3>Receipts and invoices</h3><p>{{ $receiptAvailable ? 'Receipt available' : 'Available after successful payment' }}</p></div><span class="material-symbols-outlined">chevron_right</span></a>
            <a class="az-user-list-item" href="{{ route('user.contact') }}"><div><h3>Contact Resarva</h3><p>Get help with this booking</p></div><span class="material-symbols-outlined">chevron_right</span></a>
        </div>
    </div>
</section>

<section class="az-user-panel" style="margin-top:18px">
    <header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Booking timeline</h2></div></header>
    <div class="az-user-panel-body az-user-list">
        @forelse($timeline as $event)<div class="az-user-list-item"><div><h3>{{ str_replace('_', ' ', $event->status ?? $event->to_status ?? 'Booking updated') }}</h3><p><x-user-local-time :value="$event->created_at" /></p></div></div>@empty<div class="az-user-list-item"><div><h3>Booking created</h3><p><x-user-local-time :value="$booking->created_at" /></p></div></div>@endforelse
    </div>
    <div class="az-user-pagination"><span>Page {{ $timeline->currentPage() }} of {{ $timeline->lastPage() }}</span>{{ $timeline->links() }}</div>
</section>

<section class="az-user-detail-grid" style="margin-top:18px">
    <div class="az-user-panel">
        <header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Manage this trip</h2><p class="az-user-panel-subtitle">Only changes allowed by the frozen booking policy are offered.</p></div></header>
        <div class="az-user-panel-body">
            <form method="POST" action="{{ route('user.bookings.modifications.store',$booking->reference) }}" class="az-form-grid">
                @csrf
                <label class="wide">
                    <span>Change request</span>
                    <select name="type" required>
                        <option value="">Choose a request</option>
                        @foreach([
                            'date_change' => 'Change dates',
                            'guest_change' => 'Change guest count',
                            'arrival_time' => 'Update arrival time',
                            'room_preference' => 'Room or bed preference',
                            'contact_details' => 'Update contact details',
                            'add_extras' => 'Add extras',
                            'cancellation' => 'Request cancellation',
                        ] as $type => $label)
                            @if($selfService[$type] ?? false)
                                <option value="{{ $type }}">{{ $label }}</option>
                            @endif
                        @endforeach
                    </select>
                </label>
                <label><span>New check-in</span><input type="date" name="check_in" min="{{ now()->toDateString() }}"></label>
                <label><span>New check-out</span><input type="date" name="check_out" min="{{ now()->addDay()->toDateString() }}"></label>
                <label><span>Arrival time</span><input type="time" name="arrival_time"></label>
                <label><span>Adult count</span><input type="number" name="adult_count" min="1" max="12"></label>
                <label><span>Child count</span><input type="number" name="child_count" min="0" max="8"></label>
                <label class="wide"><span>Room or bed preference</span><input name="room_preference" maxlength="500"></label>
                <label class="wide"><span>Reason or notes</span><textarea name="guest_note" maxlength="2000"></textarea></label>
                <label class="wide"><span>Cancellation reason if applicable</span><input name="cancellation_reason" maxlength="500"></label>
                <button class="az-user-button az-user-button--dark" type="submit">Submit request</button>
            </form>
        </div>
    </div>

    <div class="az-user-panel">
        <header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Change requests</h2><p class="az-user-panel-subtitle">Status and staff response</p></div></header>
        <div class="az-user-panel-body az-user-list">
            @forelse($booking->modificationRequests as $requestItem)
                <div class="az-user-list-item">
                    <div>
                        <h3>{{ Str::headline($requestItem->type) }} · {{ $requestItem->reference }}</h3>
                        <p>{{ $requestItem->guest_note ?: 'No additional note.' }}</p>
                        @if($requestItem->staff_note)<p><strong>Resarva:</strong> {{ $requestItem->staff_note }}</p>@endif
                    </div>
                    <span class="az-user-status {{ $requestItem->status==='pending'?'az-user-status--warning':'' }}">{{ Str::headline($requestItem->status) }}</span>
                </div>
            @empty
                <div class="az-user-empty"><p>No change requests for this trip.</p></div>
            @endforelse
        </div>
    </div>
</section>

@if(
    !$booking->review
    && (in_array($booking->status,['completed','checked_out'],true) || $booking->checked_out_at || $booking->completed_at)
)
<section class="az-user-panel" style="margin-top:18px">
    <header class="az-user-panel-header">
        <div><h2 class="az-user-panel-title">Review your stay</h2><p class="az-user-panel-subtitle">Only completed verified stays can submit one review.</p></div>
    </header>
    <div class="az-user-panel-body">
        <form method="POST" action="{{ route('user.reviews.store',$booking) }}" class="az-form-grid">
            @csrf
            @foreach([
                'rating' => 'Overall',
                'cleanliness' => 'Cleanliness',
                'comfort' => 'Comfort',
                'facilities' => 'Facilities',
                'location_score' => 'Location',
                'staff_service' => 'Staff/service',
                'value_score' => 'Value',
                'wifi_score' => 'Wi-Fi',
            ] as $name => $label)
                <label>
                    <span>{{ $label }}</span>
                    <select name="{{ $name }}" {{ $name==='wifi_score' ? '' : 'required' }}>
                        <option value="">Choose</option>
                        @foreach([5,4,3,2,1] as $score)
                            <option value="{{ $score }}">{{ $score }}/5</option>
                        @endforeach
                    </select>
                </label>
            @endforeach
            <label>
                <span>Trip type</span>
                <select name="trip_type">
                    <option value="">Prefer not to say</option>
                    @foreach(['business','couple','family','friends','solo','other'] as $tripType)
                        <option value="{{ $tripType }}">{{ Str::headline($tripType) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="wide"><span>Review title</span><input name="title" maxlength="120"></label>
            <label class="wide"><span>What did you like?</span><textarea name="positive_feedback" maxlength="1500"></textarea></label>
            <label class="wide"><span>What could be better?</span><textarea name="negative_feedback" maxlength="1500"></textarea></label>
            <label class="wide"><span>Your review</span><textarea name="body" minlength="20" maxlength="3000" required></textarea></label>
            <button class="az-user-button az-user-button--dark" type="submit">Submit verified review</button>
        </form>
    </div>
</section>
@elseif($booking->review)
<section class="az-user-panel" style="margin-top:18px">
    <header class="az-user-panel-header">
        <div><h2 class="az-user-panel-title">Your stay review</h2><p class="az-user-panel-subtitle">Review status: {{ Str::headline($booking->review->status) }}</p></div>
    </header>
    <div class="az-user-panel-body">
        <strong>{{ $booking->review->rating }}/5</strong>
        @if($booking->review->title)<h3>{{ $booking->review->title }}</h3>@endif
        <p>{{ $booking->review->body }}</p>
        @if($booking->review->admin_reply)
            <div class="az-user-alert"><strong>Resarva response</strong><p>{{ $booking->review->admin_reply }}</p></div>
        @endif
    </div>
</section>
@endif
@endsection
