@extends('layouts.user')
@section('title',$ticket->reference)
@section('page_title','Support request')
@section('content')
<section class="az-user-panel"><div class="az-user-panel-body">
<h2>{{ $ticket->subject }}</h2><p>{{ $ticket->reference }} · {{ ucwords(str_replace('_',' ',$ticket->status)) }}</p>
@forelse($messages as $m)
<article style="padding:16px;border:1px solid #052058;margin:12px 0"><strong>{{ $m->user?->name ?: 'Resarva Support' }}</strong><p>{!! nl2br(e($m->body)) !!}</p><small>{{ $m->created_at->format('j M Y, g:i A') }}</small>@if($m->attachment_path)<div><a href="{{ URL::temporarySignedRoute('user.support.attachment',now()->addMinutes(15),['ticket'=>$ticket,'message'=>$m]) }}">Download {{ $m->attachment_name ?: 'attachment' }}</a></div>@endif</article>
@empty<div class="az-user-empty"><p>No messages have been posted.</p></div>@endforelse
<div class="az-pagination-block">{{ $messages->onEachSide(1)->links() }}</div>
@if($ticket->status!=='closed')<form method="post" enctype="multipart/form-data" action="{{ route('user.support.reply',$ticket) }}" class="az-user-form">@csrf<textarea name="body" rows="5" required></textarea><input type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx"><button class="az-user-button az-user-button--primary">Send reply</button></form><form method="post" action="{{ route('user.support.close',$ticket) }}">@csrf @method('patch')<button class="az-user-button">Close ticket</button></form>@else<form method="post" action="{{ route('user.support.reopen',$ticket) }}">@csrf @method('patch')<button class="az-user-button">Reopen ticket</button></form>@endif
</div></section>
@endsection
