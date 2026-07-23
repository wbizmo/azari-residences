@extends('components.public.layout')
@section('title', '401 Error')
@section('content')
<section class="site-container" style="min-height:70vh;display:grid;place-items:center;padding:120px 0 80px">
<div class="az-error-page"><span>401</span><h1>@switch(401)@case(401)Authentication required@break @case(403)Access denied@break @case(404)Page not found@break @case(419)Your session expired@break @case(422)Unable to process request@break @case(429)Too many requests@break @case(500)Internal server error@break @case(503)Service temporarily unavailable@break @endswitch</h1><p>Please return to the homepage or try again shortly.</p><a class="az-button" href="{{ url('/') }}">Return home</a></div>
</section>
@endsection
