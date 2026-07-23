@extends('admin.layouts.app')
@section('content')
    <div class="admin-heading"><div><span>CMS</span><h1>Frontend content</h1></div></div>
    <div class="admin-stack">
        @foreach($blocks as $block)
            <form class="admin-form" method="POST" action="{{ route('azari.admin.content.update', $block) }}">
                @csrf
                @method('PUT')
                <h2>{{ $block->label }}</h2>
                @if($block->type === 'textarea')
                    <textarea name="value" rows="5">{{ old('value', $block->value) }}</textarea>
                @else
                    <input name="value" value="{{ old('value', $block->value) }}">
                @endif
                <label class="admin-checkbox"><input type="checkbox" name="is_active" value="1" @checked($block->is_active)> Active</label>
                <button class="button button-primary" type="submit">Update</button>
            </form>
        @endforeach
    </div>
@endsection
