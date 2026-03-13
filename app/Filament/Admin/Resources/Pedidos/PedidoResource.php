<?php

namespace App\Filament\Admin\Resources\Pedidos;

use App\Filament\Admin\Resources\Pedidos\Pages\CreatePedido;
use App\Filament\Admin\Resources\Pedidos\Pages\EditPedido;
use App\Filament\Admin\Resources\Pedidos\Pages\ListPedidos;
use App\Filament\Admin\Resources\Pedidos\RelationManagers\ArquivosRelationManager;
use App\Filament\Admin\Resources\Pedidos\RelationManagers\HistoricosRelationManager;
use App\Filament\Admin\Resources\Pedidos\Schemas\PedidoCriacaoForm;
use App\Filament\Admin\Resources\Pedidos\Schemas\PedidoGestaoForm;
use App\Filament\Admin\Resources\Pedidos\Tables\PedidosTable;
use App\Models\Pedido;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use App\Filament\Resources\PedidoResource\Pages;
use App\Services\PedidoService as Service;
use Filament\Forms\Form;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use UnitEnum;


class PedidoResource extends Resource
{
    protected static ?string $model = Pedido::class;
    protected static ?string $modelLabel = 'Pedido';
    protected static ?string $pluralModelLabel = 'Pedidos';
    protected static ?string $slug = 'pedidos';
    protected static ?int $navigationSort = 1;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentList;
    protected static string|UnitEnum|null $navigationGroup = 'Manutenção';

    protected static ?string $recordTitleAttribute = 'numero_protocolo';

    public static function form(Schema $schema): Schema
    {
        $service = app(Service::class);
        $user    = Auth::user();

        if ($schema->getOperation() === 'edit' && $service->podeGerenciarPedidos($user)) {
            return PedidoGestaoForm::configure($schema);
        }
        return PedidoCriacaoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PedidosTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPedidos::route('/'),
            'create' => CreatePedido::route('/create'),
            'edit' => EditPedido::route('/{record}/edit'),
        ];
    }



    public static function getNavigationBadge(): ?string
    {
        return app(Service::class)->contarPedidosNovos(Auth::user());
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getRelations(): array
    {
        /** @var User $user */
        $user = Auth::user();

        $relations = [];

        if ($user?->hasPermissionTo('Visualizar Histórico de Pedidos')) {
            $relations[] = HistoricosRelationManager::class;
        }

        if ($user?->hasPermissionTo('Visualizar Arquivos de Pedidos')) {
            $relations[] = ArquivosRelationManager::class;
        }

        return $relations;
    }

    public static function getEloquentQuery(): Builder
    {
        return app(Service::class)->queryPorPerfil(
            parent::getEloquentQuery(),
            Auth::user()
        );
    }
}
