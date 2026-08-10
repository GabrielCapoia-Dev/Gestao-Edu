@php
    use App\Models\PedidoArquivo;
    use App\Support\PedidoPdfImageDataUri;
    use Illuminate\Support\Facades\Storage;

    $escola = $pedido->escola;
    $storagePublico = Storage::disk('public');
    $arquivoEhImagem = static fn (PedidoArquivo $arquivo): bool => PedidoPdfImageDataUri::isSupportedImage($storagePublico, $arquivo);
    $arquivoExigeImagem = static fn (PedidoArquivo $arquivo): bool => $arquivo->tipo_arquivo?->exigeImagem() ?? false;

    $imagemDataUri = static fn (PedidoArquivo $arquivo): ?string => PedidoPdfImageDataUri::fromStorage($storagePublico, $arquivo);

    $formatarTipoArquivo = static fn (PedidoArquivo $arquivo): string => $arquivo->tipo_arquivo?->label() ?? 'Arquivo';

    $endereco = $escola
        ? "{$escola->logradouro}, {$escola->numero} - {$escola->bairro}, {$escola->cidade}/{$escola->estado} - CEP: {$escola->cep}"
        : 'Não informado';

    $tipo = match($pedido->nivel_prioridade?->value) {
        'indeterminado' => 'Indeterminado',
        default => $pedido->nivel_prioridade?->value ?? 'Não informado',
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
@extends('relatorios.layouts.base-pdf')

@section('reportTitle', $reportTitle ?? 'Relatório Técnico de Manutenção')
@section('reportSubtitle', $reportSubtitle ?? ('Protocolo ' . ($pedido->numero_protocolo ?? 'N/A')))

@section('styles')
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

    .section-title-inline {
        page-break-after: avoid;
        page-break-inside: avoid;
        margin-bottom: 6px;
        font-weight: bold;
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

    .missing-file {
        display: block;
        border: 1px solid #f3c8c8;
        border-radius: 4px;
        color: #991b1b;
        font-size: 9px;
        padding: 10px 6px;
    }

    .files-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 9.5px;
    }

    .files-table th,
    .files-table td {
        border: 1px solid #e5e7eb;
        padding: 5px;
        vertical-align: top;
    }

    .files-table th {
        background: #f3f4f6;
        font-weight: bold;
    }

    .history-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 9.5px;
    }

    .history-table th,
    .history-table td {
        border: 1px solid #e5e7eb;
        padding: 5px;
        vertical-align: top;
    }

    .history-table th {
        background: #f3f4f6;
        font-weight: bold;
    }
@endsection

@section('content')
    <table style="width: 100%;">
        <tr>
            <td style="width: 33.33%;" class="espaco-line">
                <strong>Protocolo:</strong><br>
                {{ $pedido->numero_protocolo ?? 'Não informado' }}
            </td>
            <td style="width: 33.33%;" class="espaco-line">
                <strong>Data:</strong><br>
                {{ $pedido->data_solicitacao?->format('d/m/Y') ?? 'Não informado' }}
            </td>
            <td style="width: 33.33%;" class="espaco-line">
                <strong>Status:</strong><br>
                <span class="badge" style="background: {{ $statusCor }};">
                    {{ $pedido->tipoStatus?->nome ?? 'Não informado' }}
                </span>
            </td>
        </tr>

        <tr>
            <td class="espaco-line">
                <strong>Identificação do problema:</strong><br>
                {{ $pedido->data_identificacao_problema?->format('d/m/Y') ?? 'Não informado' }}
            </td>
            <td class="espaco-line">
                <strong>Setor Atual:</strong><br>
                {{ $pedido->setor?->nome ?? 'Não informado' }}
            </td>
            <td class="espaco-line">
                <strong>Empresa:</strong><br>
                {{ $pedido->empresaContratada?->nome ?? 'Não informado' }}
            </td>
        </tr>

        <tr>
            <td class="espaco-line">
                <strong>Escola:</strong><br>
                {{ $pedido->escolaNomeExibicao() }}
            </td>
            <td class="espaco-line">
                <strong>Solicitante:</strong><br>
                {{ $pedido->nome_solicitante ?? 'Não informado' }}
            </td>
            <td class="espaco-line">
                <strong>Telefone:</strong><br>
                {{ $escola?->telefone ?? 'Não informado' }}
            </td>
        </tr>

        <tr>
            <td class="espaco-line">
                <strong>E-mail:</strong><br>
                {{ $pedido->solicitanteUsuarioEmailExibicao() ?? 'Não informado' }}
            </td>
            <td colspan="2" class="espaco-line">
                <strong>Endereco:</strong><br>
                {{ $endereco }}
            </td>
        </tr>

        <tr>
            <td class="espaco-line">
                <strong>Tipo:</strong><br>
                {{ $pedido->tipoManutencao?->nome ?? 'Não informado' }}
            </td>
            <td class="espaco-line"></td>
            <td class="espaco-line">
                <strong>Prioridade:</strong><br>
                <span class="badge" style="background: {{ $prioridadeCor }};">
                    {{ $tipo }}
                </span>
            </td>
        </tr>

        <tr>
            <td class="espaco-line" colspan="3">
                <strong>Descrição:</strong>
                <div class="descricao">
                    {{ $pedido->descricao_pedido ?? 'Não informado' }}
                </div>
            </td>
        </tr>

        @if($pedido->problemas->isNotEmpty())
            <tr>
                <td class="espaco-line" colspan="3">
                    <strong>Problemas segmentados:</strong>
                    <div class="descricao">
                        {{ $pedido->problemas->pluck('texto_problema')->join(' | ') }}
                    </div>
                </td>
            </tr>
        @endif

        @if($feedback)
            <tr>
                <td class="espaco-line" colspan="3">
                    <strong>Avaliação do serviço:</strong>

                    <div class="descricao">
                        <div style="margin-bottom: 6px;">
                            <strong>Nota:</strong> {{ $feedback->valor }}/5
                        </div>

                        @if($feedback->descricao)
                            <div>
                                <strong>Comentario:</strong><br>
                                {{ $feedback->descricao }}
                            </div>
                        @endif

                        @if($feedback->itens->isNotEmpty())
                            <div style="margin-top: 8px;">
                                <strong>Avaliação por problema:</strong><br>
                                @foreach($feedback->itens as $item)
                                    <div style="margin-top: 4px;">
                                        {{ $item->problema?->texto_problema ?? 'Problema' }}:
                                        {{ $item->valor }}/5 -
                                        {{ $item->resultado?->label() ?? $item->resultado }}
                                        @if($item->comentario)
                                            <br><span style="color:#4b5563;">{{ $item->comentario }}</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </td>
            </tr>
        @endif
    </table>

    @if($pedido->pedidosAdicionais->isNotEmpty())
        <div class="divider"></div>
        <div class="section-block espaco-line">
            <div class="section-title-inline">Pedidos Adicionais Vinculados</div>

            <table style="width:100%; border-collapse: collapse; font-size: 10px;">
                <thead>
                    <tr>
                        <th style="border:1px solid #d1d5db; padding:5px;">Protocolo</th>
                        <th style="border:1px solid #d1d5db; padding:5px;">Tipo</th>
                        <th style="border:1px solid #d1d5db; padding:5px;">Problemas</th>
                        <th style="border:1px solid #d1d5db; padding:5px;">Descrição</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pedido->pedidosAdicionais as $adicional)
                        <tr>
                            <td style="border:1px solid #e5e7eb; padding:5px;">{{ $adicional->numero_protocolo }}</td>
                            <td style="border:1px solid #e5e7eb; padding:5px;">{{ $adicional->tipoManutencao?->nome ?? '-' }}</td>
                            <td style="border:1px solid #e5e7eb; padding:5px;">{{ $adicional->problemas->pluck('texto_problema')->join(' | ') ?: '-' }}</td>
                            <td style="border:1px solid #e5e7eb; padding:5px;">{{ $adicional->descricao_pedido }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @foreach($pedido->pedidosAdicionais as $adicional)
                @php
                    $arquivosAdicional = $adicional->arquivos ?? collect();
                    $imagensAdicional = $arquivosAdicional->filter($arquivoEhImagem);
                    $anexosAdicional = $arquivosAdicional
                        ->reject($arquivoEhImagem)
                        ->reject($arquivoExigeImagem)
                        ->values();
                @endphp

                @if($imagensAdicional->isNotEmpty())
                    <div class="section-title-inline" style="margin-top: 10px;">
                        Fotos do Adicional {{ $adicional->numero_protocolo }}
                    </div>

                    <table class="photo-table">
                        @foreach($imagensAdicional->chunk(3) as $grupo)
                            <tr>
                                @foreach($grupo as $arquivo)
                                    <td>
                                        @php
                                            $srcImagem = $imagemDataUri($arquivo);
                                        @endphp
                                        @if($srcImagem)
                                            <img src="{{ $srcImagem }}" alt="Foto do adicional {{ $adicional->numero_protocolo }}">
                                        @else
                                            <span class="missing-file">
                                                Foto indisponivel: {{ $arquivo->nome_original ?? basename((string) $arquivo->caminho) }}
                                            </span>
                                        @endif
                                    </td>
                                @endforeach

                                @for($i = $grupo->count(); $i < 3; $i++)
                                    <td></td>
                                @endfor
                            </tr>
                        @endforeach
                    </table>
                @endif

                @if($anexosAdicional->isNotEmpty())
                    <div class="section-title-inline" style="margin-top: 10px;">
                        Arquivos do Adicional {{ $adicional->numero_protocolo }}
                    </div>

                    <table class="files-table">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Arquivo</th>
                                <th>Descrição</th>
                                <th>Enviado por</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($anexosAdicional as $arquivo)
                                <tr>
                                    <td>{{ $formatarTipoArquivo($arquivo) }}</td>
                                    <td>{{ $arquivo->nome_original ?? basename((string) $arquivo->caminho) }}</td>
                                    <td>{{ $arquivo->descricao ?: '-' }}</td>
                                    <td>{{ $arquivo->usuarioNomeExibicao() }}</td>
                                    <td>{{ $arquivo->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            @endforeach
        </div>
    @endif

    @php
        $historicosOrdenados = $pedido->historicos
            ->sortByDesc(fn ($item) => (($item->created_at?->timestamp ?? 0) * 10) + ($item->status_anterior_id ? 1 : 0));

        $arquivosPedido = $pedido->arquivos ?? collect();
        $imagensPorTipo = $arquivosPedido
            ->filter($arquivoEhImagem)
            ->groupBy(fn ($arquivo) => $formatarTipoArquivo($arquivo));
        $anexosPedido = $arquivosPedido
            ->reject($arquivoEhImagem)
            ->reject($arquivoExigeImagem)
            ->values();
    @endphp

    @if($historicosOrdenados->isNotEmpty())
        <div class="divider"></div>
        <div class="section-block espaco-line">
            <div class="section-title-inline">Histórico de alterações</div>

            <table class="history-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Status anterior</th>
                        <th>Novo status</th>
                        <th>Setor</th>
                        <th>Alterado por</th>
                        <th>Descrição</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($historicosOrdenados as $item)
                        <tr>
                            <td>{{ $item->created_at?->format('d/m/Y H:i:s') }}</td>
                            <td>{{ $item->statusAnterior?->nome ?? '-' }}</td>
                            <td>{{ $item->statusNovo?->nome ?? '-' }}</td>
                            <td>{{ $item->setor?->nome ?? '-' }}</td>
                            <td>{{ $item->usuarioNomeExibicao() }}</td>
                            <td>{{ $item->descricao_alteracao ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($imagensPorTipo->isNotEmpty())
        <div class="divider"></div>

        @foreach($imagensPorTipo as $tipoArquivo => $arquivos)
            <div class="section-block espaco-line">
                <div class="section-title-inline">{{ $tipoArquivo }}</div>
                <table class="photo-table">
                    @foreach($arquivos->chunk(3) as $grupo)
                        <tr>
                            @foreach($grupo as $arquivo)
                                <td>
                                    @php
                                        $srcImagem = $imagemDataUri($arquivo);
                                    @endphp
                                    @if($srcImagem)
                                        <img src="{{ $srcImagem }}" alt="{{ $tipoArquivo }}">
                                    @else
                                        <span class="missing-file">
                                            Foto indisponivel: {{ $arquivo->nome_original ?? basename((string) $arquivo->caminho) }}
                                        </span>
                                    @endif
                                </td>
                            @endforeach

                            @for($i = $grupo->count(); $i < 3; $i++)
                                <td></td>
                            @endfor
                        </tr>
                    @endforeach
                </table>
            </div>
        @endforeach
    @endif

    @if($anexosPedido->isNotEmpty())
        <div class="divider"></div>
        <div class="section-block espaco-line">
            <div class="section-title-inline">Arquivos Anexados</div>

            <table class="files-table">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Arquivo</th>
                        <th>Descrição</th>
                        <th>Enviado por</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($anexosPedido as $arquivo)
                        <tr>
                            <td>{{ $formatarTipoArquivo($arquivo) }}</td>
                            <td>{{ $arquivo->nome_original ?? basename((string) $arquivo->caminho) }}</td>
                            <td>{{ $arquivo->descricao ?: '-' }}</td>
                            <td>{{ $arquivo->usuarioNomeExibicao() }}</td>
                            <td>{{ $arquivo->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
