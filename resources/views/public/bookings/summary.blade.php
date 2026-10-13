<x-public-site.layout title="Booking {{ $booking->reference }}"><main class="site-container az-s56-page"><header><span class="eyebrow">Booking status</span><h1>{{ in_array($booking->status,['confirmed','paid','check_in','checked_in'])?'Booking confirmed':'Booking request recorded' }}</h1><p>Your booking reference is <strong>{{ $booking->reference }}</strong>. Keep it for payment, arrival verification and support.</p></header>@if(session('success'))<div class="az-notice">{{ session('success') }}</div>@endif @if(session('warning'))<div class="az-alert">{{ session('warning') }}</div>@endif<section class="az-panel"><p>Status: {{ ucfirst(str_replace('_',' ',$booking->status)) }}</p><p>Payment status: {{ ucfirst($booking->payments->first()?->status ?? 'not started') }}</p><p>@if($booking->approval)
    <p role="status"><strong>Request review:</strong> {{ Str::headline($booking->approval->status) }}.
        {{ $booking->approval->status === 'approved' ? 'You may proceed under the payment terms; this is not yet a paid confirmation.' : 'Do not make a payment until the request is approved.' }}
    </p>
    @if($booking->approval->review_note)
        <p>Response: {{ $booking->approval->review_note }}</p>
    @endif
    @if($booking->approval->proposed_check_in)
        <p>Proposed dates: {{ $booking->approval->proposed_check_in->format('j M Y') }} – {{ $booking->approval->proposed_check_out?->format('j M Y') }}. Submit a new quote and request to accept these dates.</p>
    @endif
@endif
Residence: {{ $booking->property->name }}</p><p>Lead guest: {{ $booking->guest_name }}</p><p>Dates: {{ $booking->check_in->format('d M Y') }} to {{ $booking->check_out->format('d M Y') }}</p><p>Guests: {{ $booking->adults }} adults · {{ $booking->children }} children</p><strong>Total: {{ $booking->currency }} {{ number_format($booking->total,2) }}</strong><div class="az-actions">@if($booking->canAcceptPayment())<a class="button button-primary" href="{{ route('public.payment.select',$booking->reference) }}">Pay balance</a>@endif @auth @if(!auth()->user()->isStaff())<a class="button" href="{{ route('user.bookings.show',$booking->reference) }}">Open guest dashboard</a>@endif @endauth<button type="button" class="button" onclick="window.print()">Print confirmation</button></div></section></main></x-public-site.layout>
