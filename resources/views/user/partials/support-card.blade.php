@php
    $contactEmail = \App\Models\SiteSetting::valueFor(
        'customer_dashboard_contact_email',
        \App\Models\SiteSetting::valueFor('public_contact_email', config('mail.from.address'))
    );
@endphp
<aside class="az-user-support-card">
    <strong>Need assistance?</strong>
    <p>Open a support ticket or contact the residence team directly.</p>
    @if($contactEmail)
        <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>
    @endif
</aside>
