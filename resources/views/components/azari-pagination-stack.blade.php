@props(['items' => []])

@php
    $paginators = collect($items)
        ->filter(fn ($value) => $value instanceof \Illuminate\Contracts\Pagination\Paginator)
        ->unique(fn ($value) => $value->getPageName());
@endphp

@if($paginators->isNotEmpty())
    <div class="az-pagination-stack" aria-label="Pagination">
        @foreach($paginators as $name => $paginator)
            <div class="az-pagination-block">
                <span class="az-pagination-label">
                    {{ str($name)->replace('_', ' ')->headline() }}
                    · Page {{ $paginator->currentPage() }}
                    @if(method_exists($paginator, 'lastPage'))
                        of {{ $paginator->lastPage() }}
                    @endif
                    · {{ $paginator->count() }} shown
                    @if(method_exists($paginator, 'total'))
                        of {{ $paginator->total() }}
                    @endif
                </span>
                {{ $paginator->onEachSide(1)->links() }}
            </div>
        @endforeach
    </div>
@endif
