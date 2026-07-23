@extends('errors.layout')

@section('title', 'Too many requests')
@section('code', '429')
@section('eyebrow', 'Please slow down')
@section('heading', 'Too many requests were received in a short time.')
@section('message')
    To protect the platform, access has been paused briefly for this connection.
@endsection
@section('support', 'Wait a moment before trying again.')
