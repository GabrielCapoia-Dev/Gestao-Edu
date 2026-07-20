<?php

namespace App\Filament\Admin\Resources\Avisos;

use App\Filament\Admin\Resources\Avisos\Pages\CreateAviso;
use App\Filament\Admin\Resources\Avisos\Pages\EditAviso;
use App\Filament\Admin\Resources\Avisos\Pages\ListAvisos;
use App\Filament\Admin\Resources\Avisos\Schemas\AvisoForm;
use App\Filament\Admin\Resources\Avisos\Tables\AvisosTable;
use App\Models\Aviso;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class AvisoResource extends Resource
{
    protected static ?string $model = Aviso::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'titulo';

    protected static ?string $modelLabel = 'Aviso';

    protected static ?string $pluralModelLabel = 'Avisos';

    public static function form(Schema $schema): Schema
    {
        return AvisoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AvisosTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();
        $policy = Gate::getPolicyFor(static::getModel());

        if (! $user || ! $policy || ! method_exists($policy, 'applyViewAnyScope')) {
            return $query->whereRaw('1 = 0');
        }

        return $policy->applyViewAnyScope($user, $query)
            ->with([
                'criadoPor:id,name',
                'atualizadoPor:id,name',
                'publicoAlvo' => fn (Builder $publicos): Builder => $publicos
                    ->with([
                        'escopoEscolas:id',
                        'escopoSetores:id',
                    ])
                    ->withCount([
                        'usuarios',
                        'roles',
                        'permissoes',
                        'funcoesAdministrativas',
                        'escolas',
                        'setores',
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAvisos::route('/'),
            'create' => CreateAviso::route('/create'),
            'edit' => EditAviso::route('/{record}/edit'),
        ];
    }
}
