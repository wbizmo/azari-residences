@extends('errors.layout')

@section('title', 'Session expired')
@section('code', '419')
@section('eyebrow', 'Session expired')
@section('heading', 'Your session has expired.')
@section('message')
    For security, this form can no longer be submitted. Return to the page, refresh it, and try again.
@endsection
@section('support', 'Any unsaved information may need to be entered again.')
