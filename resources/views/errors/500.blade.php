@extends('errors.layout')
@section('title', 'Something went wrong')
@section('code', '500')
@section('eyebrow', 'Unexpected interruption')
@section('heading', 'An error occurred on our side.')
@section('message')
    We could not complete your request. We are aware that something went wrong and are working to resolve it.
@endsection
@section('support', 'Please try again shortly. Before retrying a payment or booking action, verify its current status.')
