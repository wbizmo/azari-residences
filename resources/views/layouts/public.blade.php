@php
    $defaultSeoTitle =
        'Resavar | Luxury Hotels, Residences, Apartments, Rooms and Hospitality';

    $defaultSeoDescription =
        'Discover Resavar, a premium hospitality and accommodation platform owned by Azari Luxury Properties Ltd. Explore hotels, residences, serviced apartments, rooms, concierge services, housekeeping, dining, airport transfers, guest services and secure online booking across Azari locations.';

    $defaultSeoKeywords = implode(', ', [
        /*
        |--------------------------------------------------------------------------
        | Brand searches
        |--------------------------------------------------------------------------
        */
        'Azari',
        'Resavar',
        'Resavar',
        'The Resavar',
        'Azari Residence',
        'Resavar',
        'Azari Hotel',
        'Azari Hotels',
        'Azari Luxury Hotels & Residences',
        'Azari Luxury Properties',
        'Azari Luxury Properties Ltd',
        'Azari Luxury Properties Limited',
        'Azari Group',
        'Resavar Group',
        'Azari Holdings',
        'Azari Holdings Ltd',
        'Azari Holdings Limited',
        'Azari Hospitality',
        'Azari Hospitality Group',
        'Azari Hospitality Services',

        /*
        |--------------------------------------------------------------------------
        | Accommodation
        |--------------------------------------------------------------------------
        */
        'Azari Accommodation',
        'Azari Luxury Accommodation',
        'Azari Apartments',
        'Azari Serviced Apartments',
        'Azari Rooms',
        'Resavar Guest House',
        'Resavar Guest Accommodation',
        'Azari Short Stay',
        'Azari Extended Stay',
        'Azari Holiday Residences',
        'Azari Vacation Residences',
        'Azari Premium Residences',
        'Azari Private Residences',
        'Azari Executive Residences',
        'Azari Corporate Accommodation',
        'Azari Family Accommodation',

        /*
        |--------------------------------------------------------------------------
        | Booking and availability
        |--------------------------------------------------------------------------
        */
        'Azari booking',
        'Resavar booking',
        'book Resavar',
        'Azari online booking',
        'Azari secure booking',
        'Azari reservation platform',
        'Azari booking platform',
        'Azari availability',
        'Resavar availability',
        'check Azari availability',
        'Azari available rooms',
        'Azari available apartments',
        'Azari room booking',
        'Azari apartment booking',
        'Azari residence booking',
        'Azari accommodation prices',
        'Azari residence prices',
        'Azari booking confirmation',
        'verify Azari booking',
        'Azari booking payment',

        /*
        |--------------------------------------------------------------------------
        | Guest services
        |--------------------------------------------------------------------------
        */
        'Resavar Guest Services',
        'Azari Concierge',
        'Azari Housekeeping',
        'Azari Airport Transfers',
        'Azari airport pickup',
        'Azari Dining',
        'Azari Restaurant',
        'Azari Local Guide',
        'Resavar guest login',
        'Azari customer portal',
        'Resavar Guest Portal',

        /*
        |--------------------------------------------------------------------------
        | Property and owner searches
        |--------------------------------------------------------------------------
        */
        'Azari Properties',
        'Azari Property',
        'Azari Property Booking',
        'Azari Property Management',
        'Azari Residence Management',
        'Azari Property Owners',
        'Azari Property Listings',
        'Azari property owner registration',
        'list property with Azari',

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
        'Azari website',
        'Resavar website',
        'what is Resavar',
        'who owns Resavar',
        'where is Resavar',
        'how to book Resavar',
        'contact Resavar',
        'Azari contact information',
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

<!-- AZARI PWA RUNTIME START -->
<script src="/pwa-install.js" defer></script>
<!-- AZARI PWA RUNTIME END -->