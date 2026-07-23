@extends('errors.layout')

@section('title', 'Unable to process request')
@section('code', '422')
@section('eyebrow', 'Request needs attention')
@section('heading', 'We could not process the submitted information.')
@section('message')
    One or more fields may be incomplete or invalid. Review the form and try again.
@endsection
@section('support', 'No completed booking or payment should be assumed unless a confirmation was displayed.')
