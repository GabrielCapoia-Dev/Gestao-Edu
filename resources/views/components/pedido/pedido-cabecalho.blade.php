@php
$feedback = $record->ultimoFeedback;
$escola = $record->escola;
$endereco = $escola
? "{$escola->logradouro}, {$escola->numero} - {$escola->bairro}, {$escola->cidade}/{$escola->estado} - CEP: {$escola->cep}"
: 'Não Informado';

$tipo = match($record->nivel_prioridade?->value) {
'indeterminado' => 'Indeterminado',
default => $record->nivel_prioridade?->value ?? 'Não Informado',
};

$prioridadeCor = match($record->nivel_prioridade?->value) {
'Emergencial' => '#ef4444',
'Corretivo' => '#f97316',
'Preventivo' => '#3b82f6',
default => '#6b7280',
};
$statusCor = '#' . ltrim($record->tipoStatus?->cor ?? '#9ca3af', '#');
@endphp

<style>
    .cab-wrapper {
        font-size: 0.875rem;
        color: #374151;
    }

    .dark .cab-wrapper {
        color: #d1d5db;
    }

    .cab-row {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem 2.5rem;
        margin-bottom: 0.75rem;
    }

    .cab-field {
        display: flex;
        flex-direction: column;
        min-width: 120px;
    }

    .cab-label {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #9ca3af;
        margin-bottom: 2px;
    }

    .dark .cab-label {
        color: #6b7280;
    }

    .cab-value {
        font-size: 0.875rem;
        color: #111827;
    }

    .dark .cab-value {
        color: #ffffff;
    }

    .cab-value-bold {
        font-weight: 600;
    }

    .cab-divider {
        border: none;
        border-top: 1px solid #e5e7eb;
        margin: 0.75rem 0;
    }

    .dark .cab-divider {
        border-top-color: #374151;
    }

    .cab-descricao {
        border-left: 2px solid #d1d5db;
        padding-left: 12px;
        color: #4b5563;
    }

    .dark .cab-descricao {
        border-left-color: #4b5563;
        color: #9ca3af;
    }

    .cab-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 5px;
        font-size: 12px;
        font-weight: 600;
        line-height: 1.6;
        background-color: color-mix(in srgb, var(--badge-color) 15%, #ffffff);
        color: var(--badge-color);
        border: 1px solid color-mix(in srgb, var(--badge-color) 40%, #ffffff);
    }

    .dark .cab-badge {
        background-color: color-mix(in srgb, var(--badge-color) 25%, #111827);
        border-color: color-mix(in srgb, var(--badge-color) 60%, #111827);
        color: #ffffff;
    }

    .cab-stars {
        display: flex;
        align-items: center;
        gap: 2px;
    }
</style>

<div class="cab-wrapper">

    {{-- Linha 1: Protocolo / Data / Status --}}
    <div class="cab-row">
        <div class="cab-field">
            <span class="cab-label">Protocolo</span>
            <span class="cab-value cab-value-bold">{{ $record->numero_protocolo ?? 'Não Informado' }}</span>
        </div>
        <div class="cab-field">
            <span class="cab-label">Data</span>
            <span class="cab-value">{{ $record->data_solicitacao?->format('d/m/Y') ?? 'Não Informado' }}</span>
        </div>
        <div class="cab-field">
            <span class="cab-label">Identificado em</span>
            <span class="cab-value">{{ $record->data_identificacao_problema?->format('d/m/Y') ?? 'Nao Informado' }}</span>
        </div>
        <div class="cab-field">
            <span class="cab-label">Status</span>
            <span class="cab-badge" style="--badge-color: {{ $statusCor }}">
                {{ $record->tipoStatus?->nome ?? 'Não Informado' }}
            </span>
        </div>
    </div>

    <hr class="cab-divider">

    {{-- Linha 2: Tipo / Prioridade --}}
    <div class="cab-row">
        <div class="cab-field">
            <span class="cab-label">Tipo</span>
            <span class="cab-value">{{ $record->tipoManutencao?->nome ?? 'Não Informado' }}</span>
        </div>
        <div class="cab-field">
            <span class="cab-label">Prioridade</span>
            <span class="cab-badge" style="--badge-color: {{ $prioridadeCor }}">{{ $tipo }}</span>
        </div>
    </div>

    {{-- Linha 3: Descrição / Fotos --}}
    <div class="cab-row">
        <div class="cab-field cab-descricao">
            <span class="cab-label">Descrição</span>
            <span class="cab-value">{{ $record->descricao_pedido ?? 'Não Informado' }}</span>
        </div>
        @can('Visualizar Arquivos de Pedidos')
        <div class="cab-field">
            <span class="cab-label">Fotos</span>
            <x-pedido.ver-fotos
                :fotos="$record->fotos"
                title="Fotos do Pedido"
                class="cab-badge"
                style="--badge-color: {{ $prioridadeCor }}" />
        </div>
        @endcan
    </div>

    {{-- Avaliação --}}
    @if($feedback)
    <hr class="cab-divider">

    <div class="cab-row">
        <div class="cab-field cab-descricao">
            <span class="cab-label">Avaliação do Serviço</span>
            <div class="cab-stars">
                @for ($i = 1; $i <= 5; $i++)
                    @if ($i <=$feedback->valor)
                    <x-heroicon-s-star class="w-4 h-4" style="width:16px;height:16px;color:#ff8018;" />
                    @else
                    <x-heroicon-s-star class="w-4 h-4" style="width:16px;height:16px;color:#6b6b6b;" />
                    @endif
                    @endfor
            </div>
        </div>

        @if($feedback->fotos?->count())
        <div class="cab-field">
            <span class="cab-label">Fotos da Avaliação</span>
            <x-pedido.ver-fotos
                :fotos="$feedback->fotos"
                title="Fotos da Avaliação"
                class="cab-badge"
                style="--badge-color: {{ $statusCor }}" />
        </div>
        @endif
    </div>

    @if($feedback->descricao)
    <div class="cab-descricao" style="margin-bottom: 0.75rem;">
        <span class="cab-value">{{ $feedback->descricao }}</span>
    </div>
    @endif
    @endif

    <hr class="cab-divider">

    {{-- Linha 4: Escola / Solicitante / Email --}}
    <div class="cab-row">
        <div class="cab-field">
            <span class="cab-label">Escola</span>
            <span class="cab-value cab-value-bold">{{ $escola?->nome ?? 'Não Informado' }}</span>
        </div>
        <div class="cab-field">
            <span class="cab-label">Solicitante</span>
            <span class="cab-value">{{ $record->nome_solicitante ?? 'Não Informado' }}</span>
        </div>
        <div class="cab-field">
            <span class="cab-label">E-mail</span>
            <span class="cab-value">{{ $record->solicitante?->email ?? 'Não Informado' }}</span>
        </div>
    </div>

    {{-- Linha 5: Telefone / Endereço --}}
    <div class="cab-row">
        <div class="cab-field">
            <span class="cab-label">Telefone</span>
            <span class="cab-value">{{ $escola?->telefone ?? 'Não Informado' }}</span>
        </div>
        <div class="cab-field" style="flex: 1; min-width: 200px;">
            <span class="cab-label">Endereço</span>
            <span class="cab-value">{{ $endereco }}</span>
        </div>
    </div>

</div>
