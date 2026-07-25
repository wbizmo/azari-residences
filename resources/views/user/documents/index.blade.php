@extends('layouts.user')
@section('title','Receipts and invoices')
@section('kicker','Document centre')
@section('page_title','Receipts & invoices')
@section('content')

<section class="az-user-panel" style="margin-top:18px"><header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Financial documents</h2><p class="az-user-panel-subtitle">Ten bookings per page</p></div></header><div class="az-user-panel-body">@if($bookings->isEmpty())<div class="az-user-empty"><span class="material-symbols-outlined">description</span><p>No bookings are available.</p></div>@else<div class="az-user-list">@foreach($bookings as $booking)@php($payment=$booking->payments->first())<div class="az-user-list-item"><div><h3>{{ $booking->reference }} · {{ $booking->property?->name }}</h3><p>Invoice: {{ $booking->status==='cancelled'?'Cancelled booking':'Financial record retained' }} · Receipt: {{ $payment?->receipt_number ?: 'Not yet available' }}</p></div><a class="az-user-button az-user-button--light" href="{{ route('user.bookings.show',$booking->reference) }}">Booking details</a></div>@endforeach</div>{{ $bookings->links() }}@endif</div></section>
@endsection
