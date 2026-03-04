@php
$escola = $pedido->escola;

$endereco = $escola
? "{$escola->logradouro}, {$escola->numero} - {$escola->bairro}, {$escola->cidade}/{$escola->estado} - CEP: {$escola->cep}"
: 'Não Informado';

$tipo = match($pedido->nivel_prioridade?->value) {
'indeterminado' => 'Indeterminado',
default => $pedido->nivel_prioridade?->value ?? 'Não Informado',
};

$prioridadeCor = match($pedido->nivel_prioridade?->value) {
'Emergencial' => '#ef4444',
'Corretivo' => '#f97316',
'Preventivo' => '#3b82f6',
default => '#6b7280',
};

$feedback = $pedido->ultimoFeedback;
$statusCor = '#' . ltrim($pedido->tipoStatus?->cor ?? '#9ca3af', '#');
@endphp

<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #1f2937;
            margin: 0;
            padding: 0;
        }

        /* Ajuste das margens da página */
        @page {
            size: A4 portrait;
            margin: 150px 25px 80px 25px;
        }

        /* HEADER FIXO - aparece em todas as páginas */
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

        /* FOOTER FIXO - aparece em todas as páginas */
        .footer-fixed {
            position: fixed;
            bottom: -60px;
            left: 0;
            right: 0;
            height: 60px;
            font-size: 10px;
            color: #6b7280;
            background: white;
            z-index: 1000;
        }

        .footer-divider {
            border-top: 1px solid #d1d5db;
            margin-bottom: 6px;
        }

        .footer-content {
            width: 100%;
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

        /* CONTEÚDO PRINCIPAL */
        .content {
            page-break-inside: auto;
        }

        .espaco-line {
            padding: 2px 4px;
        }

        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            color: #fff;
            line-height: 1.4;
            vertical-align: middle;
        }

        .divider {
            border-top: 1px solid #d1d5db;
            margin: 10px 0;
        }

        .descricao {
            border-left: 3px solid #d1d5db;
            padding: 8px 0 8px 12px;
            margin-top: 4px;
            text-align: justify;
            text-justify: inter-word;
            line-height: 1.6;
            font-size: 12px;
        }

        .section-block {
            page-break-inside: avoid;
            margin-top: 10px;
        }

        .section-title {
            page-break-after: avoid;
            page-break-inside: avoid;
            margin-bottom: 6px;
        }

        .photo-table {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: auto;
        }

        .photo-table tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }

        .photo-table td {
            page-break-inside: avoid;
            padding: 4px;
            text-align: center;
            vertical-align: middle;
        }

        .photo-table img {
            max-width: 100%;
            max-height: 150px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            page-break-inside: avoid;
        }

        /* Forçar quebra de página antes das fotos se necessário */
        .page-break-before {
            page-break-before: always;
        }

        /* Limpar floats */
        .clearfix::after {
            content: "";
            clear: both;
            display: table;
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
            <h3>Relatório Técnico de Manutenção</h3>
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

        <table style="width:100%;">
            {{-- LINHA 1 --}}
            <tr>
                <td style="width:33.33%;" class="espaco-line">
                    <strong>Protocolo:</strong><br>
                    {{ $pedido->numero_protocolo ?? 'Não Informado' }}
                </td>
                <td style="width:33.33%;" class="espaco-line">
                    <strong>Data:</strong><br>
                    {{ $pedido->data_solicitacao?->format('d/m/Y') ?? 'Não Informado' }}
                </td>
                <td style="width:33.33%;" class="espaco-line">
                    <strong>Status:</strong><br>
                    <span class="badge" style="background: {{ $statusCor }}">
                        {{ $pedido->tipoStatus?->nome ?? 'Não Informado' }}
                    </span>
                </td>
            </tr>

            {{-- LINHA 2 --}}
            <tr>
                <td class="espaco-line">
                    <strong>Escola:</strong><br>
                    {{ $escola?->nome ?? 'Não Informado' }}
                </td>
                <td class="espaco-line">
                    <strong>Solicitante:</strong><br>
                    {{ $pedido->nome_solicitante ?? 'Não Informado' }}
                </td>
                <td class="espaco-line">
                    <strong>Telefone:</strong><br>
                    {{ $escola?->telefone ?? 'Não Informado' }}
                </td>
            </tr>

            {{-- LINHA 3 --}}
            <tr>
                <td class="espaco-line">
                    <strong>E-mail:</strong><br>
                    {{ $pedido->solicitante?->email ?? 'Não Informado' }}
                </td>
                <td colspan="2" class="espaco-line">
                    <strong>Endereço:</strong><br>
                    {{ $endereco }}
                </td>
            </tr>

            {{-- LINHA 4 --}}
            <tr>
                <td class="espaco-line">
                    <strong>Tipo:</strong><br>
                    {{ $pedido->tipoManutencao?->nome ?? 'Não Informado' }}
                </td>
                <td class="espaco-line"></td>
                <td class="espaco-line">
                    <strong>Prioridade:</strong><br>
                    <span class="badge" style="background: {{ $prioridadeCor }}">
                        {{ $tipo }}
                    </span>
                </td>
            </tr>

            {{-- LINHA DESCRIÇÃO --}}
            <tr>
                <td class="espaco-line" colspan="3">
                    <strong>Descrição:</strong>
                    <div class="descricao">
                        {{ $pedido->descricao_pedido ?? 'Não Informado' }}
                    </div>
                </td>
            </tr>

            {{-- LINHA FEEDBACK --}}
            @if($feedback)
            <tr>
                <td class="espaco-line" colspan="3">

                    <strong>Avaliação do Serviço:</strong>

                    <div class="descricao">

                        <div style="margin-bottom:6px;">
                            <strong>Nota:</strong>

                            <span style="font-size:14px;">
                                @for ($i = 1; $i <= 5; $i++)
                                    <span style="color: {{ $i <= $feedback->valor ? '#ff8018' : '#6b6b6b' }};">★</span>
                            @endfor
                            </span>
                        </div>

                        @if($feedback->descricao)
                        <div>
                            <strong>Comentário:</strong><br>
                            {{ $feedback->descricao }}
                        </div>
                        @endif

                    </div>

                </td>
            </tr>
            @endif
        </table>

        @php
        $fotosProblema = $pedido->arquivos()
        ->where('tipo_arquivo', 'fotos_problema')
        ->get();

        $fotosConclusao = $pedido->arquivos()
        ->where('tipo_arquivo', 'fotos_conclusao')
        ->get();

        $temFotos = $fotosProblema->isNotEmpty() || $fotosConclusao->isNotEmpty();


        @endphp

        @if($temFotos)
        <div class="divider"></div>

        {{-- FOTOS DO PROBLEMA --}}
        @if($fotosProblema->isNotEmpty())
        <div class="section-block espaco-line">
            <div class="section-title">
                <strong>Fotos do Problema:</strong>
            </div>

            <table class="photo-table">
                @foreach($fotosProblema->chunk(3) as $grupo)
                <tr>
                    @foreach($grupo as $arquivo)
                    <td>
                        <img src="{{ public_path('storage/' . $arquivo->caminho) }}">
                    </td>
                    @endforeach
                    @for($i = $grupo->count(); $i < 3; $i++)
                        <td>
                        </td>
                        @endfor
                </tr>
                @endforeach
            </table>
        </div>
        @endif

        {{-- FOTOS DE CONCLUSÃO --}}
        @if($fotosConclusao->isNotEmpty())
        <div class="section-block espaco-line">
            <div class="section-title">
                <strong>Fotos da Conclusão:</strong>
            </div>

            <table class="photo-table">
                @foreach($fotosConclusao->chunk(3) as $grupo)
                <tr>
                    @foreach($grupo as $arquivo)
                    <td>
                        <img src="{{ public_path('storage/' . $arquivo->caminho) }}">
                    </td>
                    @endforeach
                    @for($i = $grupo->count(); $i < 3; $i++)
                        <td>
                        </td>
                        @endfor
                </tr>
                @endforeach
            </table>
        </div>
        @endif
        @endif

    </div> {{-- fim content --}}

</body>

</html>