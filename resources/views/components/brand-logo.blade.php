@props([
    'variant' => 'header',
    'dark' => false,
])

@php
    $settings = isset($siteSettings) && is_array($siteSettings) ? $siteSettings : [];
    $siteName = trim((string) ($settings['site_name'] ?? 'Azari Hotels & Residences'));
    $isFooter = $variant === 'footer';
    $isGuestSidebar = in_array($variant, ['guest-sidebar', 'guest-drawer'], true);
    $logoUrl = trim((string) (
        $isGuestSidebar || $dark
            ? ($settings['light_logo_url'] ?? $settings['logo_url'] ?? asset('images/logo-light.png'))
            : ($settings['dark_logo_url'] ?? $settings['logo_url'] ?? asset('images/logo-dark.png'))
    ));
@endphp

<a
    href="{{ $isGuestSidebar ? route('user.dashboard') : url('/') }}"
    {{ $attributes->class([
        'brand',
        'brand-dark' => $dark,
        'azari-brand',
        'azari-brand--footer' => $isFooter,
        'azari-brand--header' => ! $isFooter && ! $isGuestSidebar,
        'azari-brand--guest-sidebar' => $isGuestSidebar,
    ]) }}
    aria-label="{{ $siteName }} {{ $isGuestSidebar ? 'guest dashboard' : 'home' }}"
>
    @if ($logoUrl !== '')
        <span @class(['brand-logo-slot', 'footer-logo-slot' => $isFooter, 'azari-brand__logo-slot', 'guest-brand-logo-slot' => $isGuestSidebar])>
            <img
                src="{{ $logoUrl }}"
                alt="{{ $siteName }}"
                @class(['brand-image', 'footer-brand-image' => $isFooter, 'azari-brand__image', 'guest-brand-image' => $isGuestSidebar])
                loading="{{ $isFooter ? 'lazy' : 'eager' }}"
                decoding="async"
            >
        </span>
    @else
        <span @class(['brand-logo-slot', 'footer-logo-slot' => $isFooter, 'azari-brand__fallback-slot', 'guest-brand-logo-slot' => $isGuestSidebar]) aria-hidden="true">
            <svg class="azari-fallback-mark" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="3" y="3" width="58" height="58" rx="17" stroke="currentColor" stroke-width="2"/>
                <path d="M17 46L32 16L47 46" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M22.5 35H41.5" stroke="currentColor" stroke-width="3.2" stroke-linecap="round"/>
                <circle cx="32" cy="12" r="2" fill="currentColor"/>
            </svg>
        </span>

        @unless ($isGuestSidebar)
            <span class="brand-copy azari-brand__copy">
                <strong>Azari</strong>
                <small>Hotels & Residences</small>
            </span>
        @endunless
    @endif
</a>
