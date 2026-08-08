@extends('errors.layout')

@section('title', $title ?? 'Something went wrong')
@section('code', $exception?->getStatusCode() ?? 'Error')
@section('eyebrow', 'Request interrupted')
@section('heading', $title ?? 'An error occurred.')
@section('message')
    {{ $message ?? 'We could not complete your request. We are working to identify and resolve the problem.' }}
@endsection
@section('support', 'Please try again shortly. If the issue continues, contact Azari Hotels & Residences support.')
