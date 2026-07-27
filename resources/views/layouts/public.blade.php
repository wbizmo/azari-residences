<x-public.layout
    :title="$title ?? trim($__env->yieldContent('title', 'Azari Residences | Premium Serviced Residences'))"
    :description="$description ?? trim($__env->yieldContent('description')) ?: null"
    :keywords="$keywords ?? null"
    :canonical="$canonical ?? null"
    :image="$image ?? null"
    :type="$type ?? 'website'"
    :body-class="$bodyClass ?? ''"
>
    @yield('content')
</x-public.layout>
