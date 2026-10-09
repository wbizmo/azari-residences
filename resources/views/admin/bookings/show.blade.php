@extends('admin.layouts.app')
@section('title', 'Booking '.$booking->reference)
@section('content')
<section class="az-page-heading">
    <div>
        <span class="eyebrow">Booking record</span>
        <h1>{{ $booking->reference }}</h1>
        <p>{{ $booking->property?->name }} · {{ $booking->accommodationType?->name ?? $booking->accommodation_type_name_snapshot }}</p>
    </div>
    <div class="az-actions" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
        <a class="az-button" href="{{ route('azari.admin.bookings.receipt', $booking) }}" target="_blank" rel="noopener noreferrer">
            <span class="material-symbols-outlined" aria-hidden="true">print</span> Print invoice
        </a>
        <a class="az-button" href="{{ route('azari.admin.bookings.documents', [$booking, 'confirmation']) }}" target="_blank" rel="noopener noreferrer">Confirmation PDF</a>
        <a class="az-button" href="{{ route('azari.admin.bookings.documents', [$booking, 'invoice']) }}" target="_blank" rel="noopener noreferrer">Invoice PDF</a>
        @if($booking->receiptAvailable())
            <a class="az-button" href="{{ route('azari.admin.bookings.documents', [$booking, 'receipt']) }}" target="_blank" rel="noopener noreferrer">Receipt PDF</a>
        @endif
        <a class="az-button" href="{{ route('azari.admin.bookings.index') }}">Back to bookings</a>
    </div>
</section>

<div class="az-detail-grid">
    <section class="az-panel">
        <h2>Stay details</h2>
        <dl class="az-definition-list">
            <dt>Property</dt><dd>{{ $booking->property?->name }}</dd>
            <dt>Accommodation</dt><dd>{{ $booking->accommodationType?->name ?? $booking->accommodation_type_name_snapshot ?? '—' }}</dd>
            <dt>Rate plan</dt><dd>{{ $booking->ratePlan?->name ?? $booking->rate_plan_name_snapshot ?? '—' }}</dd>
            <dt>Dates</dt><dd>{{ $booking->check_in?->format('d M Y') }} to {{ $booking->check_out?->format('d M Y') }}</dd>
            <dt>Occupancy</dt><dd>{{ $booking->adults }} adults · {{ $booking->children }} children · {{ $booking->rooms }} {{ Str::plural('room',$booking->rooms) }}</dd>
            <dt>Status</dt><dd>{{ Str::headline($booking->status) }}</dd>
            @if((float)$booking->discount_total > 0)
                <dt>Voucher {{ $booking->voucher_code }}</dt>
                <dd>-{{ $booking->currency }} {{ number_format((float)$booking->discount_total,2) }}</dd>
            @endif
            <dt>Total</dt><dd>{{ $booking->currency }} {{ number_format((float) $booking->total, 2) }}</dd>
            <dt>Paid</dt><dd>{{ $booking->currency }} {{ number_format($booking->successfulPaymentsTotal(),2) }}</dd>
            <dt>Refunded</dt><dd>{{ $booking->currency }} {{ number_format($booking->successfulRefundsTotal(),2) }}</dd>
            <dt>Outstanding</dt><dd>{{ $booking->currency }} {{ number_format($booking->balanceDue(),2) }}</dd>
        </dl>

        @if($allowedTransitions !== [])
            <form method="POST" action="{{ route('azari.admin.bookings.status',$booking) }}" class="az-form-grid az-contained-form">
                @csrf
                @method('PUT')
                <label>
                    <span>Next status</span>
                    <select name="status" required>
                        @foreach($allowedTransitions as $status)
                            <option value="{{ $status }}">{{ Str::headline($status) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="az-span-2"><span>Operational note</span><textarea name="note" maxlength="2000"></textarea></label>
                <button class="az-button" type="submit">Apply status transition</button>
            </form>
        @endif
    </section>

    <section class="az-panel">
        <h2>Lead guest</h2>
        <dl class="az-definition-list">
            <dt>Name</dt><dd>{{ $booking->guest_name }}</dd>
            <dt>Email</dt><dd>{{ $booking->guest_email }}</dd>
            <dt>Phone</dt><dd>{{ $booking->guest_phone ?: 'Not supplied' }}</dd>
            <dt>Nationality</dt><dd>{{ $booking->nationality ?: 'Not supplied' }}</dd>
            <dt>Address</dt><dd>{{ collect([$booking->address, $booking->city, $booking->country])->filter()->implode(', ') ?: 'Not supplied' }}</dd>
            <dt>Arrival time</dt><dd>{{ $booking->arrival_time ?: 'Not supplied' }}</dd>
            <dt>Special requests</dt><dd>{{ $booking->guest_notes ?: 'None' }}</dd>
        </dl>
    </section>
</div>

<div class="az-detail-grid">
    <section class="az-panel">
        <h2>Pricing and policy snapshot</h2>
        <pre style="white-space:pre-wrap;overflow-wrap:anywhere">{{ json_encode([
            'pricing' => $booking->pricing_snapshot,
            'policy' => $booking->policy_snapshot,
        ], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
    </section>

    <section class="az-panel">
        <h2>Operational notes</h2>
        <form method="POST" action="{{ route('azari.admin.bookings.notes.store',$booking) }}" class="az-form-grid az-contained-form">
            @csrf
            <label><span>Visibility</span><select name="visibility"><option value="staff">Staff only</option><option value="guest">Guest-visible</option></select></label>
            <label class="az-span-2"><span>Note</span><textarea name="body" maxlength="3000" required></textarea></label>
            <button class="az-button" type="submit">Add note</button>
        </form>
        @forelse($booking->operationalNotes as $note)
            <article class="az-s78-event">
                <strong>{{ $note->author?->name ?? 'System' }} · {{ Str::headline($note->visibility) }}</strong>
                <p>{{ $note->body }}</p>
                <small>{{ $note->created_at?->format('d M Y H:i') }}</small>
            </article>
        @empty
            <p>No operational notes.</p>
        @endforelse
    </section>
</div>

<section class="az-panel">
    <h2>Guests</h2>
    <div class="az-booking-grid">
        @forelse($guests as $guest)
            <article class="az-booking-card">
                <span>{{ ucfirst($guest->type) }} {{ $guest->position }}</span>
                <h3>{{ $guest->full_name }}</h3>
            </article>
        @empty
            <p>No additional guests.</p>
        @endforelse
    </div>
    <div class="az-pagination-block">{{ $guests->onEachSide(1)->links() }}</div>
</section>

<section class="az-panel">
    <h2>Payments and refunds</h2>
    <div class="az-admin-table-wrap">
        <table class="az-admin-table">
            <thead><tr><th>Reference</th><th>Provider</th><th>Kind</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
                @forelse($payments as $payment)
                    <tr>
                        <td><a href="{{ route('azari.admin.payments.show',$payment) }}">{{ $payment->reference }}</a></td>
                        <td>{{ ucfirst($payment->provider) }}</td>
                        <td>{{ Str::headline($payment->payment_kind ?? 'full') }}</td>
                        <td>{{ $payment->currency }} {{ number_format((float)$payment->amount, 2) }}</td>
                        <td>{{ Str::headline($payment->status) }}</td>
                        <td>{{ optional($payment->paid_at ?: $payment->created_at)->format('d M Y, g:i A') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">No payments recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="az-pagination-block">{{ $payments->onEachSide(1)->links() }}</div>

    @if($booking->refunds->isNotEmpty())
        <h3>Refunds</h3>
        @foreach($booking->refunds as $refund)
            <p>{{ $refund->reference }} · {{ $refund->currency }} {{ number_format((float)$refund->amount,2) }} · {{ Str::headline($refund->status) }}</p>
        @endforeach
    @endif
</section>

<section class="az-panel">
    <h2>Status history</h2>
    @forelse($statusHistory as $history)
        <article class="az-s78-event">
            <strong>{{ Str::headline($history->from_status ?: 'created') }} → {{ Str::headline($history->to_status) }}</strong>
            <p>{{ $history->note ?: 'No note' }} · {{ $history->changedBy?->name ?? 'System' }}</p>
            <small>{{ $history->created_at?->format('d M Y H:i:s') }}</small>
        </article>
    @empty
        <p>No status history recorded.</p>
    @endforelse
    {{ $statusHistory->links() }}
</section>

@if($booking->modificationRequests->isNotEmpty())
<section class="az-panel">
    <h2>Guest change requests</h2>
    @foreach($booking->modificationRequests as $requestItem)
        <article class="az-s78-event">
            <strong>{{ $requestItem->reference }} · {{ Str::headline($requestItem->type) }} · {{ Str::headline($requestItem->status) }}</strong>
            <p>{{ $requestItem->guest_note ?: 'No guest note.' }}</p>
            <pre style="white-space:pre-wrap">{{ json_encode($requestItem->requested_changes, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
            @if($requestItem->status === 'pending' && auth()->user()?->hasPermission('bookings.edit'))
                <form method="POST" action="{{ route('azari.admin.bookings.modifications.review', [$booking, $requestItem]) }}" class="az-form-grid az-contained-form">
                    @csrf
                    @method('PUT')
                    <label class="az-field az-span-2"><span>Staff review note</span><textarea name="staff_note" maxlength="2000"></textarea></label>
                    <div class="az-form-actions az-span-2">
                        @if(in_array($requestItem->type, ['date_change', 'add_extras', 'room_change'], true))
                            <p>Check availability and offer a repriced date change for guest acceptance. This does not reserve inventory.</p>
                            <button type="submit" formaction="{{ route('azari.admin.bookings.modifications.offer', [$booking, $requestItem]) }}"
                                    formmethod="POST" class="az-button az-button--primary">
                                Offer amended price
                            </button>
                        @endif
                        @if(in_array($requestItem->type, ['contact_details', 'arrival_time', 'guest_change'], true))
                            <button type="submit" name="decision" value="approve" class="az-button az-button--primary">Approve and apply</button>
                        @endif
                        <button type="submit" name="decision" value="decline" class="az-button az-button--secondary">Decline request</button>
                    </div>
                    @unless(in_array($requestItem->type, ['contact_details', 'arrival_time', 'guest_change'], true))
                        <p>Inventory-changing or financial modifications require a separate repricing and confirmation workflow; they cannot be applied from this panel.</p>
                    @endunless
                </form>
            @endif
        </article>
    @endforeach
</section>
@endif

@if(!in_array($booking->status, ['cancelled', 'completed', 'checked_out'], true))
<section class="az-panel az-cancellation-panel">
    <h2>Cancel booking</h2>
    <form method="POST" action="{{ route('azari.admin.bookings.cancel', $booking) }}" class="az-form-grid az-contained-form">
        @csrf
        @method('PUT')
        <label class="az-field az-span-2"><span>Cancellation reason</span><textarea name="reason" required maxlength="1000"></textarea></label>
        <label class="az-field az-span-2"><span>Internal note</span><textarea name="internal_note" maxlength="3000"></textarea></label>
        <div class="az-form-actions az-span-2"><button class="az-button az-button--danger" type="submit">Cancel booking</button></div>
    </form>
</section>
@endif
@endsection
