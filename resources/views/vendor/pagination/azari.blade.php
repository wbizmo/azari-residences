@if ($paginator->hasPages())
    <nav class="azari-pagination" role="navigation" aria-label="Pagination">
        <div class="azari-pagination__summary">
            @if(method_exists($paginator, 'firstItem'))
                <span>{{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }}</span>
            @endif
        </div>
        <div class="azari-pagination__links">
            @if ($paginator->onFirstPage())
                <span class="pagination-link is-disabled" aria-disabled="true">Previous</span>
            @else
                <a class="pagination-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="pagination-gap">{{ $element }}</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="pagination-link is-current" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="pagination-link" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="pagination-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
            @else
                <span class="pagination-link is-disabled" aria-disabled="true">Next</span>
            @endif
        </div>
    </nav>
@endif
