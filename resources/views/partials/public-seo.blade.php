@php
    /*
     * Central SEO metadata for PUBLIC Reserva pages only.
     * User, staff, admin and authentication shells do not include this file.
     */
    $azariSeoTitle = trim((string) ($seoTitle ?? $title ?? 'Reserva | Premium Serviced Hotels & Residences'));

    $azariSeoDescription = trim((string) ($seoDescription ?? $description ?? <<<'TEXT'
Reserva delivers a refined hospitality experience for guests seeking privacy, comfort, dependable service, and thoughtfully managed accommodation. Each Reserva Residence is prepared to support business travel, leisure visits, family stays, romantic escapes, relocations, weekend breaks, and extended living with a calm sense of place. Guests can discover premium serviced residences, furnished apartments, elegant suites, and carefully selected spaces designed to make arrival simple, rest effortless, and everyday living more enjoyable. Reserva combines contemporary interiors, secure surroundings, professional housekeeping, responsive guest support, transparent booking information, and convenient digital services from reservation through departure. The Reserva experience extends beyond accommodation through concierge assistance, airport transfer coordination, dining guidance, local recommendations, booking verification, secure payments, invoices, receipts, service requests, and clear notifications throughout every stay. Visitors can review live availability, compare residence categories, understand policies, manage bookings, access documents, update guest information, and request support through an accessible platform built around clarity and confidence. Reserva welcomes solo travellers, couples, families, corporate guests, groups, and returning visitors who value consistent quality without unnecessary complexity. Every interaction reflects the Reserva commitment to cleanliness, discretion, practical convenience, thoughtful design, and genuine hospitality. Whether planning a short visit, arranging an important business stay, enjoying a relaxed holiday, or settling in for a longer period, guests receive straightforward choices, reliable assistance, and spaces prepared with care. Reserva is more than a place to sleep; it is a carefully managed destination where visitors can feel at home while enjoying polished service and modern convenience. From the first search to checkout and post-stay support, Reserva technology and hospitality operations work together to reduce friction, protect guest information, and provide a memorable experience. Discover Reserva for premium accommodation that feels elegant yet welcoming, private yet supported, and designed for comfortable living at Africa's finest address.
TEXT));

    /* Exactly 100 focused phrases. Every phrase intentionally contains “Reserva”. */
    $azariSeoKeywords = $seoKeywords ?? implode(', ', [
        'Reserva',
        'Reserva',
        'Reserva Residence',
        'Reserva luxury residences',
        'Reserva premium residences',
        'Reserva serviced residences',
        'Reserva private residences',
        'Reserva furnished residences',
        'Reserva modern residences',
        'Reserva elegant residences',
        'Reserva exclusive residences',
        'Reserva boutique residences',
        'Reserva residential suites',
        'Reserva residence booking',
        'Reserva residences booking',
        'Reserva residences availability',
        'Reserva residences accommodation',
        'Reserva residences hospitality',
        'Reserva residences guest experience',
        'Reserva residences direct booking',
        'Reserva serviced apartments',
        'Reserva luxury apartments',
        'Reserva premium apartments',
        'Reserva furnished apartments',
        'Reserva private apartments',
        'Reserva executive apartments',
        'Reserva family apartments',
        'Reserva holiday apartments',
        'Reserva modern apartments',
        'Reserva elegant apartments',
        'Reserva spacious apartments',
        'Reserva secure apartments',
        'Reserva apartment booking',
        'Reserva apartment rentals',
        'Reserva short stay apartments',
        'Reserva extended stay apartments',
        'Reserva luxury suites',
        'Reserva premium suites',
        'Reserva executive suites',
        'Reserva private suites',
        'Reserva holiday suites',
        'Reserva serviced suites',
        'Reserva suite booking',
        'Reserva short stay',
        'Reserva short let',
        'Reserva long stay',
        'Reserva extended stay',
        'Reserva weekend stay',
        'Reserva holiday stay',
        'Reserva vacation stay',
        'Reserva business stay',
        'Reserva corporate stay',
        'Reserva family stay',
        'Reserva romantic stay',
        'Reserva luxury stay',
        'Reserva premium stay',
        'Reserva secure stay',
        'Reserva comfortable stay',
        'Reserva peaceful stay',
        'Reserva memorable stay',
        'Reserva city accommodation',
        'Reserva travel accommodation',
        'Reserva tourist accommodation',
        'Reserva business accommodation',
        'Reserva family accommodation',
        'Reserva luxury accommodation',
        'Reserva premium accommodation',
        'Reserva furnished accommodation',
        'Reserva serviced accommodation',
        'Reserva boutique accommodation',
        'Reserva direct booking',
        'Reserva online booking',
        'Reserva secure booking',
        'Reserva booking platform',
        'Reserva live availability',
        'Reserva room availability',
        'Reserva apartment availability',
        'Reserva guest booking',
        'Reserva booking verification',
        'Reserva payment verification',
        'Reserva concierge services',
        'Reserva guest services',
        'Reserva housekeeping services',
        'Reserva airport transfers',
        'Reserva dining reservations',
        'Reserva local guide',
        'Reserva guest support',
        'Reserva service requests',
        'Reserva hospitality services',
        'Reserva premium hospitality',
        'Reserva luxury hospitality',
        'Reserva refined living',
        'Reserva premium living',
        'Reserva luxury living',
        'Reserva home away from home',
        'Reserva urban retreat',
        'Reserva private getaway',
        'Reserva family getaway',
        'Reserva business travel',
        'Reserva Africa finest address',
    ]);

    $azariSeoCanonical = $seoCanonical ?? $canonical ?? url()->current();
    $azariSeoImage = $seoImage ?? $image ?? asset('images/azari-favicon.png');
    $azariSeoType = $seoType ?? $type ?? 'website';
    $azariSeoLocale = str_replace('-', '_', app()->getLocale());
    $azariSeoSiteName = 'Reserva';
    $azariSeoHome = url('/');

    /* No phone, email, postal address or other contact details are included. */
    $azariLodgingSchema = [
        '@context' => 'https://schema.org',
        '@type' => ['LodgingBusiness', 'Hotel'],
        '@id' => $azariSeoHome . '#lodging',
        'name' => $azariSeoSiteName,
        'alternateName' => ['The Reserva', 'Reserva Serviced Residences', 'Reserva Luxury Residences'],
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
            'result' => ['@type' => 'LodgingReservation', 'name' => 'Reserva booking'],
        ],
    ];

    $azariOrganizationSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        '@id' => $azariSeoHome . '#organization',
        'name' => $azariSeoSiteName,
        'alternateName' => 'The Reserva',
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
        'alternateName' => 'The Reserva',
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
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Reserva', 'item' => $azariSeoHome],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $azariSeoTitle, 'item' => $azariSeoCanonical],
        ],
    ];
@endphp

<title>{{ $azariSeoTitle }}</title>
<meta name="description" content="{{ $azariSeoDescription }}">
<meta name="keywords" content="{{ $azariSeoKeywords }}">
<meta name="author" content="Reserva">
<meta name="application-name" content="Reserva">
<meta name="apple-mobile-web-app-title" content="Reserva">
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
