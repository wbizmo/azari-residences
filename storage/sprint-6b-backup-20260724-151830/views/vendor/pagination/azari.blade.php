@if ($paginator->hasPages())
<nav class="az-pagination" role="navigation" aria-label="Pagination">
@if(method_exists($paginator, 'total'))<p class="az-pagination__summary">{{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }}</p>@endif
<div class="az-pagination__items">
@if($paginator->onFirstPage())<span class="az-pagination__link is-disabled">Previous</span>@else<a class="az-pagination__link" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>@endif
@foreach($elements as $element)
@if(is_string($element))<span class="az-pagination__gap">{{ $element }}</span>@endif
@if(is_array($element))@foreach($element as $page => $url)@if($page == $paginator->currentPage())<span class="az-pagination__link is-current" aria-current="page">{{ $page }}</span>@else<a class="az-pagination__link" href="{{ $url }}">{{ $page }}</a>@endif @endforeach @endif
@endforeach
@if($paginator->hasMorePages())<a class="az-pagination__link" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>@else<span class="az-pagination__link is-disabled">Next</span>@endif
</div></nav>
@endif
