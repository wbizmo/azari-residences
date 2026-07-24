@extends('admin.layouts.app')
@section('content')
    <div class="admin-heading">
        <div><span>Security</span><h1>Staff accounts</h1></div>
        <a class="button button-primary" href="{{ route('azari.admin.staff.create') }}">Add staff</a>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @foreach($staff as $member)
                <tr>
                    <td>{{ $member->name }}</td>
                    <td>{{ $member->email }}</td>
                    <td>{{ ucfirst($member->staff_role) }}</td>
                    <td>{{ $member->is_active ? 'Active' : 'Disabled' }}</td>
                    <td>
                        @if($member->id !== auth()->id())
                            <form method="POST" action="{{ route('azari.admin.staff.toggle', $member) }}">
                                @csrf @method('PATCH')
                                <button type="submit">{{ $member->is_active ? 'Disable' : 'Enable' }}</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endsection
