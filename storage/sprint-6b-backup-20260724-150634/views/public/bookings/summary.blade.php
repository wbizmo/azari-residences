<x-public-site.layout title="Booking {{ $booking->reference }}">
<main class="site-container az-s56-page"><header><span class="eyebrow">Booking created</span><h1>{{ $booking->reference }}</h1><p>Keep this reference for verification and support.</p></header>
<section class="az-s56-summary"><p>Status: {{ ucfirst(str_replace('_',' ',$booking->status)) }}</p><p>Residence: {{ $booking->property->name }}</p><p>Guest: {{ $booking->guest_name }}</p><p>Dates: {{ $booking->check_in->format('d M Y') }} – {{ $booking->check_out->format('d M Y') }}</p><strong>Total: {{ $booking->currency }} {{ number_format($booking->total,2) }}</strong></section>
</main></x-public-site.layout>
