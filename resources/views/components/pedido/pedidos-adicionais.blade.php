@once
    <style>
        .pedido-adicionais-modal {
            display: grid;
            gap: 0.875rem;
        }

        .pedido-adicionais-modal__summary {
            align-items: center;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            justify-content: space-between;
            padding: 0.875rem 1rem;
        }

        .pedido-adicionais-modal__summary-title {
            color: #0f172a;
            font-size: 0.9rem;
            font-weight: 750;
            line-height: 1.25rem;
        }

        .pedido-adicionais-modal__summary-text {
            color: #64748b;
            font-size: 0.78rem;
            line-height: 1.2rem;
            margin-top: 0.1rem;
        }

        .pedido-adicionais-modal__list {
            display: grid;
            gap: 0.875rem;
        }

        .pedido-adicionais-modal__card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
            display: grid;
            gap: 0.875rem;
            padding: 1rem;
        }

        .pedido-adicionais-modal__header {
            align-items: flex-start;
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            justify-content: space-between;
        }

        .pedido-adicionais-modal__protocolo {
            color: #111827;
            font-size: 0.98rem;
            font-weight: 750;
            line-height: 1.25rem;
        }

        .pedido-adicionais-modal__tipo {
            color: #64748b;
            font-size: 0.8rem;
            line-height: 1.15rem;
            margin-top: 0.15rem;
        }

        .pedido-adicionais-modal__badges,
        .pedido-adicionais-modal__problemas {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
        }

        .pedido-adicionais-modal__badge {
            align-items: center;
            background: color-mix(in srgb, var(--badge-color) 12%, #ffffff);
            border: 1px solid color-mix(in srgb, var(--badge-color) 34%, #ffffff);
            border-radius: 999px;
            color: var(--badge-color);
            display: inline-flex;
            font-size: 0.72rem;
            font-weight: 750;
            line-height: 1rem;
            min-height: 1.6rem;
            padding: 0.28rem 0.62rem;
        }

        .pedido-adicionais-modal__grid {
            display: grid;
            gap: 0.7rem;
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .pedido-adicionais-modal__field {
            min-width: 0;
        }

        .pedido-adicionais-modal__field--wide {
            grid-column: span 2;
        }

        .pedido-adicionais-modal__label {
            color: #64748b;
            display: block;
            font-size: 0.66rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            line-height: 1rem;
            margin-bottom: 0.15rem;
            text-transform: uppercase;
        }

        .pedido-adicionais-modal__value {
            color: #1f2937;
            display: block;
            font-size: 0.83rem;
            line-height: 1.35rem;
            overflow-wrap: anywhere;
        }

        .pedido-adicionais-modal__section {
            border-top: 1px solid #e5e7eb;
            display: grid;
            gap: 0.45rem;
            padding-top: 0.75rem;
        }

        .pedido-adicionais-modal__descricao {
            border-left: 3px solid #cbd5e1;
            color: #374151;
            font-size: 0.86rem;
            line-height: 1.45rem;
            padding-left: 0.75rem;
            white-space: pre-line;
        }

        .pedido-adicionais-modal__avaliacoes {
            display: grid;
            gap: 0.5rem;
        }

        .pedido-adicionais-modal__imagens {
            display: grid;
            gap: 0.65rem;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .pedido-adicionais-modal__imagem {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
        }

        .pedido-adicionais-modal__imagem img {
            aspect-ratio: 4 / 3;
            display: block;
            height: auto;
            object-fit: cover;
            width: 100%;
        }

        .pedido-adicionais-modal__imagem-caption {
            color: #475569;
            display: block;
            font-size: 0.72rem;
            line-height: 1rem;
            overflow: hidden;
            padding: 0.45rem 0.55rem;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .pedido-adicionais-modal__avaliacao {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            display: grid;
            gap: 0.25rem;
            padding: 0.65rem 0.75rem;
        }

        .pedido-adicionais-modal__avaliacao-top {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            justify-content: space-between;
        }

        .pedido-adicionais-modal__avaliacao-title {
            color: #111827;
            font-size: 0.82rem;
            font-weight: 700;
            line-height: 1.25rem;
        }

        .pedido-adicionais-modal__empty {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            color: #64748b;
            font-size: 0.875rem;
            padding: 1.5rem;
            text-align: center;
        }

        .dark .pedido-adicionais-modal__summary,
        .dark .pedido-adicionais-modal__avaliacao,
        .dark .pedido-adicionais-modal__imagem {
            background: #111827;
            border-color: #334155;
        }

        .dark .pedido-adicionais-modal__card {
            background: #0f172a;
            border-color: #334155;
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.28);
        }

        .dark .pedido-adicionais-modal__summary-title,
        .dark .pedido-adicionais-modal__protocolo,
        .dark .pedido-adicionais-modal__avaliacao-title {
            color: #ffffff;
        }

        .dark .pedido-adicionais-modal__summary-text,
        .dark .pedido-adicionais-modal__tipo,
        .dark .pedido-adicionais-modal__label {
            color: #94a3b8;
        }

        .dark .pedido-adicionais-modal__value,
        .dark .pedido-adicionais-modal__descricao,
        .dark .pedido-adicionais-modal__imagem-caption {
            color: #e5e7eb;
        }

        .dark .pedido-adicionais-modal__section {
            border-color: #334155;
        }

        .dark .pedido-adicionais-modal__badge {
            background: color-mix(in srgb, var(--badge-color) 24%, #111827);
            border-color: color-mix(in srgb, var(--badge-color) 58%, #111827);
            color: #ffffff;
        }

        .dark .pedido-adicionais-modal__empty {
            background: #111827;
            border-color: #334155;
            color: #94a3b8;
        }

        @media (max-width: 900px) {
            .pedido-adicionais-modal__grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .pedido-adicionais-modal__card,
            .pedido-adicionais-modal__summary {
                padding: 0.875rem;
            }

            .pedido-adicionais-modal__grid {
                grid-template-columns: 1fr;
            }

            .pedido-adicionais-modal__field--wide {
                grid-column: span 1;
            }

            .pedido-adicionais-modal__imagens {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endonce

@php
    $pedido?->loadMissing(['tipoManutencao', 'tipoStatus']);
@endphp

<div class="pedido-adicionais-modal">
    <div class="pedido-adicionais-modal__summary">
        <div>
            <div class="pedido-adicionais-modal__summary-title">
                Pedido principal {{ $pedido->numero_protocolo ?? '-' }}
            </div>
            <div class="pedido-adicionais-modal__summary-text">
                {{ $adicionais->count() }} pedido(s) adicional(is) vinculado(s)
                @if($pedido->tipoManutencao?->nome)
                    | {{ $pedido->tipoManutencao->nome }}
                @endif
            </div>
        </div>

        <span class="pedido-adicionais-modal__badge" style="--badge-color: #475569">
            {{ $pedido->tipoStatus?->nome ?? 'Status nao informado' }}
        </span>
    </div>

    <div class="pedido-adicionais-modal__list">
        @forelse ($adicionais as $adicional)
            @php
                $statusCor = '#' . ltrim($adicional->tipoStatus?->cor ?? '#64748b', '#');
                $avaliacoes = $adicional->feedbackItens ?? collect();
                $imagens = ($adicional->arquivos ?? collect())
                    ->filter(fn ($arquivo) => str_starts_with((string) $arquivo->mime_type, 'image/')
                        || preg_match('/\.(jpe?g|png|webp)$/i', (string) $arquivo->caminho));
                $notaMedia = $avaliacoes->isNotEmpty()
                    ? number_format((float) $avaliacoes->avg('valor'), 1, ',', '.')
                    : null;
            @endphp

            <div class="pedido-adicionais-modal__card">
                <div class="pedido-adicionais-modal__header">
                    <div>
                        <div class="pedido-adicionais-modal__protocolo">
                            {{ $adicional->numero_protocolo }}
                        </div>

                        <div class="pedido-adicionais-modal__tipo">
                            {{ $adicional->tipoManutencao?->nome ?? 'Tipo nao informado' }}
                        </div>
                    </div>

                    <div class="pedido-adicionais-modal__badges">
                        <span class="pedido-adicionais-modal__badge" style="--badge-color: {{ $statusCor }}">
                            {{ $adicional->tipoStatus?->nome ?? 'Pedido Adicional' }}
                        </span>

                        @if($notaMedia)
                            <span class="pedido-adicionais-modal__badge" style="--badge-color: #047857">
                                Nota {{ $notaMedia }}/5
                            </span>
                        @endif
                    </div>
                </div>

                <div class="pedido-adicionais-modal__grid">
                    <div class="pedido-adicionais-modal__field">
                        <span class="pedido-adicionais-modal__label">Solicitado em</span>
                        <span class="pedido-adicionais-modal__value">{{ $adicional->data_solicitacao?->format('d/m/Y') ?? '-' }}</span>
                    </div>

                    <div class="pedido-adicionais-modal__field">
                        <span class="pedido-adicionais-modal__label">Identificado em</span>
                        <span class="pedido-adicionais-modal__value">{{ $adicional->data_identificacao_problema?->format('d/m/Y') ?? '-' }}</span>
                    </div>

                    <div class="pedido-adicionais-modal__field">
                        <span class="pedido-adicionais-modal__label">Criado em</span>
                        <span class="pedido-adicionais-modal__value">{{ $adicional->created_at?->format('d/m/Y H:i') ?? '-' }}</span>
                    </div>

                    <div class="pedido-adicionais-modal__field">
                        <span class="pedido-adicionais-modal__label">Solicitante</span>
                        <span class="pedido-adicionais-modal__value">{{ $adicional->nome_solicitante ?? $adicional->solicitante?->name ?? '-' }}</span>
                    </div>

                    <div class="pedido-adicionais-modal__field pedido-adicionais-modal__field--wide">
                        <span class="pedido-adicionais-modal__label">Escola</span>
                        <span class="pedido-adicionais-modal__value">{{ $adicional->escola?->nome ?? '-' }}</span>
                    </div>

                    <div class="pedido-adicionais-modal__field">
                        <span class="pedido-adicionais-modal__label">Setor atual</span>
                        <span class="pedido-adicionais-modal__value">{{ $adicional->setor?->nome_completo ?? $adicional->setor?->nome ?? '-' }}</span>
                    </div>

                    <div class="pedido-adicionais-modal__field">
                        <span class="pedido-adicionais-modal__label">Empresa</span>
                        <span class="pedido-adicionais-modal__value">{{ $adicional->empresaContratada?->nome ?? '-' }}</span>
                    </div>
                </div>

                <div class="pedido-adicionais-modal__section">
                    <span class="pedido-adicionais-modal__label">Descricao do adicional</span>
                    <div class="pedido-adicionais-modal__descricao">
                        {{ $adicional->descricao_pedido ?: 'Sem descricao informada.' }}
                    </div>
                </div>

                <div class="pedido-adicionais-modal__section">
                    <span class="pedido-adicionais-modal__label">Problemas atendidos</span>
                    <div class="pedido-adicionais-modal__problemas">
                        @forelse ($adicional->problemas as $problema)
                            <span class="pedido-adicionais-modal__badge" style="--badge-color: #475569">
                                {{ $problema->texto_problema }}
                            </span>
                        @empty
                            <span class="pedido-adicionais-modal__value">Nenhum problema segmentado.</span>
                        @endforelse
                    </div>
                </div>

                <div class="pedido-adicionais-modal__section">
                    <span class="pedido-adicionais-modal__label">Fotos do adicional</span>

                    @if($imagens->isNotEmpty())
                        <div class="pedido-adicionais-modal__imagens">
                            @foreach($imagens as $imagem)
                                <a
                                    href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($imagem->caminho) }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="pedido-adicionais-modal__imagem"
                                >
                                    <img
                                        src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($imagem->caminho) }}"
                                        alt="{{ $imagem->nome_original ?? 'Foto do adicional' }}"
                                        loading="lazy"
                                    />
                                    <span class="pedido-adicionais-modal__imagem-caption">
                                        {{ $imagem->nome_original ?? basename($imagem->caminho) }}
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <span class="pedido-adicionais-modal__value">Nenhuma foto registrada para este adicional.</span>
                    @endif
                </div>

                <div class="pedido-adicionais-modal__section">
                    <span class="pedido-adicionais-modal__label">Avaliacao registrada</span>

                    @if($avaliacoes->isNotEmpty())
                        <div class="pedido-adicionais-modal__avaliacoes">
                            @foreach($avaliacoes as $item)
                                <div class="pedido-adicionais-modal__avaliacao">
                                    <div class="pedido-adicionais-modal__avaliacao-top">
                                        <span class="pedido-adicionais-modal__avaliacao-title">
                                            {{ $item->problema?->texto_problema ?? 'Problema avaliado' }}
                                        </span>
                                        <span class="pedido-adicionais-modal__badge" style="--badge-color: #047857">
                                            {{ $item->valor }}/5
                                            @if($item->resultado)
                                                - {{ $item->resultado?->label() ?? $item->resultado }}
                                            @endif
                                        </span>
                                    </div>

                                    @if($item->comentario)
                                        <span class="pedido-adicionais-modal__value">{{ $item->comentario }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <span class="pedido-adicionais-modal__value">Ainda sem avaliacao registrada para este adicional.</span>
                    @endif
                </div>
            </div>
        @empty
            <div class="pedido-adicionais-modal__empty">
                Nenhum pedido adicional vinculado.
            </div>
        @endforelse
    </div>
</div>
