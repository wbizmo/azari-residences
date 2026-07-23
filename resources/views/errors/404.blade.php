@extends('errors.layout')

@section('title', 'Page not found')
@section('code', '404')
@section('eyebrow', 'Nothing at this address')
@section('heading', 'The page you requested could not be found.')
@section('message')
    The address may be incorrect, the page may have moved, or the content may no longer be available.
@endsection
@section('support', 'Check the address, return to the previous page, or continue from the homepage.')
