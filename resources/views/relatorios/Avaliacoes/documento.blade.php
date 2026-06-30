<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Documento de Avaliação</title>
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
        .component-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .legend-table th,
        .legend-table td,
        .component-table th,
        .component-table td {
            border: 1px solid #9ca3af;
            padding: 4px 6px;
            vertical-align: top;
            word-wrap: break-word;
        }

        .legend-table th,
        .component-table th {
            background: #f3f4f6;
            text-align: center;
            font-weight: 700;
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

        .component-table .complementary-title {
            background: #fff;
            text-align: center;
            font-weight: 700;
        }

        .component-table .complementary-text {
            min-height: 24px;
        }

        .document-footer {
            margin-top: 28px;
            page-break-inside: avoid;
        }

        .document-footer-meta {
            min-height: 88px;
            text-align: right;
        }

        .document-footer-meta p {
            margin: 0 0 14px;
            font-size: 12px;
        }

        .signature {
            margin-top: 26px;
            min-height: 120px;
            text-align: center;
            page-break-inside: avoid;
        }

        .signature-line {
            display: inline-block;
            min-width: 360px;
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
                    <td class="right"><span class="label">VÍNCULO:</span> {{ $documento['vinculo'] ?? '' }}</td>
                </tr>
                <tr>
                    <td><span class="label">CURSO:</span> {{ $documento['curso'] ?? '' }}</td>
                    <td class="right"><span class="label">ANO LETIVO:</span> {{ $documento['ano_letivo'] ?? '' }}</td>
                </tr>
                <tr>
                    <td></td>
                    <td class="right"><span class="label">TURNO:</span> {{ $documento['turno'] ?? '' }}</td>
                </tr>
                @if (! empty($documento['documento_tipo']))
                <tr>
                    <td></td>
                    <td class="right"><span class="label">{{ $documento['documento_tipo'] }}</span></td>
                </tr>
                @endif
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
                                <th>{{ $alternativa['nome'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            @foreach ($documento['legenda'] as $alternativa)
                                <td>{{ $alternativa['descricao'] }}</td>
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
                            <th class="observation">Observações</th>
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
                        @if (! empty($componente['mostrar_informacoes_complementares']))
                            <tr>
                                <th class="complementary-title" colspan="3">Informações Complementares</th>
                            </tr>
                            <tr>
                                <td class="complementary-text" colspan="3">{{ $componente['informacoes_complementares'] ?? '' }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </section>
        @endforeach

        <section class="document-footer">
            <div class="document-footer-meta">
                @if (! empty($documento['periodo_avaliacao']))
                    <p><span class="label">Período:</span> {{ $documento['periodo_avaliacao'] }}</p>
                @endif

                <p>Umuarama {{ $documento['data_impressao'] ?? '' }}</p>
            </div>

            <div class="signature">
                <div class="signature-line">______________________________________________________</div>
                <div><span class="label">DIRETOR(A):</span> {{ $documento['diretor'] ?? '' }}</div>
            </div>
        </section>
    </section>

    @if ($documento['precisa_pagina_em_branco'] ?? false)
        <div class="duplex-blank"></div>
    @elseif (! $loop->last)
            <div class="student-break"></div>
    @endif
@endforeach
</body>
</html>
