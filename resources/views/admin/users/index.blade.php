@extends('admin.layouts.app')
@section('title','Users')
@section('content')
<section class="az-page-heading"><div><h1>Users and access</h1></div></section>
<form class="az-filter-bar"><input name="search" value="{{ request('search') }}" placeholder="Search users"><button class="az-button" type="submit">Filter</button></form>
<div class="az-data-card"><table class="az-table"><thead><tr><th>User</th><th>Type</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($users as $user)
<tr><td><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></td><td>{{ ucfirst($user->account_type ?? 'customer') }}</td><td>{{ ucfirst($user->status ?? 'active') }}</td><td>
@if(($user->status ?? 'active')==='suspended')
<button type="button" class="az-button az-button--secondary" data-az-modal-open="user-action-modal" data-action="{{ route('azari.admin.users.reactivate',$user) }}" data-method="PUT" data-title="Reactivate user" data-message="Restore {{ $user->name }}'s access to Azari Residences?" data-confirm-label="Reactivate">Reactivate</button>
@else
<button type="button" class="az-button az-button--danger" data-az-modal-open="user-action-modal" data-action="{{ route('azari.admin.users.suspend',$user) }}" data-method="PUT" data-title="Suspend user" data-message="Suspend {{ $user->name }}? They will immediately lose access until reactivated." data-confirm-label="Suspend user" data-reason="Suspended by administrator">Suspend</button>
@endif
</td></tr>
@empty<tr><td colspan="4">No users found.</td></tr>@endforelse
</tbody></table>{{ $users->links() }}</div>

<x-azari-confirm-modal id="user-action-modal" />
@endsection
