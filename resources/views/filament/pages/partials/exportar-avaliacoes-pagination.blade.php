@if ($paginator->hasPages())
    @php
        $elements = Illuminate\Pagination\UrlWindow::make($paginator);
        $previousPage = max(1, $paginator->currentPage() - 1);
        $nextPage = min($paginator->lastPage(), $paginator->currentPage() + 1);
    @endphp

    <nav class="av-pager" aria-label="Paginação da listagem">
        <p class="av-pager-summary">
            Mostrando {{ $paginator->firstItem() }} até {{ $paginator->lastItem() }} de {{ $paginator->total() }} resultados
        </p>

        <div class="av-pager-list">
            @if ($paginator->onFirstPage())
                <span class="av-pager-button is-disabled" aria-disabled="true">Anterior</span>
            @else
                <button type="button" class="av-pager-button" wire:click="setPage({{ $previousPage }}, 'exportarAvaliacoesPage')">
                    Anterior
                </button>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="av-pager-ellipsis">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page === $paginator->currentPage())
                            <span class="av-pager-button is-active" aria-current="page">{{ $page }}</span>
                        @else
                            <button type="button" class="av-pager-button av-pager-number" wire:click="setPage({{ $page }}, 'exportarAvaliacoesPage')">
                                {{ $page }}
                            </button>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <button type="button" class="av-pager-button" wire:click="setPage({{ $nextPage }}, 'exportarAvaliacoesPage')">
                    Próxima
                </button>
            @else
                <span class="av-pager-button is-disabled" aria-disabled="true">Próxima</span>
            @endif
        </div>
    </nav>
@else
    <div class="av-pager av-pager--single">
        <p class="av-pager-summary">
            Mostrando {{ $paginator->total() }} resultado{{ $paginator->total() === 1 ? '' : 's' }}
        </p>
    </div>
@endif
