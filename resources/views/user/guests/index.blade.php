@extends('layouts.user')
@section('title','Additional guests')
@section('kicker','Booking guests')
@section('page_title','Additional guests')

@section('content')
<section class="az-user-panel">
<header class="az-user-panel-header">
    <div>
        <h2 class="az-user-panel-title">Additional guests</h2>
        <p class="az-user-panel-subtitle">Guests attached to your bookings</p>
    </div>
</header>

<div class="az-user-panel-body">
@if($guests->isEmpty())
    <div class="az-user-empty">
        <span class="material-symbols-outlined">group</span>
        <p>No additional adults are currently attached to your bookings.</p>
    </div>
@else
    <div class="az-user-list">
    @foreach($guests as $guest)
        <article class="az-user-list-item">
            <div>
                <h3>{{ $guest->full_name }} · {{ $guest->booking?->reference }}</h3>
                <p>
                    {{ $guest->booking?->property?->name }}
                    @if($guest->email) · {{ $guest->email }} @endif
                </p>
            </div>
            <span class="az-user-status">{{ ucfirst($guest->type) }}</span>
        </article>
    @endforeach
    </div>

    {{ $guests->links() }}
@endif
</div>
</section>
@endsection
