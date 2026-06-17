@php
    $feedback?->loadMissing([
        'itens.problema',
        'itens.pedido.tipoManutencao',
        'fotos',
    ]);

    $statusCor = '#' . ltrim($pedido->tipoStatus?->cor ?? '#64748b', '#');
    $notaCor = match (true) {
        (int) $feedback?->valor >= 4 => '#047857',
        (int) $feedback?->valor >= 3 => '#c2410c',
        default => '#b91c1c',
    };
@endphp

@once
    <style>
        .pedido-feedback {
            color: #334155;
            display: grid;
            gap: 1rem;
        }

        .pedido-feedback__summary {
            display: grid;
            gap: 0.75rem;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .pedido-feedback__field,
        .pedido-feedback__item {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            min-width: 0;
            padding: 0.75rem;
        }

        .pedido-feedback__field--wide {
            grid-column: span 2;
        }

        .pedido-feedback__label {
            color: #64748b;
            display: block;
            font-size: 0.68rem;
            font-weight: 750;
            letter-spacing: 0.04em;
            line-height: 1rem;
            margin-bottom: 0.2rem;
            text-transform: uppercase;
        }

        .pedido-feedback__value {
            color: #111827;
            display: block;
            font-size: 0.88rem;
            line-height: 1.35rem;
            overflow-wrap: anywhere;
        }

        .pedido-feedback__badges {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
        }

        .pedido-feedback__badge {
            align-items: center;
            background: color-mix(in srgb, var(--badge-color) 12%, #ffffff);
            border: 1px solid color-mix(in srgb, var(--badge-color) 38%, #ffffff);
            border-radius: 999px;
            color: var(--badge-color);
            display: inline-flex;
            font-size: 0.75rem;
            font-weight: 750;
            line-height: 1rem;
            min-height: 1.65rem;
            padding: 0.28rem 0.65rem;
        }

        .pedido-feedback__section {
            border-top: 1px solid #e5e7eb;
            display: grid;
            gap: 0.7rem;
            padding-top: 0.9rem;
        }

        .pedido-feedback__text {
            border-left: 3px solid #cbd5e1;
            color: #334155;
            font-size: 0.88rem;
            line-height: 1.45rem;
            padding-left: 0.75rem;
            white-space: pre-line;
        }

        .pedido-feedback__items {
            display: grid;
            gap: 0.65rem;
        }

        .pedido-feedback__item-head {
            align-items: flex-start;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            justify-content: space-between;
        }

        .pedido-feedback__rating {
            align-items: center;
            display: inline-flex;
            gap: 0.15rem;
        }

        .dark .pedido-feedback,
        .dark .pedido-feedback__value,
        .dark .pedido-feedback__text {
            color: #f8fafc;
        }

        .dark .pedido-feedback__field,
        .dark .pedido-feedback__item {
            background: #0f172a;
            border-color: #334155;
        }

        .dark .pedido-feedback__label {
            color: #94a3b8;
        }

        @media (max-width: 800px) {
            .pedido-feedback__summary {
                grid-template-columns: 1fr;
            }

            .pedido-feedback__field--wide {
                grid-column: span 1;
            }
        }
    </style>
@endonce

<div class="pedido-feedback">
    <div class="pedido-feedback__badges">
        <span class="pedido-feedback__badge" style="--badge-color: {{ $statusCor }}">
            {{ $pedido->tipoStatus?->nome ?? 'Sem status' }}
        </span>
        <span class="pedido-feedback__badge" style="--badge-color: {{ $notaCor }}">
            Nota {{ $feedback?->valor ?? '-' }}/5
        </span>
        <span class="pedido-feedback__badge" style="--badge-color: {{ $feedback?->reabrir_pedido ? '#b91c1c' : '#047857' }}">
            Reaberto: {{ $feedback?->reabrir_pedido ? 'Sim' : 'Não' }}
        </span>
    </div>

    <div class="pedido-feedback__summary">
        <div class="pedido-feedback__field">
            <span class="pedido-feedback__label">Protocolo</span>
            <span class="pedido-feedback__value">{{ $pedido->numero_protocolo }}</span>
        </div>

        <div class="pedido-feedback__field pedido-feedback__field--wide">
            <span class="pedido-feedback__label">Escola</span>
            <span class="pedido-feedback__value">{{ $pedido->escola?->nome ?? 'Não informada' }}</span>
        </div>

        <div class="pedido-feedback__field">
            <span class="pedido-feedback__label">Tipo</span>
            <span class="pedido-feedback__value">{{ $pedido->tipoManutencao?->nome ?? 'Não informado' }}</span>
        </div>

        <div class="pedido-feedback__field">
            <span class="pedido-feedback__label">Empresa</span>
            <span class="pedido-feedback__value">{{ $pedido->empresaContratada?->nome ?? 'Sem empresa' }}</span>
        </div>

        <div class="pedido-feedback__field">
            <span class="pedido-feedback__label">Avaliado em</span>
            <span class="pedido-feedback__value">{{ $feedback?->created_at?->format('d/m/Y H:i') ?? '-' }}</span>
        </div>
    </div>

    <div class="pedido-feedback__section">
        <span class="pedido-feedback__label">Comentário geral</span>
        <div class="pedido-feedback__text">{{ $feedback?->descricao ?: 'Nenhum comentário geral informado.' }}</div>
    </div>

    <div class="pedido-feedback__section">
        <span class="pedido-feedback__label">Avaliação por problema</span>

        <div class="pedido-feedback__items">
            @forelse($feedback?->itens ?? collect() as $item)
                @php
                    $itemPedido = $item->pedido;
                    $resultadoLabel = $item->resultado?->label() ?? (string) $item->resultado;
                    $resultadoCor = match ($item->resultado?->value) {
                        'nao_atendido' => '#b91c1c',
                        'parcialmente_atendido' => '#c2410c',
                        default => '#047857',
                    };
                @endphp

                <div class="pedido-feedback__item">
                    <div class="pedido-feedback__item-head">
                        <div>
                            <span class="pedido-feedback__label">
                                {{ $itemPedido?->numero_protocolo ?? $pedido->numero_protocolo }}
                                @if($itemPedido && ! $itemPedido->is($pedido))
                                    | adicional
                                @endif
                            </span>
                            <span class="pedido-feedback__value">
                                {{ $item->problema?->texto_problema ?? 'Problema não informado' }}
                            </span>
                            <span class="pedido-feedback__label" style="margin-top: 0.2rem;">
                                {{ $itemPedido?->tipoManutencao?->nome ?? $pedido->tipoManutencao?->nome ?? 'Tipo não informado' }}
                            </span>
                        </div>

                        <div class="pedido-feedback__badges">
                            <span class="pedido-feedback__badge" style="--badge-color: {{ $notaCor }}">
                                {{ $item->valor }}/5
                            </span>
                            <span class="pedido-feedback__badge" style="--badge-color: {{ $resultadoCor }}">
                                {{ $resultadoLabel }}
                            </span>
                        </div>
                    </div>

                    @if($item->comentario)
                        <div class="pedido-feedback__text" style="margin-top: 0.65rem;">{{ $item->comentario }}</div>
                    @endif
                </div>
            @empty
                <div class="pedido-feedback__text">Nenhuma avaliação por problema registrada.</div>
            @endforelse
        </div>
    </div>

    @if($feedback?->fotos?->isNotEmpty())
        <div class="pedido-feedback__section">
            <span class="pedido-feedback__label">Fotos da conclusão</span>
            <x-pedido.ver-fotos
                :fotos="$feedback->fotos"
                title="Fotos da Conclusão"
                class="pedido-feedback__badge"
                style="--badge-color: #047857" />
        </div>
    @endif
</div>
