@extends('layouts.user')
@section('title','Guest reviews')
@section('kicker','Property Centre')
@section('page_title',$property->name.' reviews')
@section('content')
<section class="az-user-panel"><header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Verified guest reviews</h2><p class="az-user-panel-subtitle">Respond as the property team. Guest review text cannot be edited by owners.</p></div></header><div class="az-user-panel-body">
@forelse($reviews as $review)
<article class="az-user-list-item"><div style="width:100%"><strong>{{ $review->rating }}/5 @if($review->title) · {{ $review->title }} @endif</strong><p>{{ $review->body }}</p>
@if($review->edited_at)<small>Guest updated {{ $review->edited_at->diffForHumans() }}</small>@endif
<form method="POST" action="{{ route('user.owner.phase2.reviews.reply',[$property,$review]) }}" class="az-form-grid" style="margin-top:10px">@csrf<label style="grid-column:1/-1"><span>Property response</span><textarea name="reply" required maxlength="2000" rows="3">{{ old('reply',$review->owner_reply) }}</textarea></label><button class="az-user-button az-user-button--dark" type="submit">Save response</button></form>
</div></article>
@empty<div class="az-user-empty"><h3>No approved reviews yet</h3></div>@endforelse
{{ $reviews->links() }}
</div></section>
@endsection
