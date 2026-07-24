<x-layouts.base :title="$title ?? null">
    <div class="azari-page">
        <aside aria-label="Administrator navigation"></aside>

        <main>
            {{ $slot }}
        </main>
    </div>
</x-layouts.base>
