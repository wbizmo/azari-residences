@props(['status'])

<span {{ $attributes->class(['resavar-ui-badge'])->merge(['data-status' => $status]) }}>
    {{ str($status)->replace('_', ' ')->title() }}
</span>
