@props(['variant' => 'header','dark' => false])
@php
    $settings = isset($siteSettings) && is_array($siteSettings) ? $siteSettings : [];
    $siteName = trim((string) ($settings['site_name'] ?? 'Azari Hotels & Residences'));
    $isFooter = $variant === 'footer';
    $isGuestSidebar = in_array($variant, ['guest-sidebar', 'guest-drawer'], true);
    $onDark = $dark || $isFooter || $isGuestSidebar;
    $logoUrl = trim((string) ($settings['logo_url'] ?? asset('images/logo-dark.png')));
@endphp
<a href="{{ $isGuestSidebar ? route('user.dashboard') : url('/') }}" {{ $attributes->class(['brand','brand-dark'=>$onDark,'azari-brand','azari-brand--footer'=>$isFooter,'azari-brand--header'=>!$isFooter&&!$isGuestSidebar,'azari-brand--guest-sidebar'=>$isGuestSidebar]) }} aria-label="{{ $siteName }} {{ $isGuestSidebar ? 'guest dashboard' : 'home' }}">
    <span @class(['brand-logo-slot','footer-logo-slot'=>$isFooter,'azari-brand__logo-slot','guest-brand-logo-slot'=>$isGuestSidebar])>
        <span class="azari-logo-mask {{ $onDark ? 'azari-logo-on-dark' : 'azari-logo-on-light' }}" style="--azari-logo-url: url('{{ $logoUrl }}')" aria-hidden="true"></span>
    </span>
</a>
