@extends('errors.layout')
@section('title', 'Access denied')
@section('code', '403')
@section('eyebrow', 'Access restricted')
@section('heading', 'You do not have permission to view this page.')
@section('message')
    Your account does not have access to the requested page or action.
@endsection
@section('support', 'Return to a page available to your account or contact Azari Residences support if you believe this is an error.')
