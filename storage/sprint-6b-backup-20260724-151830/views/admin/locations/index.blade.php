@extends('admin.layouts.app')
@section('content')
<div class="admin-heading">
    <div><span>Inventory</span><h1>Locations</h1></div>
    <a class="button button-primary" href="{{ route('azari.admin.locations.create') }}">Add location</a>
</div>

<div class="admin-card">
    <table class="admin-table">
        <thead><tr><th>Location</th><th>City</th><th>Country</th><th>Properties</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse($locations as $location)
            <tr>
                <td>{{ $location->name }}</td>
                <td>{{ $location->city }}</td>
                <td>{{ $location->country }}</td>
                <td>{{ $location->properties_count }}</td>
                <td>{{ $location->is_active ? 'Active' : 'Inactive' }}</td>
                <td><a href="{{ route('azari.admin.locations.edit', $location) }}">Edit</a></td>
            </tr>
        @empty
            <tr><td colspan="6">No locations have been added.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $locations->links('vendor.pagination.azari') }}
@endsection
