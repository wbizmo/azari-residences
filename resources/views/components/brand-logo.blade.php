@props(['variant' => 'header','dark' => false])
@php
    $settings = isset($siteSettings) && is_array($siteSettings) ? $siteSettings : [];
    $siteName = trim((string) ($settings['site_name'] ?? 'Resavar')) ?: 'Resavar';
    $isFooter = $variant === 'footer';
    $isGuestSidebar = in_array($variant, ['guest-sidebar', 'guest-drawer'], true);
    $onDark = $dark || $isFooter || $isGuestSidebar;

    /*
     * Public branding is pinned to the bundled Resavar assets so stale CMS
     * uploads cannot replace the production logo.
     * logo-light.png = navy artwork for light surfaces.
     * logo-dark.png  = white artwork for dark/navy surfaces.
     */
    $logoUrl = asset($onDark ? 'images/logo-dark.png' : 'images/logo-light.png');
@endphp
<a href="{{ $isGuestSidebar ? route('user.dashboard') : url('/') }}" {{ $attributes->class(['brand','brand-dark'=>$onDark,'azari-brand','azari-brand--footer'=>$isFooter,'azari-brand--header'=>!$isFooter&&!$isGuestSidebar,'azari-brand--guest-sidebar'=>$isGuestSidebar]) }} aria-label="{{ $siteName }} {{ $isGuestSidebar ? 'guest dashboard' : 'home' }}">
    <span @class(['brand-logo-slot','footer-logo-slot'=>$isFooter,'azari-brand__logo-slot','guest-brand-logo-slot'=>$isGuestSidebar])>
        <img class="azari-brand__logo-image" src="{{ $logoUrl }}" alt="{{ $siteName }}" loading="{{ $variant === 'header' ? 'eager' : 'lazy' }}" decoding="async">
    </span>
</a>
