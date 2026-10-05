@php
    $defaultSeoTitle =
        'Reserva | Luxury Hotels, Residences, Apartments, Rooms and Hospitality';

    $defaultSeoDescription =
        'Discover Reserva, a premium hospitality and accommodation platform owned by Reserva Luxury Properties Ltd. Explore hotels, residences, serviced apartments, rooms, concierge services, housekeeping, dining, airport transfers, guest services and secure online booking across Reserva locations.';

    $defaultSeoKeywords = implode(', ', [
        /*
        |--------------------------------------------------------------------------
        | Brand searches
        |--------------------------------------------------------------------------
        */
        'Reserva',
        'Reserva',
        'Reserva',
        'The Reserva',
        'Reserva Residence',
        'Reserva',
        'Reserva Hotel',
        'Reserva Hotels',
        'Reserva Luxury Hotels & Residences',
        'Reserva Luxury Properties',
        'Reserva Luxury Properties Ltd',
        'Reserva Luxury Properties Limited',
        'Reserva Group',
        'Reserva Group',
        'Reserva Holdings',
        'Reserva Holdings Ltd',
        'Reserva Holdings Limited',
        'Reserva Hospitality',
        'Reserva Hospitality Group',
        'Reserva Hospitality Services',

        /*
        |--------------------------------------------------------------------------
        | Accommodation
        |--------------------------------------------------------------------------
        */
        'Reserva Accommodation',
        'Reserva Luxury Accommodation',
        'Reserva Apartments',
        'Reserva Serviced Apartments',
        'Reserva Rooms',
        'Reserva Guest House',
        'Reserva Guest Accommodation',
        'Reserva Short Stay',
        'Reserva Extended Stay',
        'Reserva Holiday Residences',
        'Reserva Vacation Residences',
        'Reserva Premium Residences',
        'Reserva Private Residences',
        'Reserva Executive Residences',
        'Reserva Corporate Accommodation',
        'Reserva Family Accommodation',

        /*
        |--------------------------------------------------------------------------
        | Booking and availability
        |--------------------------------------------------------------------------
        */
        'Reserva booking',
        'Reserva booking',
        'book Reserva',
        'Reserva online booking',
        'Reserva secure booking',
        'Reserva reservation platform',
        'Reserva booking platform',
        'Reserva availability',
        'Reserva availability',
        'check Reserva availability',
        'Reserva available rooms',
        'Reserva available apartments',
        'Reserva room booking',
        'Reserva apartment booking',
        'Reserva residence booking',
        'Reserva accommodation prices',
        'Reserva residence prices',
        'Reserva booking confirmation',
        'verify Reserva booking',
        'Reserva booking payment',

        /*
        |--------------------------------------------------------------------------
        | Guest services
        |--------------------------------------------------------------------------
        */
        'Reserva Guest Services',
        'Reserva Concierge',
        'Reserva Housekeeping',
        'Reserva Airport Transfers',
        'Reserva airport pickup',
        'Reserva Dining',
        'Reserva Restaurant',
        'Reserva Local Guide',
        'Reserva guest login',
        'Reserva customer portal',
        'Reserva Guest Portal',

        /*
        |--------------------------------------------------------------------------
        | Property and owner searches
        |--------------------------------------------------------------------------
        */
        'Reserva Properties',
        'Reserva Property',
        'Reserva Property Booking',
        'Reserva Property Management',
        'Reserva Residence Management',
        'Reserva Property Owners',
        'Reserva Property Listings',
        'Reserva property owner registration',
        'list property with Reserva',

        /*
        |--------------------------------------------------------------------------
        | General hospitality discovery
        |--------------------------------------------------------------------------
        */
        'luxury hotel booking',
        'luxury residence booking',
        'luxury apartment booking',
        'serviced apartment booking',
        'short stay accommodation',
        'holiday accommodation',
        'business travel accommodation',
        'corporate accommodation',
        'premium accommodation',
        'private accommodation',
        'secure accommodation booking',
        'hotel booking in Nigeria',
        'hotel booking in Lagos',
        'hotel booking in Rwanda',
        'hotel booking in USA',

        /*
        |--------------------------------------------------------------------------
        | Informational searches
        |--------------------------------------------------------------------------
        */
        'Reserva website',
        'Reserva website',
        'what is Reserva',
        'who owns Reserva',
        'where is Reserva',
        'how to book Reserva',
        'contact Reserva',
        'Reserva contact information',
    ]);

    $resolvedTitle =
        $title
        ?? trim($__env->yieldContent('title'))
        ?: $defaultSeoTitle;

    $resolvedDescription =
        $description
        ?? trim($__env->yieldContent('description'))
        ?: $defaultSeoDescription;

    $resolvedKeywords =
        $keywords
        ?? trim($__env->yieldContent('keywords'))
        ?: $defaultSeoKeywords;
@endphp

<x-public.layout
    :title="$resolvedTitle"
    :description="$resolvedDescription"
    :keywords="$resolvedKeywords"
    :canonical="$canonical ?? null"
    :image="$image ?? null"
    :type="$type ?? 'website'"
    :body-class="$bodyClass ?? ''"
>
    @yield('content')
</x-public.layout>

<!-- RESERVA PWA RUNTIME START -->
<script src="/pwa-install.js" defer></script>
<!-- RESERVA PWA RUNTIME END -->