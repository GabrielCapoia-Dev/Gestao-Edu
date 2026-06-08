@php
    use App\Models\PedidoArquivo;
    use Illuminate\Support\Facades\Storage;

    $storagePublico = Storage::disk('public');
    $extensoesImagem = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'];

    $arquivoEhImagem = static function (PedidoArquivo $arquivo) use ($extensoesImagem): bool {
        $mime = mb_strtolower((string) $arquivo->mime_type);
        $ext = mb_strtolower(pathinfo((string) $arquivo->caminho, PATHINFO_EXTENSION));

        return str_starts_with($mime, 'image/') || in_array($ext, $extensoesImagem, true);
    };

    $arquivoExiste = static function (PedidoArquivo $arquivo) use ($storagePublico): bool {
        return filled($arquivo->caminho) && $storagePublico->exists($arquivo->caminho);
    };

    $imagemDataUri = static function (PedidoArquivo $arquivo) use ($storagePublico, $arquivoEhImagem, $arquivoExiste): ?string {
        if (! $arquivoEhImagem($arquivo) || ! $arquivoExiste($arquivo)) {
            return null;
        }

        try {
            $mime = str_starts_with((string) $arquivo->mime_type, 'image/')
                ? $arquivo->mime_type
                : ($storagePublico->mimeType($arquivo->caminho) ?: 'image/jpeg');

            return sprintf('data:%s;base64,%s', $mime, base64_encode($storagePublico->get($arquivo->caminho)));
        } catch (\Throwable) {
            return null;
        }
    };

    $prioridadeLabel = static fn ($pedido): string => match ($pedido->nivel_prioridade?->value) {
        'indeterminado' => 'Indeterminado',
        default => $pedido->nivel_prioridade?->value ?? 'Nao Informado',
    };

    $prioridadeCor = static fn ($pedido): string => match ($pedido->nivel_prioridade?->value) {
        'Emergencial' => '#ef4444',
        'Corretivo' => '#f97316',
        'Preventivo' => '#3b82f6',
        default => '#6b7280',
    };
@endphp
@extends('relatorios.layouts.base-pdf')

@section('reportTitle', $reportTitle ?? 'Relatorio Simplificado de Manutencao')
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
        margin: 4px 0 8px;
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
                : 'Nao Informado';
            $statusCor = '#' . ltrim($pedido->tipoStatus?->cor ?? '#9ca3af', '#');
            $imagens = ($pedido->fotos ?? collect())->filter($arquivoEhImagem)->values();
        @endphp

        <div class="bulk-pedido-page">
            <table class="pedido-meta-table">
                <tr>
                    <td>
                        <strong>Protocolo:</strong>
                        {{ $pedido->numero_protocolo ?? 'Nao Informado' }}
                    </td>
                    <td>
                        <strong>Data:</strong>
                        {{ $pedido->data_solicitacao?->format('d/m/Y') ?? 'Nao Informado' }}
                    </td>
                    <td>
                        <strong>Status:</strong>
                        <span class="badge" style="background: {{ $statusCor }};">
                            {{ $pedido->tipoStatus?->nome ?? 'Nao Informado' }}
                        </span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong>Identificacao do Problema:</strong>
                        {{ $pedido->data_identificacao_problema?->format('d/m/Y') ?? 'Nao Informado' }}
                    </td>
                    <td>
                        <strong>Setor Atual:</strong>
                        {{ $pedido->setor?->nome ?? 'Nao Informado' }}
                    </td>
                    <td>
                        <strong>Empresa:</strong>
                        {{ $pedido->empresaContratada?->nome ?? 'Nao Informado' }}
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong>Escola:</strong>
                        {{ $escola?->nome ?? 'Nao Informado' }}
                    </td>
                    <td>
                        <strong>Solicitante:</strong>
                        {{ $pedido->nome_solicitante ?? 'Nao Informado' }}
                    </td>
                    <td>
                        <strong>Telefone:</strong>
                        {{ $escola?->telefone ?? 'Nao Informado' }}
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong>E-mail:</strong>
                        {{ $pedido->solicitante?->email ?? 'Nao Informado' }}
                    </td>
                    <td colspan="2">
                        <strong>Endereco:</strong>
                        {{ $endereco }}
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong>Tipo:</strong>
                        {{ $pedido->tipoManutencao?->nome ?? 'Nao Informado' }}
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
