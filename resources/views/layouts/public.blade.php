@php
    $defaultSeoTitle =
        'Resavar | Luxury Hotels, Residences, Apartments, Rooms and Hospitality';

    $defaultSeoDescription =
        'Discover Resavar, a premium hospitality and accommodation platform owned by Azari Luxury Properties Limited Explore hotels, residences, serviced apartments, rooms, concierge services, housekeeping, dining, airport transfers, guest services and secure online booking across Resavar locations.';

    $defaultSeoKeywords = implode(', ', [
        /*
        |--------------------------------------------------------------------------
        | Brand searches
        |--------------------------------------------------------------------------
        */
        'Resavar',
        'Resavar',
        'Resavar',
        'The Resavar',
        'Resavar Residence',
        'Resavar',
        'Resavar Hotel',
        'Resavar Hotels',
        'Resavar Luxury Hotels & Residences',
        'Resavar Luxury Properties',
        'Azari Luxury Properties Limited',
        'Azari Luxury Properties Limited',
        'Resavar Group',
        'Resavar Group',
        'Resavar Holdings',
        'Resavar Holdings Ltd',
        'Resavar Holdings Limited',
        'Resavar Hospitality',
        'Resavar Hospitality Group',
        'Resavar Hospitality Services',

        /*
        |--------------------------------------------------------------------------
        | Accommodation
        |--------------------------------------------------------------------------
        */
        'Resavar Accommodation',
        'Resavar Luxury Accommodation',
        'Resavar Apartments',
        'Resavar Serviced Apartments',
        'Resavar Rooms',
        'Resavar Guest House',
        'Resavar Guest Accommodation',
        'Resavar Short Stay',
        'Resavar Extended Stay',
        'Resavar Holiday Residences',
        'Resavar Vacation Residences',
        'Resavar Premium Residences',
        'Resavar Private Residences',
        'Resavar Executive Residences',
        'Resavar Corporate Accommodation',
        'Resavar Family Accommodation',

        /*
        |--------------------------------------------------------------------------
        | Booking and availability
        |--------------------------------------------------------------------------
        */
        'Resavar booking',
        'Resavar booking',
        'book Resavar',
        'Resavar online booking',
        'Resavar secure booking',
        'Resavar reservation platform',
        'Resavar booking platform',
        'Resavar availability',
        'Resavar availability',
        'check Resavar availability',
        'Resavar available rooms',
        'Resavar available apartments',
        'Resavar room booking',
        'Resavar apartment booking',
        'Resavar residence booking',
        'Resavar accommodation prices',
        'Resavar residence prices',
        'Resavar booking confirmation',
        'verify Resavar booking',
        'Resavar booking payment',

        /*
        |--------------------------------------------------------------------------
        | Guest services
        |--------------------------------------------------------------------------
        */
        'Resavar Guest Services',
        'Resavar Concierge',
        'Resavar Housekeeping',
        'Resavar Airport Transfers',
        'Resavar airport pickup',
        'Resavar Dining',
        'Resavar Restaurant',
        'Resavar Local Guide',
        'Resavar guest login',
        'Resavar customer portal',
        'Resavar Guest Portal',

        /*
        |--------------------------------------------------------------------------
        | Property and owner searches
        |--------------------------------------------------------------------------
        */
        'Resavar Properties',
        'Resavar Property',
        'Resavar Property Booking',
        'Resavar Property Management',
        'Resavar Residence Management',
        'Resavar Property Owners',
        'Resavar Property Listings',
        'Resavar property owner registration',
        'list property with Resavar',

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
        'Resavar website',
        'Resavar website',
        'what is Resavar',
        'who owns Resavar',
        'where is Resavar',
        'how to book Resavar',
        'contact Resavar',
        'Resavar contact information',
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

<!-- RESAVAR PWA RUNTIME START -->
<script src="/pwa-install.js" defer></script>
<!-- RESAVAR PWA RUNTIME END -->