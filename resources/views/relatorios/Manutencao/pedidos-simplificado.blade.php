@php
    use App\Models\PedidoArquivo;
    use App\Support\PedidoPdfImageDataUri;
    use Illuminate\Support\Facades\Storage;

    $storagePublico = Storage::disk('public');
    $arquivoEhImagem = static fn (PedidoArquivo $arquivo): bool => PedidoPdfImageDataUri::isSupportedImage($storagePublico, $arquivo);

    $imagemDataUri = static fn (PedidoArquivo $arquivo): ?string => PedidoPdfImageDataUri::fromStorage($storagePublico, $arquivo);

    $prioridadeLabel = static fn ($pedido): string => match ($pedido->nivel_prioridade?->value) {
        'indeterminado' => 'Indeterminado',
        default => $pedido->nivel_prioridade?->value ?? 'Não informado',
    };

    $prioridadeCor = static fn ($pedido): string => match ($pedido->nivel_prioridade?->value) {
        'Emergencial' => '#ef4444',
        'Corretivo' => '#f97316',
        'Preventivo' => '#3b82f6',
        default => '#6b7280',
    };
@endphp
@extends('relatorios.layouts.base-pdf')

@section('reportTitle', $reportTitle ?? 'Relatório Simplificado de Manutenção')
@section('reportSubtitle', $reportSubtitle ?? null)

@section('styles')
    .bulk-pedido-page {
        page-break-after: always;
    }

    .bulk-pedido-page:last-child {
        page-break-after: auto;
    }

    .pedido-meta-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 10px;
        page-break-inside: avoid;
    }

    .pedido-meta-table td {
        width: 33.33%;
        padding: 4px 5px;
        vertical-align: top;
        font-size: 10.5px;
    }

    .pedido-meta-table strong {
        display: block;
        color: #111827;
        font-size: 10px;
        margin-bottom: 2px;
    }

    .pedido-description {
        border-left: 3px solid #d1d5db;
        margin: 0 0 10px;
        padding: 7px 0 7px 10px;
        page-break-inside: avoid;
    }

    .pedido-description strong {
        display: block;
        color: #111827;
        font-size: 10.5px;
        margin-bottom: 4px;
    }

    .pedido-description-text {
        color: #374151;
        font-size: 11px;
        line-height: 1.5;
        text-align: justify;
        white-space: pre-line;
    }

    .badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: bold;
        color: #fff;
        line-height: 1.35;
        vertical-align: middle;
    }

    .photos-title {
        border-top: 1px solid #d1d5db;
        padding-top: 8px;
        margin: 6px 0 8px;
        font-size: 11px;
        font-weight: bold;
        color: #111827;
        page-break-after: avoid;
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
        width: 50%;
        padding: 5px;
        text-align: center;
        vertical-align: middle;
        page-break-inside: avoid;
    }

    .photo-table img {
        max-width: 100%;
        max-height: 225px;
        border: 1px solid #d1d5db;
        border-radius: 4px;
        page-break-inside: avoid;
    }

    .missing-file,
    .empty-photos {
        display: block;
        border: 1px solid #e5e7eb;
        border-radius: 4px;
        color: #6b7280;
        font-size: 10px;
        padding: 14px 8px;
        text-align: center;
        page-break-inside: avoid;
    }

    .missing-file {
        border-color: #f3c8c8;
        color: #991b1b;
    }
@endsection

@section('content')
    @foreach($pedidos as $pedido)
        @php
            $escola = $pedido->escola;
            $endereco = $escola
                ? "{$escola->logradouro}, {$escola->numero} - {$escola->bairro}, {$escola->cidade}/{$escola->estado} - CEP: {$escola->cep}"
                : 'Não informado';
            $statusCor = '#' . ltrim($pedido->tipoStatus?->cor ?? '#9ca3af', '#');
            $imagens = ($pedido->fotos ?? collect())->filter($arquivoEhImagem)->values();
        @endphp

        <div class="bulk-pedido-page">
            <table class="pedido-meta-table">
                <tr>
                    <td>
                        <strong>Protocolo:</strong>
                        {{ $pedido->numero_protocolo ?? 'Não informado' }}
                    </td>
                    <td>
                        <strong>Data:</strong>
                        {{ $pedido->data_solicitacao?->format('d/m/Y') ?? 'Não informado' }}
                    </td>
                    <td>
                        <strong>Status:</strong>
                        <span class="badge" style="background: {{ $statusCor }};">
                            {{ $pedido->tipoStatus?->nome ?? 'Não informado' }}
                        </span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong>Identificação do problema:</strong>
                        {{ $pedido->data_identificacao_problema?->format('d/m/Y') ?? 'Não informado' }}
                    </td>
                    <td>
                        <strong>Setor Atual:</strong>
                        {{ $pedido->setor?->nome ?? 'Não informado' }}
                    </td>
                    <td>
                        <strong>Empresa:</strong>
                        {{ $pedido->empresaContratada?->nome ?? 'Não informado' }}
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong>Escola:</strong>
                        {{ $pedido->escolaNomeExibicao() }}
                    </td>
                    <td>
                        <strong>Solicitante:</strong>
                        {{ $pedido->nome_solicitante ?? 'Não informado' }}
                    </td>
                    <td>
                        <strong>Telefone:</strong>
                        {{ $escola?->telefone ?? 'Não informado' }}
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong>E-mail:</strong>
                        {{ $pedido->solicitanteUsuarioEmailExibicao() ?? 'Não informado' }}
                    </td>
                    <td colspan="2">
                        <strong>Endereco:</strong>
                        {{ $endereco }}
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong>Tipo:</strong>
                        {{ $pedido->tipoManutencao?->nome ?? 'Não informado' }}
                    </td>
                    <td></td>
                    <td>
                        <strong>Prioridade:</strong>
                        <span class="badge" style="background: {{ $prioridadeCor($pedido) }};">
                            {{ $prioridadeLabel($pedido) }}
                        </span>
                    </td>
                </tr>
            </table>

            <div class="pedido-description">
                <strong>Descrição do pedido</strong>
                <div class="pedido-description-text">
                    {{ filled($pedido->descricao_pedido) ? $pedido->descricao_pedido : 'Não informado' }}
                </div>
            </div>

            <div class="photos-title">Imagens do problema</div>

            @if($imagens->isEmpty())
                <span class="empty-photos">Nenhuma imagem do problema foi anexada a este pedido.</span>
            @else
                <table class="photo-table">
                    @foreach($imagens->chunk(2) as $grupo)
                        <tr>
                            @foreach($grupo as $arquivo)
                                <td>
                                    @php
                                        $srcImagem = $imagemDataUri($arquivo);
                                    @endphp

                                    @if($srcImagem)
                                        <img src="{{ $srcImagem }}" alt="Imagem do problema {{ $pedido->numero_protocolo }}">
                                    @else
                                        <span class="missing-file">
                                            Imagem indisponivel: {{ $arquivo->nome_original ?? basename((string) $arquivo->caminho) }}
                                        </span>
                                    @endif
                                </td>
                            @endforeach

                            @for($i = $grupo->count(); $i < 2; $i++)
                                <td></td>
                            @endfor
                        </tr>
                    @endforeach
                </table>
            @endif
        </div>
    @endforeach
@endsection
