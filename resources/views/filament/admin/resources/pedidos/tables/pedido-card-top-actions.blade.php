@php
    use App\Filament\Admin\Resources\Pedidos\Tables\PedidosTable;
    use App\Models\Pedido;
    use App\Services\PedidoService;

    $record = $getRecord();
    $user = auth()->user();
    $service = app(PedidoService::class);
    $recordKey = $record instanceof Pedido ? (string) $record->getKey() : '';

    $canView = $record instanceof Pedido && PedidosTable::podeExibirAcaoVisualizar($user);
    $canManage = $record instanceof Pedido && PedidosTable::podeExibirAcaoGerenciar($record, $user, $service);
@endphp

@if ($canView || $canManage)
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
    </div>
@endif
