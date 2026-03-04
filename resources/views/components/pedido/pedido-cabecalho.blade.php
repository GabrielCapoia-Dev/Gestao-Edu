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
    .margin-info {
        margin-right: 1rem;
    }

    .badge {
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

    .badge:hover {
        background-color: color-mix(in srgb, var(--badge-color) 15%, #cccccc);
    }

    .dark .badge {
        background-color: color-mix(in srgb, var(--badge-color) 25%, #111827);
        border-color: color-mix(in srgb, var(--badge-color) 60%, #111827);
        color: #ffffff;
    }

    .dark .text-value-record {
        color: #ffffff;
    }

    .label-info {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        display: block;
        margin-bottom: 2px;
        color: #9ca3af;
    }

    .dark .label-info {
        color: #6b7280;
    }

    .section-divider {
        border: none;
        border-top: 1px solid #e5e7eb;
    }

    .dark .section-divider {
        border-top-color: #374151;
    }

    .descricao-block {
        border-left: 2px solid #d1d5db;
        padding-left: 12px;
        color: #4b5563;
    }

    .dark .descricao-block {
        border-left-color: #4b5563;
        color: #9ca3af;
    }
</style>

<div class="space-y-4 text-sm text-gray-700 dark:text-gray-300">

    <div class="flex flex-wrap gap-x-10 gap-y-2">
        <div class="margin-info">
            <span class="label-info">Protocolo:</span>
            <span class="font-semibold text-value-record">{{ $record->numero_protocolo ?? 'Não Informado' }}</span>
        </div>
        <div class="margin-info">
            <span class="label-info">Data:</span>
            <span class="text-value-record"> {{ $record->data_solicitacao?->format('d/m/Y') ?? 'Não Informado' }}</span>
        </div>
        <div class="margin-info">
            <span class="label-info">Status:</span>
            <span class="badge" style="--badge-color: {{ $statusCor }}">
                {{ $record->tipoStatus?->nome ?? 'Não Informado' }}
            </span>
        </div>
    </div>

    <hr class="section-divider">

    <div class="flex flex-wrap gap-x-10 gap-y-2">
        <div class="margin-info">
            <span class="label-info">Tipo:</span>
            <span class="text-value-record">{{ $record->tipoManutencao?->nome ?? 'Não Informado' }}</span>
        </div>
        <div class="margin-info">
            <span class="label-info">Prioridade:</span>
            <span class="badge" style="--badge-color: {{ $prioridadeCor }}">
                {{ $tipo }}
            </span>
        </div>

    </div>


    <div class="flex flex-wrap gap-x-10 gap-y-2">

        <div class="margin-info descricao-block flex justify-between items-start gap-4">
            <div>
                <span class="label-info">Descrição</span>
                <span class="text-value-record block">
                    {{ $record->descricao_pedido ?? 'Não Informado' }}
                </span>
            </div>


        </div>
        @can('Visualizar Arquivos de Pedidos')
        <div class="margin-info">
            <span class="label-info">Fotos:</span>
            <x-pedido.ver-fotos
                :pedido="$record"
                class="badge"
                style="--badge-color: {{ $prioridadeCor }}" />
        </div>
        @endcan
    </div>

    @if($feedback)

    <hr class="section-divider">

    <div class="space-y-2">

        <div class="flex flex-wrap gap-x-10 gap-y-2">

            <div class="margin-info descricao-block flex justify-between items-start gap-4">
                <div class="margin-info">
                    <span class="label-info">Avaliação do Serviço</span>

                    <div class="flex items-center gap-1">
                        @for ($i = 1; $i <= 5; $i++)
                            @if ($i <=$feedback->valor)
                            <x-heroicon-s-star class="w-4 h-4 text-yellow-400" style="color: #ff8018;" />
                            @else
                            <x-heroicon-s-star class="w-4 h-4 text-gray-300" style="color: #6b6b6b;" />
                            @endif
                            @endfor
                    </div>
                </div>

                @if($feedback && $feedback->fotos?->count())

                <div class="margin-info">
                    <span class="label-info">Fotos da Avaliação:</span>

                    <x-pedido.ver-fotos
                        :fotos="$feedback->fotos"
                        :pedido="$record"
                        class="badge"
                        style="--badge-color: {{ $statusCor }}" />
                </div>

                @endif
            </div>
        </div>

        @if($feedback->descricao)
        <div class="descricao-block">
            {{ $feedback->descricao }}
        </div>
        @endif



    </div>
    @endif

    <hr class="section-divider">

    <div class="flex flex-wrap gap-x-10 gap-y-2">
        <div class="margin-info">
            <span class="label-info">Escola:</span>
            <span class="font-semibold text-value-record">{{ $escola?->nome ?? 'Não Informado' }}</span>
        </div>
        <div class="margin-info">
            <span class="label-info">Solicitante:</span>
            <span class="text-value-record">{{ $record->nome_solicitante ?? 'Não Informado' }}</span>
        </div>
        <div class="margin-info">
            <span class="label-info">E-mail:</span>
            <span class="text-value-record">{{ $record->solicitante?->email ?? 'Não Informado' }}</span>
        </div>
    </div>

    <div class="flex flex-wrap gap-x-10 gap-y-2">
        <div class="margin-info">
            <span class="label-info">Telefone:</span>
            <span class="text-value-record">{{ $escola?->telefone ?? 'Não Informado' }}</span>
        </div>
        <div class="margin-info">
            <span class="label-info">Endereço:</span>
            <span class="text-value-record">{{ $endereco }}</span>
        </div>
    </div>

</div>