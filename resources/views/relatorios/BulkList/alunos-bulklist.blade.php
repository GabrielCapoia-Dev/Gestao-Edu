<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório - Lista de Alunos</title>

    <style>
        * {
            box-sizing: border-box;
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
        }

        body {
            margin: 10px 20px;
        }

        h1 {
            font-size: 16px;
            margin-bottom: 4px;
        }

        .subtitulo {
            font-size: 10px;
            margin-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 4px 6px;
            vertical-align: top;
            word-wrap: break-word;
        }

        th {
            background: #f0f0f0;
            text-align: left;
        }

        .text-center {
            text-align: center;
        }

        /* Ajuda o DOMPDF a quebrar a página sem cortar linhas no meio */
        table {
            page-break-inside: auto;
        }

        tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }
    </style>
</head>
<body>
    <h1>Relatório - Lista de Alunos</h1>

    <div class="subtitulo">
        Gerado em: {{ now()->format('d/m/Y H:i') }}<br>
        Total de alunos: {{ is_countable($alunos) ? count($alunos) : 0 }}
    </div>

    <table>
        <thead>
            <tr>
                <th>CGM</th>
                <th>NOME DO ALUNO</th>
                <th>SEXO</th>
                <th>ESCOLA</th>
                <th>SÉRIE/TURMA</th>
                <th>TURNO</th>
                <th>LAUDOS</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($alunos as $aluno)
                @php
                    $cgm    = data_get($aluno, 'cgm');
                    $nome   = data_get($aluno, 'nome');
                    $sexo   = data_get($aluno, 'sexo');

                    $escola = data_get($aluno, 'turma.escola.nome');
                    $serie  = data_get($aluno, 'turma.serie.nome');
                    $turma  = data_get($aluno, 'turma.turma');
                    $turno  = data_get($aluno, 'turma.turno');

                    // laudos vem como array no JSON; pluck('nome') e junta
                    $laudosArray = data_get($aluno, 'laudos', []);
                    $laudosNomes = collect($laudosArray)
                        ->pluck('nome')
                        ->filter()            // tira nulos/vazios
                        ->implode(', ');
                @endphp

                <tr>
                    <td>{{ $cgm }}</td>
                    <td>{{ $nome }}</td>
                    <td class="text-center">{{ $sexo }}</td>
                    <td>{{ $escola }}</td>
                    <td>
                        {{ trim(($serie ? $serie . ' - ' : '') . ($turma ?? '')) }}
                    </td>
                    <td class="text-center">{{ $turno }}</td>
                    <td>{{ $laudosNomes ?: '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
