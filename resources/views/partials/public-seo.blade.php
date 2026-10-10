@php
    /*
     * Central SEO metadata for PUBLIC Resavar pages only.
     * User, staff, admin and authentication shells do not include this file.
     */
    $azariSeoTitle = trim((string) ($seoTitle ?? $title ?? 'Resavar: Exceptional Stays, Everywhere.'));

    $azariSeoDescription = trim((string) ($seoDescription ?? $description ?? <<<'TEXT'
Resavar is a global accommodation and travel marketplace. Exceptional Stays, Everywhere. Find stays chosen for quality with a clear, calm and dependable booking experience.
TEXT));

    /* Exactly 100 focused phrases. Every phrase intentionally contains “Resavar”. */
    $azariSeoKeywords = $seoKeywords ?? implode(', ', [
        'Resavar',
        'Resavar',
        'Resavar Stay',
        'Resavar luxury stays',
        'Resavar premium stays',
        'Resavar serviced stays',
        'Resavar private stays',
        'Resavar furnished stays',
        'Resavar modern stays',
        'Resavar elegant stays',
        'Resavar exclusive stays',
        'Resavar boutique stays',
        'Resavar residential suites',
        'Resavar stay booking',
        'Resavar stays booking',
        'Resavar stays availability',
        'Resavar stays accommodation',
        'Resavar stays hospitality',
        'Resavar stays guest experience',
        'Resavar stays direct booking',
        'Resavar serviced apartments',
        'Resavar luxury apartments',
        'Resavar premium apartments',
        'Resavar furnished apartments',
        'Resavar private apartments',
        'Resavar executive apartments',
        'Resavar family apartments',
        'Resavar holiday apartments',
        'Resavar modern apartments',
        'Resavar elegant apartments',
        'Resavar spacious apartments',
        'Resavar secure apartments',
        'Resavar apartment booking',
        'Resavar apartment rentals',
        'Resavar short stay apartments',
        'Resavar extended stay apartments',
        'Resavar luxury suites',
        'Resavar premium suites',
        'Resavar executive suites',
        'Resavar private suites',
        'Resavar holiday suites',
        'Resavar serviced suites',
        'Resavar suite booking',
        'Resavar short stay',
        'Resavar short let',
        'Resavar long stay',
        'Resavar extended stay',
        'Resavar weekend stay',
        'Resavar holiday stay',
        'Resavar vacation stay',
        'Resavar business stay',
        'Resavar corporate stay',
        'Resavar family stay',
        'Resavar romantic stay',
        'Resavar luxury stay',
        'Resavar premium stay',
        'Resavar secure stay',
        'Resavar comfortable stay',
        'Resavar peaceful stay',
        'Resavar memorable stay',
        'Resavar city accommodation',
        'Resavar travel accommodation',
        'Resavar tourist accommodation',
        'Resavar business accommodation',
        'Resavar family accommodation',
        'Resavar luxury accommodation',
        'Resavar premium accommodation',
        'Resavar furnished accommodation',
        'Resavar serviced accommodation',
        'Resavar boutique accommodation',
        'Resavar direct booking',
        'Resavar online booking',
        'Resavar secure booking',
        'Resavar booking platform',
        'Resavar live availability',
        'Resavar room availability',
        'Resavar apartment availability',
        'Resavar guest booking',
        'Resavar booking verification',
        'Resavar payment verification',
        'Resavar concierge services',
        'Resavar guest services',
        'Resavar housekeeping services',
        'Resavar airport transfers',
        'Resavar dining reservations',
        'Resavar local guide',
        'Resavar guest support',
        'Resavar service requests',
        'Resavar hospitality services',
        'Resavar premium hospitality',
        'Resavar luxury hospitality',
        'Resavar refined living',
        'Resavar premium living',
        'Resavar luxury living',
        'Resavar home away from home',
        'Resavar urban retreat',
        'Resavar private getaway',
        'Resavar family getaway',
        'Resavar business travel',
        'Resavar exceptional stays everywhere',
    ]);

    $azariSeoCanonical = $seoCanonical ?? $canonical ?? url()->current();
    $azariSeoImage = $seoImage ?? $image ?? asset('images/resavar-logo-light.png');
    $azariSeoType = $seoType ?? $type ?? 'website';
    $azariSeoRobots = $seoRobots ?? 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
    $azariSeoLocale = str_replace('-', '_', app()->getLocale());
    $azariSeoSiteName = 'Resavar';
    $azariSeoHome = url('/');

    /* No phone, email, postal address or other contact details are included. */
    $azariLodgingSchema = [
        '@context' => 'https://schema.org',
        '@type' => ['LodgingBusiness', 'Hotel'],
        '@id' => $azariSeoHome . '#lodging',
        'name' => $azariSeoSiteName,
        'alternateName' => ['The Resavar', 'Resavar Serviced Stays', 'Resavar Luxury Stays'],
        'url' => $azariSeoHome,
        'description' => $azariSeoDescription,
        'logo' => $azariSeoImage,
        'image' => $azariSeoImage,
        'slogan' => 'Exceptional Stays, Everywhere.',
        'currenciesAccepted' => implode(', ', array_keys((array) config('localization.supported_currencies', []))),
        'paymentAccepted' => ['Credit Card', 'Debit Card', 'Online Payment'],
        'amenityFeature' => [
            ['@type' => 'LocationFeatureSpecification', 'name' => 'Serviced stays', 'value' => true],
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
            'result' => ['@type' => 'LodgingReservation', 'name' => 'Resavar booking'],
        ],
    ];

    $azariOrganizationSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        '@id' => $azariSeoHome . '#organization',
        'name' => $azariSeoSiteName,
        'alternateName' => 'The Resavar',
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
        'alternateName' => 'The Resavar',
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
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Resavar', 'item' => $azariSeoHome],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $azariSeoTitle, 'item' => $azariSeoCanonical],
        ],
    ];
@endphp

<title>{{ $azariSeoTitle }}</title>
<meta name="description" content="{{ $azariSeoDescription }}">
<meta name="keywords" content="{{ $azariSeoKeywords }}">
<meta name="author" content="Resavar">
<meta name="application-name" content="Resavar">
<meta name="apple-mobile-web-app-title" content="Resavar">
<meta name="robots" content="{{ $azariSeoRobots }}">
<meta name="googlebot" content="{{ $azariSeoRobots }}">
<meta name="bingbot" content="{{ $azariSeoRobots }}">
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

<script type="application/ld+json">{!! json_encode($azariLodgingSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script type="application/ld+json">{!! json_encode($azariOrganizationSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script type="application/ld+json">{!! json_encode($azariWebsiteSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script type="application/ld+json">{!! json_encode($azariWebPageSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script type="application/ld+json">{!! json_encode($azariBreadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
