@props([
    'variant' => 'header',
    'dark' => false,
])

@php
    $settings = isset($siteSettings) && is_array($siteSettings)
        ? $siteSettings
        : [];

    $logoUrl = trim((string) ($settings['logo_url'] ?? ''));
    $siteName = trim((string) ($settings['site_name'] ?? 'Azari Residences'));

    $isFooter = $variant === 'footer';
@endphp

<a
    href="{{ url('/') }}"
    {{ $attributes->class([
        'brand',
        'brand-dark' => $dark,
        'azari-brand',
        'azari-brand--footer' => $isFooter,
        'azari-brand--header' => ! $isFooter,
    ]) }}
    aria-label="{{ $siteName }} home"
>
    @if ($logoUrl !== '')
        <span
            @class([
                'brand-logo-slot',
                'footer-logo-slot' => $isFooter,
                'azari-brand__logo-slot',
            ])
        >
            <img
                src="{{ $logoUrl }}"
                alt="{{ $siteName }}"
                @class([
                    'brand-image',
                    'footer-brand-image' => $isFooter,
                    'azari-brand__image',
                ])
                loading="{{ $isFooter ? 'lazy' : 'eager' }}"
                decoding="async"
            >
        </span>
    @else
        <span
            @class([
                'brand-logo-slot',
                'footer-logo-slot' => $isFooter,
                'azari-brand__fallback-slot',
            ])
            aria-hidden="true"
        >
            <span class="material-symbols-outlined">hotel_class</span>
        </span>

        <span class="brand-copy azari-brand__copy">
            <strong>Azari</strong>
            <small>Residences</small>
        </span>
    @endif
</a>
