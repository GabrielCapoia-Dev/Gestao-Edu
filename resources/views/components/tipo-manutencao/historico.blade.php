<style>
    .hist-status-wrapper {
        overflow-x: auto;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
    }

    .hist-status-table {
        width: 100%;
        font-size: 0.875rem;
        border-collapse: collapse;
    }

    .hist-status-table thead {
        background-color: #f3f4f6;
    }

    .hist-status-table th {
        padding: 0.625rem 0.75rem;
        text-align: left;
        font-weight: 600;
        color: #374151;
        white-space: nowrap;
    }

    .hist-status-table td {
        padding: 0.625rem 0.75rem;
        color: #4b5563;
    }

    .hist-status-table tbody tr {
        border-top: 1px solid #e5e7eb;
    }

    .hist-status-table tbody tr:hover {
        background-color: #f9fafb;
    }

    .hist-status-ativo {
        color: #16a34a;
        font-weight: 600;
    }

    .hist-status-antigo {
        color: #9ca3af;
    }
</style>

<div class="hist-status-wrapper">
    <table class="hist-status-table">
        <thead>
            <tr>
                <th>Data</th>
                <th>Nome</th>
                <th>Descrição</th>
                <th>Alterado Por</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($historico as $item)
            <tr>
                <td style="white-space: nowrap;">{{ $item->created_at->format('d/m/Y H:i') }}</td>
                <td>{{ $item->nome }}</td>
                <td>{{ $item->descricao }}</td>
                <td>{{ $item->alterado_por }}</td>
                <td>
                    @if($item->ativo)
                        <span class="hist-status-ativo">Ativo</span>
                    @else
                        <span class="hist-status-antigo">Versão Antiga</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>