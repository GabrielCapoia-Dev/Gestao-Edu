<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Relatório - Fichas de Alunos</title>

    <style>
        * {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            box-sizing: border-box;
        }

        body {
            margin: 20px;
        }

        h1 {
            font-size: 18px;
            margin-bottom: 4px;
        }

        h2 {
            font-size: 16px;
            margin: 0 0 10px 0;
        }

        h3 {
            font-size: 13px;
            margin: 12px 0 6px 0;
        }

        .subtitulo-geral {
            font-size: 11px;
            margin-bottom: 15px;
            color: #555;
        }

        .ficha {
            width: 100%;
        }

        .linha-topo {
            width: 100%;
            margin-bottom: 10px;
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
    </style>
</head>

<body>
    <h1>Relatório - Fichas de Alunos</h1>
    <div class="subtitulo-geral">
        Gerado em: {{ now()->format('d/m/Y H:i') }}<br>
        Total de alunos:
        {{ is_array($alunos) ? count($alunos) : $alunos->count() }}
    </div>


    @foreach ($alunos as $aluno)
    @php
    $dataNascimento = $aluno->data_nascimento
    ? \Carbon\Carbon::parse($aluno->data_nascimento)
    : null;

    $idade = $dataNascimento ? $dataNascimento->age : null;

    $escola = data_get($aluno, 'turma.escola.nome');
    $serie = data_get($aluno, 'turma.serie.nome');
    $turma = data_get($aluno, 'turma.turma');
    $profNome = data_get($aluno, 'professor.nome');

    $temLaudos = $aluno->laudos?->count() > 0;
    $frequentaSrm = (bool) ($aluno->frequenta_srm ?? false);
    $encaminhadoSme = (bool) ($aluno->encaminhado_para_sme ?? false);
    @endphp

    <div class="ficha">
        {{-- Cabeçalho da ficha --}}
        <table class="linha-topo">
            <tr>
                <td>
                    <div class="titulo-escola">
                        {{ $escola ?? 'Escola não informada' }}
                    </div>
                    <div class="info-escola">
                        Série/Turma:
                        @if ($serie || $turma)
                        {{ $serie }}{{ $serie && $turma ? ' - ' : '' }}{{ $turma }}
                        @else
                        —
                        @endif
                        <br>
                        Professor(a): {{ $profNome ?? '—' }}
                    </div>
                </td>
                <td style="text-align: right;">
                    <span class="small">
                        CGM: {{ $aluno->cgm }}<br>
                        ID interno: {{ $aluno->id }}
                    </span>
                </td>
            </tr>
        </table>

        <h2>{{ $aluno->nome }}</h2>

        {{-- Dados principais --}}
        <div class="bloco">
            <table class="dados">
                <tr>
                    <th>Nome</th>
                    <td>{{ $aluno->nome }}</td>
                </tr>
                <tr>
                    <th>CGM</th>
                    <td>{{ $aluno->cgm }}</td>
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
                    <td>{{ $aluno->sexo }}</td>
                </tr>
                <tr>
                    <th>Escola</th>
                    <td>{{ $escola ?? '—' }}</td>
                </tr>
                <tr>
                    <th>Série / Turma</th>
                    <td>
                        @if ($serie || $turma)
                        {{ $serie }}{{ $serie && $turma ? ' - ' : '' }}{{ $turma }}
                        @else
                        —
                        @endif
                    </td>
                </tr>
                <tr>
                    <th>Professor(a)</th>
                    <td>{{ $profNome ?? '—' }}</td>
                </tr>
                <tr>
                    <th>Indicadores</th>
                    <td>
                        <span class="tag {{ $frequentaSrm ? 'tag-positivo' : 'tag-negativo' }}">
                            SRM: {{ $frequentaSrm ? 'Sim' : 'Não' }}
                        </span>

                        <span class="tag {{ $encaminhadoSme ? 'tag-positivo' : 'tag-negativo' }}">
                            Encaminhado SME: {{ $encaminhadoSme ? 'Sim' : 'Não' }}
                        </span>

                        <span class="tag {{ $temLaudos ? 'tag-positivo' : 'tag-negativo' }}">
                            Laudos: {{ $temLaudos ? $aluno->laudos->count() : 0 }}
                        </span>
                    </td>
                </tr>
            </table>
        </div>

        {{-- Laudos --}}
        <div class="bloco">
            <h3>Laudos cadastrados</h3>

            @if (! $temLaudos)
            <p class="small">Sem laudos cadastrados para este aluno.</p>
            @else
            <ul>
                @foreach ($aluno->laudos as $laudo)
                <li>
                    {{ $laudo->nome ?? 'Laudo sem nome' }}
                    @if (! empty($laudo->pivot?->created_at))
                    <span class="small">
                        (cadastrado em {{ \Carbon\Carbon::parse($laudo->pivot->created_at)->format('d/m/Y') }})
                    </span>
                    @endif
                </li>
                @endforeach
            </ul>
            @endif
        </div>

        {{-- Rodapé da ficha --}}
        <div class="small" style="margin-top: 12px;">
            Relatório interno para acompanhamento pedagógico e inclusão.
        </div>
    </div>

    @if (! $loop->last)
    <div class="page-break"></div>
    @endif
    @endforeach
</body>

</html>