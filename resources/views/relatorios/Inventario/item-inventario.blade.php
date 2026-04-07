@extends('relatorios.layouts.base-pdf')

@section('reportTitle', $reportTitle ?? 'Relatorio Individual de Inventario')
@section('reportSubtitle', $reportSubtitle ?? 'Historico completo do item no inventario escolar')

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
        font-size: 16px;
        font-weight: bold;
        margin-top: 4px;
    }

    table.listagem {
        width: 100%;
        border-collapse: collapse;
        font-size: 9px;
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

    .right {
        text-align: right;
    }
@endsection

@section('content')
    <table class="cards">
        <tr>
            <td>
                <div class="label">Escola</div>
                <div class="value" style="font-size: 13px;">{{ $inventario?->escola?->nome ?? 'N/A' }}</div>
            </td>
            <td>
                <div class="label">Saldo Atual</div>
                <div class="value">{{ number_format($resumo->saldo_atual, 3, ',', '.') }}</div>
            </td>
            <td>
                <div class="label">Movimentacoes</div>
                <div class="value">{{ $resumo->total_movimentacoes }}</div>
            </td>
            <td>
                <div class="label">Baixas</div>
                <div class="value">{{ $resumo->total_baixas }}</div>
            </td>
        </tr>
    </table>

    <div class="section-title">Dados do Item</div>
    <table class="listagem">
        <tbody>
            <tr>
                <th>Item</th>
                <td>{{ $estoque->item?->nome ?? 'N/A' }}</td>
                <th>Categoria</th>
                <td>{{ $estoque->item?->tipo_item?->label() ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Descricao</th>
                <td>{{ $estoque->item?->descricao ?: '-' }}</td>
                <th>Unidade</th>
                <td>{{ strtoupper($estoque->item?->unidade_medida?->value ?? 'N/A') }}</td>
            </tr>
            <tr>
                <th>Primeira movimentacao</th>
                <td>{{ $resumo->primeira_movimentacao }}</td>
                <th>Ultima movimentacao</th>
                <td>{{ $resumo->ultima_movimentacao }}</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">Historico de Movimentacoes</div>
    <table class="listagem">
        <thead>
            <tr>
                <th>Data</th>
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
                    <td>{{ $mov['tipo_label'] }}</td>
                    <td class="right">{{ number_format($mov['quantidade'], 3, ',', '.') }}</td>
                    <td>{{ $mov['pedido_id'] ?: '-' }}</td>
                    <td>{{ $mov['registrado_por'] }}</td>
                    <td>{{ $mov['observacao'] ?: '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">Nenhuma movimentacao registrada para este item.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Historico de Baixas</div>
    <table class="listagem">
        <thead>
            <tr>
                <th>Data</th>
                <th>Motivo</th>
                <th>Descricao</th>
                <th class="right">Quantidade</th>
                <th class="right">Saldo Antes</th>
                <th class="right">Saldo Depois</th>
                <th>Registrado por</th>
            </tr>
        </thead>
        <tbody>
            @forelse($baixas as $baixa)
                <tr>
                    <td>{{ $baixa['data'] }}</td>
                    <td>{{ $baixa['motivo'] }}</td>
                    <td>{{ $baixa['descricao'] ?: '-' }}</td>
                    <td class="right">{{ number_format($baixa['quantidade'], 3, ',', '.') }}</td>
                    <td class="right">{{ number_format($baixa['saldo_anterior'], 3, ',', '.') }}</td>
                    <td class="right">{{ number_format($baixa['saldo_posterior'], 3, ',', '.') }}</td>
                    <td>{{ $baixa['registrado_por'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">Nenhuma baixa registrada para este item.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
