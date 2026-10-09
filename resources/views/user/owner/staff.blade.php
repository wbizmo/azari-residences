@extends('layouts.user')
@section('title','Property staff')
@section('kicker','Property Centre')
@section('page_title',$property->name.' staff')
@section('content')
<section class="az-user-panel">
<header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Property staff</h2><p class="az-user-panel-subtitle">Invite verified Resavar accounts with least-privilege property access.</p></div></header>
<div class="az-user-panel-body">
<form method="POST" action="{{ route('user.owner.phase2.staff.invite',$property) }}" class="az-form-grid">@csrf
<label><span>Email</span><input type="email" name="email" required autocomplete="email"></label>
<label><span>Role</span><select name="role" required><option value="manager">Manager</option><option value="front_desk">Front desk</option><option value="inventory_editor">Inventory editor</option><option value="finance_viewer">Finance viewer</option><option value="support_agent">Support agent</option></select></label>
<button class="az-user-button az-user-button--dark" type="submit">Send invitation</button>
</form>
</div></section>
<section class="az-user-panel" style="margin-top:18px"><div class="az-user-panel-body">
@if($members->isEmpty())<div class="az-user-empty"><h3>No collaborators yet</h3><p>The property owner retains full access.</p></div>
@else
<div class="az-user-list">@foreach($members as $member)<div class="az-user-list-item"><div><h3>{{ $users->get($member->user_id)?->name ?? 'Property collaborator' }}</h3><p>{{ Str::headline($member->role) }} · {{ $users->get($member->user_id)?->email }}</p></div><form method="POST" action="{{ route('user.owner.phase2.staff.revoke',[$property,$member]) }}">@csrf @method('DELETE')<button class="az-user-button" type="submit">Revoke</button></form></div>@endforeach</div>
@endif
</div></section>
@endsection
