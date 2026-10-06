<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.material-symbols-preload')
        @include('partials.azari-head-assets')

        @php
            $seoTitle = 'Resavar — Luxury Stays, Hotels, Apartments, Rooms and Hospitality';
            $seoDescription = 'Discover Resavar, a premium hospitality and accommodation platform owned by Resavar Luxury Properties Ltd. Explore hotels & residences, apartments, rooms, concierge services, dining, housekeeping, airport transfers and secure online booking.';
            $seoUrl = url()->current();
            $seoImage = asset('images/resavar-logo-light.png');

            $seoLongDescription = <<<'DESCRIPTION'
    Resavar Residences is a premium hospitality and accommodation brand owned and operated by Resavar Luxury Properties Ltd, providing thoughtfully managed residences, apartments, rooms, and guest services for business travellers, families, couples, groups, and leisure guests. The platform makes discovering suitable accommodation simple by presenting detailed property information, room features, photographs, locations, guest capacities, amenities, policies, pricing, and date-based availability in one accessible experience.

    Guests can explore Resavar Residences properties, compare accommodation options, check available dates, select their preferred stay period, provide guest information, submit required identification, review booking details, and proceed securely to payment. The booking system is designed to reduce uncertainty by checking existing reservations, active booking holds, maintenance periods, property capacity, and applicable stay requirements before confirming availability.

    Beyond accommodation, Resavar Residences supports a complete hospitality experience through concierge assistance, housekeeping requests, airport transfer arrangements, restaurant and dining information, local guides, service requests, customer support, booking verification, notifications, invoices, receipts, and guest account management. Registered guests can manage bookings, payments, identity documents, additional guests, service requests, support tickets, notifications, profiles, and account security from a dedicated customer area.

    Resavar Residences also enables eligible property owners to submit properties for professional review and possible listing within the platform. Approved properties can be managed through structured booking, revenue, balance, and withdrawal processes while remaining subject to Resavar standards and administrative oversight.

    Resavar Luxury Properties Ltd maintains the platform as part of the wider Resavar ecosystem associated with Resavar Group and Resavar Holdings. Its objective is to combine dependable property management, refined hospitality, secure digital booking, responsive guest support, and carefully selected accommodation. Whether a guest requires a short stay, an extended residence, a private apartment, a comfortable room, or coordinated hospitality services, Resavar Residences provides a convenient starting point for planning and managing the complete stay.
    DESCRIPTION;

            $nameKeywords = [
                'Resavar', 'Resavar', 'The Resavar', 'The Resavar',
                'Resavar Residence', 'Resavar Luxury Hotels & Residences', 'Resavar Luxury Properties',
                'Resavar Luxury Properties Ltd', 'Resavar Luxury Properties Limited',
                'Resavar Group', 'The Resavar Group', 'Resavar Holdings', 'Resavar Holdings Ltd',
                'Resavar Holdings Limited', 'Resavar Hospitality', 'Resavar Hospitality Group',
                'Resavar Hospitality Services', 'Resavar Accommodation', 'Resavar Apartments',
                'Resavar Rooms', 'Resavar Properties', 'Resavar Property', 'Resavar Property Booking',
                'Resavar Residence Booking', 'Resavar Booking', 'Resavar Hotel',
                'Resavar Hotels', 'Resavar Guest House', 'Resavar Guest Accommodation',
                'Resavar Serviced Apartments', 'Resavar Short Stay', 'Resavar Extended Stay',
                'Resavar Holiday Hotels & Residences', 'Resavar Vacation Hotels & Residences',
                'Resavar Premium Hotels & Residences', 'Resavar Private Hotels & Residences',
                'Resavar Executive Hotels & Residences', 'Resavar Corporate Accommodation',
                'Resavar Family Accommodation', 'Resavar Luxury Accommodation',
                'Resavar Property Management', 'Resavar Residence Management',
                'Resavar Resort Management', 'Resavar Guest Services', 'Resavar Concierge',
                'Resavar Housekeeping', 'Resavar Airport Transfers', 'Resavar Dining',
                'Resavar Restaurant', 'Resavar Local Guide', 'Resavar Booking Platform',
                'Resavar Reservation Platform', 'Resavar Online Booking',
                'Resavar Secure Booking', 'Resavar Availability', 'Resavar Property Owners',
                'Resavar Property Listings', 'Resavar Guest Portal', 'Resavar Website',
                'Resavar Luxury Properties Website',
            ];

            $searchKeywords = [
                'Resavar website', 'Resavar website', 'Resavar booking',
                'Resavar booking', 'book Resavar',
                'how to book Resavar', 'where is Resavar',
                'what is Resavar', 'who owns Resavar',
                'Resavar availability', 'check Resavar availability',
                'check Resavar availability', 'Resavar available rooms',
                'Resavar available apartments', 'Resavar room booking',
                'Resavar apartment booking', 'Resavar residence prices',
                'Resavar accommodation prices', 'Resavar booking confirmation',
                'verify Resavar booking', 'Resavar booking payment',
                'Resavar guest login', 'Resavar customer portal',
                'Resavar property owner registration', 'list property with Resavar',
                'Resavar concierge booking', 'Resavar airport pickup',
                'Resavar housekeeping request', 'Resavar contact information',
                'contact Resavar','Resavar','Resavar luxury','Resavar group',
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
                'name' => 'Resavar Luxury Properties Ltd',
                'alternateName' => [
                    'Resavar Luxury Properties Limited',
                    'Resavar Group',
                    'Resavar Holdings',
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
                        'name' => 'Resavar',
                        'alternateName' => [
                            'The Resavar',
                            'Resavar Luxury Hotels & Residences',
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
        <meta name="author" content="Resavar Luxury Properties Ltd">
        <meta name="publisher" content="Resavar Luxury Properties Ltd">
        <meta name="application-name" content="Resavar">
        <meta name="apple-mobile-web-app-title" content="Resavar Residences">
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
        <meta property="og:site_name" content="Resavar Residences">
        <meta property="og:title" content="{{ $seoTitle }}">
        <meta property="og:description" content="{{ $seoDescription }}">
        <meta property="og:url" content="{{ $seoUrl }}">
        <meta property="og:image" content="{{ $seoImage }}">
        <meta property="og:image:secure_url" content="{{ $seoImage }}">
        <meta property="og:image:type" content="image/png">
        <meta property="og:image:alt" content="Resavar Residences">
        <meta property="og:locale" content="en_US">

        <meta name="twitter:card" content="summary">
        <meta name="twitter:title" content="{{ $seoTitle }}">
        <meta name="twitter:description" content="{{ $seoDescription }}">
        <meta name="twitter:image" content="{{ $seoImage }}">
        <meta name="twitter:image:alt" content="Resavar Residences">

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
