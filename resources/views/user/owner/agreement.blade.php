@extends('layouts.user')
@section('title','Listing Agreement')
@section('content')
<div class="az-premium-page-head"><div><span class="az-premium-kicker">Owner onboarding</span><h1>Property listing agreement</h1><p>Sign once before submitting your first property. Version {{ $version }}.</p></div></div>
<section class="az-premium-card">
    @unless($identity)
        <div class="az-s78-note">Upload your means of identification in <a class="az-premium-link" href="{{ route('user.identity.index') }}">My Identity</a> before signing.</div>
    @endunless
    <div class="az-s78-note" style="white-space:pre-line">{{ $agreementText }}</div>
    <form class="az-s78-form" method="POST" action="{{ route('user.owner.agreement.sign') }}">
        @csrf
        <label class="az-s78-field"><span>Full legal name</span><input name="legal_name" value="{{ old('legal_name',auth()->user()->name) }}" required></label>
        <label class="az-s78-check"><input type="checkbox" name="agree" value="1" required><span>I agree to the listing terms and adopt my typed name as my electronic signature.</span></label>
        <button class="az-premium-button" type="submit" @disabled(!$identity)>Agree and continue</button>
    </form>
</section>
@endsection
