@php
    use App\Models\PedidoArquivo;

    $imageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'];
    $imagens = $pedido->arquivos
        ->filter(function (PedidoArquivo $arquivo) use ($imageExtensions): bool {
            $mime = mb_strtolower((string) $arquivo->mime_type);
            $ext = mb_strtolower(pathinfo((string) $arquivo->caminho, PATHINFO_EXTENSION));

            return str_starts_with($mime, 'image/') || in_array($ext, $imageExtensions, true);
        })
        ->groupBy(fn (PedidoArquivo $arquivo): string => $arquivo->tipo_arquivo?->label() ?? 'Imagens');

    $tabs = [
        'geral' => 'Visao geral',
        'imagens' => 'Imagens',
        'historico' => 'Historico',
    ];

    if ($adicionais->isNotEmpty()) {
        $tabs['adicionais'] = 'Adicionais';
    }

    $exportImagesUrl = \Illuminate\Support\Facades\Route::has('pedidos.imagens.export')
        ? route('pedidos.imagens.export', $pedido)
        : null;

    $statusCor = '#' . ltrim($pedido->tipoStatus?->cor ?? '#64748b', '#');
    $prioridade = $pedido->nivel_prioridade?->value ?? 'Indeterminado';
@endphp

@once
    <style>
        .pedido-view {
            display: grid;
            gap: 1rem;
        }

        .pedido-view-modal-window {
            max-width: 50vw !important;
            width: 50vw !important;
        }

        .pedido-view__head {
            align-items: flex-start;
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            justify-content: space-between;
        }

        .pedido-view__title {
            color: #0f172a;
            font-size: 1.05rem;
            font-weight: 800;
            line-height: 1.35rem;
        }

        .pedido-view__subtitle {
            color: #64748b;
            font-size: 0.82rem;
            line-height: 1.3rem;
            margin-top: 0.15rem;
        }

        .pedido-view__actions,
        .pedido-view__tabs,
        .pedido-view__badges {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
        }

        .pedido-view__action,
        .pedido-view__tab,
        .pedido-view__badge {
            align-items: center;
            border-radius: 999px;
            display: inline-flex;
            font-size: 0.74rem;
            font-weight: 750;
            line-height: 1rem;
            min-height: 1.7rem;
            padding: 0.3rem 0.7rem;
            text-decoration: none;
        }

        .pedido-view__action {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1d4ed8;
        }

        .pedido-view__tab {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #475569;
            cursor: pointer;
        }

        .pedido-view__tab[aria-selected="true"] {
            background: #1d4ed8;
            border-color: #1d4ed8;
            color: #ffffff;
        }

        .pedido-view__badge {
            background: color-mix(in srgb, var(--badge-color) 12%, #ffffff);
            border: 1px solid color-mix(in srgb, var(--badge-color) 34%, #ffffff);
            color: var(--badge-color);
        }

        .pedido-view__panel {
            max-height: min(62vh, 680px);
            overflow: auto;
            padding-right: 0.25rem;
        }

        .pedido-view__grid {
            display: grid;
            gap: 0.75rem;
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .pedido-view__field {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            min-width: 0;
            padding: 0.75rem;
        }

        .pedido-view__field--wide {
            grid-column: span 2;
        }

        .pedido-view__label {
            color: #64748b;
            display: block;
            font-size: 0.66rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            line-height: 1rem;
            margin-bottom: 0.15rem;
            text-transform: uppercase;
        }

        .pedido-view__value {
            color: #111827;
            display: block;
            font-size: 0.85rem;
            line-height: 1.35rem;
            overflow-wrap: anywhere;
        }

        .pedido-view__section {
            border-top: 1px solid #e5e7eb;
            display: grid;
            gap: 0.6rem;
            margin-top: 0.9rem;
            padding-top: 0.9rem;
        }

        .pedido-view__text {
            border-left: 3px solid #cbd5e1;
            color: #334155;
            font-size: 0.88rem;
            line-height: 1.45rem;
            padding-left: 0.75rem;
            white-space: pre-line;
        }

        .pedido-view__images {
            display: grid;
            gap: 0.75rem;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .pedido-view__image-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            display: grid;
            gap: 0.45rem;
            padding: 0.6rem;
        }

        .pedido-view__image-card img {
            aspect-ratio: 16 / 10;
            border-radius: 6px;
            cursor: zoom-in;
            object-fit: cover;
            width: 100%;
        }

        .pedido-view__fullscreen {
            align-items: center;
            background: rgba(15, 23, 42, 0.94);
            bottom: 0;
            display: flex;
            justify-content: center;
            left: 0;
            padding: 1rem;
            position: fixed;
            right: 0;
            top: 0;
            z-index: 9999;
        }

        .pedido-view__fullscreen img {
            max-height: 94vh;
            max-width: 94vw;
            object-fit: contain;
        }

        .pedido-view__table-wrap {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: auto;
        }

        .pedido-view__table {
            border-collapse: collapse;
            font-size: 0.82rem;
            min-width: 760px;
            width: 100%;
        }

        .pedido-view__table th {
            background: #f8fafc;
            color: #334155;
            font-weight: 750;
            padding: 0.65rem;
            text-align: left;
            white-space: nowrap;
        }

        .pedido-view__table td {
            border-top: 1px solid #e5e7eb;
            color: #475569;
            padding: 0.65rem;
            vertical-align: top;
        }

        .dark .pedido-view__title,
        .dark .pedido-view__value {
            color: #f8fafc;
        }

        .dark .pedido-view__subtitle,
        .dark .pedido-view__label,
        .dark .pedido-view__table td {
            color: #cbd5e1;
        }

        .dark .pedido-view__field,
        .dark .pedido-view__image-card {
            background: #0f172a;
            border-color: #334155;
        }

        @media (max-width: 900px) {
            .pedido-view__grid,
            .pedido-view__images {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 1024px) {
            .pedido-view-modal-window {
                max-width: calc(100vw - 2rem) !important;
                width: calc(100vw - 2rem) !important;
            }
        }

        @media (max-width: 640px) {
            .pedido-view__grid,
            .pedido-view__images {
                grid-template-columns: 1fr;
            }

            .pedido-view__field--wide {
                grid-column: span 1;
            }
        }
    </style>
@endonce

<div class="pedido-view" x-data="{ tab: 'geral', fullscreen: null }">
    <div class="pedido-view__head">
        <div>
            <div class="pedido-view__title">Protocolo {{ $pedido->numero_protocolo }}</div>
            <div class="pedido-view__subtitle">
                {{ $pedido->tipoManutencao?->nome ?? 'Tipo nao informado' }} | {{ $pedido->escola?->nome ?? 'Escola nao informada' }}
            </div>
        </div>

        <div class="pedido-view__actions">
            <a class="pedido-view__action" href="{{ route('pedidos.pdf', $pedido) }}" target="_blank">Exportar PDF</a>
            @if($imagens->flatten(1)->isNotEmpty() && $exportImagesUrl)
                <a class="pedido-view__action" href="{{ $exportImagesUrl }}" target="_blank">Exportar imagens</a>
            @endif
        </div>
    </div>

    <div class="pedido-view__tabs" role="tablist">
        @foreach($tabs as $key => $label)
            <button
                type="button"
                class="pedido-view__tab"
                x-on:click="tab = '{{ $key }}'"
                x-bind:aria-selected="tab === '{{ $key }}'">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="pedido-view__panel" x-show="tab === 'geral'">
        <div class="pedido-view__badges" style="margin-bottom: 0.8rem;">
            <span class="pedido-view__badge" style="--badge-color: {{ $statusCor }}">{{ $pedido->tipoStatus?->nome ?? 'Sem status' }}</span>
            <span class="pedido-view__badge" style="--badge-color: #475569">{{ $prioridade }}</span>
            <span class="pedido-view__badge" style="--badge-color: #2563eb">{{ $pedido->pedidosAdicionais->count() }} adicional(is)</span>
        </div>

        <div class="pedido-view__grid">
            <div class="pedido-view__field">
                <span class="pedido-view__label">Solicitado em</span>
                <span class="pedido-view__value">{{ $pedido->data_solicitacao?->format('d/m/Y') ?? '-' }}</span>
            </div>
            <div class="pedido-view__field">
                <span class="pedido-view__label">Identificado em</span>
                <span class="pedido-view__value">{{ $pedido->data_identificacao_problema?->format('d/m/Y') ?? '-' }}</span>
            </div>
            <div class="pedido-view__field">
                <span class="pedido-view__label">Previsto para</span>
                <span class="pedido-view__value">{{ $pedido->data_prevista?->format('d/m/Y') ?? 'Sem previsao' }}</span>
            </div>
            <div class="pedido-view__field">
                <span class="pedido-view__label">Concluido em</span>
                <span class="pedido-view__value">{{ $pedido->data_entrega?->format('d/m/Y') ?? 'Em andamento' }}</span>
            </div>
            <div class="pedido-view__field pedido-view__field--wide">
                <span class="pedido-view__label">Solicitante</span>
                <span class="pedido-view__value">{{ $pedido->nome_solicitante ?? '-' }} | {{ $pedido->solicitante?->email ?? 'sem e-mail' }}</span>
            </div>
            <div class="pedido-view__field">
                <span class="pedido-view__label">Setor</span>
                <span class="pedido-view__value">{{ $pedido->setor?->nome_completo ?? $pedido->setor?->nome ?? '-' }}</span>
            </div>
            <div class="pedido-view__field">
                <span class="pedido-view__label">Empresa</span>
                <span class="pedido-view__value">{{ $pedido->empresaContratada?->nome ?? '-' }}</span>
            </div>
        </div>

        <div class="pedido-view__section">
            <span class="pedido-view__label">Problemas</span>
            <div class="pedido-view__badges">
                @forelse($pedido->problemas as $problema)
                    <span class="pedido-view__badge" style="--badge-color: #475569">{{ $problema->texto_problema }}</span>
                @empty
                    <span class="pedido-view__value">Nenhum problema segmentado.</span>
                @endforelse
            </div>
        </div>

        <div class="pedido-view__section">
            <span class="pedido-view__label">Descricao</span>
            <div class="pedido-view__text">{{ $pedido->descricao_pedido ?? '-' }}</div>
        </div>

        @if($pedido->ultimoFeedback)
            <div class="pedido-view__section">
                <span class="pedido-view__label">Avaliacao</span>
                <div class="pedido-view__text">
                    Nota {{ $pedido->ultimoFeedback->valor }}/5
                    @if($pedido->ultimoFeedback->descricao)
                        <br>{{ $pedido->ultimoFeedback->descricao }}
                    @endif
                </div>
            </div>
        @endif
    </div>

    <div class="pedido-view__panel" x-show="tab === 'imagens'" x-cloak>
        @forelse($imagens as $tipo => $arquivos)
            <div class="pedido-view__section" style="border-top: 0; margin-top: 0;">
                <span class="pedido-view__label">{{ $tipo }}</span>
                <div class="pedido-view__images">
                    @foreach($arquivos as $arquivo)
                        <div class="pedido-view__image-card">
                            <img
                                src="{{ Storage::url($arquivo->caminho) }}"
                                alt="{{ $arquivo->nome_original }}"
                                x-on:click="fullscreen = '{{ Storage::url($arquivo->caminho) }}'">
                            <span class="pedido-view__value">{{ $arquivo->nome_original }}</span>
                            <span class="pedido-view__subtitle">{{ $arquivo->created_at?->format('d/m/Y H:i') }} | {{ $arquivo->usuario?->name ?? 'Sistema' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="pedido-view__text">Nenhuma imagem vinculada ao pedido.</div>
        @endforelse
    </div>

    <div class="pedido-view__panel" x-show="tab === 'historico'" x-cloak>
        <div class="pedido-view__table-wrap">
            <table class="pedido-view__table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Status anterior</th>
                        <th>Novo status</th>
                        <th>Setor</th>
                        <th>Alterado por</th>
                        <th>Descricao</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($historico as $item)
                        <tr>
                            <td>{{ $item->created_at?->format('d/m/Y H:i:s') }}</td>
                            <td>{{ $item->statusAnterior?->nome ?? '-' }}</td>
                            <td>
                                <span style="font-weight: 700; color: {{ $item->statusNovo?->cor ?? '#111827' }}">
                                    {{ $item->statusNovo?->nome ?? '-' }}
                                </span>
                            </td>
                            <td>{{ $item->setor?->nome ?? '-' }}</td>
                            <td>{{ $item->usuario?->name ?? 'Sistema' }}</td>
                            <td>{{ $item->descricao_alteracao ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6">Nenhum historico encontrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($adicionais->isNotEmpty())
        <div class="pedido-view__panel" x-show="tab === 'adicionais'" x-cloak>
            <x-pedido.pedidos-adicionais :pedido="$pedido" :adicionais="$adicionais" />
        </div>
    @endif

    <template x-if="fullscreen">
        <div class="pedido-view__fullscreen" x-on:click="fullscreen = null">
            <img x-bind:src="fullscreen" alt="Imagem ampliada">
        </div>
    </template>
</div>
