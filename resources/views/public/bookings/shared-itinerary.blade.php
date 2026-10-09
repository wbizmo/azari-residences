<x-public-site.layout title="Shared stay itinerary | Resavar">
<main class="site-container" style="max-width:740px;margin:0 auto;padding:48px 20px 72px">
    <section class="reserva-property-section" aria-labelledby="shared-itinerary-title">
        <p class="eyebrow">Resavar stay itinerary</p>
        <h1 id="shared-itinerary-title">Shared stay summary</h1>
        <p>This read-only link was shared by the person who made the reservation. It contains only basic stay information.</p>

        <dl class="az-user-detail-grid">
            <div><dt>Property</dt><dd><strong>{{ $booking->property_name_snapshot }}</strong></dd></div>
            <div><dt>Check-in date</dt><dd><strong>{{ $booking->check_in->format('j M Y') }}</strong></dd></div>
            <div><dt>Check-out date</dt><dd><strong>{{ $booking->check_out->format('j M Y') }}</strong></dd></div>
            <div><dt>Stay length</dt><dd><strong>{{ $booking->nights }} {{ Str::plural('night', $booking->nights) }}</strong></dd></div>
            @if(filled($booking->accommodation_type_name_snapshot))
                <div><dt>Booked accommodation</dt><dd><strong>{{ $booking->accommodation_type_name_snapshot }}</strong></dd></div>
            @endif
        </dl>

        <p><small>Link expires {{ $expiresAt->format('j M Y, H:i T') }}. The guest can revoke this link at any time.</small></p>
        <p><small>This page does not disclose guest identity, payment information, booking reference or identity documents, and cannot authorize check-in or changes to a reservation.</small></p>
        <a href="{{ route('home') }}" class="button button-secondary">Visit Resavar</a>
    </section>
</main>
</x-public-site.layout>
