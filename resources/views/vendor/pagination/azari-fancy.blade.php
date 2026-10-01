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
    style="padding:1rem 1.25rem;border-radius:1.25rem"
>
    @if($hasTotal)
        <div class="az-fancy-pagination__summary">
            <span>Showing</span>
            <strong>{{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }}</strong>
            <span>of {{ $paginator->total() }}</span>
        </div>
    @endif

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
