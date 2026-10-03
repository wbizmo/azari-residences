@php
    $defaultSeoTitle = 'RESAVAR | Exceptional Stays, Everywhere.';
    $defaultSeoDescription = 'Resavar is a global accommodation and travel marketplace for exceptional stays, wherever you are going.';
    $defaultSeoKeywords = implode(', ', [
        'Resavar',
        'RESAVAR',
        'Resavar accommodation',
        'Resavar travel marketplace',
        'Resavar stays',
        'Resavar booking',
        'exceptional stays',
        'accommodation marketplace',
        'travel marketplace',
        'serviced accommodation',
        'apartments',
        'rooms',
        'holiday accommodation',
        'business travel accommodation',
        'secure accommodation booking',
    ]);
    $resolvedTitle = $title ?? trim($__env->yieldContent('title')) ?: $defaultSeoTitle;
    $resolvedDescription = $description ?? trim($__env->yieldContent('description')) ?: $defaultSeoDescription;
    $resolvedKeywords = $keywords ?? trim($__env->yieldContent('keywords')) ?: $defaultSeoKeywords;
@endphp

<x-public.layout
    :title="$resolvedTitle"
    :description="$resolvedDescription"
    :keywords="$resolvedKeywords"
    :canonical="$canonical ?? null"
    :image="$image ?? null"
    :type="$type ?? 'website'"
    :body-class="$bodyClass ?? ''"
>
    @yield('content')
</x-public.layout>

