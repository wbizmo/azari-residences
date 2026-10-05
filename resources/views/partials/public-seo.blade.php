@php
    /*
     * Central SEO metadata for PUBLIC Reserva pages only.
     * User, staff, admin and authentication shells do not include this file.
     */
    $azariSeoTitle = trim((string) ($seoTitle ?? $title ?? 'RESERVA | Exceptional Stays, Everywhere.'));

    $azariSeoDescription = trim((string) ($seoDescription ?? $description ?? <<<'TEXT'
Reserva is a global accommodation and travel marketplace. Exceptional Stays, Everywhere. Find stays chosen for quality with a clear, calm and dependable booking experience.
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
        'Reserva exceptional stays everywhere',
    ]);

    $azariSeoCanonical = $seoCanonical ?? $canonical ?? url()->current();
    $azariSeoImage = $seoImage ?? $image ?? asset('images/resavar-logo-light.png');
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
        'slogan' => 'Exceptional Stays, Everywhere.',
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
        'slogan' => 'Exceptional Stays, Everywhere.',
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
<meta name="theme-color" content="#052058">
<meta name="color-scheme" content="light">
<link rel="canonical" href="{{ $azariSeoCanonical }}">


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
