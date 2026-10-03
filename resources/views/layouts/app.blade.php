<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.material-symbols-preload')
        @include('partials.azari-head-assets')

        @php
            $seoTitle = 'Resavar | Luxury Hotels & Residences, Apartments, Rooms and Hospitality';
            $seoDescription = 'Discover Resavar, a premium hospitality and accommodation platform owned by Azari Luxury Properties Ltd. Explore hotels & residences, apartments, rooms, concierge services, dining, housekeeping, airport transfers and secure online booking.';
            $seoUrl = url()->current();
            $seoImage = asset('images/logo-light.png');

            $seoLongDescription = <<<'DESCRIPTION'
    Resavar is a premium hospitality and accommodation brand owned and operated by Azari Luxury Properties Ltd, providing thoughtfully managed residences, apartments, rooms, and guest services for business travellers, families, couples, groups, and leisure guests. The platform makes discovering suitable accommodation simple by presenting detailed property information, room features, photographs, locations, guest capacities, amenities, policies, pricing, and date-based availability in one accessible experience.

    Guests can explore Resavar properties, compare accommodation options, check available dates, select their preferred stay period, provide guest information, submit required identification, review booking details, and proceed securely to payment. The booking system is designed to reduce uncertainty by checking existing reservations, active booking holds, maintenance periods, property capacity, and applicable stay requirements before confirming availability.

    Beyond accommodation, Resavar supports a complete hospitality experience through concierge assistance, housekeeping requests, airport transfer arrangements, restaurant and dining information, local guides, service requests, customer support, booking verification, notifications, invoices, receipts, and guest account management. Registered guests can manage bookings, payments, identity documents, additional guests, service requests, support tickets, notifications, profiles, and account security from a dedicated customer area.

    Resavar also enables eligible property owners to submit properties for professional review and possible listing within the platform. Approved properties can be managed through structured booking, revenue, balance, and withdrawal processes while remaining subject to Azari standards and administrative oversight.

    Azari Luxury Properties Ltd maintains the platform as part of the wider Azari ecosystem associated with Azari Group and Azari Holdings. Its objective is to combine dependable property management, refined hospitality, secure digital booking, responsive guest support, and carefully selected accommodation. Whether a guest requires a short stay, an extended residence, a private apartment, a comfortable room, or coordinated hospitality services, Resavar provides a convenient starting point for planning and managing the complete stay.
    DESCRIPTION;

            $nameKeywords = [
                'Azari', 'Resavar', 'The Azari', 'The Resavar',
                'Azari Residence', 'Azari Luxury Hotels & Residences', 'Azari Luxury Properties',
                'Azari Luxury Properties Ltd', 'Azari Luxury Properties Limited',
                'Azari Group', 'The Azari Group', 'Azari Holdings', 'Azari Holdings Ltd',
                'Azari Holdings Limited', 'Azari Hospitality', 'Azari Hospitality Group',
                'Azari Hospitality Services', 'Azari Accommodation', 'Azari Apartments',
                'Azari Rooms', 'Azari Properties', 'Azari Property', 'Azari Property Booking',
                'Azari Residence Booking', 'Resavar Booking', 'Azari Hotel',
                'Azari Hotels', 'Azari Guest House', 'Azari Guest Accommodation',
                'Azari Serviced Apartments', 'Azari Short Stay', 'Azari Extended Stay',
                'Azari Holiday Hotels & Residences', 'Azari Vacation Hotels & Residences',
                'Azari Premium Hotels & Residences', 'Azari Private Hotels & Residences',
                'Azari Executive Hotels & Residences', 'Azari Corporate Accommodation',
                'Azari Family Accommodation', 'Azari Luxury Accommodation',
                'Azari Property Management', 'Azari Residence Management',
                'Azari Resort Management', 'Azari Guest Services', 'Azari Concierge',
                'Azari Housekeeping', 'Azari Airport Transfers', 'Azari Dining',
                'Azari Restaurant', 'Azari Local Guide', 'Azari Booking Platform',
                'Azari Reservation Platform', 'Azari Online Booking',
                'Azari Secure Booking', 'Azari Availability', 'Azari Property Owners',
                'Azari Property Listings', 'Azari Guest Portal', 'Resavar Website',
                'Azari Luxury Properties Website',
            ];

            $searchKeywords = [
                'Azari website', 'Resavar website', 'Azari booking',
                'Resavar booking', 'book Resavar',
                'how to book Resavar', 'where is Resavar',
                'what is Resavar', 'who owns Resavar',
                'Resavar availability', 'check Azari availability',
                'check Resavar availability', 'Azari available rooms',
                'Azari available apartments', 'Azari room booking',
                'Azari apartment booking', 'Azari residence prices',
                'Azari accommodation prices', 'Azari booking confirmation',
                'verify Azari booking', 'Azari booking payment',
                'Azari guest login', 'Azari customer portal',
                'Azari property owner registration', 'list property with Azari',
                'Azari concierge booking', 'Azari airport pickup',
                'Azari housekeeping request', 'Azari contact information',
                'contact Resavar','Azari','Azari luxury','Azari group',
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
                '@id' => url('/').'#azari-luxury-properties',
                'name' => 'Azari Luxury Properties Ltd',
                'alternateName' => [
                    'Azari Luxury Properties Limited',
                    'Azari Group',
                    'Azari Holdings',
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
                        '@id' => url('/').'#azari-residences',
                        'name' => 'Resavar',
                        'alternateName' => [
                            'The Resavar',
                            'Azari Luxury Hotels & Residences',
                        ],
                        'url' => url('/'),
                        'description' => $seoLongDescription,
                        'image' => $seoImage,
                        'logo' => $seoImage,
                        'sameAs' => [],
                        'parentOrganization' => [
                            '@id' => url('/').'#azari-luxury-properties',
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
                            '@id' => url('/').'#azari-residences',
                        ],
                        'about' => [
                            '@id' => url('/').'#azari-luxury-properties',
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
        <meta name="author" content="Azari Luxury Properties Ltd">
        <meta name="publisher" content="Azari Luxury Properties Ltd">
        <meta name="application-name" content="Resavar">
        <meta name="apple-mobile-web-app-title" content="Resavar">
        <meta name="theme-color" content="#153b34">
        <meta name="color-scheme" content="light">
        <meta name="format-detection" content="telephone=yes">
        <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
        <meta name="googlebot" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
        <meta name="bingbot" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">

        <link rel="canonical" href="{{ $seoUrl }}">
        <link rel="alternate" hreflang="en" href="{{ $seoUrl }}">
        <link rel="alternate" hreflang="x-default" href="{{ $seoUrl }}">

        <link rel="icon" type="image/png" href="{{ $seoImage }}">
        <link rel="shortcut icon" type="image/png" href="{{ $seoImage }}">
        <link rel="apple-touch-icon" href="{{ $seoImage }}">

        <meta property="og:type" content="website">
        <meta property="og:site_name" content="Resavar">
        <meta property="og:title" content="{{ $seoTitle }}">
        <meta property="og:description" content="{{ $seoDescription }}">
        <meta property="og:url" content="{{ $seoUrl }}">
        <meta property="og:image" content="{{ $seoImage }}">
        <meta property="og:image:secure_url" content="{{ $seoImage }}">
        <meta property="og:image:type" content="image/png">
        <meta property="og:image:alt" content="Resavar">
        <meta property="og:locale" content="en_US">

        <meta name="twitter:card" content="summary">
        <meta name="twitter:title" content="{{ $seoTitle }}">
        <meta name="twitter:description" content="{{ $seoDescription }}">
        <meta name="twitter:image" content="{{ $seoImage }}">
        <meta name="twitter:image:alt" content="Resavar">

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
