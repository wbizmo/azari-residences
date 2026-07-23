@extends('errors.layout')

@section('title', 'Service unavailable')
@section('code', '503')
@section('eyebrow', 'Temporarily unavailable')
@section('heading', 'Azari Residences is temporarily unavailable.')
@section('message')
    The platform may be undergoing maintenance or experiencing a temporary service interruption.
@endsection
@section('support', 'Please try again in a few minutes.')
