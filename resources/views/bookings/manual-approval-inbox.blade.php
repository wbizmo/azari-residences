@extends($isStaff ? 'admin.layouts.app' : 'layouts.user')
@section('title', 'Booking requests')
@if(! $isStaff)
    @section('kicker', 'Property Centre')
    @section('page_title', 'Booking requests')
@endif
@section('content')
<section class="{{ $isStaff ? 'az-panel' : 'az-user-panel' }}" aria-labelledby="requests-title">
    <header class="{{ $isStaff ? 'az-page-heading' : 'az-user-panel-header' }}">
        <div>
            <h1 id="requests-title">Accommodation booking requests</h1>
            <p>Requests are not confirmed or paid. Review the requested dates, occupancy and frozen quote before approving. Declining releases the reservation without recording a refund.</p>
        </div>
    </header>
    <div class="{{ $isStaff ? 'az-panel-body' : 'az-user-panel-body' }}">
        @if(session('success'))
            <p role="status">{{ session('success') }}</p>
        @endif
        @if($errors->any())
            <div role="alert">{{ $errors->first() }}</div>
        @endif

        @forelse($bookings as $booking)
            @php $prefix = $isStaff ? 'azari.admin.booking-requests.' : 'user.owner.booking-requests.'; @endphp
            <article class="az-panel" style="padding:18px;margin:0 0 18px">
                <h2>{{ $booking->property?->name ?? 'Accommodation' }}</h2>
                <p>Request {{ $booking->reference }} · {{ $booking->approval?->status === 'under_review' ? 'Under review' : 'Awaiting review' }}</p>
                <p>{{ $booking->check_in?->format('j M Y') }} – {{ $booking->check_out?->format('j M Y') }} · {{ $booking->rooms }} room(s) · {{ $booking->adults + $booking->children }} guest(s)</p>
                <p><strong>Frozen request quote:</strong> {{ $booking->currency }} {{ number_format((float) $booking->total, 2) }}. No funds have been collected.</p>
                <p>Respond by: {{ $booking->expires_at?->format('j M Y, H:i') ?? 'Unavailable' }}</p>
                @if($booking->expires_at?->isPast())
                    <p role="status">The review window has expired. Ask the guest to submit a new request.</p>
                @else
                    <div class="az-actions" style="display:flex;flex-wrap:wrap;gap:12px">
                        <form method="POST" action="{{ route($prefix.'review', $booking) }}">
                            @csrf
                            <button class="az-button" type="submit">Mark under review</button>
                        </form>
                        <form method="POST" action="{{ route($prefix.'approve', $booking) }}">
                            @csrf
                            <label>Approval note (optional)
                                <input type="text" name="note" maxlength="2000">
                            </label>
                            <button class="az-button" type="submit">Approve request</button>
                        </form>
                        <form method="POST" action="{{ route($prefix.'decline', $booking) }}">
                            @csrf
                            <label>Decline reason
                                <textarea name="reason" minlength="10" maxlength="2000" required></textarea>
                            </label>
                            <button class="az-button" type="submit">Decline request</button>
                        </form>
                    </div>
                    <details style="margin-top:12px">
                        <summary>Suggest different dates</summary>
                        <form method="POST" action="{{ route($prefix.'propose', $booking) }}" class="az-form-grid">
                            @csrf
                            <label>Alternative arrival <input type="date" name="check_in" required min="{{ today()->toDateString() }}"></label>
                            <label>Alternative departure <input type="date" name="check_out" required min="{{ today()->addDay()->toDateString() }}"></label>
                            <label>Explanation <textarea name="note" minlength="10" maxlength="2000" required></textarea></label>
                            <button class="az-button" type="submit">Propose dates</button>
                        </form>
                        <p>A suggestion never changes the existing request or charges the guest. The guest must create a new request with a current price.</p>
                    </details>
                @endif
                @if($isStaff)
                    <p><a href="{{ route('azari.admin.bookings.show', $booking) }}">View booking audit and payment history</a></p>
                @endif
            </article>
        @empty
            <p>No accommodation requests are waiting for review.</p>
        @endforelse
        {{ $bookings->links() }}
    </div>
</section>
@endsection
