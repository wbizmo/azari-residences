@extends('errors.layout')
@section('title', 'Too many requests')
@section('code', '429')
@section('eyebrow', 'Please slow down')
@section('heading', 'Too many requests were sent in a short period.')
@section('message')
    We temporarily paused this request to protect the service.
@endsection
@section('support', 'Wait briefly before trying again.')
