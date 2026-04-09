@php
    $compacta = $compacta ?? false;
    $exibirStatus = $exibirStatus ?? false;
    $colspan = 4 + ($exibirStatus ? 1 : 0) + ($compacta ? 0 : 2);
@endphp

<div class="pm-table-wrap">
    <table class="pm-table">
        <thead>
            <tr>
                <th>Pedido</th>
                <th>Itens</th>
                <th>Quantidade</th>
                @if ($exibirStatus)
                    <th>Status</th>
                @endif
                @unless ($compacta)
                    <th>Empresas</th>
                    <th>Criado em</th>
                @endunless
                <th class="text-right">Acoes</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($pedidos as $pedido)
                @php
                    $empresas = $pedido->itens
                        ->map(fn ($item) => $item->contratoItem?->contrato?->empresaContratada?->nome)
                        ->filter()
                        ->unique()
                        ->values();
                @endphp
                <tr>
                    <td>
                        <strong>#{{ $pedido->id }}</strong>
                        <small>{{ $pedido->criado_por ?: 'Nao informado' }}</small>
                    </td>
                    <td>
                        <strong>{{ $pedido->itens_count ?? $pedido->itens->count() }} item(ns)</strong>
                        <small>{{ $pedido->itens->pluck('contratoItem.item.nome')->filter()->take(1)->implode(', ') ?: 'Sem itens' }}</small>
                    </td>
                    <td>
                        <strong>{{ number_format((float) ($pedido->quantidade_total_pedida ?? $pedido->itens->sum('quantidade_pedida')), 3, ',', '.') }}</strong>
                        <small>Entregue {{ number_format((float) ($pedido->quantidade_total_entregue ?? $pedido->itens->sum('quantidade_entregue')), 3, ',', '.') }}</small>
                    </td>
                    @if ($exibirStatus)
                        <td>
                            <span class="{{ $page->badgeStatusClasse($pedido->status) }}">{{ $page->formatarStatus($pedido->status) }}</span>
                        </td>
                    @endif
                    @unless ($compacta)
                        <td>
                            <strong>{{ $empresas->count() }} empresa(s)</strong>
                            <small>{{ $empresas->take(1)->implode(', ') ?: 'Sem empresa' }}</small>
                        </td>
                        <td>
                            <strong>{{ $pedido->created_at?->format('d/m/Y') }}</strong>
                            <small>{{ $pedido->created_at?->format('H:i') }}</small>
                        </td>
                    @endunless
                    <td class="text-right">
                        <div class="pm-row-actions">
                            <button type="button" wire:click="abrirModalItens({{ $pedido->id }})">Itens</button>

                            @if ($mostrarEmpenho && $pedido->status === \App\Models\Enums\StatusPedidoMerenda::Aguardando)
                                <a href="{{ route('pedidos-merenda.exportar-empenho', $pedido) }}">Exportar empenho</a>
                            @endif

                            @if (in_array($pedido->status, [\App\Models\Enums\StatusPedidoMerenda::Aguardando, \App\Models\Enums\StatusPedidoMerenda::ParcialmenteEntregue], true))
                                <button
                                    type="button"
                                    class="danger"
                                    wire:click="cancelarPedido({{ $pedido->id }})"
                                    wire:confirm="Confirma o cancelamento deste pedido? O saldo pendente sera devolvido aos contratos."
                                >
                                    Cancelar
                                </button>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $colspan }}" class="pm-empty">Nenhum pedido encontrado nesta listagem.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="pm-pagination">
    <span>Mostrando {{ $paginacao['de'] }}-{{ $paginacao['ate'] }} de {{ $paginacao['total'] }}</span>

    <div class="pm-pagination-controls">
        <button
            type="button"
            wire:click="mudarPagina('{{ $secao }}', {{ max(1, $paginacao['paginaAtual'] - 1) }})"
            @disabled($paginacao['paginaAtual'] === 1)
        >
            Anterior
        </button>
        <button
            type="button"
            wire:click="mudarPagina('{{ $secao }}', {{ min($paginacao['totalPaginas'], $paginacao['paginaAtual'] + 1) }})"
            @disabled($paginacao['paginaAtual'] === $paginacao['totalPaginas'])
        >
            Proxima
        </button>
    </div>
</div>
