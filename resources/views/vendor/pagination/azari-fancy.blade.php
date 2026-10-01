@php
    $current = max(1, (int) $paginator->currentPage());

    $last = method_exists($paginator, 'lastPage')
        ? max(1, (int) $paginator->lastPage())
        : ($paginator->hasMorePages() ? $current + 1 : $current);

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

    $hasTotal = method_exists($paginator, 'total');
@endphp

<nav
    class="az-fancy-pagination"
    role="navigation"
    aria-label="Pagination"
>
    @if($hasTotal)
        <div class="az-fancy-pagination__summary">
            <span>Showing</span>
            <strong>
                {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }}
            </strong>
            <span>of {{ $paginator->total() }}</span>
        </div>
    @endif

    <div class="az-fancy-pagination__controls">

        {{-- FIRST --}}
        @if($current <= 1)
            <span
                class="az-fancy-pagination__button is-disabled"
                aria-disabled="true"
            >
                First
            </span>
        @else
            <a
                class="az-fancy-pagination__button"
                href="{{ $paginator->url(1) }}"
            >
                First
            </a>
        @endif


        {{-- PREVIOUS --}}
        @if($paginator->onFirstPage())
            <span
                class="az-fancy-pagination__button is-disabled"
                aria-disabled="true"
            >
                <span class="material-symbols-outlined">west</span>
                <span>Previous</span>
            </span>
        @else
            <a
                class="az-fancy-pagination__button"
                href="{{ $paginator->previousPageUrl() }}"
                rel="prev"
            >
                <span class="material-symbols-outlined">west</span>
                <span>Previous</span>
            </a>
        @endif


        {{-- MAXIMUM FOUR PAGE NUMBERS --}}
        <div class="az-fancy-pagination__pages">
            @for($page = $start; $page <= $end; $page++)
                @if($page === $current)
                    <span
                        class="az-fancy-pagination__page is-current"
                        aria-current="page"
                    >
                        {{ $page }}
                    </span>
                @else
                    <a
                        class="az-fancy-pagination__page"
                        href="{{ $paginator->url($page) }}"
                    >
                        {{ $page }}
                    </a>
                @endif
            @endfor
        </div>


        {{-- NEXT --}}
        @if($paginator->hasMorePages())
            <a
                class="az-fancy-pagination__button"
                href="{{ $paginator->nextPageUrl() }}"
                rel="next"
            >
                <span>Next</span>
                <span class="material-symbols-outlined">east</span>
            </a>
        @else
            <span
                class="az-fancy-pagination__button is-disabled"
                aria-disabled="true"
            >
                <span>Next</span>
                <span class="material-symbols-outlined">east</span>
            </span>
        @endif


        {{-- LAST --}}
        @if($current >= $last)
            <span
                class="az-fancy-pagination__button is-disabled"
                aria-disabled="true"
            >
                Last
            </span>
        @else
            <a
                class="az-fancy-pagination__button"
                href="{{ $paginator->url($last) }}"
            >
                Last
            </a>
        @endif

    </div>
</nav>
