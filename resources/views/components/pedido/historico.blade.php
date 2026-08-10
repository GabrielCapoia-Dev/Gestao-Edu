<style>
    .protocolo-header h2 {
        font-size: 1.125rem;
        font-weight: 700;
        color: #111827;
        margin-bottom: 0.25rem;
    }

    .protocolo-header p {
        font-size: 0.875rem;
        color: #6b7280;
    }

    .historico-pedido-wrapper {
        overflow-x: auto;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
    }

    .historico-pedido-table {
        width: 100%;
        font-size: 0.875rem;
        border-collapse: collapse;
    }

    .historico-pedido-table thead {
        background-color: #f3f4f6;
    }

    .historico-pedido-table th {
        padding: 0.75rem;
        text-align: left;
        font-weight: 600;
        color: #374151;
        white-space: nowrap;
    }

    .historico-pedido-table td {
        padding: 0.75rem;
        color: #4b5563;
    }

    .historico-pedido-table tbody tr {
        border-top: 1px solid #e5e7eb;
    }

    .historico-pedido-table tbody tr:hover {
        background-color: #f9fafb;
    }

    .historico-pedido-table tbody tr.primeira-linha {
        background-color: #f9fafb;
        font-weight: 500;
    }

    .historico-pedido-table .status-novo {
        font-weight: 600;
    }

    .historico-pedido-table .empty-row td {
        padding: 1rem;
        text-align: center;
        color: #9ca3af;
    }

    .td-nowrap {
        white-space: nowrap;
    }
</style>

<div class="space-y-6">

    <div class="protocolo-header">
        <h2>Protocolo {{ $pedido->numero_protocolo }}</h2>
        <p>{{ $pedido->descricao_pedido }}</p>
    </div>

    <div class="historico-pedido-wrapper">
        <table class="historico-pedido-table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Status Anterior</th>
                    <th>Novo Status</th>
                    <th>Setor</th>
                    <th>Alterado Por</th>
                    <th>Descrição</th>
                </tr>
            </thead>
            <tbody>
                @forelse($historico as $item)
                <tr class="{{ $loop->first ? 'primeira-linha' : '' }}">
                    <td class="td-nowrap">{{ $item->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $item->statusAnterior?->nome ?? '-' }}</td>
                    <td>
                        <span class="status-novo" style="color: {{ $item->statusNovo?->cor ?? '#000' }}">
                            {{ $item->statusNovo?->nome }}
                        </span>
                    </td>
                    <td>{{ $item->setor?->nome ?? '-' }}</td>
                    <td>{{ $item->usuarioNomeExibicao() }}</td>
                    <td>{{ $item->descricao_alteracao ?? '-' }}</td>
                </tr>
                @empty
                <tr class="empty-row">
                    <td colspan="6">Nenhum histórico encontrado.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
