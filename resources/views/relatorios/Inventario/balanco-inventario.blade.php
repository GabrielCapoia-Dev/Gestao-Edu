@extends('relatorios.layouts.base-pdf')

@section('reportTitle', $reportTitle ?? 'Relatório de Balanço de Inventário')
@section('reportSubtitle', $reportSubtitle ?? 'Resumo completo das divergências e do impacto financeiro do inventário escolar')

@section('styles')
    .cards {
        width: 100%;
        margin-bottom: 16px;
        border-collapse: separate;
        border-spacing: 6px;
    }

    .cards td {
        width: 33.33%;
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

    .obs-box {
        border: 1px solid #d1d5db;
        background: #f8fafc;
        padding: 10px;
        font-size: 10px;
        margin-bottom: 16px;
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
                <div class="label">Itens Selecionados</div>
                <div class="value">{{ $metricas->itens_selecionados }}</div>
            </td>
            <td>
                <div class="label">Pendentes</div>
                <div class="value">{{ $metricas->itens_pendentes }}</div>
            </td>
            <td>
                <div class="label">Divergências</div>
                <div class="value">{{ $metricas->divergencias }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Impacto Financeiro Total</div>
                <div class="value">R$ {{ number_format($metricas->impacto_financeiro_total, 2, ',', '.') }}</div>
            </td>
            <td>
                <div class="label">Impacto Positivo</div>
                <div class="value">R$ {{ number_format($metricas->impacto_financeiro_positivo, 2, ',', '.') }}</div>
            </td>
            <td>
                <div class="label">Impacto Negativo</div>
                <div class="value">R$ {{ number_format($metricas->impacto_financeiro_negativo, 2, ',', '.') }}</div>
            </td>
        </tr>
    </table>

    <div class="section-title">Resumo do Balanço</div>
    <table class="listagem">
        <tbody>
            <tr>
                <th>Código</th>
                <td>{{ $balanco->codigo }}</td>
                <th>Status</th>
                <td>{{ $balanco->status?->label() ?? '-' }}</td>
            </tr>
            <tr>
                <th>Escola</th>
                <td>{{ $balanco->inventario?->escola?->nome ?? '-' }}</td>
                <th>Inventário</th>
                <td>{{ $balanco->inventario?->nome ?? '-' }}</td>
            </tr>
            <tr>
                <th>Data agendada</th>
                <td>{{ $balanco->data_agendada?->format('d/m/Y H:i') ?? '-' }}</td>
                <th>Criado por</th>
                <td>{{ $balanco->criadoPor?->name ?? '-' }}</td>
            </tr>
            <tr>
                <th>Iniciado em</th>
                <td>{{ $balanco->iniciado_em?->format('d/m/Y H:i') ?? '-' }}</td>
                <th>Iniciado por</th>
                <td>{{ $balanco->iniciadoPor?->name ?? '-' }}</td>
            </tr>
            <tr>
                <th>Concluído em</th>
                <td>{{ $balanco->concluido_em?->format('d/m/Y H:i') ?? '-' }}</td>
                <th>Concluído por</th>
                <td>{{ $balanco->concluidoPor?->name ?? '-' }}</td>
            </tr>
            <tr>
                <th>Cancelado em</th>
                <td>{{ $balanco->cancelado_em?->format('d/m/Y H:i') ?? '-' }}</td>
                <th>Cancelado por</th>
                <td>{{ $balanco->canceladoPor?->name ?? '-' }}</td>
            </tr>
        </tbody>
    </table>

    @if(filled($balanco->observacao_inicial))
        <div class="section-title">Observação Inicial</div>
        <div class="obs-box">{{ $balanco->observacao_inicial }}</div>
    @endif

    <div class="section-title">Timeline do Balanço</div>
    <table class="listagem">
        <thead>
            <tr>
                <th>Data</th>
                <th>Evento</th>
                <th>Responsável</th>
                <th>Descrição</th>
            </tr>
        </thead>
        <tbody>
            @forelse($eventos as $evento)
                <tr>
                    <td>{{ $evento['data'] }}</td>
                    <td>{{ $evento['tipo'] }}</td>
                    <td>{{ $evento['responsavel'] }}</td>
                    <td>{{ $evento['descricao'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="muted">Nenhum evento registrado para este balanço.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Itens Selecionados no Balanço</div>
    <table class="listagem">
        <thead>
            <tr>
                <th>Tipo</th>
                <th>Item</th>
                <th>Unidade</th>
                <th class="right">Saldo Antes</th>
                <th class="right">Qtd. Contada</th>
                <th class="right">Divergência</th>
                <th class="right">Valor Unitário</th>
                <th class="right">Impacto Financeiro</th>
                <th class="right">Saldo Final</th>
                <th>Status</th>
                <th>Contado por</th>
                <th>Observação</th>
            </tr>
        </thead>
        <tbody>
            @forelse($itensContagem as $item)
                <tr>
                    <td>{{ $item['tipo'] }}</td>
                    <td>{{ $item['item_nome'] }}</td>
                    <td>{{ $item['unidade'] }}</td>
                    <td class="right">{{ number_format($item['saldo_sistema_antes'], 3, ',', '.') }}</td>
                    <td class="right">
                        {{ $item['quantidade_contada'] !== null ? number_format($item['quantidade_contada'], 3, ',', '.') : '-' }}
                    </td>
                    <td class="right">
                        {{ $item['diferenca'] !== null ? number_format($item['diferenca'], 3, ',', '.') : '-' }}
                    </td>
                    <td class="right">R$ {{ number_format($item['valor_unitario_referencia'], 2, ',', '.') }}</td>
                    <td class="right">R$ {{ number_format($item['valor_impacto'], 2, ',', '.') }}</td>
                    <td class="right">
                        {{ $item['saldo_final'] !== null ? number_format($item['saldo_final'], 3, ',', '.') : '-' }}
                    </td>
                    <td>{{ $item['status'] }}</td>
                    <td>{{ $item['contado_por'] }}</td>
                    <td>{{ $item['observacao'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" class="muted">Este balanço ainda não possui itens selecionados para contagem.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
