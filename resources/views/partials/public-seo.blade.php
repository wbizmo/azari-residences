@php
    /*
     * Central SEO metadata for PUBLIC Azari pages only.
     * User, staff, admin and authentication shells do not include this file.
     */
    $azariSeoTitle = trim((string) ($seoTitle ?? $title ?? 'Azari Residences | Premium Serviced Residences'));

    $azariSeoDescription = trim((string) ($seoDescription ?? $description ?? <<<'TEXT'
Azari Residences delivers a refined hospitality experience for guests seeking privacy, comfort, dependable service, and thoughtfully managed accommodation. Each Azari Residence is prepared to support business travel, leisure visits, family stays, romantic escapes, relocations, weekend breaks, and extended living with a calm sense of place. Guests can discover premium serviced residences, furnished apartments, elegant suites, and carefully selected spaces designed to make arrival simple, rest effortless, and everyday living more enjoyable. Azari Residences combines contemporary interiors, secure surroundings, professional housekeeping, responsive guest support, transparent booking information, and convenient digital services from reservation through departure. The Azari experience extends beyond accommodation through concierge assistance, airport transfer coordination, dining guidance, local recommendations, booking verification, secure payments, invoices, receipts, service requests, and clear notifications throughout every stay. Visitors can review live availability, compare residence categories, understand policies, manage bookings, access documents, update guest information, and request support through an accessible platform built around clarity and confidence. Azari Residences welcomes solo travellers, couples, families, corporate guests, groups, and returning visitors who value consistent quality without unnecessary complexity. Every interaction reflects the Azari commitment to cleanliness, discretion, practical convenience, thoughtful design, and genuine hospitality. Whether planning a short visit, arranging an important business stay, enjoying a relaxed holiday, or settling in for a longer period, guests receive straightforward choices, reliable assistance, and spaces prepared with care. Azari Residences is more than a place to sleep; it is a carefully managed destination where visitors can feel at home while enjoying polished service and modern convenience. From the first search to checkout and post-stay support, Azari technology and hospitality operations work together to reduce friction, protect guest information, and provide a memorable experience. Discover Azari Residences for premium accommodation that feels elegant yet welcoming, private yet supported, and designed for comfortable living at Africa's finest address.
TEXT));

    /* Exactly 100 focused phrases. Every phrase intentionally contains “Azari”. */
    $azariSeoKeywords = $seoKeywords ?? implode(', ', [
        'Azari Residences',
        'The Azari Residences',
        'Azari Residence',
        'Azari luxury residences',
        'Azari premium residences',
        'Azari serviced residences',
        'Azari private residences',
        'Azari furnished residences',
        'Azari modern residences',
        'Azari elegant residences',
        'Azari exclusive residences',
        'Azari boutique residences',
        'Azari residential suites',
        'Azari residence booking',
        'Azari residences booking',
        'Azari residences availability',
        'Azari residences accommodation',
        'Azari residences hospitality',
        'Azari residences guest experience',
        'Azari residences direct booking',
        'Azari serviced apartments',
        'Azari luxury apartments',
        'Azari premium apartments',
        'Azari furnished apartments',
        'Azari private apartments',
        'Azari executive apartments',
        'Azari family apartments',
        'Azari holiday apartments',
        'Azari modern apartments',
        'Azari elegant apartments',
        'Azari spacious apartments',
        'Azari secure apartments',
        'Azari apartment booking',
        'Azari apartment rentals',
        'Azari short stay apartments',
        'Azari extended stay apartments',
        'Azari luxury suites',
        'Azari premium suites',
        'Azari executive suites',
        'Azari private suites',
        'Azari holiday suites',
        'Azari serviced suites',
        'Azari suite booking',
        'Azari short stay',
        'Azari short let',
        'Azari long stay',
        'Azari extended stay',
        'Azari weekend stay',
        'Azari holiday stay',
        'Azari vacation stay',
        'Azari business stay',
        'Azari corporate stay',
        'Azari family stay',
        'Azari romantic stay',
        'Azari luxury stay',
        'Azari premium stay',
        'Azari secure stay',
        'Azari comfortable stay',
        'Azari peaceful stay',
        'Azari memorable stay',
        'Azari city accommodation',
        'Azari travel accommodation',
        'Azari tourist accommodation',
        'Azari business accommodation',
        'Azari family accommodation',
        'Azari luxury accommodation',
        'Azari premium accommodation',
        'Azari furnished accommodation',
        'Azari serviced accommodation',
        'Azari boutique accommodation',
        'Azari direct booking',
        'Azari online booking',
        'Azari secure booking',
        'Azari booking platform',
        'Azari live availability',
        'Azari room availability',
        'Azari apartment availability',
        'Azari guest booking',
        'Azari booking verification',
        'Azari payment verification',
        'Azari concierge services',
        'Azari guest services',
        'Azari housekeeping services',
        'Azari airport transfers',
        'Azari dining reservations',
        'Azari local guide',
        'Azari guest support',
        'Azari service requests',
        'Azari hospitality services',
        'Azari premium hospitality',
        'Azari luxury hospitality',
        'Azari refined living',
        'Azari premium living',
        'Azari luxury living',
        'Azari home away from home',
        'Azari urban retreat',
        'Azari private getaway',
        'Azari family getaway',
        'Azari business travel',
        'Azari Africa finest address',
    ]);

    $azariSeoCanonical = $seoCanonical ?? $canonical ?? url()->current();
    $azariSeoImage = $seoImage ?? $image ?? asset('images/azari-favicon.png');
    $azariSeoType = $seoType ?? $type ?? 'website';
    $azariSeoLocale = str_replace('-', '_', app()->getLocale());
    $azariSeoSiteName = 'Azari Residences';
    $azariSeoHome = url('/');

    /* No phone, email, postal address or other contact details are included. */
    $azariLodgingSchema = [
        '@context' => 'https://schema.org',
        '@type' => ['LodgingBusiness', 'Hotel'],
        '@id' => $azariSeoHome . '#lodging',
        'name' => $azariSeoSiteName,
        'alternateName' => ['The Azari', 'Azari Serviced Residences', 'Azari Luxury Residences'],
        'url' => $azariSeoHome,
        'description' => $azariSeoDescription,
        'logo' => $azariSeoImage,
        'image' => $azariSeoImage,
        'slogan' => "Africa's finest address",
        'priceRange' => '$$',
        'currenciesAccepted' => 'USD',
        'paymentAccepted' => ['Credit Card', 'Debit Card', 'Online Payment'],
        'amenityFeature' => [
            ['@type' => 'LocationFeatureSpecification', 'name' => 'Serviced residences', 'value' => true],
            ['@type' => 'LocationFeatureSpecification', 'name' => 'Guest support', 'value' => true],
            ['@type' => 'LocationFeatureSpecification', 'name' => 'Concierge assistance', 'value' => true],
            ['@type' => 'LocationFeatureSpecification', 'name' => 'Housekeeping', 'value' => true],
            ['@type' => 'LocationFeatureSpecification', 'name' => 'Airport transfer coordination', 'value' => true],
            ['@type' => 'LocationFeatureSpecification', 'name' => 'Online booking', 'value' => true],
        ],
        'potentialAction' => [
            '@type' => 'ReserveAction',
            'target' => [
                '@type' => 'EntryPoint',
                'urlTemplate' => url('/availability'),
                'actionPlatform' => [
                    'https://schema.org/DesktopWebPlatform',
                    'https://schema.org/MobileWebPlatform',
                ],
            ],
            'result' => ['@type' => 'LodgingReservation', 'name' => 'Azari Residences booking'],
        ],
    ];

    $azariOrganizationSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        '@id' => $azariSeoHome . '#organization',
        'name' => $azariSeoSiteName,
        'alternateName' => 'The Azari',
        'url' => $azariSeoHome,
        'logo' => ['@type' => 'ImageObject', 'url' => $azariSeoImage],
        'description' => $azariSeoDescription,
        'slogan' => "Africa's finest address",
    ];

    $azariWebsiteSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        '@id' => $azariSeoHome . '#website',
        'url' => $azariSeoHome,
        'name' => $azariSeoSiteName,
        'alternateName' => 'The Azari',
        'description' => $azariSeoDescription,
        'inLanguage' => app()->getLocale(),
        'publisher' => ['@id' => $azariSeoHome . '#organization'],
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => [
                '@type' => 'EntryPoint',
                'urlTemplate' => url('/availability/results') . '?query={search_term_string}',
            ],
            'query-input' => 'required name=search_term_string',
        ],
    ];

    $azariWebPageSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        '@id' => $azariSeoCanonical . '#webpage',
        'url' => $azariSeoCanonical,
        'name' => $azariSeoTitle,
        'headline' => $azariSeoTitle,
        'description' => $azariSeoDescription,
        'isPartOf' => ['@id' => $azariSeoHome . '#website'],
        'about' => ['@id' => $azariSeoHome . '#lodging'],
        'primaryImageOfPage' => ['@type' => 'ImageObject', 'url' => $azariSeoImage],
        'inLanguage' => app()->getLocale(),
    ];

    $azariBreadcrumbSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        '@id' => $azariSeoCanonical . '#breadcrumb',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Azari Residences', 'item' => $azariSeoHome],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $azariSeoTitle, 'item' => $azariSeoCanonical],
        ],
    ];
@endphp

<title>{{ $azariSeoTitle }}</title>
<meta name="description" content="{{ $azariSeoDescription }}">
<meta name="keywords" content="{{ $azariSeoKeywords }}">
<meta name="author" content="Azari Residences">
<meta name="application-name" content="Azari Residences">
<meta name="apple-mobile-web-app-title" content="Azari Residences">
<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
<meta name="googlebot" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
<meta name="bingbot" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
<meta name="referrer" content="strict-origin-when-cross-origin">
<meta name="theme-color" content="#173f35">
<meta name="color-scheme" content="light">
<link rel="canonical" href="{{ $azariSeoCanonical }}">

<link rel="icon" type="image/png" href="{{ asset('images/azari-favicon.png') }}">
<link rel="shortcut icon" type="image/png" href="{{ asset('images/azari-favicon.png') }}">
<link rel="apple-touch-icon" href="{{ asset('images/azari-favicon.png') }}">

<meta property="og:title" content="{{ $azariSeoTitle }}">
<meta property="og:description" content="{{ $azariSeoDescription }}">
<meta property="og:type" content="{{ $azariSeoType }}">
<meta property="og:url" content="{{ $azariSeoCanonical }}">
<meta property="og:image" content="{{ $azariSeoImage }}">
<meta property="og:image:secure_url" content="{{ $azariSeoImage }}">
<meta property="og:image:type" content="image/png">
<meta property="og:image:alt" content="{{ $azariSeoTitle }}">
<meta property="og:site_name" content="{{ $azariSeoSiteName }}">
<meta property="og:locale" content="{{ $azariSeoLocale }}">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $azariSeoTitle }}">
<meta name="twitter:description" content="{{ $azariSeoDescription }}">
<meta name="twitter:image" content="{{ $azariSeoImage }}">
<meta name="twitter:image:alt" content="{{ $azariSeoTitle }}">

<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="format-detection" content="telephone=no">

<script type="application/ld+json">{!! json_encode($azariLodgingSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
<script type="application/ld+json">{!! json_encode($azariOrganizationSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
<script type="application/ld+json">{!! json_encode($azariWebsiteSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
<script type="application/ld+json">{!! json_encode($azariWebPageSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
<script type="application/ld+json">{!! json_encode($azariBreadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
