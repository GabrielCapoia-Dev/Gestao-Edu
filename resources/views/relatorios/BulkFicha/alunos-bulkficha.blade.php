@extends('relatorios.layouts.base-pdf')

@section('reportTitle', $reportTitle ?? 'Relatorio - Fichas de Alunos')
@section('reportSubtitle', $reportSubtitle ?? 'Fichas detalhadas em lote')

@section('styles')
    .summary-box {
        margin-bottom: 12px;
        padding: 8px 10px;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        background: #f9fafb;
        font-size: 10px;
    }

    .ficha {
        width: 100%;
    }

    .linha-topo {
        width: 100%;
        margin-bottom: 10px;
        border-collapse: collapse;
    }

    .linha-topo td {
        vertical-align: top;
    }

    .titulo-escola {
        font-size: 13px;
        font-weight: bold;
    }

    .info-escola {
        font-size: 10px;
    }

    .bloco {
        margin-bottom: 10px;
    }

    table.dados {
        width: 100%;
        border-collapse: collapse;
    }

    table.dados th,
    table.dados td {
        border: 1px solid #444;
        padding: 4px 6px;
        vertical-align: top;
    }

    table.dados th {
        background-color: #f0f0f0;
        width: 25%;
        text-align: left;
        font-weight: bold;
    }

    ul {
        margin: 0;
        padding-left: 16px;
    }

    li {
        margin-bottom: 2px;
    }

    .page-break {
        page-break-after: always;
    }

    .tag {
        display: inline-block;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 9px;
        margin-right: 4px;
    }

    .tag-positivo {
        background-color: #d4edda;
        border: 1px solid #c3e6cb;
    }

    .tag-negativo {
        background-color: #f8d7da;
        border: 1px solid #f5c6cb;
    }

    .small {
        font-size: 9px;
    }
@endsection

@section('content')
    <div class="summary-box">
        <strong>Total de alunos:</strong>
        {{ is_array($alunos) ? count($alunos) : $alunos->count() }}
    </div>

    @foreach ($alunos as $aluno)
        @php
            $dataNascimento = data_get($aluno, 'data_nascimento')
                ? \Carbon\Carbon::parse(data_get($aluno, 'data_nascimento'))
                : null;

            $idade = $dataNascimento ? $dataNascimento->age : null;
            $escola = data_get($aluno, 'turma.escola.nome');
            $serie = data_get($aluno, 'turma.serie.nome');
            $turma = data_get($aluno, 'turma.turma');
            $profNome = data_get($aluno, 'professor.nome');
            $laudos = collect(data_get($aluno, 'laudos', []));
            $temLaudos = $laudos->count() > 0;
            $frequentaSrm = (bool) data_get($aluno, 'frequenta_srm', false);
            $encaminhadoSme = (bool) data_get($aluno, 'encaminhado_para_sme', false);
        @endphp

        <div class="ficha">
            <table class="linha-topo">
                <tr>
                    <td>
                        <div class="titulo-escola">
                            {{ $escola ?? 'Escola nao informada' }}
                        </div>
                        <div class="info-escola">
                            Serie/Turma:
                            @if ($serie || $turma)
                                {{ $serie }}{{ $serie && $turma ? ' - ' : '' }}{{ $turma }}
                            @else
                                -
                            @endif
                            <br>
                            Professor(a): {{ $profNome ?? '-' }}
                        </div>
                    </td>
                    <td style="text-align: right;">
                        <span class="small">
                            CGM: {{ data_get($aluno, 'cgm') }}<br>
                            ID interno: {{ data_get($aluno, 'id') }}
                        </span>
                    </td>
                </tr>
            </table>

            <div class="section-title">{{ data_get($aluno, 'nome') }}</div>

            <div class="bloco">
                <table class="dados">
                    <tr>
                        <th>Nome</th>
                        <td>{{ data_get($aluno, 'nome') }}</td>
                    </tr>
                    <tr>
                        <th>CGM</th>
                        <td>{{ data_get($aluno, 'cgm') }}</td>
                    </tr>
                    <tr>
                        <th>Data de nascimento</th>
                        <td>
                            {{ $dataNascimento?->format('d/m/Y') }}
                            @if($idade !== null)
                                ({{ $idade }} anos)
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>Sexo</th>
                        <td>{{ data_get($aluno, 'sexo') }}</td>
                    </tr>
                    <tr>
                        <th>Escola</th>
                        <td>{{ $escola ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th>Serie / Turma</th>
                        <td>
                            @if ($serie || $turma)
                                {{ $serie }}{{ $serie && $turma ? ' - ' : '' }}{{ $turma }}
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>Professor(a)</th>
                        <td>{{ $profNome ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th>Indicadores</th>
                        <td>
                            <span class="tag {{ $frequentaSrm ? 'tag-positivo' : 'tag-negativo' }}">
                                SRM: {{ $frequentaSrm ? 'Sim' : 'Nao' }}
                            </span>

                            <span class="tag {{ $encaminhadoSme ? 'tag-positivo' : 'tag-negativo' }}">
                                Encaminhado SME: {{ $encaminhadoSme ? 'Sim' : 'Nao' }}
                            </span>

                            <span class="tag {{ $temLaudos ? 'tag-positivo' : 'tag-negativo' }}">
                                Laudos: {{ $temLaudos ? $laudos->count() : 0 }}
                            </span>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="bloco">
                <div class="section-title">Laudos cadastrados</div>

                @if (! $temLaudos)
                    <p class="small">Sem laudos cadastrados para este aluno.</p>
                @else
                    <ul>
                        @foreach ($laudos as $laudo)
                            <li>
                                {{ data_get($laudo, 'nome', 'Laudo sem nome') }}
                                @if (! empty(data_get($laudo, 'pivot.created_at')))
                                    <span class="small">
                                        (cadastrado em {{ \Carbon\Carbon::parse(data_get($laudo, 'pivot.created_at'))->format('d/m/Y') }})
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="small" style="margin-top: 12px;">
                Relatorio interno para acompanhamento pedagogico e inclusao.
            </div>
        </div>

        @if (! $loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach
@endsection
