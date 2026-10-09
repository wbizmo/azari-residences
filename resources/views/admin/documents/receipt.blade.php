@extends('admin.layouts.app')

@section('content')
    @include('admin.documents.partials.summary', ['documentType' => 'Receipt'])
@endsection
