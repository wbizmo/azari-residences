@props([
    'label',
    'name',
    'type' => 'text',
])

<label>
    <span>{{ $label }}</span>
    <input
        name="{{ $name }}"
        type="{{ $type }}"
        {{ $attributes->merge(['class' => 'azari-field']) }}
    >
    @error($name)
        <span role="alert">{{ $message }}</span>
    @enderror
</label>
