@extends('errors.layout')

@section('title', 'Something went wrong')
@section('code', '5xx')
@section('eyebrow', 'Unexpected interruption')
@section('heading', 'Something went wrong on our side.')
@section('message')
    We could not complete your request. The issue has been recorded where application logging is available, and it will be reviewed.
@endsection
@section('support', 'Please try again shortly. For payments or bookings, verify your status before attempting the action again.')
