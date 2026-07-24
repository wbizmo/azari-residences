<x-layouts.base :title="$title ?? null">
    <div class="azari-page">
        <aside aria-label="Guest navigation"></aside>

        <main>
            {{ $slot }}
        </main>
    </div>
</x-layouts.base>
