@extends('relatorios.layouts.base-pdf')

@section('reportTitle', $reportTitle ?? 'Relatorio Individual de Estoque')
@section('reportSubtitle', $reportSubtitle ?? 'Historico completo de movimentacoes do item')

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
        font-size: 17px;
        font-weight: bold;
        margin-top: 4px;
    }

    .section-title {
        font-size: 13px;
        font-weight: bold;
        margin: 16px 0 8px;
        color: #0f172a;
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
@endsection

@section('content')
    <table class="cards">
        <tr>
            <td>
                <div class="label">Saldo Atual</div>
                <div class="value">{{ number_format($resumo->saldo_atual, 3, ',', '.') }}</div>
            </td>
            <td>
                <div class="label">Movimentacoes</div>
                <div class="value">{{ $resumo->total_movimentacoes }}</div>
            </td>
            <td>
                <div class="label">Entradas</div>
                <div class="value">{{ number_format($resumo->total_entradas, 3, ',', '.') }}</div>
            </td>
            <td>
                <div class="label">Saidas</div>
                <div class="value">{{ number_format($resumo->total_saidas, 3, ',', '.') }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Baixas</div>
                <div class="value">{{ $resumo->total_baixas }}</div>
            </td>
            <td>
                <div class="label">Qtd. Baixada</div>
                <div class="value">{{ number_format($resumo->quantidade_baixada, 3, ',', '.') }}</div>
            </td>
            <td>
                <div class="label">Primeira Mov.</div>
                <div class="value" style="font-size: 12px;">{{ $resumo->primeira_movimentacao }}</div>
            </td>
            <td>
                <div class="label">Ultima Mov.</div>
                <div class="value" style="font-size: 12px;">{{ $resumo->ultima_movimentacao }}</div>
            </td>
        </tr>
    </table>

    <div class="section-title">Dados do Item</div>
    <table class="listagem">
        <tbody>
            <tr>
                <th style="width: 20%;">Item</th>
                <td>{{ $estoque->item?->nome ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Descricao</th>
                <td>{{ $estoque->item?->descricao ?: '-' }}</td>
            </tr>
            <tr>
                <th>Categoria</th>
                <td>{{ $estoque->item?->tipo_item?->label() ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Unidade</th>
                <td>{{ strtoupper($estoque->item?->unidade_medida?->value ?? 'N/A') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">Historico Completo de Movimentacoes</div>
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
