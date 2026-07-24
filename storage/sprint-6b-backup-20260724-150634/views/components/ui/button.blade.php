@props([
    'type' => 'button',
    'href' => null,
])

@if ($href)
    <a {{ $attributes->merge(['class' => 'azari-button', 'href' => $href]) }}>
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['class' => 'azari-button', 'type' => $type]) }}>
        {{ $slot }}
    </button>
@endif
