@extends('relatorios.layouts.base-pdf')

@section('reportTitle', $reportTitle ?? 'Relatorio - Lista de Alunos')
@section('reportSubtitle', $reportSubtitle ?? 'Listagem consolidada de alunos')

@section('styles')
    .summary-box {
        margin-bottom: 12px;
        padding: 8px 10px;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        background: #f9fafb;
        font-size: 10px;
    }

    table.listagem {
        width: 100%;
        border-collapse: collapse;
        page-break-inside: auto;
    }

    table.listagem th,
    table.listagem td {
        border: 1px solid #d1d5db;
        padding: 5px 6px;
        vertical-align: top;
        word-wrap: break-word;
        font-size: 10px;
    }

    table.listagem thead {
        display: table-header-group;
    }

    table.listagem th {
        background: #f3f4f6;
        text-align: left;
    }

    table.listagem tr {
        page-break-inside: avoid;
        page-break-after: auto;
    }

    .text-center {
        text-align: center;
    }
@endsection

@section('content')
    @php
        $totalAlunos = is_countable($alunos) ? count($alunos) : 0;
    @endphp

    <div class="summary-box">
        <strong>Total de alunos:</strong> {{ $totalAlunos }}
    </div>

    <table class="listagem">
        <thead>
            <tr>
                <th>CGM</th>
                <th>Nome do Aluno</th>
                <th>Sexo</th>
                <th>Escola</th>
                <th>Serie/Turma</th>
                <th>Turno</th>
                <th>Laudos</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($alunos as $aluno)
                @php
                    $cgm = data_get($aluno, 'cgm');
                    $nome = data_get($aluno, 'nome');
                    $sexo = data_get($aluno, 'sexo');
                    $escola = data_get($aluno, 'turma.escola.nome');
                    $serie = data_get($aluno, 'turma.serie.nome');
                    $turma = data_get($aluno, 'turma.turma');
                    $turno = data_get($aluno, 'turma.turno');
                    $laudosArray = data_get($aluno, 'laudos', []);
                    $laudosNomes = collect($laudosArray)
                        ->pluck('nome')
                        ->filter()
                        ->implode(', ');
                @endphp

                <tr>
                    <td>{{ $cgm }}</td>
                    <td>{{ $nome }}</td>
                    <td class="text-center">{{ $sexo }}</td>
                    <td>{{ $escola }}</td>
                    <td>{{ trim(($serie ? $serie . ' - ' : '') . ($turma ?? '')) }}</td>
                    <td class="text-center">{{ $turno }}</td>
                    <td>{{ $laudosNomes ?: '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
