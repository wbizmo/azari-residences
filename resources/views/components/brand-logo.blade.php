@props([
    'variant' => 'header',
    'dark' => false,
])

@php
    $siteName = 'Resavar';
    $isFooter = $variant === 'footer';
    $isGuestSidebar = in_array($variant, ['guest-sidebar', 'guest-drawer'], true);
    $onDark = $dark || $isFooter || $isGuestSidebar || in_array($variant, ['drawer', 'hero', 'mobile'], true);
    $logoUrl = asset($onDark ? 'images/resavar-logo-dark.png' : 'images/resavar-logo-light.png');
@endphp

<a
    href="{{ $isGuestSidebar ? route('user.dashboard') : url('/') }}"
    {{ $attributes->class([
        'brand',
        'brand-dark' => $onDark,
        'azari-brand',
        'azari-brand--footer' => $isFooter,
        'azari-brand--header' => ! $isFooter && ! $isGuestSidebar,
        'azari-brand--guest-sidebar' => $isGuestSidebar,
    ]) }}
    aria-label="{{ $siteName }} {{ $isGuestSidebar ? 'guest dashboard' : 'home' }}"
>
    <span @class([
        'brand-logo-slot',
        'footer-logo-slot' => $isFooter,
        'azari-brand__logo-slot',
        'guest-brand-logo-slot' => $isGuestSidebar,
    ])>
        <img
            src="{{ $logoUrl }}"
            alt="{{ $siteName }}"
            class="brand-image azari-brand__image azari-brand__logo-image"
            loading="{{ $variant === 'header' ? 'eager' : 'lazy' }}"
            decoding="async"
        >
    </span>
</a>
