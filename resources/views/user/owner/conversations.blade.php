@extends('layouts.user')
@section('title','Guest messages')
@section('kicker','Property Centre')
@section('page_title',$property->name.' messages')
@section('content')
<section class="az-user-panel"><header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Guest messages</h2><p class="az-user-panel-subtitle">Booking-linked conversations only. Do not use this inbox for marketing.</p></div></header><div class="az-user-panel-body">
@if($conversations->isEmpty())<div class="az-user-empty"><h3>No conversations yet</h3></div>@else<div class="az-user-list">@foreach($conversations as $conversation)<a class="az-user-list-item" href="{{ route('user.owner.phase2.messages.show',[$property,$conversation]) }}"><div><h3>Booking #{{ $conversation->booking_id }}</h3><p>{{ Str::limit($conversation->messages->first()?->body ?? 'No messages yet',120) }}</p></div><span>@if((int) $conversation->guest_unread_count > 0)<strong class="az-user-status" aria-label="{{ $conversation->guest_unread_count }} unread guest messages">{{ $conversation->guest_unread_count }} unread</strong> @endif{{ $conversation->last_message_at?->diffForHumans() }}</span></a>@endforeach</div>{{ $conversations->links() }}@endif
</div></section>
@endsection
