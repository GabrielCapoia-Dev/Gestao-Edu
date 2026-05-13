@once
    <style>
        .pedido-adicionais-modal {
            display: flex;
            flex-direction: column;
            gap: 0.875rem;
        }

        .pedido-adicionais-modal__card {
            border: 1px solid #e5e7eb;
            background: #ffffff;
            border-radius: 0.875rem;
            padding: 1rem;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            transition:
                border-color 180ms ease,
                box-shadow 180ms ease,
                transform 180ms ease;
        }

        .pedido-adicionais-modal__card:hover {
            border-color: #cbd5e1;
            box-shadow: 0 14px 32px rgba(15, 23, 42, 0.10);
            transform: translateY(-1px);
        }

        .pedido-adicionais-modal__header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.875rem;
            flex-wrap: wrap;
        }

        .pedido-adicionais-modal__protocolo {
            font-size: 0.925rem;
            font-weight: 700;
            color: #111827;
            line-height: 1.25rem;
        }

        .pedido-adicionais-modal__tipo {
            margin-top: 0.15rem;
            font-size: 0.825rem;
            color: #64748b;
            line-height: 1.15rem;
        }

        .pedido-adicionais-modal__status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 0.35rem 0.65rem;
            font-size: 0.72rem;
            font-weight: 700;
            color: #ffffff;
            line-height: 1;
            min-height: 1.6rem;
            box-shadow: inset 0 -1px 0 rgba(0, 0, 0, 0.14);
        }

        .pedido-adicionais-modal__descricao {
            margin-top: 0.9rem;
            font-size: 0.875rem;
            line-height: 1.45rem;
            color: #374151;
            white-space: pre-line;
        }

        .pedido-adicionais-modal__problemas {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            margin-top: 0.9rem;
        }

        .pedido-adicionais-modal__problema {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            background: #f1f5f9;
            color: #475569;
            padding: 0.35rem 0.65rem;
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1rem;
        }

        .pedido-adicionais-modal__empty {
            border: 1px dashed #cbd5e1;
            border-radius: 0.875rem;
            padding: 1.5rem;
            color: #64748b;
            background: #f8fafc;
            font-size: 0.875rem;
            text-align: center;
        }

        .dark .pedido-adicionais-modal__card {
            border-color: #374151;
            background: #111827;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
        }

        .dark .pedido-adicionais-modal__card:hover {
            border-color: #4b5563;
            box-shadow: 0 14px 32px rgba(0, 0, 0, 0.34);
        }

        .dark .pedido-adicionais-modal__protocolo {
            color: #ffffff;
        }

        .dark .pedido-adicionais-modal__tipo {
            color: #cbd5e1;
        }

        .dark .pedido-adicionais-modal__descricao {
            color: #e5e7eb;
        }

        .dark .pedido-adicionais-modal__problema {
            background: #1f2937;
            color: #e5e7eb;
        }

        .dark .pedido-adicionais-modal__empty {
            border-color: #374151;
            background: #111827;
            color: #9ca3af;
        }

        @media (max-width: 640px) {
            .pedido-adicionais-modal__card {
                padding: 0.875rem;
            }

            .pedido-adicionais-modal__header {
                gap: 0.65rem;
            }

            .pedido-adicionais-modal__status {
                width: 100%;
            }
        }
    </style>
@endonce

<div class="pedido-adicionais-modal">
    @forelse ($adicionais as $adicional)
        <div class="pedido-adicionais-modal__card">
            <div class="pedido-adicionais-modal__header">
                <div>
                    <div class="pedido-adicionais-modal__protocolo">
                        {{ $adicional->numero_protocolo }}
                    </div>

                    <div class="pedido-adicionais-modal__tipo">
                        {{ $adicional->tipoManutencao?->nome ?? 'Tipo não informado' }}
                    </div>
                </div>

                <span
                    class="pedido-adicionais-modal__status"
                    style="background-color: {{ $adicional->tipoStatus?->cor ?? '#64748b' }}"
                >
                    {{ $adicional->tipoStatus?->nome ?? 'Pedido Adicional' }}
                </span>
            </div>

            <div class="pedido-adicionais-modal__descricao">
                {{ $adicional->descricao_pedido }}
            </div>

            @if ($adicional->problemas->isNotEmpty())
                <div class="pedido-adicionais-modal__problemas">
                    @foreach ($adicional->problemas as $problema)
                        <span class="pedido-adicionais-modal__problema">
                            {{ $problema->texto_problema }}
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    @empty
        <div class="pedido-adicionais-modal__empty">
            Nenhum pedido adicional vinculado.
        </div>
    @endforelse
</div>