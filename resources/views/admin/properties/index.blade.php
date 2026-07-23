@extends('admin.layouts.app')
@section('content')
    <div class="admin-heading">
        <div><span>Inventory</span><h1>Properties</h1></div>
        <a class="button button-primary" href="{{ route('azari.admin.properties.create') }}">Add property</a>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Property</th><th>Location</th><th>Bedrooms</th><th>Featured</th><th>Published</th><th></th></tr></thead>
            <tbody>
            @foreach($properties as $property)
                <tr>
                    <td>{{ $property->name }}</td>
                    <td>{{ $property->location }}, {{ $property->country }}</td>
                    <td>{{ $property->bedrooms }}</td>
                    <td>{{ $property->is_featured ? 'Yes' : 'No' }}</td>
                    <td>{{ $property->is_published ? 'Yes' : 'No' }}</td>
                    <td><a href="{{ route('azari.admin.properties.edit', $property) }}">Edit</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endsection
