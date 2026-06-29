@extends('relatorios.layouts.base-pdf')

@section('reportTitle', $reportTitle ?? 'Acompanhamento de Pareceres')
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

    .card-detail {
        font-size: 8.5px;
        color: #5b6b7c;
        margin-top: 4px;
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
        <div class="card card-blue">
            <div class="card-label">% preenchimento geral</div>
            <div class="card-value">{{ number_format((float) ($cards['percentual_preenchimento_geral'] ?? 0), 1, ',', '.') }}%</div>
            <div class="card-detail">{{ $cards['preenchimentos_respondidos'] ?? 0 }} de {{ $cards['preenchimentos_esperados'] ?? 0 }} preenchimentos</div>
        </div>
        <div class="card">
            <div class="card-label">Turmas incompletas</div>
            <div class="card-value">{{ $cards['turmas_incompletas'] ?? 0 }}</div>
            <div class="card-detail">{{ $cards['turmas_preenchidas'] ?? 0 }} de {{ $cards['turmas_esperadas'] ?? 0 }} turmas concluidas</div>
        </div>
        <div class="card">
            <div class="card-label">Alunos pendentes manha</div>
            <div class="card-value">{{ $cards['turno_manha_alunos_pendentes'] ?? 0 }}</div>
            <div class="card-detail">{{ $cards['turno_manha_alunos_total'] ?? 0 }} alunos no turno</div>
        </div>
        <div class="card">
            <div class="card-label">Alunos pendentes tarde</div>
            <div class="card-value">{{ $cards['turno_tarde_alunos_pendentes'] ?? 0 }}</div>
            <div class="card-detail">{{ $cards['turno_tarde_alunos_total'] ?? 0 }} alunos no turno</div>
        </div>
    </div>

    <div class="section-title">Pendencia por Escola</div>

    <table class="table">
        <thead>
            <tr>
                <th>Escola</th>
                <th class="text-right">Turmas incompletas</th>
                <th class="text-right">Turmas concluidas</th>
                <th class="text-right">Turmas no escopo</th>
                <th class="text-right">% incompletas</th>
                <th class="text-right">% preenchimento</th>
            </tr>
        </thead>
        <tbody>
            @forelse($escolas as $item)
                <tr>
                    <td>{{ $item['nome'] }}</td>
                    <td class="text-right">{{ $item['turmas_incompletas'] }}</td>
                    <td class="text-right">{{ $item['turmas_preenchidas'] }}</td>
                    <td class="text-right">{{ $item['turmas_esperadas'] }}</td>
                    <td class="text-right">{{ number_format((float) $item['percentual_turmas_incompletas'], 1, ',', '.') }}%</td>
                    <td class="text-right">{{ number_format((float) $item['percentual_preenchimento'], 1, ',', '.') }}%</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">Nenhuma escola encontrada para os filtros aplicados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Preenchimento por Componente</div>

    <table class="table">
        <thead>
            <tr>
                <th>Componente</th>
                <th class="text-right">Esperados</th>
                <th class="text-right">Respondidos</th>
                <th class="text-right">Pendentes</th>
                <th class="text-right">% preenchimento</th>
            </tr>
        </thead>
        <tbody>
            @forelse($componentes ?? [] as $item)
                <tr>
                    <td>{{ $item['nome'] }}</td>
                    <td class="text-right">{{ $item['preenchimentos_esperados'] }}</td>
                    <td class="text-right">{{ $item['preenchimentos_respondidos'] }}</td>
                    <td class="text-right">{{ $item['preenchimentos_pendentes'] }}</td>
                    <td class="text-right">{{ number_format((float) $item['percentual_preenchimento'], 1, ',', '.') }}%</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">Nenhum componente encontrado para os filtros aplicados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Preenchimento por Serie</div>

    <table class="table">
        <thead>
            <tr>
                <th>Serie</th>
                <th class="text-right">Esperados</th>
                <th class="text-right">Respondidos</th>
                <th class="text-right">Pendentes</th>
                <th class="text-right">% preenchimento</th>
            </tr>
        </thead>
        <tbody>
            @forelse($series ?? [] as $item)
                <tr>
                    <td>{{ $item['nome'] }}</td>
                    <td class="text-right">{{ $item['preenchimentos_esperados'] }}</td>
                    <td class="text-right">{{ $item['preenchimentos_respondidos'] }}</td>
                    <td class="text-right">{{ $item['preenchimentos_pendentes'] }}</td>
                    <td class="text-right">{{ number_format((float) $item['percentual_preenchimento'], 1, ',', '.') }}%</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">Nenhuma serie encontrada para os filtros aplicados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Acompanhamento de Pareceres</div>

    <table class="table">
        <thead>
            <tr>
                <th>Escola</th>
                <th>Serie</th>
                <th>Turma</th>
                <th>Componente</th>
                <th>Professor</th>
                <th class="text-right">Esperados</th>
                <th class="text-right">Respondidos</th>
                <th class="text-right">Pendentes</th>
                <th class="text-right">% preenchimento</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($acompanhamentoTurmas ?? [] as $item)
                <tr>
                    <td>{{ $item['escola_nome'] }}</td>
                    <td>{{ $item['serie_nome'] }}</td>
                    <td>{{ $item['turma_nome'] }}<br><span style="font-size: 8px; color: #6b7280;">{{ ucfirst((string) $item['turno']) }}</span></td>
                    <td>{{ $item['componente_nome'] }}</td>
                    <td>{{ $item['professor_nome'] }}</td>
                    <td class="text-right">{{ $item['preenchimentos_esperados'] }}</td>
                    <td class="text-right">{{ $item['preenchimentos_respondidos'] }}</td>
                    <td class="text-right">{{ $item['preenchimentos_pendentes'] }}</td>
                    <td class="text-right">{{ number_format((float) $item['percentual_preenchimento'], 1, ',', '.') }}%</td>
                    <td>{{ $item['status_label'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10">Nenhuma turma encontrada para os filtros aplicados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
