<style>
    .historico-table {
        width: 100%;
        font-size: 0.875rem;
        border-collapse: collapse;
        border-radius: 0.5rem;
        overflow: hidden;
    }

    .historico-table thead {
        background-color: #f3f4f6;
    }

    .historico-table th {
        padding: 0.625rem 0.75rem;
        text-align: left;
        font-weight: 600;
        color: #374151;
        white-space: nowrap;
    }

    .historico-table td {
        padding: 0.625rem 0.75rem;
        color: #4b5563;
        white-space: nowrap;
    }

    .historico-table tbody tr {
        border-top: 1px solid #e5e7eb;
    }

    .historico-table tbody tr:hover {
        background-color: #f9fafb;
    }

    .badge-ativo {
        color: #16a34a;
        font-weight: 600;
    }

    .badge-antigo {
        color: #9ca3af;
    }

    .table-wrapper {
        overflow-x: auto;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
    }
</style>

<div class="space-y-4">
    <div class="table-wrapper">
        <table class="historico-table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Telefone</th>
                    <th>Logradouro</th>
                    <th>CEP</th>
                    <th>N°</th>
                    <th>Bairro</th>
                    <th>Complemento</th>
                    <th>Cidade</th>
                    <th>UF</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($historico as $item)
                <tr>
                    <td>{{ $item->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $item->nome }}</td>
                    <td>{{ $item->email }}</td>
                    <td>{{ $item->telefone }}</td>
                    <td>{{ $item->logradouro }}</td>
                    <td>{{ $item->cep }}</td>
                    <td>{{ $item->numero }}</td>
                    <td>{{ $item->bairro }}</td>
                    <td>{{ $item->complemento }}</td>
                    <td>{{ $item->cidade }}</td>
                    <td>{{ $item->estado }}</td>
                    <td>
                        @if($item->ativo)
                            <span class="badge-ativo">Ativo</span>
                        @else
                            <span class="badge-antigo">Versão Antiga</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>