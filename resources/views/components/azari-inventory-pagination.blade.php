@props([
    'paginator',
    'label',
])

@php
    $current = $paginator->currentPage();
    $last = max(1, $paginator->lastPage());

    $start = max(1, $current - 2);
    $end = min($last, $current + 2);

    if (($end - $start) < 4) {
        if ($start === 1) {
            $end = min($last, 5);
        } elseif ($end === $last) {
            $start = max(1, $last - 4);
        }
    }
@endphp

<div class="az-inventory-pagination">
    <div class="az-pagination-label">
        {{ $label }}
        · Page {{ $current }} of {{ $last }}
        · {{ $paginator->count() }} shown of {{ $paginator->total() }}
    </div>

    <nav
        aria-label="{{ $label }} pagination"
        style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;margin-top:.75rem"
    >
        @if($paginator->onFirstPage())
            <span
                class="button button-secondary"
                aria-disabled="true"
                style="opacity:.45;cursor:not-allowed;pointer-events:none"
            >
                Previous
            </span>
        @else
            <a
                class="button button-secondary"
                href="{{ $paginator->previousPageUrl() }}"
                rel="prev"
            >
                Previous
            </a>
        @endif

        @for($page = $start; $page <= $end; $page++)
            @if($page === $current)
                <span
                    class="button button-primary"
                    aria-current="page"
                >
                    {{ $page }}
                </span>
            @else
                <a
                    class="button button-secondary"
                    href="{{ $paginator->url($page) }}"
                >
                    {{ $page }}
                </a>
            @endif
        @endfor

        @if($paginator->hasMorePages())
            <a
                class="button button-secondary"
                href="{{ $paginator->nextPageUrl() }}"
                rel="next"
            >
                Next
            </a>
        @else
            <span
                class="button button-secondary"
                aria-disabled="true"
                style="opacity:.45;cursor:not-allowed;pointer-events:none"
            >
                Next
            </span>
        @endif
    </nav>
</div>
