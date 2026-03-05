@php

// EXEMPLO
$cores = [
5 => '#10b981',
4 => '#3b82f6',
3 => '#f59e0b',
2 => '#ef4444',
1 => '#dc2626',
];
@endphp

<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1f2937;
            margin: 0;
            padding: 0;
        }

        @page {
            size: A4 portrait;
            margin: 150px 25px 80px 25px;
        }

        .header-fixed {
            position: fixed;
            top: -148px;
            left: 0;
            right: 0;
            height: 120px;
            background: white;
            z-index: 1000;
        }

        .header-table {
            width: 100%;
            margin-bottom: 10px;
        }

        .header-table td {
            vertical-align: middle;
        }

        .logo-left {
            width: 170px;
        }

        .logo-right {
            text-align: right;
        }

        .header-text {
            text-align: center;
            margin-top: 5px;
            margin-bottom: 15px;
        }

        .header-text h2 {
            margin: 0;
            font-size: 16px;
        }

        .header-text h3 {
            margin: 2px 0 0 0;
            font-size: 13px;
            font-weight: normal;
        }

        .footer-fixed {
            position: fixed;
            bottom: -60px;
            left: 0;
            right: 0;
            height: 60px;
            font-size: 9px;
            color: #6b7280;
            background: white;
            z-index: 1000;
        }

        .footer-divider {
            border-top: 1px solid #d1d5db;
            margin-bottom: 6px;
        }

        .footer-left {
            float: left;
            width: 60%;
            text-align: left;
        }

        .footer-right {
            float: right;
            width: 40%;
            text-align: right;
        }

        .content {
            page-break-inside: auto;
        }

        .divider {
            border-top: 1px solid #d1d5db;
            margin: 10px 0;
        }

        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }

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

        .card+.card {
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

        .section-title {
            font-weight: bold;
            font-size: 12px;
            margin: 15px 0 10px 0;
            page-break-inside: avoid;
            color: #1f2937;
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

        .grafico-wrapper+.grafico-wrapper {
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
    </style>
</head>

<body>

    <!-- HEADER FIXO -->
    <div class="header-fixed">
        <table class="header-table">
            <tr>
                <td style="width:50%;">
                    <img src="{{ public_path('images/logo-educacao.png') }}" class="logo-left">
                </td>
                <td style="width:50%;" class="logo-right">
                    <img src="{{ public_path('images/logo-abrinq-preto.png') }}" style="width:190px;">
                </td>
            </tr>
        </table>

        <div class="header-text">
            <h2>Gestão Educacional</h2>
            <h3>Relatório de Feedback de Pedidos</h3>
        </div>
        <div class="divider"></div>
    </div>

    <!-- FOOTER FIXO -->
    <div class="footer-fixed">
        <div class="footer-divider"></div>
        <div class="clearfix">
            <div class="footer-left">
                Documento exportado do Sistema de Gestão Educacional<br>
                Secretaria Municipal de Educação – Umuarama
            </div>
            <div class="footer-right">
                Exportado por: <strong>{{ $usuarioExportacao->name ?? 'Sistema' }}</strong><br>
                Em: {{ $dataExportacao->format('d/m/Y H:i') }}
            </div>
        </div>
    </div>

    <!-- CONTEÚDO PRINCIPAL -->
    <div class="content">

        <!-- CARDS - MOSTRAR EM: geral, graficos -->
        @if(in_array($tipoRelatorio ?? 'geral', ['geral', 'graficos']))
        <div class="cards-container">
            <div class="card">
                <div class="card-label">Média Geral</div>
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

        <!-- TABELA DE FEEDBACKS - MOSTRAR EM: geral, listagem APENAS (NÃO em graficos) -->
        @if(in_array($tipoRelatorio ?? 'geral', ['geral', 'listagem']))
        <div class="section-title">Detalhes das Avaliações</div>

        @if($feedbacks->isEmpty())
        <p style="text-align: center; color: #6b7280; padding: 20px 0;">
            Nenhuma avaliação registrada para os filtros selecionados.
        </p>
        @else
        <table class="feedback-table">
            <thead>
                <tr>
                    <th style="width: 12%;">Protocolo</th>
                    <th style="width: 15%;">Escola</th>
                    <th style="width: 12%;">Nota</th>
                    <th style="width: 30%;">Descrição</th>
                    <th style="width: 13%;">Data</th>
                    <th style="width: 18%;">Tipo Manutenção</th>
                </tr>
            </thead>
            <tbody>
                @foreach($feedbacks as $feedback)
                <tr>
                    <td><strong>{{ $feedback->pedido?->numero_protocolo ?? '-' }}</strong></td>
                    <td>{{ $feedback->pedido?->escola?->nome ?? '-' }}</td>
                    <td style="text-align: center;">
                        <span class="badge" style="background: {{ $cores[$feedback->valor] ?? '#6b7280' }}">
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

        <!-- GRÁFICOS LADO A LADO - MOSTRAR EM: geral, graficos -->
        @if(in_array($tipoRelatorio ?? 'geral', ['geral', 'graficos']) && ($graficoMediaMensal || $graficoPorNota))
        <div class="divider" style="margin-top: 20px;"></div>
        <div class="section-title">Análise Visual</div>

        <div class="graficos-container">
            @if($graficoMediaMensal)
            <div class="grafico-wrapper">
                <div class="grafico-titulo">Quantidade Mensal de Avaliações</div>
                <img src="{{ $graficoMediaMensal }}" alt="Gráfico Mensal">
            </div>
            @endif

            @if($graficoPorNota)
            <div class="grafico-wrapper">
                <div class="grafico-titulo">Distribuição por Nota</div>
                <img src="{{ $graficoPorNota }}" alt="Gráfico por Nota">
            </div>
            @endif
        </div>
        @endif

        <!-- MATRIZ DE AVALIAÇÕES - MOSTRAR EM: geral, graficos -->
        @if(in_array($tipoRelatorio ?? 'geral', ['geral', 'graficos']) && !empty($matrizesAgrupadas))
        <div class="divider" style="margin-top: 20px;"></div>
        <div class="section-title">Matriz de Avaliações por Mês</div>

        @foreach($matrizesAgrupadas as $ano => $meses)
        <div class="ano-titulo">{{ $ano }}</div>

        <table class="matriz-table">
            <thead>
                <tr>
                    <th style="width: 10%; text-align: left;">Avaliação</th>
                    @foreach($meses as $mes => $dados)
                    <th style="width: {{ (90 / count($meses)) }}%;">{{ $mes }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @for($nota = 5; $nota >= 1; $nota--)
                <tr>
                    <td class="nota-label nota-{{ $nota }}">
                        Nota {{ $nota }}
                    </td>
                    @foreach($meses as $mes => $dados)
                    <td class="nota-{{ $nota }}">
                        @if($dados[$nota] > 0)
                        <strong>{{ $dados[$nota] }}</strong>
                        @else
                        <span class="matrix-empty">—</span>
                        @endif
                    </td>
                    @endforeach
                </tr>

                @endfor
                <tr class="total-row">
                    <td style="font-weight: bold;">
                        Total
                    </td>

                    @foreach($meses as $mes => $dados)
                    @php
                    $totalMes = array_sum($dados);
                    @endphp

                    <td style="font-weight: bold;">
                        {{ $totalMes > 0 ? $totalMes : '—' }}
                    </td>
                    @endforeach
                </tr>
            </tbody>
        </table>
        @endforeach
        @endif

    </div>

</body>

</html>