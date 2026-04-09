<div class="pm-table-wrap">
    <table class="pm-table">
        <thead>
            <tr>
                <th>Pedido</th>
                <th>Item</th>
                <th>Empresa / Contrato</th>
                <th>Pedida</th>
                <th>Entregue</th>
                <th>Pendente</th>
                <th class="text-right">Acoes</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($itens as $entry)
                <tr>
                    <td>
                        <strong>#{{ $entry['pedido']->id }}</strong>
                        <small>{{ $entry['pedido']->created_at?->format('d/m/Y') }}</small>
                    </td>
                    <td>
                        <strong>{{ $entry['item_nome'] }}</strong>
                        <small>{{ $entry['unidade'] }}</small>
                    </td>
                    <td>
                        <strong>{{ $entry['empresa'] }}</strong>
                        <small>Contrato {{ $entry['contrato'] }}</small>
                    </td>
                    <td>
                        <strong>{{ number_format($entry['quantidade_pedida'], 3, ',', '.') }}</strong>
                    </td>
                    <td>
                        <strong>{{ number_format($entry['quantidade_entregue'], 3, ',', '.') }}</strong>
                    </td>
                    <td>
                        <strong>{{ number_format($entry['quantidade_pendente'], 3, ',', '.') }}</strong>
                    </td>
                    <td class="text-right">
                        <div class="pm-row-actions">
                            <button type="button" wire:click="abrirModalItens({{ $entry['pedido']->id }})">Ver pedido</button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="pm-empty">Nenhum item parcialmente entregue nesta listagem.</td>
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
