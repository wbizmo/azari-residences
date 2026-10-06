@props([
    'path',
    'alt' => '',
    'width' => 1200,
    'height' => 800,
    'sizes' => '100vw',
    'priority' => false,
])
@php
    $original = \App\Support\ResponsiveImage::originalUrl((string) $path);
    $webp = \App\Support\ResponsiveImage::srcset((string) $path, 'webp');
    $avif = \App\Support\ResponsiveImage::srcset((string) $path, 'avif');
@endphp
<picture {{ $attributes->only('class') }}>
    @if($avif)<source type="image/avif" srcset="{{ $avif }}" sizes="{{ $sizes }}">@endif
    @if($webp)<source type="image/webp" srcset="{{ $webp }}" sizes="{{ $sizes }}">@endif
    <img
        src="{{ $original }}"
        alt="{{ $alt }}"
        width="{{ $width }}"
        height="{{ $height }}"
        sizes="{{ $sizes }}"
        @if($priority) loading="eager" fetchpriority="high" @else loading="lazy" decoding="async" @endif
        {{ $attributes->except('class') }}
    >
</picture>
