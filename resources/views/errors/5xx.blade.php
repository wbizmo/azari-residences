@extends('errors.layout')
@section('title', 'Something went wrong')
@section('code', '5xx')
@section('eyebrow', 'Unexpected interruption')
@section('heading', 'An error occurred on our side.')
@section('message')
    We could not complete your request. We are working to identify and resolve the problem.
@endsection
@section('support', 'Please try again shortly. Before retrying a payment or booking action, verify its current status.')
