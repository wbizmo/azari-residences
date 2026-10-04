@props([
    'variant' => 'header',
    'dark' => false,
])

@php
    $isFooter = $variant === 'footer';
    $isGuestSidebar = $variant === 'guest-sidebar';
    $onDark = $dark || $isFooter || in_array($variant, ['drawer', 'hero', 'mobile'], true);
    $siteName = 'Resavar';
    $logoUrl = asset($onDark ? 'images/logo-light.png' : 'images/logo-dark.png');
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
