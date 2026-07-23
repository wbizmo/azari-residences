@extends('errors.layout')

@section('title', 'Service connection error')
@section('code', '502')
@section('eyebrow', 'Temporary connection issue')
@section('heading', 'A supporting service returned an invalid response.')
@section('message')
    The platform could not complete the request because a connected service did not respond correctly.
@endsection
@section('support', 'Please wait briefly and try again.')
