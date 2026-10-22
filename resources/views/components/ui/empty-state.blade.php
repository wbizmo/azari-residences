@props([
    'title',
    'description' => null,
    'icon' => 'inbox',
])

<section {{ $attributes->class(['resavar-ui-empty']) }} aria-live="polite">
    <x-ui.icon :name="$icon" />
    <h2>{{ $title }}</h2>
    @if($description)
        <p>{{ $description }}</p>
    @endif
    {{ $slot }}
</section>
