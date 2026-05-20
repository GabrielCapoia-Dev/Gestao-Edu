@php
    $record?->loadMissing([
        'escola',
        'solicitante',
        'tipoStatus',
        'tipoManutencao',
        'problemas.opcao',
        'fotos',
        'fotosConclusao',
        'ultimoFeedback.itens.problema',
        'ultimoFeedback.fotos',
        'pedidosAdicionais',
    ]);

    $feedback = $record->ultimoFeedback;
    $escola = $record->escola;
    $endereco = $escola
        ? "{$escola->logradouro}, {$escola->numero} - {$escola->bairro}, {$escola->cidade}/{$escola->estado} - CEP: {$escola->cep}"
        : 'Nao informado';

    $prioridadeLabel = match($record->nivel_prioridade?->value) {
        'indeterminado' => 'Indeterminado',
        default => $record->nivel_prioridade?->value ?? 'Nao informado',
    };

    $prioridadeCor = match($record->nivel_prioridade?->value) {
        'Emergencial' => '#b91c1c',
        'Corretivo' => '#c2410c',
        'Preventivo' => '#1d4ed8',
        default => '#4b5563',
    };

    $statusCor = '#' . ltrim($record->tipoStatus?->cor ?? '#6b7280', '#');
    $problemas = $record->problemas->pluck('texto_problema')->filter()->values();
    $adicionaisCount = $record->pedidosAdicionais->count();
@endphp

@once
    <style>
        .pedido-header {
            color: #334155;
            display: grid;
            gap: 1rem;
        }

        .dark .pedido-header {
            color: #d1d5db;
        }

        .pedido-header__top {
            align-items: flex-start;
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            justify-content: space-between;
        }

        .pedido-header__identity {
            display: grid;
            gap: 0.2rem;
            min-width: 14rem;
        }

        .pedido-header__protocol {
            color: #0f172a;
            font-size: 1.05rem;
            font-weight: 750;
            line-height: 1.25;
        }

        .dark .pedido-header__protocol {
            color: #ffffff;
        }

        .pedido-header__subtitle {
            color: #64748b;
            font-size: 0.82rem;
            line-height: 1.35;
        }

        .dark .pedido-header__subtitle {
            color: #cbd5e1;
        }

        .pedido-header__badges,
        .pedido-header__problems,
        .pedido-header__photos {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
        }

        .pedido-header__badge {
            align-items: center;
            background: color-mix(in srgb, var(--badge-color) 12%, #ffffff);
            border: 1px solid color-mix(in srgb, var(--badge-color) 38%, #ffffff);
            border-radius: 999px;
            color: var(--badge-color);
            display: inline-flex;
            font-size: 0.75rem;
            font-weight: 700;
            line-height: 1rem;
            min-height: 1.65rem;
            padding: 0.28rem 0.65rem;
        }

        .dark .pedido-header__badge {
            background: color-mix(in srgb, var(--badge-color) 22%, #111827);
            border-color: color-mix(in srgb, var(--badge-color) 58%, #111827);
            color: #ffffff;
        }

        .pedido-header__grid {
            display: grid;
            gap: 0.75rem;
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .pedido-header__field {
            min-width: 0;
        }

        .pedido-header__field--wide {
            grid-column: span 2;
        }

        .pedido-header__label {
            color: #64748b;
            display: block;
            font-size: 0.68rem;
            font-weight: 750;
            letter-spacing: 0.04em;
            line-height: 1rem;
            margin-bottom: 0.15rem;
            text-transform: uppercase;
        }

        .dark .pedido-header__label {
            color: #94a3b8;
        }

        .pedido-header__value {
            color: #111827;
            display: block;
            font-size: 0.86rem;
            line-height: 1.35;
            overflow-wrap: anywhere;
        }

        .dark .pedido-header__value {
            color: #f8fafc;
        }

        .pedido-header__description {
            border-left: 3px solid #cbd5e1;
            padding-left: 0.75rem;
            white-space: pre-line;
        }

        .dark .pedido-header__description {
            border-color: #475569;
        }

        .pedido-header__section {
            border-top: 1px solid #e5e7eb;
            display: grid;
            gap: 0.5rem;
            padding-top: 0.85rem;
        }

        .dark .pedido-header__section {
            border-color: #334155;
        }

        .pedido-header__rating {
            align-items: center;
            display: flex;
            gap: 0.15rem;
        }

        @media (max-width: 900px) {
            .pedido-header__grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .pedido-header__grid {
                grid-template-columns: 1fr;
            }

            .pedido-header__field--wide {
                grid-column: span 1;
            }
        }
    </style>
@endonce

<div class="pedido-header">
    <div class="pedido-header__top">
        <div class="pedido-header__identity">
            <span class="pedido-header__protocol">{{ $record->numero_protocolo ?? 'Nao informado' }}</span>
            <span class="pedido-header__subtitle">
                {{ $record->tipoManutencao?->nome ?? 'Tipo nao informado' }}
                @if($adicionaisCount > 0)
                    | {{ $adicionaisCount }} adicional(is)
                @endif
            </span>
        </div>

        <div class="pedido-header__badges">
            <span class="pedido-header__badge" style="--badge-color: {{ $statusCor }}">
                {{ $record->tipoStatus?->nome ?? 'Sem status' }}
            </span>
            <span class="pedido-header__badge" style="--badge-color: {{ $prioridadeCor }}">
                {{ $prioridadeLabel }}
            </span>
        </div>
    </div>

    <div class="pedido-header__grid">
        <div class="pedido-header__field">
            <span class="pedido-header__label">Solicitado em</span>
            <span class="pedido-header__value">{{ $record->data_solicitacao?->format('d/m/Y') ?? 'Nao informado' }}</span>
        </div>

        <div class="pedido-header__field">
            <span class="pedido-header__label">Identificado em</span>
            <span class="pedido-header__value">{{ $record->data_identificacao_problema?->format('d/m/Y') ?? 'Nao informado' }}</span>
        </div>

        <div class="pedido-header__field">
            <span class="pedido-header__label">Previsto para</span>
            <span class="pedido-header__value">{{ $record->data_prevista?->format('d/m/Y') ?? 'Sem previsao' }}</span>
        </div>

        <div class="pedido-header__field">
            <span class="pedido-header__label">Concluido em</span>
            <span class="pedido-header__value">{{ $record->data_entrega?->format('d/m/Y') ?? 'Em andamento' }}</span>
        </div>

        <div class="pedido-header__field pedido-header__field--wide">
            <span class="pedido-header__label">Escola</span>
            <span class="pedido-header__value">{{ $escola?->nome ?? 'Nao informado' }}</span>
        </div>

        <div class="pedido-header__field">
            <span class="pedido-header__label">Solicitante</span>
            <span class="pedido-header__value">{{ $record->nome_solicitante ?? 'Nao informado' }}</span>
        </div>

        <div class="pedido-header__field">
            <span class="pedido-header__label">E-mail</span>
            <span class="pedido-header__value">{{ $record->solicitante?->email ?? 'Nao informado' }}</span>
        </div>

        <div class="pedido-header__field">
            <span class="pedido-header__label">Telefone</span>
            <span class="pedido-header__value">{{ $escola?->telefone ?? 'Nao informado' }}</span>
        </div>

        <div class="pedido-header__field pedido-header__field--wide">
            <span class="pedido-header__label">Endereco</span>
            <span class="pedido-header__value">{{ $endereco }}</span>
        </div>
    </div>

    <div class="pedido-header__section">
        <span class="pedido-header__label">Problemas identificados</span>
        <div class="pedido-header__problems">
            @forelse($problemas as $problema)
                <span class="pedido-header__badge" style="--badge-color: #475569">{{ $problema }}</span>
            @empty
                <span class="pedido-header__value">Nenhum problema segmentado.</span>
            @endforelse
        </div>
    </div>

    <div class="pedido-header__section">
        <span class="pedido-header__label">Descricao do pedido</span>
        <span class="pedido-header__value pedido-header__description">{{ $record->descricao_pedido ?? 'Nao informado' }}</span>
    </div>

    @can('Visualizar Arquivos de Pedidos')
        <div class="pedido-header__section">
            <span class="pedido-header__label">Fotos</span>
            <div class="pedido-header__photos">
                <x-pedido.ver-fotos
                    :fotos="$record->fotos"
                    title="Fotos do Pedido"
                    class="pedido-header__badge"
                    style="--badge-color: #475569" />

                @if($record->fotosConclusao->isNotEmpty())
                    <x-pedido.ver-fotos
                        :fotos="$record->fotosConclusao"
                        title="Fotos da Conclusao"
                        class="pedido-header__badge"
                        style="--badge-color: #047857" />
                @endif
            </div>
        </div>
    @endcan

    @if($feedback)
        <div class="pedido-header__section">
            <span class="pedido-header__label">Avaliacao do servico</span>
            <div class="pedido-header__rating">
                @for ($i = 1; $i <= 5; $i++)
                    @if ($i <= $feedback->valor)
                        <x-heroicon-s-star style="width:16px;height:16px;color:#f97316;" />
                    @else
                        <x-heroicon-s-star style="width:16px;height:16px;color:#94a3b8;" />
                    @endif
                @endfor
                <span class="pedido-header__value" style="margin-left: 0.35rem;">{{ $feedback->valor }}/5</span>
            </div>

            @if($feedback->descricao)
                <span class="pedido-header__value pedido-header__description">{{ $feedback->descricao }}</span>
            @endif
        </div>
    @endif
</div>
