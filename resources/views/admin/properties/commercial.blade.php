@extends('admin.layouts.app')
@section('content')
<div class="admin-heading">
    <div>
        <span>Commercial setup</span>
        <h1>{{ $property->name }}</h1>
        <p>Manage bookable accommodation types, unit quantities, rate plans, cancellation rules and payment choices.</p>
    </div>
    <a class="button button-secondary" href="{{ route('azari.admin.properties.edit', $property) }}">Back to property</a>
</div>

@include('partials.commercial-inventory-manager', ['property' => $property, 'roomTypes' => $roomTypes, 'ownerMode' => false])
@endsection
