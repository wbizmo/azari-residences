@extends('errors.layout')
@section('title', 'Request could not be completed')
@section('code', '4xx')
@section('eyebrow', 'Request interrupted')
@section('heading', 'We could not complete this request.')
@section('message')
    The requested page or action is unavailable, invalid, expired, or restricted.
@endsection
@section('support', 'Return to the previous page and try again. Contact Resavar support if the issue continues.')
