@extends('errors.layout')
@section('title', 'Request timed out')
@section('code', '408')
@section('eyebrow', 'Connection took too long')
@section('heading', 'The request timed out before it could be completed.')
@section('message')
    Your connection may have been interrupted or the request may have taken longer than expected.
@endsection
@section('support', 'Check your connection and try again.')
