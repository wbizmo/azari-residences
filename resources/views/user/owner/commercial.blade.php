@extends('layouts.user')
@section('title','Commercial setup')
@section('kicker','Property Centre')
@section('page_title',$property->name)
@section('content')
<section class="az-user-panel">
    <header class="az-user-panel-header">
        <div>
            <h2 class="az-user-panel-title">Commercial setup</h2>
            <p class="az-user-panel-subtitle">Manage unit quantities, accommodation types, rates and booking policies for this approved property.</p>
        </div>
    </header>
</section>

@include('partials.commercial-inventory-manager', ['property' => $property, 'roomTypes' => $roomTypes, 'ownerMode' => true])
@endsection
