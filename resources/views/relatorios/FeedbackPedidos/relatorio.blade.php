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

@section('reportTitle', $reportTitle ?? 'Relatorio de Feedback de Pedidos')
@section('reportSubtitle', $reportSubtitle ?? 'Resumo geral com indicadores, listagem e analise visual')

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
@endsection

@section('content')
    @if(in_array($tipoRelatorio ?? 'geral', ['geral', 'graficos'], true))
        <div class="cards-container">
            <div class="card">
                <div class="card-label">Media Geral</div>
                <div class="card-value">
                    {{ $mediaGeral }}
                    <span class="card-unit">/ 5</span>
                </div>
            </div>
            <div class="card">
                <div class="card-label">Total de Avaliacoes</div>
                <div class="card-value">{{ $totalAvaliacoes }}</div>
            </div>
            <div class="card">
                <div class="card-label">Nivel de Satisfacao</div>
                <div class="card-value">
                    {{ $percentualSatisfacao }}<span class="card-unit">%</span>
                </div>
            </div>
        </div>

        <div class="divider"></div>
    @endif

    @if(in_array($tipoRelatorio ?? 'geral', ['geral', 'listagem'], true))
        <div class="section-title">Detalhes das Avaliacoes</div>

        @if($feedbacks->isEmpty())
            <p style="text-align: center; color: #6b7280; padding: 20px 0;">
                Nenhuma avaliacao registrada para os filtros selecionados.
            </p>
        @else
            <table class="feedback-table">
                <thead>
                    <tr>
                        <th style="width: 12%;">Protocolo</th>
                        <th style="width: 15%;">Escola</th>
                        <th style="width: 12%;">Nota</th>
                        <th style="width: 30%;">Descricao</th>
                        <th style="width: 13%;">Data</th>
                        <th style="width: 18%;">Tipo Manutencao</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($feedbacks as $feedback)
                        <tr>
                            <td><strong>{{ $feedback->pedido?->numero_protocolo ?? '-' }}</strong></td>
                            <td>{{ $feedback->pedido?->escola?->nome ?? '-' }}</td>
                            <td style="text-align: center;">
                                <span class="badge" style="background: {{ $cores[$feedback->valor] ?? '#6b7280' }};">
                                    {{ $feedback->valor }}/5
                                </span>
                            </td>
                            <td class="text-truncate">{{ $feedback->descricao ?? '-' }}</td>
                            <td>{{ $feedback->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
                            <td>{{ $feedback->pedido?->tipoManutencao?->nome ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endif

    @if(in_array($tipoRelatorio ?? 'geral', ['geral', 'graficos'], true) && ($graficoMediaMensal || $graficoPorNota))
        <div class="divider" style="margin-top: 20px;"></div>
        <div class="section-title">Analise Visual</div>

        <div class="graficos-container">
            @if($graficoMediaMensal)
                <div class="grafico-wrapper">
                    <div class="grafico-titulo">Quantidade Mensal de Avaliacoes</div>
                    <img src="{{ $graficoMediaMensal }}" alt="Grafico Mensal">
                </div>
            @endif

            @if($graficoPorNota)
                <div class="grafico-wrapper">
                    <div class="grafico-titulo">Distribuicao por Nota</div>
                    <img src="{{ $graficoPorNota }}" alt="Grafico por Nota">
                </div>
            @endif
        </div>
    @endif

    @if(in_array($tipoRelatorio ?? 'geral', ['geral', 'graficos'], true) && ! empty($matrizesAgrupadas))
        <div class="divider" style="margin-top: 20px;"></div>
        <div class="section-title">Matriz de Avaliacoes por Mes</div>

        @foreach($matrizesAgrupadas as $ano => $meses)
            <div class="ano-titulo">{{ $ano }}</div>

            <table class="matriz-table">
                <thead>
                    <tr>
                        <th style="width: 10%; text-align: left;">Avaliacao</th>
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
