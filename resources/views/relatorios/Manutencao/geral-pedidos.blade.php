{{--
    Relatório Analítico de Pedidos de Manutenção
    Variáveis recebidas (todas pré-calculadas no Service):
      $metricas         → object  (total, concluidos, cancelados, abertos, taxa_conclusao,
                                   no_prazo, atrasados, vencidos, taxa_prazo, tempo_medio)
      $porStatus        → Collection of objects (nome, cor, total)
      $porPrioridade    → Collection of objects (prioridade, total)
      $porTipo          → Collection of objects (nome, total, concluidos, taxa)
      $porEscola        → Collection of objects (nome, total, pct_bar)
      $porMes           → Collection of objects (mes, total, pct_bar)
      $metricaFeedback  → object  (total, media, satisfeitos, tx_satisfacao)
      $pedidos          → Collection of stdClass com colunas planas (sem relacionamentos)
      $filtros          → array   (periodo?, escola?, status?, tipo?)
      $usuarioExportacao → User
      $dataExportacao   → Carbon
--}}
@php
$coresPrioridade = [
'Emergencial' => '#a10000',
'Corretivo' => '#973f00',
'Preventivo' => '#013891',
'Indeterminado' => '#2b2b2b',
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

        /* HEADER FIXO */
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

        /* FOOTER FIXO */
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

        /* LAYOUT */
        .content {
            page-break-inside: auto;
        }

        .divider {
            border-top: 1px solid #d1d5db;
            margin: 12px 0;
        }

        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }

        .page-break {
            page-break-before: always;
        }

        .section-title {
            font-weight: bold;
            font-size: 12px;
            color: #111827;
            margin: 14px 0 8px 0;
            padding-bottom: 4px;
            border-bottom: 2px solid #e5e7eb;
            page-break-after: avoid;
        }

        /* CARDS */
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

        /* BADGE */
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
            color: #fff;
        }

        /* TABELA INSIGHTS */
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

        /* BARRA */
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

        /* DUAS COLUNAS */
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

        /* TABELA LISTAGEM */
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

        /* UTILITÁRIOS */
        .text-danger {
            color: #a10000;
            font-weight: bold;
        }

        .text-warn {
            color: #d97706;
            font-weight: bold;
        }

        .text-ok {
            color: #059669;
            font-weight: bold;
        }

        .text-gray {
            color: #6b7280;
        }
    </style>
</head>

<body>

    {{-- HEADER FIXO --}}
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
            <h3>Relatório Analítico de Pedidos de Manutenção</h3>
        </div>
        <div class="divider"></div>
    </div>

    {{-- FOOTER FIXO --}}
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

    {{-- CONTEÚDO --}}
    <div class="content">

        {{-- FILTROS ATIVOS --}}
        @if(!empty($filtros))
        <table style="width:100%; font-size:10px; color:#374151; margin-bottom:10px;">
            <tr>
                @if(!empty($filtros['periodo']))
                <td><strong>Período:</strong> {{ $filtros['periodo'] }}</td>
                @endif
                @if(!empty($filtros['escola']))
                <td><strong>Escola:</strong> {{ $filtros['escola'] }}</td>
                @endif
                @if(!empty($filtros['status']))
                <td><strong>Status:</strong> {{ $filtros['status'] }}</td>
                @endif
                @if(!empty($filtros['tipo']))
                <td><strong>Tipo:</strong> {{ $filtros['tipo'] }}</td>
                @endif
            </tr>
        </table>
        @endif

        {{-- BLOCO 1 — VISÃO GERAL --}}
        <div class="section-title">Visão Geral</div>

        <div class="cards-row">
            <div class="section-title">Pedidos</div>
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
                    <div class="card-label">Em Andamento</div>
                    <div class="card-value card-value-blue">{{ $metricas->abertos }}</div>
                </div>
                <div class="card card-red">
                    <div class="card-label">Cancelados</div>
                    <div class="card-value card-value-red">{{ $metricas->cancelados }}</div>
                </div>
                <div class="card card-yellow">
                    <div class="card-label">Taxa de Conclusão</div>
                    <div class="card-value card-value-yellow">{{ $metricas->taxa_conclusao }}<span class="card-unit">%</span></div>
                </div>
            </div>
        </div>


        <div class="cards-row">
            <div class="section-title">Execução</div>
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
                    <div class="card-label">Tempo Médio (dias)</div>
                    <div class="card-value card-value-purple">{{ $metricas->tempo_medio ?? '—' }}</div>
                </div>
                <div class="card card-blue">
                    <div class="card-label">Pontualidade</div>
                    <div class="card-value card-value-blue">{{ $metricas->taxa_prazo }}<span class="card-unit">%</span></div>
                </div>
            </div>
        </div>

        @if($metricaFeedback->total > 0)

        <div class="cards-row">
            <div class="section-title">Avaliações</div>
            <div class="cards-row">
                <div class="card">
                    <div class="card-label">Avaliações Recebidas</div>
                    <div class="card-value">{{ $metricaFeedback->total }}</div>
                </div>
                <div class="card card-green">
                    <div class="card-label">Nota Média</div>
                    <div class="card-value card-value-green">{{ $metricaFeedback->media }}<span class="card-unit">/5</span></div>
                </div>
                <div class="card card-blue">
                    <div class="card-label">Satisfação (≥ 4)</div>
                    <div class="card-value card-value-blue">{{ $metricaFeedback->tx_satisfacao }}<span class="card-unit">%</span></div>
                </div>
            </div>
        </div>
        @endif

        <div class="divider"></div>

        {{-- BLOCO 2 — STATUS × PRIORIDADE --}}
        <div class="two-col">

            <div class="col-left">
                <div class="section-title">Distribuição por Status</div>
                <table class="insight-table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th style="text-align:center;">Qtd</th>
                            <th style="width:40%;">Distribuição</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($porStatus as $s)
                        @php $pct = $metricas->total > 0 ? round(($s->total / $metricas->total) * 100) : 0; @endphp
                        <tr>
                            <td><span class="badge" style="background:{{ $s->cor }};">{{ $s->nome }}</span></td>
                            <td style="text-align:center; font-weight:bold;">{{ $s->total }}</td>
                            <td>
                                <div class="bar-outer">
                                    <div class="bar-inner" style="width:{{ $pct }}%; background:{{ $s->cor }};"></div>
                                </div>
                                <span style="font-size:8px; color:#6b7280;">{{ $pct }}%</span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="col-right">
                <div class="section-title">Distribuição por Prioridade</div>
                <table class="insight-table">
                    <thead>
                        <tr>
                            <th>Prioridade</th>
                            <th style="text-align:center;">Qtd</th>
                            <th style="width:40%;">Distribuição</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($porPrioridade as $p)
                        @php
                        $pct = $metricas->total > 0 ? round(($p->total / $metricas->total) * 100) : 0;
                        $cor = $coresPrioridade[$p->prioridade ?? 'Indeterminado'] ?? '#2b2b2b';
                        @endphp
                        <tr>
                            <td><span class="badge" style="background:{{ $cor }};">{{ $p->prioridade }}</span></td>
                            <td style="text-align:center; font-weight:bold;">{{ $p->total }}</td>
                            <td>
                                <div class="bar-outer">
                                    <div class="bar-inner" style="width:{{ $pct }}%; background:{{ $cor }};"></div>
                                </div>
                                <span style="font-size:8px; color:#6b7280;">{{ $pct }}%</span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        </div>

        <div class="divider"></div>

        {{-- BLOCO 3 — TIPO × ESCOLA --}}
        <div class="two-col">

            <div class="col-left">
                <div class="section-title">Top Tipos de Manutenção</div>
                <table class="insight-table">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th style="text-align:center;">Total</th>
                            <th style="text-align:center;">Concluídos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($porTipo as $t)
                        <tr>
                            <td>{{ $t->nome }}</td>
                            <td style="text-align:center; font-weight:bold;">{{ $t->total }}</td>
                            <td style="text-align:center;">
                                <span style="color:{{ $t->taxa >= 70 ? '#059669' : ($t->taxa >= 40 ? '#d97706' : '#a10000') }}; font-weight:bold;">
                                    {{ $t->concluidos }}
                                    <span style="font-size:8px; font-weight:normal;">({{ $t->taxa }}%)</span>
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
                            <th style="text-align:center;">Pedidos</th>
                            <th style="width:35%;">Volume</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($porEscola as $e)
                        <tr>
                            <td>{{ $e->nome }}</td>
                            <td style="text-align:center; font-weight:bold;">{{ $e->total }}</td>
                            <td>
                                <div class="bar-outer">
                                    <div class="bar-inner" style="width:{{ $e->pct_bar }}%; background:#3b82f6;"></div>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        </div>

        {{-- BLOCO 4 — EVOLUÇÃO MENSAL --}}
        @if($porMes->count() > 1)
        <div class="divider"></div>
        <div class="section-title">Evolução Mensal de Pedidos</div>
        <table class="insight-table">
            <thead>
                <tr>
                    <th style="width:15%;">Mês/Ano</th>
                    <th style="text-align:center; width:10%;">Pedidos</th>
                    <th>Volume</th>
                </tr>
            </thead>
            <tbody>
                @foreach($porMes as $m)
                <tr>
                    <td>{{ $m->mes }}</td>
                    <td style="text-align:center; font-weight:bold;">{{ $m->total }}</td>
                    <td>
                        <div class="bar-outer">
                            <div class="bar-inner" style="width:{{ $m->pct_bar }}%; background:#6366f1;"></div>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        {{-- BLOCO 5 — LISTAGEM DETALHADA --}}
        <div class="page-break"></div>
        <div class="section-title">Listagem de Pedidos</div>

        @if($pedidos->isEmpty())
        <p style="text-align:center; color:#6b7280; padding:20px 0;">
            Nenhum pedido encontrado para os filtros selecionados.
        </p>
        @else
        <table class="listing-table">
            <thead>
                <tr>
                    <th style="width:10%;">Protocolo</th>
                    <th style="width:20%;">Escola</th>
                    <th style="width:13%;">Tipo</th>
                    <th style="width:11%;">Status</th>
                    <th style="width:9%;">Prioridade</th>
                    <th style="width:8%;">Solicitado</th>
                    <th style="width:8%;">Previsto</th>
                    <th style="width:8%;">Concluído</th>
                    <th style="width:13%;">Empresa</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pedidos as $pedido)
                @php
                $statusCor = '#' . ltrim($pedido->status_cor ?? '6b7280', '#');
                $prioridadeCor = $coresPrioridade[$pedido->nivel_prioridade ?? 'Indeterminado'] ?? '#2b2b2b';

                // Classe de prazo
                $prazoCls = '';
                if (!empty($pedido->data_prevista)) {
                if (!empty($pedido->data_entrega)) {
                $prazoCls = $pedido->data_entrega > $pedido->data_prevista ? 'text-danger' : 'text-ok';
                } elseif ($pedido->data_prevista < now()->toDateString()) {
                    $prazoCls = 'text-danger';
                    }
                    }
                    @endphp
                    <tr>
                        <td><strong>{{ $pedido->numero_protocolo }}</strong></td>
                        <td>{{ $pedido->escola_nome ?? '—' }}</td>
                        <td>{{ $pedido->tipo_manutencao_nome ?? '—' }}</td>
                        <td>
                            <span class="badge" style="background:{{ $statusCor }};">
                                {{ $pedido->status_nome ?? '—' }}
                            </span>
                        </td>
                        <td>
                            <span class="badge" style="background:{{ $prioridadeCor }};">
                                {{ $pedido->nivel_prioridade ?? 'Indet.' }}
                            </span>
                        </td>
                        <td>{{ $pedido->data_solicitacao ? \Carbon\Carbon::parse($pedido->data_solicitacao)->format('d/m/Y') : '—' }}</td>
                        <td class="{{ $prazoCls }}">
                            {{ $pedido->data_prevista ? \Carbon\Carbon::parse($pedido->data_prevista)->format('d/m/Y') : '—' }}
                        </td>
                        <td class="{{ $pedido->data_entrega ? 'text-ok' : 'text-gray' }}">
                            {{ $pedido->data_entrega ? \Carbon\Carbon::parse($pedido->data_entrega)->format('d/m/Y') : '—' }}
                        </td>
                        <td style="font-size:8px;">{{ $pedido->empresa_nome ?? '—' }}</td>
                    </tr>
                    @endforeach
            </tbody>
        </table>

        <p style="text-align:right; font-size:9px; color:#6b7280; margin-top:6px;">
            Total: {{ $metricas->total }} pedido(s)
        </p>
        @endif

    </div>{{-- fim .content --}}

</body>

</html>