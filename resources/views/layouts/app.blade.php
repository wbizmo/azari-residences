<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.material-symbols-preload')
        @include('partials.azari-head-assets')

        @php
            $seoTitle = 'Reserva | Luxury Hotels & Residences, Apartments, Rooms and Hospitality';
            $seoDescription = 'Discover Reserva, a premium hospitality and accommodation platform owned by Reserva Luxury Properties Ltd. Explore hotels & residences, apartments, rooms, concierge services, dining, housekeeping, airport transfers and secure online booking.';
            $seoUrl = url()->current();
            $seoImage = asset('images/resavar-logo-light.png');

            $seoLongDescription = <<<'DESCRIPTION'
    Reserva Residences is a premium hospitality and accommodation brand owned and operated by Reserva Luxury Properties Ltd, providing thoughtfully managed residences, apartments, rooms, and guest services for business travellers, families, couples, groups, and leisure guests. The platform makes discovering suitable accommodation simple by presenting detailed property information, room features, photographs, locations, guest capacities, amenities, policies, pricing, and date-based availability in one accessible experience.

    Guests can explore Reserva Residences properties, compare accommodation options, check available dates, select their preferred stay period, provide guest information, submit required identification, review booking details, and proceed securely to payment. The booking system is designed to reduce uncertainty by checking existing reservations, active booking holds, maintenance periods, property capacity, and applicable stay requirements before confirming availability.

    Beyond accommodation, Reserva Residences supports a complete hospitality experience through concierge assistance, housekeeping requests, airport transfer arrangements, restaurant and dining information, local guides, service requests, customer support, booking verification, notifications, invoices, receipts, and guest account management. Registered guests can manage bookings, payments, identity documents, additional guests, service requests, support tickets, notifications, profiles, and account security from a dedicated customer area.

    Reserva Residences also enables eligible property owners to submit properties for professional review and possible listing within the platform. Approved properties can be managed through structured booking, revenue, balance, and withdrawal processes while remaining subject to Reserva standards and administrative oversight.

    Reserva Luxury Properties Ltd maintains the platform as part of the wider Reserva ecosystem associated with Reserva Group and Reserva Holdings. Its objective is to combine dependable property management, refined hospitality, secure digital booking, responsive guest support, and carefully selected accommodation. Whether a guest requires a short stay, an extended residence, a private apartment, a comfortable room, or coordinated hospitality services, Reserva Residences provides a convenient starting point for planning and managing the complete stay.
    DESCRIPTION;

            $nameKeywords = [
                'Reserva', 'Reserva', 'The Reserva', 'The Reserva',
                'Reserva Residence', 'Reserva Luxury Hotels & Residences', 'Reserva Luxury Properties',
                'Reserva Luxury Properties Ltd', 'Reserva Luxury Properties Limited',
                'Reserva Group', 'The Reserva Group', 'Reserva Holdings', 'Reserva Holdings Ltd',
                'Reserva Holdings Limited', 'Reserva Hospitality', 'Reserva Hospitality Group',
                'Reserva Hospitality Services', 'Reserva Accommodation', 'Reserva Apartments',
                'Reserva Rooms', 'Reserva Properties', 'Reserva Property', 'Reserva Property Booking',
                'Reserva Residence Booking', 'Reserva Booking', 'Reserva Hotel',
                'Reserva Hotels', 'Reserva Guest House', 'Reserva Guest Accommodation',
                'Reserva Serviced Apartments', 'Reserva Short Stay', 'Reserva Extended Stay',
                'Reserva Holiday Hotels & Residences', 'Reserva Vacation Hotels & Residences',
                'Reserva Premium Hotels & Residences', 'Reserva Private Hotels & Residences',
                'Reserva Executive Hotels & Residences', 'Reserva Corporate Accommodation',
                'Reserva Family Accommodation', 'Reserva Luxury Accommodation',
                'Reserva Property Management', 'Reserva Residence Management',
                'Reserva Resort Management', 'Reserva Guest Services', 'Reserva Concierge',
                'Reserva Housekeeping', 'Reserva Airport Transfers', 'Reserva Dining',
                'Reserva Restaurant', 'Reserva Local Guide', 'Reserva Booking Platform',
                'Reserva Reservation Platform', 'Reserva Online Booking',
                'Reserva Secure Booking', 'Reserva Availability', 'Reserva Property Owners',
                'Reserva Property Listings', 'Reserva Guest Portal', 'Reserva Website',
                'Reserva Luxury Properties Website',
            ];

            $searchKeywords = [
                'Reserva website', 'Reserva website', 'Reserva booking',
                'Reserva booking', 'book Reserva',
                'how to book Reserva', 'where is Reserva',
                'what is Reserva', 'who owns Reserva',
                'Reserva availability', 'check Reserva availability',
                'check Reserva availability', 'Reserva available rooms',
                'Reserva available apartments', 'Reserva room booking',
                'Reserva apartment booking', 'Reserva residence prices',
                'Reserva accommodation prices', 'Reserva booking confirmation',
                'verify Reserva booking', 'Reserva booking payment',
                'Reserva guest login', 'Reserva customer portal',
                'Reserva property owner registration', 'list property with Reserva',
                'Reserva concierge booking', 'Reserva airport pickup',
                'Reserva housekeeping request', 'Reserva contact information',
                'contact Reserva','Reserva','Reserva luxury','Reserva group',
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
                'name' => 'Reserva Luxury Properties Ltd',
                'alternateName' => [
                    'Reserva Luxury Properties Limited',
                    'Reserva Group',
                    'Reserva Holdings',
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
                        'name' => 'Reserva',
                        'alternateName' => [
                            'The Reserva',
                            'Reserva Luxury Hotels & Residences',
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
        <meta name="author" content="Reserva Luxury Properties Ltd">
        <meta name="publisher" content="Reserva Luxury Properties Ltd">
        <meta name="application-name" content="Reserva Residences">
        <meta name="apple-mobile-web-app-title" content="Reserva Residences">
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
        <meta property="og:site_name" content="Reserva Residences">
        <meta property="og:title" content="{{ $seoTitle }}">
        <meta property="og:description" content="{{ $seoDescription }}">
        <meta property="og:url" content="{{ $seoUrl }}">
        <meta property="og:image" content="{{ $seoImage }}">
        <meta property="og:image:secure_url" content="{{ $seoImage }}">
        <meta property="og:image:type" content="image/png">
        <meta property="og:image:alt" content="Reserva Residences">
        <meta property="og:locale" content="en_US">

        <meta name="twitter:card" content="summary">
        <meta name="twitter:title" content="{{ $seoTitle }}">
        <meta name="twitter:description" content="{{ $seoDescription }}">
        <meta name="twitter:image" content="{{ $seoImage }}">
        <meta name="twitter:image:alt" content="Reserva Residences">

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
