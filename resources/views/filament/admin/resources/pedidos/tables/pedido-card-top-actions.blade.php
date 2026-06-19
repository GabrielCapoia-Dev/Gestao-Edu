@php
    use App\Filament\Admin\Resources\Pedidos\Tables\PedidosTable;
    use App\Models\Pedido;
    use App\Services\PedidoService;
    use App\Services\ProfilePreviewService;

    $record = $getRecord();
    $user = app(ProfilePreviewService::class)->effectiveUser();
    $service = app(PedidoService::class);
    $recordKey = $record instanceof Pedido ? (string) $record->getKey() : '';

    $canView = $record instanceof Pedido && PedidosTable::podeExibirAcaoVisualizar($user);
    $canManage = $record instanceof Pedido && PedidosTable::podeExibirAcaoGerenciar($record, $user, $service);
    $canPromoteAdditional = $record instanceof Pedido
        && $record->is_pedido_adicional
        && $service->podePromoverPedidoAdicional($record, $user);
    $canCancelAdditional = $record instanceof Pedido
        && $record->is_pedido_adicional
        && $service->podeCancelarPedidoAdicional($record, $user);
@endphp

@if ($canView || $canManage || $canPromoteAdditional || $canCancelAdditional)
    <div class="pedido-card-actions pedido-card-actions--top">
        @if ($canView)
            <x-filament::button
                color="info"
                icon="heroicon-o-eye"
                size="sm"
                wire:click.stop.prevent="mountTableAction('visualizar', '{{ $recordKey }}')"
            >
                Visualizar
            </x-filament::button>
        @endif

        @if ($canManage)
            <x-filament::button
                color="warning"
                icon="heroicon-o-pencil-square"
                size="sm"
                wire:click.stop.prevent="mountTableAction('gerenciar', '{{ $recordKey }}')"
            >
                Gerenciar
            </x-filament::button>
        @endif

        @if ($canPromoteAdditional)
            <x-filament::button
                color="warning"
                icon="heroicon-o-arrow-up-circle"
                size="sm"
                wire:click.stop.prevent="mountTableAction('promover_adicional', '{{ $recordKey }}')"
            >
                Transformar em principal
            </x-filament::button>
        @endif

        @if ($canCancelAdditional)
            <x-filament::button
                color="danger"
                icon="heroicon-o-x-circle"
                size="sm"
                wire:click.stop.prevent="mountTableAction('cancelar_adicional', '{{ $recordKey }}')"
            >
                Cancelar pedido
            </x-filament::button>
        @endif
    </div>
@endif
