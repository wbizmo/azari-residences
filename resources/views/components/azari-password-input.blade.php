@props([
    'id',
    'name',
    'autocomplete' => 'current-password',
    'required' => true,
    'autofocus' => false,
    'value' => null,
    'label' => 'password',
])

<div class="az-password-control" data-azari-password-control>
    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="password"
        @if(!is_null($value)) value="{{ $value }}" @endif
        autocomplete="{{ $autocomplete }}"
        @required($required)
        @if($autofocus) autofocus @endif
        {{ $attributes->except(['class'])->class(['az-password-control__input']) }}
    >

    <button
        type="button"
        class="az-password-control__toggle"
        data-azari-password-toggle
        aria-controls="{{ $id }}"
        aria-label="Show {{ $label }}"
        aria-pressed="false"
    >
        <svg
            class="az-password-control__icon"
            data-azari-eye-open
            viewBox="0 0 24 24"
            aria-hidden="true"
            focusable="false"
        >
            <path d="M2.5 12s3.6-6 9.5-6 9.5 6 9.5 6-3.6 6-9.5 6-9.5-6-9.5-6Z"/>
            <circle cx="12" cy="12" r="2.75"/>
        </svg>

        <svg
            class="az-password-control__icon"
            data-azari-eye-closed
            viewBox="0 0 24 24"
            aria-hidden="true"
            focusable="false"
        >
            <path d="M3 3l18 18"/>
            <path d="M10.6 6.1A10.5 10.5 0 0 1 12 6c5.9 0 9.5 6 9.5 6a17 17 0 0 1-2.35 3.15"/>
            <path d="M6.15 6.2C3.8 8.05 2.5 12 2.5 12s3.6 6 9.5 6a10 10 0 0 0 3.1-.5"/>
            <path d="M9.85 9.85a3 3 0 0 0 4.3 4.3"/>
        </svg>
    </button>
</div>
