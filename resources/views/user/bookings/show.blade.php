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
@endphp

@if(in_array($booking->status, ['approved','confirmed','paid','check_in','checked_in','checked_out','completed'], true))
<section class="az-user-panel" aria-label="Encrypted offline itinerary" style="margin-bottom:18px">
    <header class="az-user-panel-header"><div>
        <h2 class="az-user-panel-title">Keep an encrypted offline stay summary</h2>
        <p class="az-user-panel-subtitle">Optional: save the property, address and stay dates on this browser, encrypted with your passphrase. No prices, payments, identity documents or contact details are saved. Shared devices should clear the copy after use.</p>
    </div></header>
    <div class="az-user-panel-body">
        <form data-offline-save class="az-form-grid">
            <label class="wide"><span>Offline encryption passphrase (minimum 12 characters)</span>
                <input type="password" name="offline_passphrase" minlength="12" required autocomplete="new-password"
                    aria-describedby="offline-itinerary-help">
            </label>
            <p class="wide" id="offline-itinerary-help">Resavar cannot recover this passphrase. Use the same passphrase for additional offline stays. This does not confirm an active booking or payment.</p>
            <button class="az-user-button az-user-button--dark" type="submit">Save encrypted offline copy</button>
            <p class="wide" role="status" aria-live="polite" data-offline-save-feedback></p>
        </form>
        <a href="/offline.html">View or clear saved offline stays</a>
    </div>
</section>
<script type="application/json" data-offline-trip-record>@json([
    'id' => (string) $booking->reference,
    'property' => (string) ($booking->property_name_snapshot ?: ($booking->property?->name ?? 'Your stay')),
    'check_in' => $booking->check_in?->toDateString(),
    'check_out' => $booking->check_out?->toDateString(),
    'address' => (string) ($booking->property_formatted_address ?: 'Address available from your host'),
])</script>
<script src="{{ asset('offline-trip.js') }}" defer></script>
@endif

@if(in_array($booking->status, ['approved','confirmed','paid','check_in','checked_in','checked_out','completed'], true))
<section class="az-user-panel" aria-label="Share a limited itinerary" style="margin-bottom:18px">
    <header class="az-user-panel-header">
        <div><h2 class="az-user-panel-title">Share a limited itinerary</h2>
            <p class="az-user-panel-subtitle">Create a 24-hour read-only link showing the property and stay dates, without your contact details, reservation reference, payments or documents. Creating a new link invalidates the previous one.</p>
        </div>
    </header>
    <div class="az-user-panel-body">
        @if(session('itinerary_share_url'))
            <label><span>Copy this link now. It will not be shown again.</span>
                <input readonly value="{{ session('itinerary_share_url') }}" aria-label="Limited itinerary share URL">
            </label>
        @endif
        <div class="az-user-actions">
            <form method="POST" action="{{ route('user.bookings.share.create', $booking->reference) }}">
                @csrf
                <button type="submit" class="az-user-button az-user-button--dark">Create new share link</button>
            </form>
            <form method="POST" action="{{ route('user.bookings.share.revoke', $booking->reference) }}">
                @csrf @method('DELETE')
                <button type="submit" class="az-user-button az-user-button--light">Revoke shared link</button>
            </form>
        </div>
    </div>
</section>
@endif

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
    <a class="az-user-button az-user-button--light" href="{{ route('user.bookings.phase2.messages', $booking->reference) }}">Messages</a>
    <a class="az-user-button az-user-button--light" href="{{ route('user.bookings.phase2.arrival', $booking->reference) }}">Arrival</a>
    @if($receiptAvailable)<a class="az-user-button az-user-button--light" target="_blank" href="{{ route('user.bookings.documents', [$booking->reference, 'receipt']) }}">Receipt PDF</a>@endif
    @if($booking->isCheckInEligible())
        <form method="post" action="{{ route('user.bookings.check-in', $booking->reference) }}">@csrf<button class="az-user-button az-user-button--primary">Check in</button></form>
    @endif
</div>

<section class="az-user-detail-grid">
    <div class="az-user-panel">
        <header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Reservation</h2><p class="az-user-panel-subtitle">{{ $booking->property_name_snapshot ?: ($booking->property?->name ?? 'Resavar Stay') }}</p></div><span class="az-user-status">{{ str_replace('_', ' ', $booking->status) }}</span></header>
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
        <header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Guests</h2></div></header>
        <div class="az-user-panel-body"><div class="az-user-list">
        @forelse($booking->guests as $guest)
            <div class="az-user-list-item">
                <div><h3>{{ $guest->full_name }} {{ $guest->is_lead ? '(booking owner)' : '' }}</h3><p>{{ ucfirst($guest->type) }}</p></div>
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
            <a class="az-user-list-item" href="{{ route('user.contact') }}"><div><h3>Contact Resavar</h3><p>Get help with this booking</p></div><span class="material-symbols-outlined">chevron_right</span></a>
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
                <label class="wide"><span>New additional adult guest</span>
                    <input name="new_adults[0][first_name]" placeholder="First name" maxlength="80">
                    <input name="new_adults[0][last_name]" placeholder="Last name" maxlength="80">
                    <input type="email" name="new_adults[0][email]" placeholder="Guest email for identity verification" maxlength="190">
                </label>
                <label class="wide"><span>New additional child</span>
                    <input name="new_children[0][first_name]" placeholder="Child first name" maxlength="80">
                    <input name="new_children[0][last_name]" placeholder="Child last name" maxlength="80">
                </label>
                <p class="wide">If adding more than one guest, please contact support to provide all individual names and adult verification email addresses.</p>
                <label class="wide"><span>Request another accommodation type</span>
                    <select name="accommodation_type_id">
                        <option value="">Select room type if requesting a room change</option>
                        @foreach($booking->property->accommodationTypes()->where('is_active', true)->where('is_published', true)->orderBy('sort_order')->get() as $type)
                            @if((int) $type->getKey() !== (int) $booking->accommodation_type_id)
                                <option value="{{ $type->getKey() }}">{{ $type->name }} · {{ $type->currency }} {{ number_format((float) $type->base_rate, 2) }} base rate</option>
                            @endif
                        @endforeach
                    </select>
                </label>
                <label class="wide"><span>Room or bed preference</span><input name="room_preference" maxlength="500"></label>
                <label class="wide"><span>Add extras (choose from available options)</span>
                    <span style="display:block">
                    @foreach(\App\Models\BookingAddOn::query()->where('is_active', true)->orderBy('sort_order')->get() as $extra)
                        <label style="display:block;margin:5px 0">
                            <input type="checkbox" name="add_on_ids[]" value="{{ $extra->getKey() }}">
                            {{ $extra->name }} · {{ $booking->currency }} {{ number_format((float) $extra->price,2) }}
                        </label>
                    @endforeach
                    </span>
                </label>
                <label class="wide"><span>Reason or notes</span><textarea name="guest_note" maxlength="2000"></textarea></label>
                <label class="wide"><span>Cancellation reason if applicable</span><input name="cancellation_reason" maxlength="500"></label>
                <button class="az-user-button az-user-button--dark" type="submit">Submit request</button>
            </form>
        </div>
    </div>

    @if($canCancel)
        <div class="az-user-panel">
            <header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Cancel this stay</h2>
                <p class="az-user-panel-subtitle">Review the policy and potential refund before confirming.</p></div></header>
            <div class="az-user-panel-body">
                <p>Policy: {{ $cancellationQuote['policy_name'] ?? 'Original booking conditions' }}</p>
                @if($cancellationQuote['manual_review_required'])
                    <p>Your booking has legacy or incomplete cancellation rules. A staff member must review any refund; no amount is promised.</p>
                @else
                    <p>Cancellation fee: {{ $booking->currency }} {{ number_format((float) $cancellationQuote['cancellation_fee'], 2) }}</p>
                    <p>Maximum eligible refund: {{ $booking->currency }} {{ number_format((float) $cancellationQuote['maximum_refund_due'], 2) }}</p>
                    <p>Refund requests are separate from successful provider settlement and may take additional time.</p>
                @endif
                <form action="{{ route('user.bookings.cancel', $booking->reference) }}" method="POST" class="az-form-grid">
                    @csrf
                    <label class="wide"><span>Why are you cancelling?</span>
                        <textarea name="reason" required minlength="4" maxlength="500"></textarea></label>
                    <button type="submit" class="az-user-button az-user-button--dark"
                        onclick="return confirm('Cancel this booking? Your existing dates will be released.');">
                        Confirm booking cancellation
                    </button>
                </form>
            </div>
        </div>
    @endif

    <div class="az-user-panel">
        <header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Change requests</h2><p class="az-user-panel-subtitle">Status and staff response</p></div></header>
        <div class="az-user-panel-body az-user-list">
            @forelse($booking->modificationRequests as $requestItem)
                <div class="az-user-list-item">
                    <div>
                        <h3>{{ Str::headline($requestItem->type) }} · {{ $requestItem->reference }}</h3>
                        <p>{{ $requestItem->guest_note ?: 'No additional note.' }}</p>
                        @if($requestItem->staff_note)<p><strong>Resavar:</strong> {{ $requestItem->staff_note }}</p>@endif
                        @if($requestItem->status === 'quoted' && $requestItem->quote_expires_at?->isFuture() && in_array($requestItem->type, ['date_change','add_extras','room_change'], true))
                            @php($offer = $requestItem->price_quote ?? [])
                            @if($requestItem->type === 'date_change')
                                <p><strong>New dates:</strong> {{ $offer['new_check_in'] ?? '' }} to {{ $offer['new_check_out'] ?? '' }}</p>
                            @elseif($requestItem->type === 'room_change')
                                <p><strong>New accommodation:</strong> {{ $offer['quote']['accommodation_type_name'] ?? 'Room change' }}</p>
                                <p>Rate plan: {{ $offer['quote']['rate_plan_name'] ?? 'Standard' }} · Policy: {{ $offer['quote']['policy']['cancellation']['name'] ?? 'Property conditions' }}</p>
                            @else
                                <p><strong>Additional extras:</strong> {{ implode(', ', array_column($offer['quote']['add_ons'] ?? [], 'name')) }}</p>
                            @endif
                            <p><strong>Price:</strong> {{ $offer['currency'] ?? $booking->currency }}
                                {{ number_format((float) ($offer['new_total'] ?? 0), 2) }}
                                (current total {{ number_format((float) ($offer['old_total'] ?? 0), 2) }})</p>
                            <p>Offer expires {{ $requestItem->quote_expires_at->format('d M Y H:i') }}. Dates are subject to a final availability and price check.</p>
                            @if((float) ($offer['delta'] ?? 0) <= 0)
                                <form method="POST" action="{{ route('user.bookings.modifications.accept', [$booking->reference, $requestItem]) }}">
                                    @csrf
                                    <button type="submit" class="az-user-button az-user-button--dark">Accept amended price</button>
                                </form>
                            @else
                                @php($amendmentPayment = $requestItem->payment_id ? \App\Models\Payment::query()->find($requestItem->payment_id) : null)
                                @if(! $amendmentPayment)
                                    <p>Your current booking remains unchanged until you pay the difference and approve the amended quotation.</p>
                                    <form method="POST" action="{{ route('user.bookings.modifications.pay', [$booking->reference, $requestItem]) }}">
                                        @csrf
                                        <button type="submit" class="az-user-button az-user-button--dark">
                                            Pay difference {{ $offer['currency'] ?? $booking->currency }} {{ number_format((float) ($offer['delta'] ?? 0), 2) }}
                                        </button>
                                    </form>
                                @elseif($amendmentPayment->status === 'successful_excess' && $amendmentPayment->verified_at)
                                    <p>Your additional payment is verified but not yet allocated. Confirm the current available dates below.</p>
                                    <form method="POST" action="{{ route('user.bookings.modifications.accept', [$booking->reference, $requestItem]) }}">
                                        @csrf
                                        <button type="submit" class="az-user-button az-user-button--dark">Accept and confirm amendment</button>
                                    </form>
                                @else
                                    <p>Your additional payment is {{ str_replace('_', ' ', $amendmentPayment->status) }}. Do not pay twice. The existing stay remains confirmed. Contact Resavar support if your payment is verified but the offer has expired.</p>
                                    @if($amendmentPayment->checkout_url && in_array($amendmentPayment->status, ['pending','initiated'], true))
                                        <a href="{{ $amendmentPayment->checkout_url }}" rel="nofollow noopener">Continue secure payment</a>
                                    @endif
                                @endif
                            @endif
                        @endif

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
        <div><h2 class="az-user-panel-title">Review your stay</h2><p class="az-user-panel-subtitle">Only completed stays can submit one review.</p></div>
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
            <label><span>Review language</span><select name="language">
                @foreach(['und'=>'Unspecified','en'=>'English','fr'=>'French','es'=>'Spanish','de'=>'German','pt'=>'Portuguese','ar'=>'Arabic','hi'=>'Hindi','zh'=>'Chinese','it'=>'Italian','yo'=>'Yoruba','ig'=>'Igbo','ha'=>'Hausa','other'=>'Other'] as $code => $label)
                    <option value="{{ $code }}" @selected(old('language', 'und') === $code)>{{ $label }}</option>
                @endforeach
            </select></label>
            <p class="wide">For everyone's privacy, do not include phone numbers, email addresses or external links in your review.</p>
            <label class="wide"><span>Review title</span><input name="title" maxlength="120"></label>
            <label class="wide"><span>What did you like?</span><textarea name="positive_feedback" maxlength="1500"></textarea></label>
            <label class="wide"><span>What could be better?</span><textarea name="negative_feedback" maxlength="1500"></textarea></label>
            <label class="wide"><span>Your review</span><textarea name="body" minlength="20" maxlength="3000" required></textarea></label>
            <button class="az-user-button az-user-button--dark" type="submit">Submit review</button>
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
        @if($booking->review->edited_at)<p><small>Updated {{ $booking->review->edited_at->diffForHumans() }}</small></p>@endif
        @if($booking->review->title)<h3>{{ $booking->review->title }}</h3>@endif
        <p>{{ $booking->review->body }}</p>
        <details style="margin-top:14px"><summary>Edit review</summary>
            <form method="POST" action="{{ route('user.reviews.update',$booking) }}" class="az-form-grid" style="margin-top:12px">
                @csrf @method('PATCH')
                @foreach(['rating'=>'Overall','cleanliness'=>'Cleanliness','comfort'=>'Comfort','facilities'=>'Facilities','location_score'=>'Location','staff_service'=>'Staff/service','value_score'=>'Value','wifi_score'=>'Wi-Fi'] as $name=>$label)
                    <label><span>{{ $label }}</span><select name="{{ $name }}" {{ $name==='wifi_score' ? '' : 'required' }}><option value="">Choose</option>@foreach([5,4,3,2,1] as $score)<option value="{{ $score }}" @selected((int)$booking->review->{$name}===$score)>{{ $score }}/5</option>@endforeach</select></label>
                @endforeach
                <label><span>Review language</span><select name="language">
                    @foreach(['und'=>'Unspecified','en'=>'English','fr'=>'French','es'=>'Spanish','de'=>'German','pt'=>'Portuguese','ar'=>'Arabic','hi'=>'Hindi','zh'=>'Chinese','it'=>'Italian','yo'=>'Yoruba','ig'=>'Igbo','ha'=>'Hausa','other'=>'Other'] as $code => $label)
                        <option value="{{ $code }}" @selected(old('language', $booking->review->language ?? 'und') === $code)>{{ $label }}</option>
                    @endforeach
                </select></label>
                <p class="wide">Remove personal contact details and website links before publishing a review.</p>
                <label class="wide"><span>Review title</span><input name="title" maxlength="120" value="{{ $booking->review->title }}"></label>
                <label class="wide"><span>What did you like?</span><textarea name="positive_feedback" maxlength="1500">{{ $booking->review->positive_feedback }}</textarea></label>
                <label class="wide"><span>What could be better?</span><textarea name="negative_feedback" maxlength="1500">{{ $booking->review->negative_feedback }}</textarea></label>
                <label class="wide"><span>Your review</span><textarea name="body" minlength="20" maxlength="3000" required>{{ $booking->review->body }}</textarea></label>
                <input type="hidden" name="trip_type" value="{{ $booking->review->trip_type }}">
                <button class="az-user-button az-user-button--dark" type="submit">Update review</button>
            </form>
        </details>
        @if($booking->review->admin_reply)
            <div class="az-user-alert"><strong>Resavar response</strong><p>{{ $booking->review->admin_reply }}</p></div>
        @endif
        @if(in_array($booking->review->status, ['hidden', 'flagged', 'archived'], true))
            <div class="az-user-alert" style="margin-top:14px">
                <strong>Review moderation decision</strong>
                <p>Your review is not currently public. You can submit one appeal for moderator review.</p>
                @if($booking->review->moderation_reason)
                    <p>Reason: {{ $booking->review->moderation_reason }}</p>
                @endif
                @if($booking->review->appeal)
                    <p role="status">Appeal status: {{ Str::headline($booking->review->appeal->status) }}</p>
                    @if($booking->review->appeal->decision_note)
                        <p>Moderator response: {{ $booking->review->appeal->decision_note }}</p>
                    @endif
                @else
                    <form method="POST" action="{{ route('user.reviews.appeal', $booking) }}" class="az-form-grid">
                        @csrf
                        <label class="wide"><span>Why do you disagree with this moderation decision?</span>
                            <textarea name="reason" required minlength="20" maxlength="2000" rows="4"></textarea>
                        </label>
                        <button type="submit" class="az-user-button az-user-button--dark">Submit appeal</button>
                    </form>
                @endif
            </div>
        @endif
    </div>
</section>
@endif
@endsection
