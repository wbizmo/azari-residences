@extends('admin.layouts.app')
@section('title', $user->exists ? 'Edit customer' : 'Add customer')
@section('content')
<div class="az-page-heading"><div><p class="az-eyebrow">Customer account</p><h1>{{ $user->exists ? 'Edit customer' : 'Add customer' }}</h1><p>Keep account details simple and focused on bookings.</p></div></div>
<form class="az-card az-form-grid" method="POST" action="{{ $user->exists ? route('azari.admin.users.update', $user) : route('azari.admin.users.store') }}">
@csrf
@if($user->exists) @method('PUT') @endif
<label class="az-field"><span>Full name</span><input name="name" value="{{ old('name', $user->name) }}" required></label>
<label class="az-field"><span>Email address</span><input type="email" name="email" value="{{ old('email', $user->email) }}" required></label>
<label class="az-field"><span>Phone</span><input name="phone" value="{{ old('phone', $user->phone) }}"></label>
<label class="az-field"><span>{{ $user->exists ? 'New password (optional)' : 'Password' }}</span><input type="password" name="password" {{ $user->exists ? '' : 'required' }}></label>
<label class="az-field"><span>Confirm password</span><input type="password" name="password_confirmation" {{ $user->exists ? '' : 'required' }}></label>
<div class="az-form-actions az-span-2"><a class="az-button az-button--secondary" href="{{ route('azari.admin.users.index') }}">Cancel</a><button class="az-button" type="submit">Save customer</button></div>
</form>
@endsection
