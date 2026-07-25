@extends('errors.layout')
@section('title', 'Gateway timeout')
@section('code', '504')
@section('eyebrow', 'Service response delayed')
@section('heading', 'A supporting service took too long to respond.')
@section('message')
    The request could not be completed within the expected time.
@endsection
@section('support', 'Check your connection and try again shortly.')
