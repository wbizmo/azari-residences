@props([
    'name',
    'label' => null,
])

<span
    {{ $attributes->merge(['class' => 'material-symbols-outlined']) }}
    @if ($label)
        role="img"
        aria-label="{{ $label }}"
    @else
        aria-hidden="true"
    @endif
>{{ $name }}</span>
