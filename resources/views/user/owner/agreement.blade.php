@extends('layouts.user')
@section('title','Property listing agreement')
@section('kicker','Property Centre')
@section('page_title','Property listing agreement')
@section('content')
<section class="az-user-panel"><header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Agreement version {{ $version }}</h2><p class="az-user-panel-subtitle">Your verified identity and legal account name are attached to this acceptance.</p></div></header><div class="az-user-panel-body">
@if(!$identity)<div class="az-user-alert az-user-alert--danger"><strong>Identity required</strong><p>Upload your identification before signing this agreement.</p><a href="{{ route('user.identity.index') }}">Open My Identity</a></div>@endif
<div style="white-space:pre-line;line-height:1.8">{{ $agreementText }}</div>
<form method="post" action="{{ route('user.owner.agreement.sign') }}" class="az-user-form">@csrf
<label>Full legal name<input name="legal_name" value="{{ old('legal_name',auth()->user()->name) }}" required></label>
<label><input type="checkbox" name="agree" value="1" required> I have read and accept this agreement.</label>
<button class="az-user-button az-user-button--dark" @disabled(!$identity)>Sign agreement</button>
</form></div></section>
@endsection
