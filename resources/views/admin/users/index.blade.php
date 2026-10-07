@extends('admin.layouts.app')
@section('title','Users')
@section('content')
<section class="az-page-heading"><div><h1>Users and access</h1><p>Customer accounts are read-only. Administrators may review records and control account access.</p></div></section>
<form class="az-filter-bar"><input name="search" value="{{ request('search') }}" placeholder="Search name, email or phone"><button class="az-button" type="submit">Filter</button></form>
<div class="az-data-card"><table class="az-table"><thead><tr><th>User</th><th>Type</th><th>Status</th><th>Joined</th><th></th></tr></thead><tbody>
@forelse($users as $user)
<tr><td><strong>{{ $user->name ?: 'Not provided' }}</strong><small>{{ $user->email ?: 'Not provided' }}</small></td><td>{{ ucfirst($user->account_type ?? 'customer') }}</td><td>
@if(!$user->email_verified_at && ($user->status ?? '') === 'verification_expired')
    Verification expired
@elseif(!$user->email_verified_at)
    Pending email verification
@else
    {{ ucfirst(str_replace('_',' ', $user->status ?? 'active')) }}
@endif
</td><td>{{ $user->created_at?->format('d M Y') ?? 'Not available' }}</td><td><a class="az-button az-button--secondary" href="{{ route('azari.admin.users.show',$user) }}">View account</a></td></tr>
@empty<tr><td colspan="5">No users found.</td></tr>@endforelse
</tbody></table>{{ $users->links() }}</div>
@endsection
