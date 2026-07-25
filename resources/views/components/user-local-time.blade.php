@props([
    'value' => null,
    'mode' => 'datetime',
    'empty' => '—',
])

@php
    $localTimeValue = $value
        ? ($value instanceof \DateTimeInterface
            ? \Carbon\CarbonImmutable::instance($value)
            : \Carbon\CarbonImmutable::parse($value))
        : null;

    $localTimeFallback = match ($mode) {
        'date' => $localTimeValue?->format('j M Y'),
        'long-date' => $localTimeValue?->format('j F Y'),
        'time' => $localTimeValue?->format('g:i A'),
        default => $localTimeValue?->format('j M Y, g:i A'),
    };
@endphp

@if($localTimeValue)
    <time
        datetime="{{ $localTimeValue->toIso8601String() }}"
        data-user-local-time="{{ $localTimeValue->utc()->toIso8601String() }}"
        data-user-time-format="{{ $mode }}"
        data-fallback-timezone="{{ config('azari.timezone', 'Africa/Lagos') }}"
    >{{ $localTimeFallback }}</time>
@else
    {{ $empty }}
@endif
