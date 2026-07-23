@extends('errors.layout')

@section('title', 'Authentication required')
@section('code', '401')
@section('eyebrow', 'Authentication required')
@section('heading', 'Please sign in to continue.')
@section('message')
    This area is available only to authenticated users. Sign in and try your request again.
@endsection
@section('support', 'For your security, protected pages cannot be opened without a valid session.')
