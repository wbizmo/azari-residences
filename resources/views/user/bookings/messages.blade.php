@extends('layouts.user')
@section('title','Messages')
@section('kicker','Trip')
@section('page_title','Message '.$booking->property?->name)
@section('content')
<section class="az-user-panel"><div class="az-user-panel-body"><p>Conversation for booking <strong>{{ $booking->reference }}</strong>. Messages stay attached to this booking for support and dispute traceability.</p>
<div class="az-user-list">@foreach($messages as $message)<article class="az-user-list-item"><div><strong>{{ $message->sender_type === 'guest' ? 'You' : 'Property team' }}</strong><p>{!! nl2br(e($message->body)) !!}</p><small>{{ $message->created_at->format('j M Y, H:i') }}</small>@if($message->attachment_path)<p><a href="{{ route('user.bookings.phase2.messages.attachment',[$booking->reference,$message]) }}">Download attachment</a></p>@endif</div></article>@endforeach</div>{{ $messages->links() }}
<form method="POST" enctype="multipart/form-data" action="{{ route('user.bookings.phase2.messages.store',$booking->reference) }}" class="az-form-grid" style="margin-top:18px">@csrf<label style="grid-column:1/-1"><span>Message</span><textarea name="body" required maxlength="5000" rows="4"></textarea></label><label><span>Attachment</span><input type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf"></label><button class="az-user-button az-user-button--dark" type="submit">Send message</button></form>
</div></section>
@endsection
