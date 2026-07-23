@extends('admin.layouts.app')
@section('title', 'Users')
@section('content')
<div class="az-page-heading"><div><p class="az-eyebrow">Access and accounts</p><h1>User management</h1></div></div>
<form class="az-inline-form"><input name="q" value="{{ request('q') }}" placeholder="Search users"><button class="az-button">Search</button></form>
<div class="az-table-wrap"><table><thead><tr><th>User</th><th>Username</th><th>Status</th><th>Role</th><th></th></tr></thead><tbody>
@foreach($users as $user)<tr><td><strong>{{ $user->name }}</strong><br><small>{{ $user->email }}</small></td><td>{{ $user->username }}</td><td>{{ $user->is_active ? 'Active' : 'Disabled' }}</td><td>{{ $user->is_admin ? 'Administrator' : 'Customer' }}</td><td><a href="{{ route('azari.admin.users.edit', $user) }}">Edit</a></td></tr>@endforeach
</tbody></table></div>
{{ $users->links() }}
@endsection
