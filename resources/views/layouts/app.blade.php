<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.material-symbols-preload')
        @include('partials.azari-head-assets')

        @php
            $seoTitle = 'Resarva | Luxury Hotels & Residences, Apartments, Rooms and Hospitality';
            $seoDescription = 'Discover Resarva, a premium hospitality and accommodation platform owned by Resarva Luxury Properties Ltd. Explore hotels & residences, apartments, rooms, concierge services, dining, housekeeping, airport transfers and secure online booking.';
            $seoUrl = url()->current();
            $seoImage = asset('images/resavar-logo-light.png');

            $seoLongDescription = <<<'DESCRIPTION'
    Resarva Residences is a premium hospitality and accommodation brand owned and operated by Resarva Luxury Properties Ltd, providing thoughtfully managed residences, apartments, rooms, and guest services for business travellers, families, couples, groups, and leisure guests. The platform makes discovering suitable accommodation simple by presenting detailed property information, room features, photographs, locations, guest capacities, amenities, policies, pricing, and date-based availability in one accessible experience.

    Guests can explore Resarva Residences properties, compare accommodation options, check available dates, select their preferred stay period, provide guest information, submit required identification, review booking details, and proceed securely to payment. The booking system is designed to reduce uncertainty by checking existing reservations, active booking holds, maintenance periods, property capacity, and applicable stay requirements before confirming availability.

    Beyond accommodation, Resarva Residences supports a complete hospitality experience through concierge assistance, housekeeping requests, airport transfer arrangements, restaurant and dining information, local guides, service requests, customer support, booking verification, notifications, invoices, receipts, and guest account management. Registered guests can manage bookings, payments, identity documents, additional guests, service requests, support tickets, notifications, profiles, and account security from a dedicated customer area.

    Resarva Residences also enables eligible property owners to submit properties for professional review and possible listing within the platform. Approved properties can be managed through structured booking, revenue, balance, and withdrawal processes while remaining subject to Resarva standards and administrative oversight.

    Resarva Luxury Properties Ltd maintains the platform as part of the wider Resarva ecosystem associated with Resarva Group and Resarva Holdings. Its objective is to combine dependable property management, refined hospitality, secure digital booking, responsive guest support, and carefully selected accommodation. Whether a guest requires a short stay, an extended residence, a private apartment, a comfortable room, or coordinated hospitality services, Resarva Residences provides a convenient starting point for planning and managing the complete stay.
    DESCRIPTION;

            $nameKeywords = [
                'Resarva', 'Resarva', 'The Resarva', 'The Resarva',
                'Resarva Residence', 'Resarva Luxury Hotels & Residences', 'Resarva Luxury Properties',
                'Resarva Luxury Properties Ltd', 'Resarva Luxury Properties Limited',
                'Resarva Group', 'The Resarva Group', 'Resarva Holdings', 'Resarva Holdings Ltd',
                'Resarva Holdings Limited', 'Resarva Hospitality', 'Resarva Hospitality Group',
                'Resarva Hospitality Services', 'Resarva Accommodation', 'Resarva Apartments',
                'Resarva Rooms', 'Resarva Properties', 'Resarva Property', 'Resarva Property Booking',
                'Resarva Residence Booking', 'Resarva Booking', 'Resarva Hotel',
                'Resarva Hotels', 'Resarva Guest House', 'Resarva Guest Accommodation',
                'Resarva Serviced Apartments', 'Resarva Short Stay', 'Resarva Extended Stay',
                'Resarva Holiday Hotels & Residences', 'Resarva Vacation Hotels & Residences',
                'Resarva Premium Hotels & Residences', 'Resarva Private Hotels & Residences',
                'Resarva Executive Hotels & Residences', 'Resarva Corporate Accommodation',
                'Resarva Family Accommodation', 'Resarva Luxury Accommodation',
                'Resarva Property Management', 'Resarva Residence Management',
                'Resarva Resort Management', 'Resarva Guest Services', 'Resarva Concierge',
                'Resarva Housekeeping', 'Resarva Airport Transfers', 'Resarva Dining',
                'Resarva Restaurant', 'Resarva Local Guide', 'Resarva Booking Platform',
                'Resarva Reservation Platform', 'Resarva Online Booking',
                'Resarva Secure Booking', 'Resarva Availability', 'Resarva Property Owners',
                'Resarva Property Listings', 'Resarva Guest Portal', 'Resarva Website',
                'Resarva Luxury Properties Website',
            ];

            $searchKeywords = [
                'Resarva website', 'Resarva website', 'Resarva booking',
                'Resarva booking', 'book Resarva',
                'how to book Resarva', 'where is Resarva',
                'what is Resarva', 'who owns Resarva',
                'Resarva availability', 'check Resarva availability',
                'check Resarva availability', 'Resarva available rooms',
                'Resarva available apartments', 'Resarva room booking',
                'Resarva apartment booking', 'Resarva residence prices',
                'Resarva accommodation prices', 'Resarva booking confirmation',
                'verify Resarva booking', 'Resarva booking payment',
                'Resarva guest login', 'Resarva customer portal',
                'Resarva property owner registration', 'list property with Resarva',
                'Resarva concierge booking', 'Resarva airport pickup',
                'Resarva housekeeping request', 'Resarva contact information',
                'contact Resarva','Resarva','Resarva luxury','Resarva group',
            ];

            $generalKeywords = [
                'hotel booking in Rwanda', 'hotel booking in USA',
                'hotel booking in Nigeria', 'hotel booking in Lagos',
                'luxury apartment booking', 'serviced apartment booking',
                'short stay accommodation', 'holiday residence booking',
                'business travel accommodation', 'secure accommodation booking',
            ];

            $seoKeywords = implode(', ', [
                ...$nameKeywords,
                ...$searchKeywords,
                ...$generalKeywords,
            ]);

            $contactPhone = config('azari.contact.phone');
            $contactEmail = config('azari.contact.email');
            $contactStreet = config('azari.contact.street_address');
            $contactCity = config('azari.contact.city');
            $contactRegion = config('azari.contact.region');
            $contactCountry = config('azari.contact.country', 'NG');

            $organizationSchema = array_filter([
                '@type' => 'Organization',
                '@id' => url('/').'#reserva',
                'name' => 'Resarva Luxury Properties Ltd',
                'alternateName' => [
                    'Resarva Luxury Properties Limited',
                    'Resarva Group',
                    'Resarva Holdings',
                ],
                'url' => url('/'),
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => $seoImage,
                ],
                'image' => $seoImage,
                'description' => $seoLongDescription,
                'email' => $contactEmail,
                'telephone' => $contactPhone,
                'address' => ($contactStreet || $contactCity || $contactRegion) ? [
                    '@type' => 'PostalAddress',
                    'streetAddress' => $contactStreet,
                    'addressLocality' => $contactCity,
                    'addressRegion' => $contactRegion,
                    'addressCountry' => $contactCountry,
                ] : null,
                'contactPoint' => ($contactPhone || $contactEmail) ? [[
                    '@type' => 'ContactPoint',
                    'telephone' => $contactPhone,
                    'email' => $contactEmail,
                    'contactType' => 'customer service',
                    'availableLanguage' => ['English'],
                ]] : null,
            ], fn ($value) => $value !== null && $value !== '');

            $schema = [
                '@context' => 'https://schema.org',
                '@graph' => [
                    $organizationSchema,
                    [
                        '@type' => ['WebSite', 'LodgingBusiness'],
                        '@id' => url('/').'#reserva-platform',
                        'name' => 'Resarva',
                        'alternateName' => [
                            'The Resarva',
                            'Resarva Luxury Hotels & Residences',
                        ],
                        'url' => url('/'),
                        'description' => $seoLongDescription,
                        'image' => $seoImage,
                        'logo' => $seoImage,
                        'sameAs' => [],
                        'parentOrganization' => [
                            '@id' => url('/').'#reserva',
                        ],
                        'potentialAction' => [
                            '@type' => 'SearchAction',
                            'target' => [
                                '@type' => 'EntryPoint',
                                'urlTemplate' => url('/availability').'?query={search_term_string}',
                            ],
                            'query-input' => 'required name=search_term_string',
                        ],
                    ],
                    [
                        '@type' => 'WebPage',
                        '@id' => $seoUrl.'#webpage',
                        'url' => $seoUrl,
                        'name' => $seoTitle,
                        'description' => $seoDescription,
                        'isPartOf' => [
                            '@id' => url('/').'#reserva-platform',
                        ],
                        'about' => [
                            '@id' => url('/').'#reserva',
                        ],
                        'primaryImageOfPage' => [
                            '@type' => 'ImageObject',
                            'url' => $seoImage,
                        ],
                        'inLanguage' => 'en',
                    ],
                ],
            ];
        @endphp

        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $seoTitle }}</title>

        <meta name="description" content="{{ $seoDescription }}">
        <meta name="keywords" content="{{ $seoKeywords }}">
        <meta name="author" content="Resarva Luxury Properties Ltd">
        <meta name="publisher" content="Resarva Luxury Properties Ltd">
        <meta name="application-name" content="Resarva Residences">
        <meta name="apple-mobile-web-app-title" content="Resarva Residences">
        <meta name="theme-color" content="#052058">
        <meta name="color-scheme" content="light">
        <meta name="format-detection" content="telephone=yes">
        <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
        <meta name="googlebot" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
        <meta name="bingbot" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">

        <link rel="canonical" href="{{ $seoUrl }}">
        <link rel="alternate" hreflang="en" href="{{ $seoUrl }}">
        <link rel="alternate" hreflang="x-default" href="{{ $seoUrl }}">



        <meta property="og:type" content="website">
        <meta property="og:site_name" content="Resarva Residences">
        <meta property="og:title" content="{{ $seoTitle }}">
        <meta property="og:description" content="{{ $seoDescription }}">
        <meta property="og:url" content="{{ $seoUrl }}">
        <meta property="og:image" content="{{ $seoImage }}">
        <meta property="og:image:secure_url" content="{{ $seoImage }}">
        <meta property="og:image:type" content="image/png">
        <meta property="og:image:alt" content="Resarva Residences">
        <meta property="og:locale" content="en_US">

        <meta name="twitter:card" content="summary">
        <meta name="twitter:title" content="{{ $seoTitle }}">
        <meta name="twitter:description" content="{{ $seoDescription }}">
        <meta name="twitter:image" content="{{ $seoImage }}">
        <meta name="twitter:image:alt" content="Resarva Residences">

        <script type="application/ld+json">
            {!! json_encode(
                $schema,
                JSON_UNESCAPED_SLASHES |
                JSON_UNESCAPED_UNICODE |
                JSON_HEX_TAG |
                JSON_HEX_AMP |
                JSON_HEX_APOS |
                JSON_HEX_QUOT
            ) !!}
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
<body class="font-sans antialiased">
    <div class="min-h-screen bg-gray-100">
        @include('layouts.navigation')

        @isset($header)
            <header class="bg-white shadow">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </header>
        @endisset

        <main>
            {{ $slot }}
        </main>
    </div>
</body>
</html>
