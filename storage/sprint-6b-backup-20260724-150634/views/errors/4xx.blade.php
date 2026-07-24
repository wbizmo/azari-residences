@extends('errors.layout')

@section('title', 'Request could not be completed')
@section('code', '4xx')
@section('eyebrow', 'Request interrupted')
@section('heading', 'We could not complete this request.')
@section('message')
    The requested page or action is unavailable, invalid, or restricted. Return to the previous page and try again.
@endsection
@section('support', 'If this keeps happening, contact Azari Residences support and describe the page or action you were using.')
