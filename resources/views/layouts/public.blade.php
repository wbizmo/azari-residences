@php
    $defaultSeoTitle =
        'Resarva | Luxury Hotels, Residences, Apartments, Rooms and Hospitality';

    $defaultSeoDescription =
        'Discover Resarva, a premium hospitality and accommodation platform owned by Resarva Luxury Properties Ltd. Explore hotels, residences, serviced apartments, rooms, concierge services, housekeeping, dining, airport transfers, guest services and secure online booking across Resarva locations.';

    $defaultSeoKeywords = implode(', ', [
        /*
        |--------------------------------------------------------------------------
        | Brand searches
        |--------------------------------------------------------------------------
        */
        'Resarva',
        'Resarva',
        'Resarva',
        'The Resarva',
        'Resarva Residence',
        'Resarva',
        'Resarva Hotel',
        'Resarva Hotels',
        'Resarva Luxury Hotels & Residences',
        'Resarva Luxury Properties',
        'Resarva Luxury Properties Ltd',
        'Resarva Luxury Properties Limited',
        'Resarva Group',
        'Resarva Group',
        'Resarva Holdings',
        'Resarva Holdings Ltd',
        'Resarva Holdings Limited',
        'Resarva Hospitality',
        'Resarva Hospitality Group',
        'Resarva Hospitality Services',

        /*
        |--------------------------------------------------------------------------
        | Accommodation
        |--------------------------------------------------------------------------
        */
        'Resarva Accommodation',
        'Resarva Luxury Accommodation',
        'Resarva Apartments',
        'Resarva Serviced Apartments',
        'Resarva Rooms',
        'Resarva Guest House',
        'Resarva Guest Accommodation',
        'Resarva Short Stay',
        'Resarva Extended Stay',
        'Resarva Holiday Residences',
        'Resarva Vacation Residences',
        'Resarva Premium Residences',
        'Resarva Private Residences',
        'Resarva Executive Residences',
        'Resarva Corporate Accommodation',
        'Resarva Family Accommodation',

        /*
        |--------------------------------------------------------------------------
        | Booking and availability
        |--------------------------------------------------------------------------
        */
        'Resarva booking',
        'Resarva booking',
        'book Resarva',
        'Resarva online booking',
        'Resarva secure booking',
        'Resarva reservation platform',
        'Resarva booking platform',
        'Resarva availability',
        'Resarva availability',
        'check Resarva availability',
        'Resarva available rooms',
        'Resarva available apartments',
        'Resarva room booking',
        'Resarva apartment booking',
        'Resarva residence booking',
        'Resarva accommodation prices',
        'Resarva residence prices',
        'Resarva booking confirmation',
        'verify Resarva booking',
        'Resarva booking payment',

        /*
        |--------------------------------------------------------------------------
        | Guest services
        |--------------------------------------------------------------------------
        */
        'Resarva Guest Services',
        'Resarva Concierge',
        'Resarva Housekeeping',
        'Resarva Airport Transfers',
        'Resarva airport pickup',
        'Resarva Dining',
        'Resarva Restaurant',
        'Resarva Local Guide',
        'Resarva guest login',
        'Resarva customer portal',
        'Resarva Guest Portal',

        /*
        |--------------------------------------------------------------------------
        | Property and owner searches
        |--------------------------------------------------------------------------
        */
        'Resarva Properties',
        'Resarva Property',
        'Resarva Property Booking',
        'Resarva Property Management',
        'Resarva Residence Management',
        'Resarva Property Owners',
        'Resarva Property Listings',
        'Resarva property owner registration',
        'list property with Resarva',

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
        'Resarva website',
        'Resarva website',
        'what is Resarva',
        'who owns Resarva',
        'where is Resarva',
        'how to book Resarva',
        'contact Resarva',
        'Resarva contact information',
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

    $resolvedRobots = trim($__env->yieldContent('robots')) ?: null;
@endphp

<x-public.layout
    :title="$resolvedTitle"
    :description="$resolvedDescription"
    :keywords="$resolvedKeywords"
    :canonical="$canonical ?? null"
    :image="$image ?? null"
    :type="$type ?? 'website'"
    :robots="$resolvedRobots"
    :body-class="$bodyClass ?? ''"
>
    @yield('content')
</x-public.layout>

<!-- RESARVA PWA RUNTIME START -->
<script src="/pwa-install.js" defer></script>
<!-- RESARVA PWA RUNTIME END -->