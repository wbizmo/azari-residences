<x-layouts.base :title="$title">
    <main class="azari-container" style="display:grid;min-height:100vh;place-items:center;padding-block:4rem;">
        <x-ui.card style="max-width:42rem;padding:3rem;text-align:center;">
            <x-ui.icon :name="$icon" style="font-size:3rem;" />
            <p>{{ $code }}</p>
            <h1>{{ $title }}</h1>
            <p>{{ $message }}</p>
            <x-ui.button :href="url('/')">
                Return home
            </x-ui.button>
        </x-ui.card>
    </main>
</x-layouts.base>
