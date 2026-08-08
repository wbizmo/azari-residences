@php
    $defaultSeoTitle =
        'Azari Hotels & Residences | Luxury Hotels, Residences, Apartments, Rooms and Hospitality';

    $defaultSeoDescription =
        'Discover Azari Hotels & Residences, a premium hospitality and accommodation platform owned by Azari Luxury Properties Ltd. Explore hotels, residences, serviced apartments, rooms, concierge services, housekeeping, dining, airport transfers, guest services and secure online booking across Azari locations.';

    $defaultSeoKeywords = implode(', ', [
        /*
        |--------------------------------------------------------------------------
        | Brand searches
        |--------------------------------------------------------------------------
        */
        'Azari',
        'Azari Hotels & Residences',
        'The Azari',
        'The Azari Hotels & Residences',
        'Azari Residence',
        'Azari Residences',
        'Azari Hotel',
        'Azari Hotels',
        'Azari Luxury Hotels & Residences',
        'Azari Luxury Properties',
        'Azari Luxury Properties Ltd',
        'Azari Luxury Properties Limited',
        'Azari Group',
        'The Azari Group',
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
        'Azari Guest House',
        'Azari Guest Accommodation',
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
        'Azari Hotels & Residences booking',
        'book Azari Hotels & Residences',
        'Azari online booking',
        'Azari secure booking',
        'Azari reservation platform',
        'Azari booking platform',
        'Azari availability',
        'Azari Hotels & Residences availability',
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
        'Azari Guest Services',
        'Azari Concierge',
        'Azari Housekeeping',
        'Azari Airport Transfers',
        'Azari airport pickup',
        'Azari Dining',
        'Azari Restaurant',
        'Azari Local Guide',
        'Azari guest login',
        'Azari customer portal',
        'Azari Guest Portal',

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
        'Azari Hotels & Residences website',
        'what is Azari Hotels & Residences',
        'who owns Azari Hotels & Residences',
        'where is Azari Hotels & Residences',
        'how to book Azari Hotels & Residences',
        'contact Azari Hotels & Residences',
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