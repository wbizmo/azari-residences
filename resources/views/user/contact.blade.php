@extends('layouts.user')
@section('title', $title)
@section('content')
<section class="az-user-detail-grid">
    <div class="az-user-panel">
        <header class="az-user-panel-header"><div><h2 class="az-user-panel-title">{{ $title }}</h2><p class="az-user-panel-subtitle">Contact the Resavar team</p></div></header>
        <div class="az-user-panel-body az-user-list">
            @if($contactEmail)<a class="az-user-list-item" href="mailto:{{ $contactEmail }}"><div><h3>Email</h3><p>{{ $contactEmail }}</p></div><span class="material-symbols-outlined">mail</span></a>@endif
            @if($contactPhone)<a class="az-user-list-item" href="tel:{{ $contactPhone }}"><div><h3>Telephone</h3><p>{{ $contactPhone }}</p></div><span class="material-symbols-outlined">call</span></a>@endif
            @if($whatsApp)<a class="az-user-list-item" href="https://wa.me/{{ preg_replace('/\D+/', '', $whatsApp) }}" rel="noopener"><div><h3>WhatsApp</h3><p>{{ $whatsApp }}</p></div><span class="material-symbols-outlined">chat</span></a>@endif
        </div>
    </div>
    <div class="az-user-panel">
        <header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Support hours</h2></div></header>
        <div class="az-user-panel-body"><p>{{ $supportHours }}</p><p class="az-user-help">Include your booking reference when contacting the team about a reservation.</p></div>
    </div>
</section>
@endsection
