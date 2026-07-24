@extends('admin.layouts.app')
@section('title', 'Edit User')
@section('content')
<div class="az-page-heading"><div><p class="az-eyebrow">Account control</p><h1>Edit {{ $user->name }}</h1></div></div>
<form method="POST" action="{{ route('azari.admin.users.update', $user) }}" class="az-card az-form-grid">@csrf @method('PUT')
<label>Name<input name="name" value="{{ old('name', $user->name) }}" required></label>
<label>Username<input name="username" value="{{ old('username', $user->username) }}" required></label>
<label>Email<input type="email" name="email" value="{{ old('email', $user->email) }}" required></label>
<label>Phone<input name="phone" value="{{ old('phone', $user->phone) }}"></label>
<label>New password<input type="password" name="password"></label>
<label>Confirm password<input type="password" name="password_confirmation"></label>
<label class="az-toggle"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))><span></span>Active account</label>
<label class="az-toggle"><input type="checkbox" name="is_admin" value="1" @checked(old('is_admin', $user->is_admin))><span></span>Administrator</label>
<div class="az-form-actions"><button class="az-button">Save user</button></div>
</form>
@endsection
