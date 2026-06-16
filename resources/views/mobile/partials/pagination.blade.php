@if ($paginator->hasPages())
    <nav class="mobile-pager" aria-label="Paginacao">
        @if ($paginator->onFirstPage())
            <span class="mobile-pager__item is-disabled">Anterior</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="mobile-pager__item">Anterior</a>
        @endif

        <span class="mobile-pager__status">
            Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}
        </span>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="mobile-pager__item">Proxima</a>
        @else
            <span class="mobile-pager__item is-disabled">Proxima</span>
        @endif
    </nav>
@endif
