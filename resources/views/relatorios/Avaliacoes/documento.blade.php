<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Documento de Avaliacao</title>
    <style>
        @page {
            size: A4;
            margin: 16mm 10mm 18mm 10mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #111827;
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 11px;
            line-height: 1.35;
        }

        .student-document {
            width: 100%;
        }

        .student-break {
            page-break-before: always;
        }

        .duplex-blank {
            page-break-before: always;
            height: 262mm;
        }

        .logo-line {
            min-height: 92px;
            margin-bottom: 4px;
        }

        .logo-line img {
            display: block;
            width: 100%;
            max-height: 92px;
        }

        .school-name {
            margin: 2px 0 4px;
            font-size: 14px;
            font-weight: 700;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        .header-table td {
            width: 50%;
            padding: 1px 0;
            vertical-align: top;
        }

        .header-table td.right {
            text-align: right;
        }

        .label {
            font-weight: 700;
        }

        .divider {
            border-top: 1px solid #b8bfcc;
            margin: 7px 0 8px;
        }

        .title {
            margin: 4px 0 8px;
            text-align: center;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .legend {
            margin-bottom: 10px;
        }

        .legend-table,
        .component-table,
        .complementary-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .legend-table th,
        .legend-table td,
        .component-table th,
        .component-table td,
        .complementary-table th,
        .complementary-table td {
            border: 1px solid #9ca3af;
            padding: 4px 6px;
            vertical-align: top;
            word-wrap: break-word;
        }

        .legend-table th,
        .component-table th,
        .complementary-table th {
            background: #f3f4f6;
            text-align: center;
            font-weight: 700;
        }

        .legend-mark {
            background: #b7d6ea;
            display: inline;
            padding: 0 1px;
            line-height: 1.25;
        }

        .component {
            margin-top: 12px;
            page-break-inside: auto;
        }

        .component h3 {
            margin: 0;
            text-align: center;
            font-size: 13px;
        }

        .teacher {
            margin: 2px 0 6px;
            text-align: center;
            font-size: 10px;
        }

        .component-table tr {
            page-break-inside: avoid;
        }

        .component-table .question {
            width: 58%;
        }

        .component-table .result {
            width: 14%;
            text-align: center;
        }

        .component-table .observation {
            width: 28%;
        }

        .complementary {
            margin-top: 10px;
            page-break-inside: avoid;
        }

        .signature {
            margin-top: 24px;
            text-align: center;
            page-break-inside: avoid;
        }

        .signature-line {
            display: inline-block;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>
@foreach ($documentos as $documento)
    <section class="student-document">
        <header>
            <div class="logo-line">
                @if ($documento['logo'] ?? '')
                    <img src="{{ $documento['logo'] }}" alt="Timbre da Prefeitura">
                @endif
            </div>

            <div class="school-name">{{ $documento['escola'] ?? '' }}</div>

            <table class="header-table">
                <tr>
                    <td><span class="label">ESTUDANTE:</span> {{ $documento['estudante'] ?? '' }}</td>
                    <td class="right"><span class="label">TURMA:</span> {{ $documento['turma'] ?? '' }}</td>
                </tr>
                <tr>
                    <td><span class="label">CGM:</span> {{ $documento['cgm'] ?? '' }}</td>
                    <td class="right"><span class="label">ANO LETIVO:</span> {{ $documento['ano_letivo'] ?? '' }}</td>
                </tr>
                <tr>
                    <td><span class="label">CURSO:</span> {{ $documento['curso'] ?? '' }}</td>
                    <td></td>
                </tr>
                <tr>
                    <td colspan="2"><span class="label">DIRETOR(A):</span> {{ $documento['diretor'] ?? '' }}</td>
                </tr>
                <tr>
                    <td colspan="2"><span class="label">COORDENACAO PEDAGOGICA:</span> {{ $documento['coordenacao'] ?? '' }}</td>
                </tr>
            </table>
        </header>

        <div class="divider"></div>

        <h1 class="title">{{ $documento['avaliacao_titulo'] ?? '' }}</h1>

        @if (! empty($documento['legenda']))
            <section class="legend">
                <table class="legend-table">
                    <thead>
                        <tr>
                            @foreach ($documento['legenda'] as $alternativa)
                                <th><span class="legend-mark">{{ $alternativa['nome'] }}</span></th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            @foreach ($documento['legenda'] as $alternativa)
                                <td><span class="legend-mark">{{ $alternativa['descricao'] }}</span></td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </section>
        @endif

        @foreach (($documento['componentes'] ?? []) as $componente)
            <section class="component">
                <h3>{{ $componente['nome'] ?? '' }}</h3>
                <p class="teacher">{{ $componente['professor'] ?? '' }}</p>

                <table class="component-table">
                    <thead>
                        <tr>
                            <th class="question">Pautas Avaliativas</th>
                            <th class="result">Resultado</th>
                            <th class="observation">Observacoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (($componente['pautas'] ?? []) as $pauta)
                            <tr>
                                <td class="question">{{ $pauta['ordem'] }}. {{ $pauta['texto'] }}</td>
                                <td class="result">{{ $pauta['resultado'] }}</td>
                                <td class="observation">{{ $pauta['observacao'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endforeach

        @if (! empty($documento['informacoes_complementares']))
            <section class="complementary">
                <table class="complementary-table">
                    <thead>
                        <tr>
                            <th>Informacoes Complementares</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>{{ $documento['informacoes_complementares'] }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>
        @endif

        <section class="signature">
            <div class="signature-line">______________________________________________________</div>
            <div><span class="label">DIRETOR(A):</span> {{ $documento['diretor'] ?? '' }}</div>
        </section>
    </section>

    @if (! $loop->last)
        @if ($documento['precisa_pagina_em_branco'] ?? false)
            <div class="duplex-blank"></div>
        @else
            <div class="student-break"></div>
        @endif
    @endif
@endforeach
</body>
</html>
