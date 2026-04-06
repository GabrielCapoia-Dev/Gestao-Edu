@extends('relatorios.layouts.base-pdf')

@section('reportTitle', $reportTitle ?? 'Relatorio Geral de Estoque')
@section('reportSubtitle', $reportSubtitle ?? 'Visao consolidada dos itens e do historico de movimentacoes')

@section('styles')
    .cards {
        width: 100%;
        margin-bottom: 16px;
        border-collapse: separate;
        border-spacing: 6px;
    }

    .cards td {
        width: 25%;
        border: 1px solid #d1d5db;
        background: #f8fafc;
        padding: 10px;
        vertical-align: top;
    }

    .cards .label {
        font-size: 9px;
        text-transform: uppercase;
        color: #64748b;
    }

    .cards .value {
        font-size: 18px;
        font-weight: bold;
        margin-top: 4px;
    }

    .section-title {
        font-size: 13px;
        font-weight: bold;
        margin: 16px 0 8px;
        color: #0f172a;
    }

    .grid {
        display: table;
        width: 100%;
        table-layout: fixed;
    }

    .col {
        display: table-cell;
        vertical-align: top;
        width: 50%;
        padding-right: 6px;
    }

    .col:last-child {
        padding-right: 0;
        padding-left: 6px;
    }

    table.listagem {
        width: 100%;
        border-collapse: collapse;
        font-size: 9px;
        page-break-inside: auto;
    }

    table.listagem thead {
        display: table-header-group;
    }

    table.listagem th,
    table.listagem td {
        border: 1px solid #d1d5db;
        padding: 6px;
        text-align: left;
        vertical-align: top;
    }

    table.listagem th {
        background: #f1f5f9;
        font-size: 10px;
    }

    table.listagem tr {
        page-break-inside: avoid;
    }

    .right {
        text-align: right;
    }

    .muted {
        color: #64748b;
    }
@endsection

@section('content')
    <table class="cards">
        <tr>
            <td>
                <div class="label">Itens Filtrados</div>
                <div class="value">{{ $metricas->total_itens }}</div>
            </td>
            <td>
                <div class="label">Qtd. Total</div>
                <div class="value">{{ number_format($metricas->quantidade_total, 3, ',', '.') }}</div>
            </td>
            <td>
                <div class="label">Estoque Baixo</div>
                <div class="value">{{ $metricas->itens_criticos }}</div>
            </td>
            <td>
                <div class="label">Itens Zerados</div>
                <div class="value">{{ $metricas->itens_zerados }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Movimentacoes</div>
                <div class="value">{{ $metricas->total_movimentacoes }}</div>
            </td>
            <td>
                <div class="label">Entradas</div>
                <div class="value">{{ number_format($metricas->total_entradas, 3, ',', '.') }}</div>
            </td>
            <td>
                <div class="label">Saidas</div>
                <div class="value">{{ number_format($metricas->total_saidas, 3, ',', '.') }}</div>
            </td>
            <td>
                <div class="label">Qtd. Baixada</div>
                <div class="value">{{ number_format($metricas->quantidade_baixada, 3, ',', '.') }}</div>
            </td>
        </tr>
    </table>

    <div class="grid">
        <div class="col">
            <div class="section-title">Distribuicao por Categoria</div>
            <table class="listagem">
                <thead>
                    <tr>
                        <th>Categoria</th>
                        <th class="right">Itens</th>
                        <th class="right">Qtd. Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($porCategoria as $categoria)
                        <tr>
                            <td>{{ $categoria['label'] }}</td>
                            <td class="right">{{ $categoria['total_itens'] }}</td>
                            <td class="right">{{ number_format($categoria['quantidade_total'], 3, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">Nenhuma categoria encontrada.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="col">
            <div class="section-title">Resumo Operacional</div>
            <table class="listagem">
                <tbody>
                    <tr>
                        <th>Saldo movimentado</th>
                        <td class="right">{{ number_format($metricas->saldo_movimentado, 3, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <th>Baixas registradas</th>
                        <td class="right">{{ $metricas->total_baixas }}</td>
                    </tr>
                    <tr>
                        <th>Movimentacoes por item</th>
                        <td class="right">
                            {{ $metricas->total_itens > 0 ? number_format($metricas->total_movimentacoes / $metricas->total_itens, 2, ',', '.') : '0,00' }}
                        </td>
                    </tr>
                    <tr>
                        <th>Quantidade media por item</th>
                        <td class="right">
                            {{ $metricas->total_itens > 0 ? number_format($metricas->quantidade_total / $metricas->total_itens, 3, ',', '.') : '0,000' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="section-title">Itens do Estoque</div>
    <table class="listagem">
        <thead>
            <tr>
                <th>Item</th>
                <th>Descricao</th>
                <th>Categoria</th>
                <th>Unidade</th>
                <th class="right">Quantidade</th>
                <th>Status</th>
                <th>Atualizado em</th>
            </tr>
        </thead>
        <tbody>
            @forelse($itens as $item)
                <tr>
                    <td>{{ $item['nome'] }}</td>
                    <td>{{ $item['descricao'] ?: '-' }}</td>
                    <td>{{ $item['tipo_label'] }}</td>
                    <td>{{ $item['unidade'] }}</td>
                    <td class="right">{{ number_format($item['quantidade'], 3, ',', '.') }}</td>
                    <td>{{ ucfirst($item['status']) }}</td>
                    <td>{{ $item['atualizado'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">Nenhum item encontrado para os filtros selecionados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Historico de Movimentacoes</div>
    <table class="listagem">
        <thead>
            <tr>
                <th>Data</th>
                <th>Item</th>
                <th>Categoria</th>
                <th>Tipo</th>
                <th class="right">Quantidade</th>
                <th>Pedido</th>
                <th>Registrado por</th>
                <th>Observacao</th>
            </tr>
        </thead>
        <tbody>
            @forelse($movimentacoes as $mov)
                <tr>
                    <td>{{ $mov['data'] }}</td>
                    <td>{{ $mov['item_nome'] }}</td>
                    <td>{{ $mov['categoria'] }}</td>
                    <td>{{ $mov['tipo_label'] }}</td>
                    <td class="right">{{ number_format($mov['quantidade'], 3, ',', '.') }}</td>
                    <td>{{ $mov['pedido_id'] ?: '-' }}</td>
                    <td>{{ $mov['registrado_por'] }}</td>
                    <td>{{ $mov['observacao'] ?: '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">Nenhuma movimentacao encontrada.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Baixas de Estoque Relacionadas</div>
    <table class="listagem">
        <thead>
            <tr>
                <th>Data</th>
                <th>Item</th>
                <th>Motivo</th>
                <th>Descricao</th>
                <th class="right">Qtd.</th>
                <th class="right">Saldo Antes</th>
                <th class="right">Saldo Depois</th>
                <th>Registrado por</th>
            </tr>
        </thead>
        <tbody>
            @forelse($baixas as $baixa)
                <tr>
                    <td>{{ $baixa['data'] }}</td>
                    <td>{{ $baixa['item_nome'] }}</td>
                    <td>{{ $baixa['motivo'] }}</td>
                    <td>{{ $baixa['descricao'] ?: '-' }}</td>
                    <td class="right">{{ number_format($baixa['quantidade'], 3, ',', '.') }}</td>
                    <td class="right">{{ number_format($baixa['saldo_anterior'], 3, ',', '.') }}</td>
                    <td class="right">{{ number_format($baixa['saldo_posterior'], 3, ',', '.') }}</td>
                    <td>{{ $baixa['registrado_por'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="muted">Nenhuma baixa relacionada aos filtros selecionados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
