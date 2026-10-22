@props([
    'type' => 'button',
    'href' => null,
    'variant' => 'primary',
])

@php
    $style = in_array($variant, ['primary', 'secondary', 'danger'], true) ? $variant : 'primary';
@endphp
@if($href)
    <a {{ $attributes->class(['azari-button', 'resavar-ui-button', 'resavar-ui-button--'.$style])->merge(['href' => $href]) }}>
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->class(['azari-button', 'resavar-ui-button', 'resavar-ui-button--'.$style])->merge(['type' => $type]) }}>
        {{ $slot }}
    </button>
@endif
