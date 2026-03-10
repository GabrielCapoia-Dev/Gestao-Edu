<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PedidoResource\Pages;
use App\Models\Pedido;
use App\Services\PedidoService as Service;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class PedidoResource extends Resource
{
    protected static ?string $model = Pedido::class;
    // protected static ?string $navigationGroup = 'Manutenção';
    // protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $modelLabel = 'Pedido';
    protected static ?string $pluralModelLabel = 'Pedidos';
    protected static ?string $slug = 'pedidos';
    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        return app(Service::class)->contarPedidosNovos(Auth::user());
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    // public static function form(Form $form): Form
    // {
    //     $service = app(Service::class);
    //     $user    = Auth::user();

    //     if ($form->getOperation() === 'edit' && $service->podeGerenciarPedidos($user)) {
    //         return $service->configurarFormularioGestao($form);
    //     }

    //     return $service->configurarFormularioCriacao($form);
    // }

    public static function table(Table $table): Table
    {
        return app(Service::class)->configurarTabela($table, Auth::user());
    }

    public static function getRelations(): array
    {
        /** @var User $user */
        $user = Auth::user();

        $relations = [];

        if ($user?->hasPermissionTo('Visualizar Histórico de Pedidos')) {
            $relations[] = PedidoResource\RelationManagers\HistoricosRelationManager::class;
        }

        if ($user?->hasPermissionTo('Visualizar Arquivos de Pedidos')) {
            $relations[] = PedidoResource\RelationManagers\ArquivosRelationManager::class;
        }

        return $relations;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPedidos::route('/'),
            'create' => Pages\CreatePedido::route('/create'),
            'view' => Pages\ViewPedido::route('/{record}'),
            'edit' => Pages\EditPedido::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return app(Service::class)->queryPorPerfil(
            parent::getEloquentQuery(),
            Auth::user()
        );
    }
}
