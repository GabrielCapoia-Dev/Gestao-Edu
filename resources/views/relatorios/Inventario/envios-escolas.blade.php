@extends('relatorios.layouts.base-pdf')

@section('reportTitle', $reportTitle ?? 'Relatorio de Envios para Escolas')
@section('reportSubtitle', $reportSubtitle ?? 'Consolidado das entregas realizadas para os inventarios escolares')

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

    .school-header {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 10px;
    }

    .school-header td {
        border: 1px solid #dbeafe;
        background: #eff6ff;
        padding: 12px;
        vertical-align: top;
    }

    .school-title {
        font-size: 15px;
        font-weight: bold;
        color: #0f172a;
    }

    .school-subtitle {
        margin-top: 4px;
        font-size: 10px;
        color: #475569;
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

    .right {
        text-align: right;
    }

    .muted {
        color: #64748b;
        font-size: 9px;
    }

    .school-block {
        page-break-inside: avoid;
    }
@endsection

@section('content')
    <table class="cards">
        <tr>
            <td>
                <div class="label">Escolas com entregas</div>
                <div class="value">{{ $resumo->total_escolas }}</div>
            </td>
            <td>
                <div class="label">Pedidos atendidos</div>
                <div class="value">{{ $resumo->total_pedidos }}</div>
            </td>
            <td>
                <div class="label">Entregas registradas</div>
                <div class="value">{{ $resumo->total_entregas }}</div>
            </td>
            <td>
                <div class="label">Periodo</div>
                <div class="value" style="font-size: 14px;">{{ $periodoLabel }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Itens consolidados</div>
                <div class="value">{{ $resumo->total_itens }}</div>
            </td>
            <td>
                <div class="label">Quantidade total enviada</div>
                <div class="value">{{ number_format($resumo->quantidade_total, 3, ',', '.') }}</div>
            </td>
            <td colspan="2">
                <div class="label">Valor total estimado</div>
                <div class="value">R$ {{ number_format($resumo->valor_total, 2, ',', '.') }}</div>
            </td>
        </tr>
    </table>

    @forelse($escolas as $escola)
        @if (! $loop->first)
            <div class="page-break"></div>
        @endif

        <div class="school-block">
            <table class="school-header">
                <tr>
                    <td style="width: 60%;">
                        <div class="school-title">{{ $escola['escola_nome'] }}</div>
                        <div class="school-subtitle">{{ $escola['inventario_nome'] }}</div>
                    </td>
                    <td style="width: 40%;">
                        <div class="label">Janela considerada</div>
                        <div class="value" style="font-size: 13px;">{{ $periodoLabel }}</div>
                    </td>
                </tr>
            </table>

            <table class="cards">
                <tr>
                    <td>
                        <div class="label">Pedidos atendidos</div>
                        <div class="value">{{ $escola['total_pedidos'] }}</div>
                    </td>
                    <td>
                        <div class="label">Itens enviados</div>
                        <div class="value">{{ $escola['total_itens'] }}</div>
                    </td>
                    <td>
                        <div class="label">Quantidade total</div>
                        <div class="value">{{ number_format($escola['quantidade_total'], 3, ',', '.') }}</div>
                    </td>
                    <td>
                        <div class="label">Valor total</div>
                        <div class="value">R$ {{ number_format($escola['valor_total'], 2, ',', '.') }}</div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="label">Primeiro envio</div>
                        <div class="value" style="font-size: 13px;">{{ $escola['primeiro_envio'] }}</div>
                    </td>
                    <td>
                        <div class="label">Ultimo envio</div>
                        <div class="value" style="font-size: 13px;">{{ $escola['ultimo_envio'] }}</div>
                    </td>
                    <td colspan="2">
                        <div class="label">Entregas registradas</div>
                        <div class="value">{{ $escola['total_entregas'] }}</div>
                    </td>
                </tr>
            </table>

            <div class="section-title">Itens enviados</div>
            <table class="listagem">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Categoria</th>
                        <th>Unidade</th>
                        <th class="right">Entregas</th>
                        <th class="right">Quantidade</th>
                        <th class="right">Valor Unitario</th>
                        <th class="right">Valor Total</th>
                        <th>Ultima entrega</th>
                        <th>Romaneios</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($escola['itens'] as $item)
                        <tr>
                            <td>{{ $item['nome'] }}</td>
                            <td>{{ $item['categoria'] }}</td>
                            <td>{{ $item['unidade'] }}</td>
                            <td class="right">{{ $item['total_entregas'] }}</td>
                            <td class="right">{{ number_format($item['quantidade_total'], 3, ',', '.') }}</td>
                            <td class="right">R$ {{ number_format($item['valor_unitario_referencia'], 2, ',', '.') }}</td>
                            <td class="right">R$ {{ number_format($item['valor_total'], 2, ',', '.') }}</td>
                            <td>{{ $item['ultima_entrega'] }}</td>
                            <td>{{ $item['romaneios'] !== '' ? $item['romaneios'] : '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">Nenhum item enviado para esta escola no periodo selecionado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <p class="muted" style="margin-top: 6px;">
                Os valores utilizam a referencia contratual mais recente disponivel no sistema para cada item.
            </p>
        </div>
    @empty
        <div class="section-title">Nenhum envio encontrado</div>
        <table class="listagem">
            <tbody>
                <tr>
                    <td>Nenhuma entrega vinculada aos inventarios escolares foi encontrada para os filtros selecionados.</td>
                </tr>
            </tbody>
        </table>
    @endforelse
@endsection
