@props([
    'label',
    'name',
    'type' => 'text',
    'id' => null,
])

@php
    $controlId = $id ?: 'resavar-field-'.\Illuminate\Support\Str::slug($name);
    $invalid = $errors->has($name);
@endphp
<label class="resavar-ui-field" for="{{ $controlId }}">
    <span class="resavar-ui-field__label">{{ $label }}</span>
    <input
        id="{{ $controlId }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if($invalid) aria-invalid="true" aria-describedby="{{ $controlId }}-error" @endif
        {{ $attributes->class(['azari-field', 'resavar-ui-control']) }}
    >
    @error($name)
        <span class="resavar-ui-field__error" id="{{ $controlId }}-error" role="alert">{{ $message }}</span>
    @enderror
</label>
