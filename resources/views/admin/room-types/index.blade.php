@extends('admin.layouts.app')
@section('content')
<div class="admin-heading">
    <div><span>Inventory</span><h1>Residence categories</h1></div>
    <a class="button button-primary" href="{{ route('azari.admin.room-types.create') }}">Add category</a>
</div>

<div class="admin-card">
    <table class="admin-table">
        <thead><tr><th>Category</th><th>Properties</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse($roomTypes as $roomType)
            <tr>
                <td>{{ $roomType->name }}</td>
                <td>{{ $roomType->properties_count }}</td>
                <td>{{ $roomType->is_active ? 'Active' : 'Inactive' }}</td>
                <td><a href="{{ route('azari.admin.room-types.edit', $roomType) }}">Edit</a></td>
            </tr>
        @empty
            <tr><td colspan="4">No residence categories have been added.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $roomTypes->links('vendor.pagination.azari') }}
@endsection
