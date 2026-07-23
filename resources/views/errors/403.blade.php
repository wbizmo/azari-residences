@extends('errors.layout')

@section('title', 'Access denied')
@section('code', '403')
@section('eyebrow', 'Access restricted')
@section('heading', 'You do not have permission to view this page.')
@section('message')
    Your account is signed in, but it does not have the required access for this section.
@endsection
@section('support', 'Return to the previous page or contact an administrator if you believe this restriction is incorrect.')
