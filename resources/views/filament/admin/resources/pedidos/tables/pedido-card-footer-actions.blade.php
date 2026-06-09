@php
    use App\Filament\Admin\Resources\Pedidos\Tables\PedidosTable;
    use App\Models\Pedido;
    use App\Services\PedidoService;

    $record = $getRecord();
    $user = auth()->user();
    $service = app(PedidoService::class);
    $recordKey = $record instanceof Pedido ? (string) $record->getKey() : '';

    $canLinkAdditional = $record instanceof Pedido && PedidosTable::podeExibirAcaoVincularAdicionais($record, $user, $service);
    $canFinish = $record instanceof Pedido && PedidosTable::podeExibirAcaoFinalizar($record, $user);
@endphp

@if ($canLinkAdditional || $canFinish)
    <div class="pedido-card-actions pedido-card-actions--footer">
        @if ($canLinkAdditional)
            <x-filament::button
                color="info"
                icon="heroicon-o-plus-circle"
                size="sm"
                wire:click.stop.prevent="mountTableAction('vincular_adicionais', '{{ $recordKey }}')"
            >
                Vincular Adicionais
            </x-filament::button>
        @endif

        @if ($canFinish)
            <x-filament::button
                color="success"
                icon="heroicon-o-check-badge"
                size="sm"
                wire:click.stop.prevent="mountTableAction('finalizar', '{{ $recordKey }}')"
            >
                Avaliar Pedido
            </x-filament::button>
        @endif
    </div>
@endif
