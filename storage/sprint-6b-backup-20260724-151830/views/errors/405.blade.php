@extends('errors.layout')

@section('title', 'Action not allowed')
@section('code', '405')
@section('eyebrow', 'Unsupported action')
@section('heading', 'This action is not available here.')
@section('message')
    The page exists, but it cannot accept the type of request that was sent.
@endsection
@section('support', 'Return to the previous page and try the action again from the appropriate control.')
