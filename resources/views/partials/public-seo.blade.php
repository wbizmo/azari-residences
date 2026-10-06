@php
    /*
     * Central SEO metadata for PUBLIC Resarva pages only.
     * User, staff, admin and authentication shells do not include this file.
     */
    $azariSeoTitle = trim((string) ($seoTitle ?? $title ?? 'RESARVA | Exceptional Stays, Everywhere.'));

    $azariSeoDescription = trim((string) ($seoDescription ?? $description ?? <<<'TEXT'
Resarva is a global accommodation and travel marketplace. Exceptional Stays, Everywhere. Find stays chosen for quality with a clear, calm and dependable booking experience.
TEXT));

    /* Exactly 100 focused phrases. Every phrase intentionally contains “Resarva”. */
    $azariSeoKeywords = $seoKeywords ?? implode(', ', [
        'Resarva',
        'Resarva',
        'Resarva Residence',
        'Resarva luxury residences',
        'Resarva premium residences',
        'Resarva serviced residences',
        'Resarva private residences',
        'Resarva furnished residences',
        'Resarva modern residences',
        'Resarva elegant residences',
        'Resarva exclusive residences',
        'Resarva boutique residences',
        'Resarva residential suites',
        'Resarva residence booking',
        'Resarva residences booking',
        'Resarva residences availability',
        'Resarva residences accommodation',
        'Resarva residences hospitality',
        'Resarva residences guest experience',
        'Resarva residences direct booking',
        'Resarva serviced apartments',
        'Resarva luxury apartments',
        'Resarva premium apartments',
        'Resarva furnished apartments',
        'Resarva private apartments',
        'Resarva executive apartments',
        'Resarva family apartments',
        'Resarva holiday apartments',
        'Resarva modern apartments',
        'Resarva elegant apartments',
        'Resarva spacious apartments',
        'Resarva secure apartments',
        'Resarva apartment booking',
        'Resarva apartment rentals',
        'Resarva short stay apartments',
        'Resarva extended stay apartments',
        'Resarva luxury suites',
        'Resarva premium suites',
        'Resarva executive suites',
        'Resarva private suites',
        'Resarva holiday suites',
        'Resarva serviced suites',
        'Resarva suite booking',
        'Resarva short stay',
        'Resarva short let',
        'Resarva long stay',
        'Resarva extended stay',
        'Resarva weekend stay',
        'Resarva holiday stay',
        'Resarva vacation stay',
        'Resarva business stay',
        'Resarva corporate stay',
        'Resarva family stay',
        'Resarva romantic stay',
        'Resarva luxury stay',
        'Resarva premium stay',
        'Resarva secure stay',
        'Resarva comfortable stay',
        'Resarva peaceful stay',
        'Resarva memorable stay',
        'Resarva city accommodation',
        'Resarva travel accommodation',
        'Resarva tourist accommodation',
        'Resarva business accommodation',
        'Resarva family accommodation',
        'Resarva luxury accommodation',
        'Resarva premium accommodation',
        'Resarva furnished accommodation',
        'Resarva serviced accommodation',
        'Resarva boutique accommodation',
        'Resarva direct booking',
        'Resarva online booking',
        'Resarva secure booking',
        'Resarva booking platform',
        'Resarva live availability',
        'Resarva room availability',
        'Resarva apartment availability',
        'Resarva guest booking',
        'Resarva booking verification',
        'Resarva payment verification',
        'Resarva concierge services',
        'Resarva guest services',
        'Resarva housekeeping services',
        'Resarva airport transfers',
        'Resarva dining reservations',
        'Resarva local guide',
        'Resarva guest support',
        'Resarva service requests',
        'Resarva hospitality services',
        'Resarva premium hospitality',
        'Resarva luxury hospitality',
        'Resarva refined living',
        'Resarva premium living',
        'Resarva luxury living',
        'Resarva home away from home',
        'Resarva urban retreat',
        'Resarva private getaway',
        'Resarva family getaway',
        'Resarva business travel',
        'Resarva exceptional stays everywhere',
    ]);

    $azariSeoCanonical = $seoCanonical ?? $canonical ?? url()->current();
    $azariSeoImage = $seoImage ?? $image ?? asset('images/resavar-logo-light.png');
    $azariSeoType = $seoType ?? $type ?? 'website';
    $azariSeoLocale = str_replace('-', '_', app()->getLocale());
    $azariSeoSiteName = 'Resarva';
    $azariSeoHome = url('/');

    /* No phone, email, postal address or other contact details are included. */
    $azariLodgingSchema = [
        '@context' => 'https://schema.org',
        '@type' => ['LodgingBusiness', 'Hotel'],
        '@id' => $azariSeoHome . '#lodging',
        'name' => $azariSeoSiteName,
        'alternateName' => ['The Resarva', 'Resarva Serviced Residences', 'Resarva Luxury Residences'],
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
            'result' => ['@type' => 'LodgingReservation', 'name' => 'Resarva booking'],
        ],
    ];

    $azariOrganizationSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        '@id' => $azariSeoHome . '#organization',
        'name' => $azariSeoSiteName,
        'alternateName' => 'The Resarva',
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
        'alternateName' => 'The Resarva',
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
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Resarva', 'item' => $azariSeoHome],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $azariSeoTitle, 'item' => $azariSeoCanonical],
        ],
    ];
@endphp

<title>{{ $azariSeoTitle }}</title>
<meta name="description" content="{{ $azariSeoDescription }}">
<meta name="keywords" content="{{ $azariSeoKeywords }}">
<meta name="author" content="Resarva">
<meta name="application-name" content="Resarva">
<meta name="apple-mobile-web-app-title" content="Resarva">
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
