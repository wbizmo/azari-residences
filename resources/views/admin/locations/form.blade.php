@extends('admin.layouts.app')
@section('content')
<div class="admin-heading"><div><span>Inventory</span><h1>{{ $location->exists ? 'Edit location' : 'Add location' }}</h1></div></div>
<form class="admin-form admin-form-grid" method="POST"
      action="{{ $location->exists ? route('azari.admin.locations.update', $location) : route('azari.admin.locations.store') }}">
    @csrf
    @if($location->exists) @method('PUT') @endif
    <label>Name<input name="name" value="{{ old('name', $location->name) }}" required></label>
    <label>Slug<input name="slug" value="{{ old('slug', $location->slug) }}"></label>
    <label>City<input name="city" value="{{ old('city', $location->city) }}" required></label>
    <label>Country<input name="country" value="{{ old('country', $location->country ?: 'Nigeria') }}" required></label>
    <label>Address<textarea name="address">{{ old('address', $location->address) }}</textarea></label>
    <label>Timezone<input name="timezone" value="{{ old('timezone', $location->timezone ?: 'Africa/Lagos') }}" required></label>
    <label>Sort order<input name="sort_order" type="number" min="0" value="{{ old('sort_order', $location->sort_order ?? 0) }}"></label>
    <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $location->exists ? $location->is_active : true))> Active</label>
    <button class="button button-primary" type="submit">Save location</button>
</form>
@endsection
