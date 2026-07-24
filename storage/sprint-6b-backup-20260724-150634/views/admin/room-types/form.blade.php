@extends('admin.layouts.app')
@section('content')
<div class="admin-heading"><div><span>Inventory</span><h1>{{ $roomType->exists ? 'Edit category' : 'Add category' }}</h1></div></div>
<form class="admin-form admin-form-grid" method="POST"
      action="{{ $roomType->exists ? route('azari.admin.room-types.update', $roomType) : route('azari.admin.room-types.store') }}">
    @csrf
    @if($roomType->exists) @method('PUT') @endif
    <label>Name<input name="name" value="{{ old('name', $roomType->name) }}" required></label>
    <label>Slug<input name="slug" value="{{ old('slug', $roomType->slug) }}"></label>
    <label>Description<textarea name="description">{{ old('description', $roomType->description) }}</textarea></label>
    <label>Material icon<input name="icon" value="{{ old('icon', $roomType->icon ?: 'bed') }}"></label>
    <label>Sort order<input name="sort_order" type="number" min="0" value="{{ old('sort_order', $roomType->sort_order ?? 0) }}"></label>
    <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $roomType->exists ? $roomType->is_active : true))> Active</label>
    <button class="button button-primary" type="submit">Save category</button>
</form>
@endsection
