<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.azari-head-assets')

        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php
            $seoTitle = $title ?? 'Azari Residences';

            $seoDescription = $description ?? <<<TEXT
Azari Residences offers a refined hospitality experience designed for guests who value comfort, privacy, thoughtful service, and a memorable sense of place. Every stay is shaped around elegant accommodation, dependable support, and the freedom to enjoy a calm home away from home, whether the visit is for business, leisure, a family escape, a romantic break, or an extended stay. Guests can explore beautifully presented residences, serviced apartments, suites, and carefully considered spaces created to make arrival easy, rest effortless, and every day more enjoyable. Azari Residences brings together modern living, secure surroundings, responsive guest assistance, professional housekeeping, convenient booking, and service options that support a smooth stay from reservation to departure. The experience extends beyond accommodation through concierge support, airport transfer arrangements, dining guidance, local recommendations, service requests, booking verification, digital payments, receipts, invoices, identity management, and clear communication throughout the guest journey. Each detail is intended to reduce friction and help visitors focus on what matters most: relaxing, working, connecting, exploring, and enjoying their time with confidence. Azari Residences welcomes solo travellers, couples, families, corporate guests, groups, and returning visitors seeking a dependable destination with character, warmth, and contemporary appeal. The brand reflects a commitment to refined living without unnecessary complexity, combining premium presentation with practical convenience and a guest-first approach. Visitors can review available apartments and rooms, understand policies, explore nearby experiences, request assistance, manage bookings, view payments, access documents, receive notifications, and maintain their profile through a secure and accessible platform. From short stays and weekend getaways to business travel and longer visits, Azari Residences is built to provide consistent quality, flexible hospitality, and a welcoming environment. With an emphasis on comfort, cleanliness, privacy, service, and trust, every interaction is designed to support a seamless experience before, during, and after each stay. Discover accommodation that feels polished yet personal, modern yet inviting, and premium without losing the warmth of genuine hospitality. Azari Residences is more than a place to sleep; it is a carefully managed destination where guests can settle in, feel supported, and enjoy Africa’s finest address with confidence, convenience, and lasting memories. Thoughtful design, intuitive technology, transparent policies, and responsive operations work together to create an experience suited to both first-time and returning guests. Whether planning ahead or arranging a spontaneous visit, guests can expect clear information, straightforward choices, reliable assistance, and spaces prepared with care for comfort, productivity, celebration, and rest at every stage.
TEXT;

            $seoKeywords = $keywords ?? implode(', ', [
                'Azari',
                'Azari Residences',
                'Azari Resort',
                'Azari Apartments',
                'Azari Luxury Residences',
                'Azari Luxury Apartments',
                'Azari Serviced Apartments',
                'Azari Short Stay',
                'Azari Short Let',
                'Azari Holiday Apartments',
                'Azari Vacation Rentals',
                'Azari Boutique Residences',
                'Azari Premium Apartments',
                'Azari Executive Apartments',
                'Azari Family Apartments',
                'Azari Business Stay',
                'Azari Corporate Stay',
                'Azari Weekend Getaway',
                'Azari City Stay',
                'Azari Urban Retreat',
                'Azari Luxury Stay',
                'Azari Premium Stay',
                'Azari Elegant Residences',
                'Azari Modern Apartments',
                'Azari Private Apartments',
                'Azari Exclusive Residences',
                'Azari Hospitality',
                'Azari Accommodation',
                'Azari Lodging',
                'Azari Guest Apartments',
                'Azari Residential Suites',
                'Azari Luxury Suites',
                'Azari Executive Suites',
                'Azari Holiday Suites',
                'Azari Private Suites',
                'Azari Premium Suites',
                'Azari Residence Booking',
                'Azari Apartment Booking',
                'Azari Resort Booking',
                'Azari Online Booking',
                'Azari Direct Booking',
                'Azari Best Rate',
                'Azari Comfortable Stay',
                'Azari Peaceful Stay',
                'Azari Secure Stay',
                'Azari Relaxing Stay',
                'Azari Stylish Apartments',
                'Azari Contemporary Residences',
                'Azari Furnished Apartments',
                'Azari Fully Furnished Apartments',
                'Azari Apartment Rentals',
                'Azari Residence Rentals',
                'Azari Vacation Stay',
                'Azari Holiday Stay',
                'Azari Long Stay',
                'Azari Extended Stay',
                'Azari Nightly Stay',
                'Azari Monthly Stay',
                'Azari Romantic Getaway',
                'Azari Family Getaway',
                'Azari Business Travel',
                'Azari Leisure Travel',
                'Azari Travel Accommodation',
                'Azari Tourist Accommodation',
                'Azari Luxury Hospitality',
                'Azari Premium Hospitality',
                'Azari Guest Experience',
                'Azari Concierge Services',
                'Azari Housekeeping Services',
                'Azari Airport Transfer',
                'Azari Dining Experience',
                'Azari Local Guide',
                'Azari Travel Guide',
                'Azari Destination Stay',
                'Azari Home Away From Home',
                'Azari Apartment Hotel',
                'Azari Aparthotel',
                'Azari Boutique Hotel',
                'Azari Luxury Hotel',
                'Azari Resort Apartments',
                'Azari Private Residence',
                'Azari Premium Residence',
                'Azari Elegant Apartments',
                'Azari Spacious Apartments',
                'Azari Comfortable Apartments',
                'Azari Secure Apartments',
                'Azari Modern Residences',
                'Azari Stylish Residences',
                'Azari Furnished Residences',
                'Azari Serviced Residences',
                'Azari Luxury Getaway',
                'Azari Premium Getaway',
                'Azari Exclusive Stay',
                'Azari Memorable Stay',
                'Azari Refined Living',
                'Azari Luxury Living',
                'Azari Premium Living',
                'Azari Urban Living',
                'Azari Residential Experience',
                'Azari Africa\'s Finest Address',
            ]);

            $seoImage = $image ?? asset('images/azari-favicon.png');
            $seoUrl = $canonical ?? url()->current();
            $seoType = $type ?? 'website';
            $seoLocale = str_replace('-', '_', app()->getLocale());
            $seoSiteName = 'Azari Residences';
        @endphp

        <title>{{ $seoTitle }}</title>

        <!-- Primary SEO -->
        <meta name="description" content="{{ $seoDescription }}">
        <meta name="keywords" content="{{ $seoKeywords }}">
        <meta name="author" content="Azari Residences">
        <meta name="application-name" content="Azari Residences">
        <meta name="apple-mobile-web-app-title" content="Azari Residences">
        <meta name="generator" content="Azari Residences">
        <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
        <meta name="googlebot" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
        <meta name="bingbot" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
        <meta name="referrer" content="strict-origin-when-cross-origin">
        <meta name="format-detection" content="telephone=no">
        <meta name="theme-color" content="#123F37">
        <meta name="msapplication-TileColor" content="#123F37">
        <meta name="color-scheme" content="light">
        <meta name="rating" content="general">
        <meta name="distribution" content="global">
        <meta name="language" content="{{ app()->getLocale() }}">

        <!-- Canonical URL -->
        <link rel="canonical" href="{{ $seoUrl }}">

        <!-- Favicons -->
        <link rel="icon" type="image/png" href="{{ asset('images/azari-favicon.png') }}">
        <link rel="shortcut icon" type="image/png" href="{{ asset('images/azari-favicon.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('images/azari-favicon.png') }}">
        <link rel="apple-touch-icon-precomposed" href="{{ asset('images/azari-favicon.png') }}">
        <link rel="mask-icon" href="{{ asset('images/azari-favicon.png') }}" color="#123F37">

        <!-- Open Graph -->
        <meta property="og:title" content="{{ $seoTitle }}">
        <meta property="og:description" content="{{ $seoDescription }}">
        <meta property="og:type" content="{{ $seoType }}">
        <meta property="og:url" content="{{ $seoUrl }}">
        <meta property="og:image" content="{{ $seoImage }}">
        <meta property="og:image:secure_url" content="{{ $seoImage }}">
        <meta property="og:image:type" content="image/png">
        <meta property="og:image:alt" content="{{ $seoTitle }} — Azari Residences">
        <meta property="og:site_name" content="{{ $seoSiteName }}">
        <meta property="og:locale" content="{{ $seoLocale }}">
        <meta property="og:locale:alternate" content="en_US">
        <meta property="og:locale:alternate" content="en_GB">
        <meta property="og:determiner" content="the">

        <!-- Twitter/X Card -->
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $seoTitle }}">
        <meta name="twitter:description" content="{{ $seoDescription }}">
        <meta name="twitter:image" content="{{ $seoImage }}">
        <meta name="twitter:image:alt" content="{{ $seoTitle }} — Azari Residences">
        <meta name="twitter:url" content="{{ $seoUrl }}">

        <!-- Mobile and Progressive Web App Metadata -->
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="HandheldFriendly" content="true">
        <meta name="MobileOptimized" content="width">
        <meta http-equiv="x-ua-compatible" content="IE=edge">

        <!-- Resource Hints -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link rel="dns-prefetch" href="//fonts.bunny.net">

        <!-- Fonts -->
        <link
            href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap"
            rel="stylesheet"
        >

        <!-- Structured Data: Lodging Business -->
        <script type="application/ld+json">
            @json([
                '@context' => 'https://schema.org',
                '@type' => [
                    'LodgingBusiness',
                    'LocalBusiness',
                ],
                '@id' => url('/') . '#azari-residences',
                'name' => 'Azari Residences',
                'alternateName' => [
                    'The Azari',
                    'Azari Resort',
                    'Azari Apartments',
                ],
                'description' => $seoDescription,
                'url' => url('/'),
                'logo' => asset('images/azari-favicon.png'),
                'image' => [
                    asset('images/azari-favicon.png'),
                ],
                'slogan' => 'Africa’s finest address',
                'priceRange' => '$$',
                'currenciesAccepted' => 'NGN',
                'paymentAccepted' => [
                    'Cash',
                    'Credit Card',
                    'Debit Card',
                    'Online Payment',
                ],
                'sameAs' => [],
                'amenityFeature' => [
                    [
                        '@type' => 'LocationFeatureSpecification',
                        'name' => 'Serviced accommodation',
                        'value' => true,
                    ],
                    [
                        '@type' => 'LocationFeatureSpecification',
                        'name' => 'Concierge services',
                        'value' => true,
                    ],
                    [
                        '@type' => 'LocationFeatureSpecification',
                        'name' => 'Housekeeping services',
                        'value' => true,
                    ],
                    [
                        '@type' => 'LocationFeatureSpecification',
                        'name' => 'Airport transfer service',
                        'value' => true,
                    ],
                    [
                        '@type' => 'LocationFeatureSpecification',
                        'name' => 'Online booking',
                        'value' => true,
                    ],
                    [
                        '@type' => 'LocationFeatureSpecification',
                        'name' => 'Guest support',
                        'value' => true,
                    ],
                ],
                'potentialAction' => [
                    [
                        '@type' => 'ReserveAction',
                        'target' => [
                            '@type' => 'EntryPoint',
                            'urlTemplate' => route('booking.create'),
                            'actionPlatform' => [
                                'https://schema.org/DesktopWebPlatform',
                                'https://schema.org/MobileWebPlatform',
                            ],
                        ],
                        'result' => [
                            '@type' => 'LodgingReservation',
                            'name' => 'Azari Residences booking',
                        ],
                    ],
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        </script>

        <!-- Structured Data: Website -->
        <script type="application/ld+json">
            @json([
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                '@id' => url('/') . '#website',
                'url' => url('/'),
                'name' => 'Azari Residences',
                'alternateName' => 'The Azari',
                'description' => $seoDescription,
                'inLanguage' => app()->getLocale(),
                'publisher' => [
                    '@id' => url('/') . '#organization',
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        </script>

        <!-- Structured Data: Organization -->
        <script type="application/ld+json">
            @json([
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                '@id' => url('/') . '#organization',
                'name' => 'Azari Residences',
                'alternateName' => 'The Azari',
                'url' => url('/'),
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => asset('images/azari-favicon.png'),
                    'contentUrl' => asset('images/azari-favicon.png'),
                    'caption' => 'Azari Residences',
                ],
                'image' => asset('images/azari-favicon.png'),
                'description' => $seoDescription,
                'slogan' => 'Africa’s finest address',
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        </script>

        <!-- Structured Data: Current Web Page -->
        <script type="application/ld+json">
            @json([
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                '@id' => $seoUrl . '#webpage',
                'url' => $seoUrl,
                'name' => $seoTitle,
                'headline' => $seoTitle,
                'description' => $seoDescription,
                'isPartOf' => [
                    '@id' => url('/') . '#website',
                ],
                'about' => [
                    '@id' => url('/') . '#azari-residences',
                ],
                'primaryImageOfPage' => [
                    '@type' => 'ImageObject',
                    'url' => $seoImage,
                    'contentUrl' => $seoImage,
                    'caption' => $seoTitle,
                ],
                'breadcrumb' => [
                    '@id' => $seoUrl . '#breadcrumb',
                ],
                'inLanguage' => app()->getLocale(),
                'dateModified' => now()->toIso8601String(),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        </script>

        <!-- Structured Data: Breadcrumb -->
        <script type="application/ld+json">
            @json([
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                '@id' => $seoUrl . '#breadcrumb',
                'itemListElement' => [
                    [
                        '@type' => 'ListItem',
                        'position' => 1,
                        'name' => 'Home',
                        'item' => url('/'),
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 2,
                        'name' => $seoTitle,
                        'item' => $seoUrl,
                    ],
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        </script>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>
    </body>
</html>