@props([
    'status',
])

<span {{ $attributes->merge(['data-status' => $status]) }}>
    {{ str($status)->replace('_', ' ')->title() }}
</span>
