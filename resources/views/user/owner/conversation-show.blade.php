@extends('layouts.user')
@section('title','Booking conversation')
@section('kicker','Property Centre')
@section('page_title','Guest conversation')
@section('content')
<section class="az-user-panel"><div class="az-user-panel-body">
<div class="az-user-list">@foreach($messages as $message)<article class="az-user-list-item"><div><strong>{{ $message->sender_type === 'property' ? 'Property team' : 'Guest' }}</strong><p>{!! nl2br(e($message->body)) !!}</p><small>{{ $message->created_at->format('j M Y, H:i') }}</small>@if($message->attachment_path)
    @if($message->attachment_scan_status === 'clean')
        <p><a href="{{ route('user.owner.phase2.messages.attachment',[$property,$conversation,$message]) }}">Download attachment</a></p>
    @elseif($message->attachment_scan_status === 'infected' || $message->attachment_scan_status === 'error')
        <p role="status">Attachment unavailable: security review required.</p>
    @else
        <p role="status">Attachment is being checked for safety. Download will be available after a clean scan.</p>
    @endif
@endif</div></article>@endforeach</div>{{ $messages->links() }}
<form method="POST" enctype="multipart/form-data" action="{{ route('user.owner.phase2.messages.store',[$property,$conversation]) }}" class="az-form-grid" style="margin-top:18px">@csrf<input type="hidden" name="client_token" value="{{ \Illuminate\Support\Str::uuid() }}"><label style="grid-column:1/-1"><span>Reply</span><textarea name="body" required maxlength="5000" rows="4"></textarea></label><label><span>Attachment</span><input type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf"></label><button class="az-user-button az-user-button--dark" type="submit">Send</button></form>
</div></section>
@endsection
