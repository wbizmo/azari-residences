@extends('errors.layout')
@section('title', 'Page expired')
@section('code', '419')
@section('eyebrow', 'Session expired')
@section('heading', 'This page has expired.')
@section('message')
    Your session or security token expired before the request could be completed.
@endsection
@section('support', 'Refresh the page, sign in again when required, and retry the action.')
