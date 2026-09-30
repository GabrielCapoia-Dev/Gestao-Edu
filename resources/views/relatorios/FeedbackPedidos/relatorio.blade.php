@php
    $cores = [
        5 => '#10b981',
        4 => '#3b82f6',
        3 => '#f59e0b',
        2 => '#ef4444',
        1 => '#dc2626',
    ];
@endphp
@extends('relatorios.layouts.base-pdf')

@section('reportTitle', $reportTitle ?? 'Relatório de Feedback de Pedidos')
@section('reportSubtitle', $reportSubtitle ?? 'Resumo geral com indicadores e análise visual')

@section('styles')
    .cards-container {
        display: table;
        width: 100%;
        margin-bottom: 15px;
        page-break-inside: avoid;
    }

    .card {
        display: table-cell;
        width: 33.33%;
        padding: 12px;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        background: #f9fafb;
        vertical-align: top;
        padding-right: 10px;
    }

    .card + .card {
        border-left: none;
    }

    .card-label {
        font-size: 10px;
        color: #6b7280;
        margin-bottom: 6px;
        font-weight: normal;
    }

    .card-value {
        font-size: 24px;
        font-weight: bold;
        color: #1f2937;
    }

    .card-unit {
        font-size: 10px;
        color: #6b7280;
        margin-left: 4px;
    }

    .feedback-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
        page-break-inside: auto;
    }

    .feedback-table thead {
        background: #f3f4f6;
        page-break-inside: avoid;
        display: table-header-group;
    }

    .feedback-table th {
        border: 1px solid #d1d5db;
        padding: 8px;
        text-align: left;
        font-weight: bold;
        font-size: 10px;
        color: #374151;
    }

    .feedback-table td {
        border: 1px solid #e5e7eb;
        padding: 8px;
        font-size: 10px;
    }

    .feedback-table tbody tr {
        page-break-inside: avoid;
    }

    .feedback-table tbody tr:nth-child(odd) {
        background: #f9fafb;
    }

    .badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 3px;
        font-weight: bold;
        color: white;
        font-size: 9px;
    }

    .text-truncate {
        max-width: 250px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .graficos-container {
        display: table;
        width: 100%;
        margin-top: 20px;
        page-break-inside: avoid;
        gap: 10px;
    }

    .grafico-wrapper {
        display: table-cell;
        width: 50%;
        padding: 10px;
        border: 1px solid #e5e7eb;
        background: #f9fafb;
        vertical-align: top;
    }

    .grafico-wrapper + .grafico-wrapper {
        padding-left: 5px;
    }

    .grafico-titulo {
        font-weight: bold;
        font-size: 11px;
        margin-bottom: 8px;
        color: #1f2937;
        text-align: center;
    }

    .grafico-wrapper img {
        width: 100%;
        height: auto;
        display: block;
    }

    .matriz-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
        font-size: 9px;
        page-break-inside: avoid;
    }

    .matriz-table thead {
        background: #374151;
        color: white;
    }

    .matriz-table th {
        border: 1px solid #4b5563;
        padding: 4px 2px;
        text-align: center;
        font-weight: bold;
        color: white;
        font-size: 8px;
        line-height: 1.1;
        word-break: break-word;
    }

    .matriz-table td {
        border: 1px solid #d1d5db;
        padding: 4px 2px;
        text-align: center;
        font-weight: bold;
        font-size: 9px;
    }

    .matriz-table tbody tr {
        page-break-inside: avoid;
    }

    .nota-label {
        text-align: left;
        font-weight: bold;
        padding-left: 4px !important;
        font-size: 8px;
    }

    .nota-1 {
        background: #fee2e2;
        color: #7f1d1d;
    }

    .nota-2 {
        background: #fed7aa;
        color: #7c2d12;
    }

    .nota-3 {
        background: #fef3c7;
        color: #78350f;
    }

    .nota-4 {
        background: #bbf7d0;
        color: #064e3b;
    }

    .nota-5 {
        background: #d1fae5;
        color: #065f46;
    }

    .matrix-empty {
        color: #9ca3af;
        font-weight: normal;
    }

    .ano-titulo {
        font-weight: bold;
        font-size: 11px;
        margin-top: 20px;
        margin-bottom: 10px;
        color: #1f2937;
        page-break-inside: avoid;
    }

    .ranking-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 12px;
        font-size: 9px;
        page-break-inside: auto;
    }

    .ranking-table th {
        background: #f3f4f6;
        border: 1px solid #d1d5db;
        color: #374151;
        padding: 6px;
        text-align: left;
    }

    .ranking-table td {
        border: 1px solid #e5e7eb;
        padding: 6px;
    }

    .ranking-table tbody tr:nth-child(odd) {
        background: #f9fafb;
    }

    .ranking-table tbody tr {
        page-break-inside: avoid;
    }

    .ranking-block + .ranking-block {
        margin-top: 22px;
        page-break-before: always;
    }
@endsection

@section('content')
    @if(in_array($tipoRelatorio ?? 'geral', ['geral', 'graficos', 'geral_satisfacao', 'satisfacao_escolas'], true))
        <div class="cards-container">
            <div class="card">
                <div class="card-label">Média geral</div>
                <div class="card-value">
                    {{ $mediaGeral }}
                    <span class="card-unit">/ 5</span>
                </div>
            </div>
            <div class="card">
                <div class="card-label">Total de Avaliações</div>
                <div class="card-value">{{ $totalAvaliacoes }}</div>
            </div>
            <div class="card">
                <div class="card-label">Nível de Satisfação</div>
                <div class="card-value">
                    {{ $percentualSatisfacao }}<span class="card-unit">%</span>
                </div>
            </div>
        </div>

        <div class="divider"></div>
    @endif

    @if(in_array($tipoRelatorio ?? 'geral', ['geral', 'graficos', 'geral_satisfacao', 'satisfacao_escolas'], true) && ! empty($rankingEscolas ?? []))
        <div class="ranking-block">
        <div class="section-title">Satisfação por Escola</div>

        <table class="ranking-table">
            <thead>
                <tr>
                    <th>Escola</th>
                    <th>Total</th>
                    <th>Média</th>
                    <th>Satisfação</th>
                    <th>Reabertos</th>
                    <th>Críticas</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rankingEscolas as $row)
                    <tr>
                        <td><strong>{{ $row['nome'] }}</strong></td>
                        <td>{{ $row['total'] }}</td>
                        <td>{{ $row['media'] }}/5</td>
                        <td>{{ $row['satisfacao'] }}%</td>
                        <td>{{ $row['reabertos'] }}</td>
                        <td>{{ $row['criticas'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="divider"></div>
        </div>
    @endif

    @if(in_array($tipoRelatorio ?? 'geral', ['geral', 'graficos', 'geral_satisfacao'], true) && ! empty($rankingEmpresas ?? []))
        <div class="ranking-block">
            <div class="section-title">Desempenho por Empresa Contratada</div>

            <table class="ranking-table">
                <thead>
                    <tr>
                        <th>Empresa</th>
                        <th>Total</th>
                        <th>Média</th>
                        <th>Satisfação</th>
                        <th>Reabertos</th>
                        <th>Críticas</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rankingEmpresas as $row)
                        <tr>
                            <td><strong>{{ $row['nome'] }}</strong></td>
                            <td>{{ $row['total'] }}</td>
                            <td>{{ $row['media'] }}/5</td>
                            <td>{{ $row['satisfacao'] }}%</td>
                            <td>{{ $row['reabertos'] }}</td>
                            <td>{{ $row['criticas'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if(in_array($tipoRelatorio ?? 'geral', ['geral', 'graficos', 'geral_satisfacao', 'satisfacao_escolas'], true) && ($graficoMediaMensal || $graficoPorNota))
        <div class="divider" style="margin-top: 20px;"></div>
        <div class="section-title">Análise visual</div>

        <div class="graficos-container">
            @if($graficoMediaMensal)
                <div class="grafico-wrapper">
                    <div class="grafico-titulo">Quantidade Mensal de Avaliações</div>
                    <img src="{{ $graficoMediaMensal }}" alt="Gráfico mensal">
                </div>
            @endif

            @if($graficoPorNota)
                <div class="grafico-wrapper">
                    <div class="grafico-titulo">Distribuição por nota</div>
                    <img src="{{ $graficoPorNota }}" alt="Gráfico por nota">
                </div>
            @endif
        </div>
    @endif

    @if(in_array($tipoRelatorio ?? 'geral', ['geral', 'graficos', 'geral_satisfacao', 'satisfacao_escolas'], true) && ! empty($matrizesAgrupadas))
        <div class="divider" style="margin-top: 20px;"></div>
        <div class="section-title">Matriz de avaliações por mês</div>

        @foreach($matrizesAgrupadas as $ano => $meses)
            <div class="ano-titulo">{{ $ano }}</div>

            <table class="matriz-table">
                <thead>
                    <tr>
                        <th style="width: 10%; text-align: left;">Avaliação</th>
                        @foreach($meses as $mes => $dados)
                            <th style="width: {{ 90 / count($meses) }}%;">{{ $mes }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @for($nota = 5; $nota >= 1; $nota--)
                        <tr>
                            <td class="nota-label nota-{{ $nota }}">Nota {{ $nota }}</td>
                            @foreach($meses as $mes => $dados)
                                <td class="nota-{{ $nota }}">
                                    @if($dados[$nota] > 0)
                                        <strong>{{ $dados[$nota] }}</strong>
                                    @else
                                        <span class="matrix-empty">-</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endfor
                    <tr class="total-row">
                        <td style="font-weight: bold;">Total</td>
                        @foreach($meses as $mes => $dados)
                            @php $totalMes = array_sum($dados); @endphp
                            <td style="font-weight: bold;">{{ $totalMes > 0 ? $totalMes : '-' }}</td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        @endforeach
    @endif
@endsection
