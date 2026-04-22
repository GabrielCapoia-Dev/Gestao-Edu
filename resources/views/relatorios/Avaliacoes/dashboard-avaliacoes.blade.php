@extends('relatorios.layouts.base-pdf')

@section('reportTitle', $reportTitle ?? 'Dashboard de Avaliações')
@section('reportSubtitle', $reportSubtitle ?? 'Resumo analítico por escopo e preenchimento')

@section('styles')
    .cards-row {
        display: table;
        width: 100%;
        border-spacing: 6px;
        margin-bottom: 8px;
        page-break-inside: avoid;
    }

    .card {
        display: table-cell;
        border: 1px solid #dbe2ea;
        border-radius: 6px;
        background: #f8fafc;
        padding: 9px 8px;
        text-align: center;
        vertical-align: top;
    }

    .card-label {
        font-size: 9px;
        color: #5b6b7c;
        margin-bottom: 4px;
    }

    .card-value {
        font-size: 20px;
        color: #0f172a;
        font-weight: bold;
    }

    .card-blue {
        background: #eff6ff;
        border-color: #c7dcfd;
    }

    .card-green {
        background: #effaf4;
        border-color: #c6ecd6;
    }

    .table {
        width: 100%;
        border-collapse: collapse;
        font-size: 9.5px;
        margin-top: 6px;
        page-break-inside: auto;
    }

    .table thead {
        display: table-header-group;
    }

    .table th {
        background: #0f4e9b;
        color: #fff;
        font-size: 8.5px;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        border: 1px solid #0b3e7d;
        padding: 6px 5px;
        text-align: left;
    }

    .table td {
        border: 1px solid #dbe2ea;
        padding: 5px 5px;
        vertical-align: top;
    }

    .table tbody tr:nth-child(odd) {
        background: #f8fafc;
    }

    .table tbody tr {
        page-break-inside: avoid;
    }

    .text-right {
        text-align: right;
    }

    .badge {
        display: inline-block;
        padding: 2px 7px;
        border-radius: 999px;
        font-size: 8px;
        font-weight: bold;
        border: 1px solid transparent;
        white-space: nowrap;
    }

    .badge-ok {
        background: #eaf9ef;
        border-color: #c6ecd6;
        color: #0d7a37;
    }

    .badge-warn {
        background: #fff8ea;
        border-color: #f4ddb1;
        color: #9d6808;
    }

    .badge-danger {
        background: #fff1ef;
        border-color: #f8ccc7;
        color: #bb2a1f;
    }

    .badge-muted {
        background: #f3f4f6;
        border-color: #d8dce2;
        color: #5f6774;
    }

    .bar-outer {
        background: #dbe6f2;
        border-radius: 4px;
        height: 8px;
        width: 100%;
        overflow: hidden;
    }

    .bar-inner {
        background: #0f4e9b;
        height: 8px;
        border-radius: 4px;
    }
@endsection

@section('content')
    <div class="section-title">Indicadores</div>

    <div class="cards-row">
        <div class="card">
            <div class="card-label">Avaliações no escopo</div>
            <div class="card-value">{{ $cards['total_avaliacoes'] ?? 0 }}</div>
        </div>
        <div class="card">
            <div class="card-label">Avaliações ativas</div>
            <div class="card-value">{{ $cards['total_avaliacoes_ativas'] ?? 0 }}</div>
        </div>
        <div class="card">
            <div class="card-label">Respostas registradas</div>
            <div class="card-value">{{ $cards['total_respostas'] ?? 0 }}</div>
        </div>
        <div class="card card-blue">
            <div class="card-label">% escolas preenchidas</div>
            <div class="card-value">{{ number_format((float) ($cards['percentual_escolas_preenchidas'] ?? 0), 1, ',', '.') }}%</div>
        </div>
        <div class="card card-green">
            <div class="card-label">% turmas preenchidas</div>
            <div class="card-value">{{ number_format((float) ($cards['percentual_turmas_preenchidas'] ?? 0), 1, ',', '.') }}%</div>
        </div>
    </div>

    <div class="cards-row">
        <div class="card">
            <div class="card-label">Escolas no escopo</div>
            <div class="card-value">{{ $cards['total_escolas'] ?? 0 }}</div>
        </div>
        <div class="card card-green">
            <div class="card-label">Escolas preenchidas</div>
            <div class="card-value">{{ $cards['escolas_preenchidas'] ?? 0 }}</div>
        </div>
        <div class="card card-blue">
            <div class="card-label">Escolas sem preenchimento</div>
            <div class="card-value">{{ $cards['escolas_nao_preenchidas'] ?? 0 }}</div>
        </div>
        <div class="card">
            <div class="card-label">Turmas esperadas</div>
            <div class="card-value">{{ $cards['turmas_esperadas'] ?? 0 }}</div>
        </div>
        <div class="card">
            <div class="card-label">Turmas respondidas</div>
            <div class="card-value">{{ $cards['turmas_respondidas'] ?? 0 }}</div>
        </div>
    </div>

    <div class="section-title">Progresso por Escola</div>

    <table class="table">
        <thead>
            <tr>
                <th>Escola</th>
                <th class="text-right">Avaliações</th>
                <th class="text-right">Avaliações resp.</th>
                <th class="text-right">Turmas esperadas</th>
                <th class="text-right">Turmas resp.</th>
                <th class="text-right">% progresso</th>
                <th class="text-right">Respostas</th>
            </tr>
        </thead>
        <tbody>
            @forelse($escolas as $item)
                <tr>
                    <td>{{ $item['nome'] }}</td>
                    <td class="text-right">{{ $item['avaliacoes_no_escopo'] }}</td>
                    <td class="text-right">{{ $item['avaliacoes_respondidas'] }}</td>
                    <td class="text-right">{{ $item['turmas_esperadas'] }}</td>
                    <td class="text-right">{{ $item['turmas_respondidas'] }}</td>
                    <td class="text-right">
                        <span class="badge {{ $item['esta_preenchida'] ? 'badge-ok' : 'badge-muted' }}">
                            {{ number_format((float) $item['percentual_turmas'], 1, ',', '.') }}%
                        </span>
                    </td>
                    <td class="text-right">{{ $item['respostas_total'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">Nenhuma escola encontrada para os filtros aplicados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Resumo por Avaliação</div>

    <table class="table">
        <thead>
            <tr>
                <th>Avaliação</th>
                <th>Tipo</th>
                <th>Período</th>
                <th>Status</th>
                <th class="text-right">Escolas</th>
                <th class="text-right">Escolas resp.</th>
                <th class="text-right">% escolas</th>
                <th class="text-right">Turmas</th>
                <th class="text-right">Turmas resp.</th>
                <th class="text-right">Respostas</th>
            </tr>
        </thead>
        <tbody>
            @forelse($avaliacoes as $item)
                @php
                    $statusClass = match ($item['status']) {
                        'ativa' => 'badge-ok',
                        'encerrada' => 'badge-warn',
                        'cancelada' => 'badge-danger',
                        default => 'badge-muted',
                    };
                @endphp
                <tr>
                    <td>
                        <strong>{{ $item['nome'] }}</strong><br>
                        <span style="font-size: 8px; color: #6b7280;">
                            {{ $item['data_inicio'] }} até {{ $item['data_fim'] }}
                        </span>
                    </td>
                    <td>{{ $item['tipo'] }}</td>
                    <td>{{ $item['periodo'] }}</td>
                    <td><span class="badge {{ $statusClass }}">{{ $item['status_label'] }}</span></td>
                    <td class="text-right">{{ $item['escolas_esperadas'] }}</td>
                    <td class="text-right">{{ $item['escolas_respondidas'] }}</td>
                    <td class="text-right">{{ number_format((float) $item['percentual_escolas'], 1, ',', '.') }}%</td>
                    <td class="text-right">{{ $item['turmas_esperadas'] }}</td>
                    <td class="text-right">{{ $item['turmas_respondidas'] }}</td>
                    <td class="text-right">{{ $item['respostas_total'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10">Nenhuma avaliação encontrada para os filtros aplicados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">{{ $alternativas['titulo'] ?? 'Distribuição de Alternativas' }}</div>
    <p style="margin: 0 0 8px 0; color: #6b7280;">
        {{ $alternativas['subtitulo'] ?? '' }}
        @if(($alternativas['total_respostas'] ?? 0) > 0)
            ({{ $alternativas['total_respostas'] }} respostas)
        @endif
    </p>

    <table class="table">
        <thead>
            <tr>
                <th>Alternativa</th>
                <th class="text-right">Respostas</th>
                <th class="text-right">%</th>
                <th>Distribuição</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($alternativas['itens'] ?? []) as $item)
                <tr>
                    <td>{{ $item['nome'] }}</td>
                    <td class="text-right">{{ $item['total'] }}</td>
                    <td class="text-right">{{ number_format((float) $item['percentual'], 1, ',', '.') }}%</td>
                    <td>
                        <div class="bar-outer">
                            <div class="bar-inner" style="width: {{ $item['percentual_barra'] }}%;"></div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">Nenhuma alternativa com respostas no recorte atual.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
