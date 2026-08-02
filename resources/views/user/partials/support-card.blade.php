@php
    $contactEmail = 'hello@' . preg_replace('/^www\./i', '', request()->getHost());
@endphp
<aside class="az-user-support-card">
    <strong>Need assistance?</strong>
    <p>Open a support ticket or contact the residence team directly.</p>
    @if($contactEmail)
        <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>
    @endif
</aside>
