@extends('admin.layouts.app')
@section('title', 'Dashboard')
@section('content')
<div class="az-page-heading"><div><p class="az-eyebrow">Administration</p><h1>Dashboard</h1></div></div>
<div class="az-stat-grid">
    <article><span>Total users</span><strong>{{ number_format($userCount) }}</strong></article>
    <article><span>Administrators</span><strong>{{ number_format($adminCount) }}</strong></article>
</div>
@endsection
