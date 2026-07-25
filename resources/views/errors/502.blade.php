@extends('errors.layout')
@section('title', 'Service connection error')
@section('code', '502')
@section('eyebrow', 'Temporary connection issue')
@section('heading', 'A supporting service returned an invalid response.')
@section('message')
    We could not complete your request because a connected service did not respond correctly.
@endsection
@section('support', 'Wait briefly and try again.')
