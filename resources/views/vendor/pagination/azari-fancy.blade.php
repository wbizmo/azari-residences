@if ($paginator->hasPages())
<nav class="az-fancy-pagination" role="navigation" aria-label="Pagination">
    <div class="az-fancy-pagination__summary">
        <span>Showing</span>
        <strong>{{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }}</strong>
        <span>of {{ $paginator->total() }}</span>
    </div>
    <div class="az-fancy-pagination__controls">
        @if ($paginator->onFirstPage())
            <span class="az-fancy-pagination__button is-disabled" aria-disabled="true"><span class="material-symbols-outlined">west</span><span>Previous</span></span>
        @else
            <a class="az-fancy-pagination__button" href="{{ $paginator->previousPageUrl() }}" rel="prev"><span class="material-symbols-outlined">west</span><span>Previous</span></a>
        @endif

        <div class="az-fancy-pagination__pages">
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="az-fancy-pagination__ellipsis">{{ $element }}</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="az-fancy-pagination__page is-current" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="az-fancy-pagination__page" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
        </div>

        @if ($paginator->hasMorePages())
            <a class="az-fancy-pagination__button" href="{{ $paginator->nextPageUrl() }}" rel="next"><span>Next</span><span class="material-symbols-outlined">east</span></a>
        @else
            <span class="az-fancy-pagination__button is-disabled" aria-disabled="true"><span>Next</span><span class="material-symbols-outlined">east</span></span>
        @endif
    </div>
</nav>
@endif
