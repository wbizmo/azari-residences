@props([
    'variant' => 'header',
    'dark' => false,
])

@php
    $isFooter = $variant === 'footer';
    $isGuestSidebar = in_array($variant, ['guest-sidebar', 'guest-drawer'], true);
    $onDark = $dark || $isFooter || $isGuestSidebar || in_array($variant, ['drawer', 'hero', 'mobile'], true);
    $siteName = 'Resarva';

    // Approved bundled assets:
    // logo-light.png = navy/dark artwork for white/light surfaces.
    // logo-dark.png  = white artwork for navy/dark surfaces.
    $logoUrl = asset($onDark ? 'images/logo-dark.png' : 'images/logo-light.png');
@endphp

<span {{ $attributes->class([
    'brand-logo-slot',
    'footer-logo-slot' => $isFooter,
    'azari-brand__logo-slot',
    'guest-brand-logo-slot' => $isGuestSidebar,
]) }}>
    <img
        class="brand-image azari-brand__image azari-brand__logo-image"
        src="{{ $logoUrl }}"
        alt="{{ $siteName }}"
        loading="{{ $variant === 'header' ? 'eager' : 'lazy' }}"
        decoding="async"
    >
</span>
