@props(['variant' => 'header','dark' => false])
@php
    $settings = isset($siteSettings) && is_array($siteSettings) ? $siteSettings : [];
    $siteName = trim((string) ($settings['site_name'] ?? 'Resavar'));
    $isFooter = $variant === 'footer';
    $isGuestSidebar = in_array($variant, ['guest-sidebar', 'guest-drawer'], true);
    $onDark = $dark || $isFooter || $isGuestSidebar;
    $configured = trim((string) ($onDark ? ($settings['light_logo_url'] ?? '') : ($settings['dark_logo_url'] ?? $settings['logo_url'] ?? '')));
    $logoUrl = $configured !== '' ? $configured : asset($onDark ? 'images/logo-light.png' : 'images/logo-dark.png');
@endphp
<a href="{{ $isGuestSidebar ? route('user.dashboard') : url('/') }}" {{ $attributes->class(['brand','brand-dark'=>$onDark,'azari-brand','azari-brand--footer'=>$isFooter,'azari-brand--header'=>!$isFooter&&!$isGuestSidebar,'azari-brand--guest-sidebar'=>$isGuestSidebar]) }} aria-label="{{ $siteName }} {{ $isGuestSidebar ? 'guest dashboard' : 'home' }}">
    <span @class(['brand-logo-slot','footer-logo-slot'=>$isFooter,'azari-brand__logo-slot','guest-brand-logo-slot'=>$isGuestSidebar])>
        <img class="azari-brand__logo-image" src="{{ $logoUrl }}" alt="{{ $siteName }}" loading="{{ $variant === 'header' ? 'eager' : 'lazy' }}">
    </span>
</a>
