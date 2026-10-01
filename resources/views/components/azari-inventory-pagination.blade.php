@props([
    'paginator',
    'label',
])

@php
    $current = max(1, (int) $paginator->currentPage());
    $last = max(1, (int) $paginator->lastPage());

    $windowSize = 4;

    if ($last <= $windowSize) {
        $start = 1;
        $end = $last;
    } else {
        $start = max(1, $current - 1);
        $end = $start + ($windowSize - 1);

        if ($end > $last) {
            $end = $last;
            $start = max(1, $last - ($windowSize - 1));
        }
    }
@endphp

<div
    class="az-inventory-pagination"
    style="margin-top:1rem"
>
    <nav
        class="az-fancy-pagination"
        role="navigation"
        aria-label="{{ $label }} pagination"
        style="padding:1rem 1.25rem;border-radius:1.25rem"
    >
        <div class="az-fancy-pagination__summary">
            <span>{{ $label }}</span>
            <strong>{{ $paginator->count() }}</strong>
            <span>shown of {{ $paginator->total() }}</span>
        </div>

        <div
            class="az-fancy-pagination__controls"
            style="margin-top:.75rem;gap:.75rem;flex-wrap:wrap"
        >
            @if($current <= 1)
                <span class="az-fancy-pagination__button is-disabled" aria-disabled="true">
                    First
                </span>
            @else
                <a class="az-fancy-pagination__button" href="{{ $paginator->url(1) }}">
                    First
                </a>
            @endif

            @if($paginator->onFirstPage())
                <span class="az-fancy-pagination__button is-disabled" aria-disabled="true">
                    Previous
                </span>
            @else
                <a class="az-fancy-pagination__button" href="{{ $paginator->previousPageUrl() }}" rel="prev">
                    Previous
                </a>
            @endif

            <div
                class="az-fancy-pagination__pages"
                style="gap:.5rem"
            >
                @for($page = $start; $page <= $end; $page++)
                    @if($page === $current)
                        <span class="az-fancy-pagination__page is-current" aria-current="page">
                            {{ $page }}
                        </span>
                    @else
                        <a class="az-fancy-pagination__page" href="{{ $paginator->url($page) }}">
                            {{ $page }}
                        </a>
                    @endif
                @endfor
            </div>

            @if($paginator->hasMorePages())
                <a class="az-fancy-pagination__button" href="{{ $paginator->nextPageUrl() }}" rel="next">
                    Next
                </a>
            @else
                <span class="az-fancy-pagination__button is-disabled" aria-disabled="true">
                    Next
                </span>
            @endif

            @if($current >= $last)
                <span class="az-fancy-pagination__button is-disabled" aria-disabled="true">
                    Last
                </span>
            @else
                <a class="az-fancy-pagination__button" href="{{ $paginator->url($last) }}">
                    Last
                </a>
            @endif
        </div>
    </nav>
</div>
