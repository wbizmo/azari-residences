@php
    $unread = auth()->user()->unreadNotifications()->count();
    $contactEmail = \App\Models\SiteSetting::valueFor('customer_dashboard_contact_email', \App\Models\SiteSetting::valueFor('contact_email', config('mail.from.address')));
@endphp
<section class="az-user-nav-group">
    <p class="az-user-nav-label">Stay</p>
    <nav class="az-user-nav-list">
        <a class="az-user-nav-link {{ request()->routeIs('user.dashboard') ? 'is-active' : '' }}" href="{{ route('user.dashboard') }}"><span class="material-symbols-outlined">space_dashboard</span><span>Dashboard</span></a>
        <a class="az-user-nav-link {{ request()->routeIs('user.bookings.*') ? 'is-active' : '' }}" href="{{ route('user.bookings.index') }}"><span class="material-symbols-outlined">calendar_month</span><span>My bookings</span></a>
        <a class="az-user-nav-link {{ request()->routeIs('user.payments.*') ? 'is-active' : '' }}" href="{{ route('user.payments.index') }}"><span class="material-symbols-outlined">account_balance_wallet</span><span>Payments</span></a>
        <a class="az-user-nav-link {{ request()->routeIs('user.documents.*') ? 'is-active' : '' }}" href="{{ route('user.documents.index') }}"><span class="material-symbols-outlined">description</span><span>Receipts & invoices</span></a>
        <a class="az-user-nav-link {{ request()->routeIs('user.identity.*') ? 'is-active' : '' }}" href="{{ route('user.identity.index') }}"><span class="material-symbols-outlined">badge</span><span>My identity</span></a>
        <a class="az-user-nav-link {{ request()->routeIs('user.guests.*') ? 'is-active' : '' }}" href="{{ route('user.guests.index') }}"><span class="material-symbols-outlined">group</span><span>Additional guests</span></a>
    </nav>
</section>
<section class="az-user-nav-group">
    <p class="az-user-nav-label">Services</p>
    <nav class="az-user-nav-list">
        <a class="az-user-nav-link {{ request()->routeIs('user.service-requests') ? 'is-active' : '' }}" href="{{ route('user.service-requests.index') }}"><span class="material-symbols-outlined">room_service</span><span>Service requests</span></a>
        <a class="az-user-nav-link {{ request()->routeIs('user.support-tickets') ? 'is-active' : '' }}" href="{{ route('user.support-tickets') }}"><span class="material-symbols-outlined">support_agent</span><span>Support tickets</span></a>
        <a class="az-user-nav-link {{ request()->routeIs('user.notifications.*') ? 'is-active' : '' }}" href="{{ route('user.notifications.index') }}"><span class="material-symbols-outlined">notifications</span><span>Notifications</span>@if($unread)<span class="az-user-nav-badge">{{ min($unread,99) }}</span>@endif</a>
    </nav>
</section>
<section class="az-user-nav-group">
    <p class="az-user-nav-label">Account</p>
    <nav class="az-user-nav-list">
        <a class="az-user-nav-link {{ request()->routeIs('user.profile.*') ? 'is-active' : '' }}" href="{{ route('user.profile.edit') }}"><span class="material-symbols-outlined">person</span><span>Profile</span></a>
        <a class="az-user-nav-link {{ request()->routeIs('user.security.*') ? 'is-active' : '' }}" href="{{ route('user.security.index') }}"><span class="material-symbols-outlined">shield</span><span>Security</span></a>
        <a class="az-user-nav-link {{ request()->routeIs('user.contact') ? 'is-active' : '' }}" href="{{ route('user.contact') }}"><span class="material-symbols-outlined">mail</span><span>Contact Azari</span></a>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="az-user-nav-link" type="submit" style="width:100%;border:0;background:transparent;text-align:left"><span class="material-symbols-outlined">logout</span><span>Logout</span></button></form>
    </nav>
</section>
<aside class="az-user-support-card">
    <strong>Need assistance?</strong>
    <p>Contact the residence team or open the support channel for your booking.</p>
    @if($contactEmail)<a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>@endif
</aside>
