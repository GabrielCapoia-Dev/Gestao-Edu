{{-- Relatorio Analitico de Pedidos de Manutencao --}}
@php
    $coresPrioridade = [
        'Emergencial' => '#a10000',
        'Corretivo' => '#973f00',
        'Preventivo' => '#013891',
        'Indeterminado' => '#2b2b2b',
    ];
@endphp
@extends('relatorios.layouts.base-pdf')

@section('reportTitle', $reportTitle ?? 'Relatório Analítico de Pedidos de Manutenção')
@section('reportSubtitle', $reportSubtitle ?? 'Visão consolidada com indicadores e agrupamentos')

@section('styles')
    .page-break {
        page-break-before: always;
    }

    .cards-row {
        display: table;
        width: 100%;
        margin-bottom: 8px;
        page-break-inside: avoid;
        border-spacing: 6px;
    }

    .card {
        display: table-cell;
        padding: 10px 8px;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        background: #f9fafb;
        vertical-align: top;
        text-align: center;
    }

    .card-label {
        font-size: 9px;
        color: #6b7280;
        margin-bottom: 4px;
    }

    .card-value {
        font-size: 20px;
        font-weight: bold;
        color: #111827;
    }

    .card-unit {
        font-size: 9px;
        color: #6b7280;
    }

    .card-green {
        background: #d1fae5;
        border-color: #6ee7b7;
    }

    .card-red {
        background: #fee2e2;
        border-color: #fca5a5;
    }

    .card-blue {
        background: #dbeafe;
        border-color: #93c5fd;
    }

    .card-yellow {
        background: #fef3c7;
        border-color: #fcd34d;
    }

    .card-purple {
        background: #ede9fe;
        border-color: #c4b5fd;
    }

    .card-value-green {
        color: #065f46;
    }

    .card-value-red {
        color: #7f1d1d;
    }

    .card-value-blue {
        color: #1e3a5f;
    }

    .card-value-yellow {
        color: #78350f;
    }

    .card-value-purple {
        color: #4c1d95;
    }

    .badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 9px;
        font-weight: bold;
        color: #fff;
    }

    .insight-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 6px;
        font-size: 10px;
        page-break-inside: avoid;
    }

    .insight-table th {
        background: #f3f4f6;
        border: 1px solid #d1d5db;
        padding: 6px 8px;
        text-align: left;
        font-size: 9px;
        color: #374151;
    }

    .insight-table td {
        border: 1px solid #e5e7eb;
        padding: 5px 8px;
    }

    .insight-table tbody tr:nth-child(odd) {
        background: #f9fafb;
    }

    .insight-table tbody tr {
        page-break-inside: avoid;
    }

    .bar-outer {
        background: #e5e7eb;
        border-radius: 4px;
        height: 10px;
        width: 100%;
    }

    .bar-inner {
        height: 10px;
        border-radius: 4px;
    }

    .two-col {
        display: table;
        width: 100%;
        page-break-inside: avoid;
    }

    .col-left {
        display: table-cell;
        width: 49%;
        vertical-align: top;
        padding-right: 8px;
    }

    .col-right {
        display: table-cell;
        width: 49%;
        vertical-align: top;
        padding-left: 8px;
    }

    .listing-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 8px;
        font-size: 9px;
        page-break-inside: auto;
    }

    .listing-table thead {
        background: #1f2937;
        color: white;
        display: table-header-group;
    }

    .listing-table th {
        border: 1px solid #374151;
        padding: 6px 5px;
        text-align: left;
        font-size: 9px;
        color: white;
    }

    .listing-table td {
        border: 1px solid #e5e7eb;
        padding: 5px 5px;
        font-size: 9px;
        vertical-align: top;
    }

    .listing-table tbody tr:nth-child(odd) {
        background: #f9fafb;
    }

    .listing-table tbody tr {
        page-break-inside: avoid;
    }

    .text-danger {
        color: #a10000;
        font-weight: bold;
    }

    .text-ok {
        color: #059669;
        font-weight: bold;
    }

    .text-gray {
        color: #6b7280;
    }
@endsection

@section('content')
    <div class="section-title">Visao Geral</div>

    <div class="cards-row">
        <div class="card">
            <div class="card-label">Total de Pedidos</div>
            <div class="card-value">{{ $metricas->total }}</div>
        </div>
        <div class="card card-green">
            <div class="card-label">Concluídos</div>
            <div class="card-value card-value-green">{{ $metricas->concluidos }}</div>
        </div>
        <div class="card card-blue">
            <div class="card-label">Abertos/Ativos</div>
            <div class="card-value card-value-blue">{{ $metricas->abertos }}</div>
        </div>
        <div class="card card-red">
            <div class="card-label">Cancelados</div>
            <div class="card-value card-value-red">{{ $metricas->cancelados }}</div>
        </div>
        <div class="card card-yellow">
            <div class="card-label">Taxa de Conclusão</div>
            <div class="card-value card-value-yellow">
                {{ $metricas->taxa_conclusao }}<span class="card-unit">%</span>
            </div>
        </div>
    </div>

    <div class="cards-row">
        <div class="card card-green">
            <div class="card-label">Entregues no Prazo</div>
            <div class="card-value card-value-green">{{ $metricas->no_prazo }}</div>
        </div>
        <div class="card card-red">
            <div class="card-label">Entregues com Atraso</div>
            <div class="card-value card-value-red">{{ $metricas->atrasados }}</div>
        </div>
        <div class="card card-yellow">
            <div class="card-label">Vencidos (sem entrega)</div>
            <div class="card-value card-value-yellow">{{ $metricas->vencidos }}</div>
        </div>
        <div class="card card-purple">
            <div class="card-label">Tempo Medio (dias)</div>
            <div class="card-value card-value-purple">{{ $metricas->tempo_medio ?? '-' }}</div>
        </div>
        <div class="card card-blue">
            <div class="card-label">Pontualidade</div>
            <div class="card-value card-value-blue">
                {{ $metricas->taxa_prazo }}<span class="card-unit">%</span>
            </div>
        </div>
    </div>

    @if($metricaFeedback->total > 0)
        <div class="cards-row">
            <div class="card">
                <div class="card-label">Avaliações Recebidas</div>
                <div class="card-value">{{ $metricaFeedback->total }}</div>
            </div>
            <div class="card card-green">
                <div class="card-label">Nota Media</div>
                <div class="card-value card-value-green">{{ $metricaFeedback->media }}<span class="card-unit">/5</span></div>
            </div>
            <div class="card card-blue">
                <div class="card-label">Satisfação (>= 4)</div>
                <div class="card-value card-value-blue">{{ $metricaFeedback->tx_satisfacao }}<span class="card-unit">%</span></div>
            </div>
        </div>
    @endif

    <div class="divider"></div>

    <div class="two-col">
        <div class="col-left">
            <div class="section-title">Distribuição por status</div>
            <table class="insight-table">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th style="text-align: center;">Qtd</th>
                        <th style="width: 40%;">Distribuição</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($porStatus as $s)
                        @php $pct = $metricas->total > 0 ? round(($s->total / $metricas->total) * 100) : 0; @endphp
                        <tr>
                            <td><span class="badge" style="background: {{ $s->cor }};">{{ $s->nome }}</span></td>
                            <td style="text-align: center; font-weight: bold;">{{ $s->total }}</td>
                            <td>
                                <div class="bar-outer">
                                    <div class="bar-inner" style="width: {{ $pct }}%; background: {{ $s->cor }};"></div>
                                </div>
                                <span style="font-size: 8px; color: #6b7280;">{{ $pct }}%</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="col-right">
            <div class="section-title">Distribuição por prioridade</div>
            <table class="insight-table">
                <thead>
                    <tr>
                        <th>Prioridade</th>
                        <th style="text-align: center;">Qtd</th>
                        <th style="width: 40%;">Distribuição</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($porPrioridade as $p)
                        @php
                            $pct = $metricas->total > 0 ? round(($p->total / $metricas->total) * 100) : 0;
                            $cor = $coresPrioridade[$p->prioridade ?? 'Indeterminado'] ?? '#2b2b2b';
                        @endphp
                        <tr>
                            <td><span class="badge" style="background: {{ $cor }};">{{ $p->prioridade }}</span></td>
                            <td style="text-align: center; font-weight: bold;">{{ $p->total }}</td>
                            <td>
                                <div class="bar-outer">
                                    <div class="bar-inner" style="width: {{ $pct }}%; background: {{ $cor }};"></div>
                                </div>
                                <span style="font-size: 8px; color: #6b7280;">{{ $pct }}%</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="divider"></div>

    <div class="two-col">
        <div class="col-left">
            <div class="section-title">Top Tipos de Manutenção</div>
            <table class="insight-table">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th style="text-align: center;">Total</th>
                        <th style="text-align: center;">Concluídos</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($porTipo as $t)
                        <tr>
                            <td>{{ $t->nome }}</td>
                            <td style="text-align: center; font-weight: bold;">{{ $t->total }}</td>
                            <td style="text-align: center;">
                                <span style="color: {{ $t->taxa >= 70 ? '#059669' : ($t->taxa >= 40 ? '#d97706' : '#a10000') }}; font-weight: bold;">
                                    {{ $t->concluidos }}
                                    <span style="font-size: 8px; font-weight: normal;">({{ $t->taxa }}%)</span>
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="col-right">
            <div class="section-title">Pedidos por Escola</div>
            <table class="insight-table">
                <thead>
                    <tr>
                        <th>Escola</th>
                        <th style="text-align: center;">Pedidos</th>
                        <th style="width: 35%;">Volume</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($porEscola as $e)
                        <tr>
                            <td>{{ $e->nome }}</td>
                            <td style="text-align: center; font-weight: bold;">{{ $e->total }}</td>
                            <td>
                                <div class="bar-outer">
                                    <div class="bar-inner" style="width: {{ $e->pct_bar }}%; background: #3b82f6;"></div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if($porMes->count() > 1)
        <div class="divider"></div>
        <div class="section-title">Evolucao Mensal de Pedidos</div>
        <table class="insight-table">
            <thead>
                <tr>
                    <th style="width: 15%;">Mes/Ano</th>
                    <th style="text-align: center; width: 10%;">Pedidos</th>
                    <th>Volume</th>
                </tr>
            </thead>
            <tbody>
                @foreach($porMes as $m)
                    <tr>
                        <td>{{ $m->mes }}</td>
                        <td style="text-align: center; font-weight: bold;">{{ $m->total }}</td>
                        <td>
                            <div class="bar-outer">
                                <div class="bar-inner" style="width: {{ $m->pct_bar }}%; background: #6366f1;"></div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

@endsection
