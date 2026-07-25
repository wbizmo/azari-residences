@extends('errors.layout')
@section('title', 'Bad request')
@section('code', '400')
@section('eyebrow', 'Invalid request')
@section('heading', 'We could not understand this request.')
@section('message')
    The request contained incomplete or invalid information and could not be processed.
@endsection
@section('support', 'Return to the previous page, review the information entered, and try again.')
