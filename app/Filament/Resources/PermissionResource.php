<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PermissionResource\Pages;
use App\Models\Permission;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class PermissionResource extends Resource
{
    public static function getNavigationBadge(): ?string
    {
        $value = (string) static::getModel()::count();

        if ($value > 0) {
            return $value;
        }
        return null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Quantidade de permissoes cadastradas';
    }

    protected static ?string $model = Permission::class;

    // protected static ?string $navigationIcon = 'heroicon-o-key';
    // public static ?string $navigationGroup = 'Administrativo';

    public static ?string $modelLabel = 'Permissão de execução';

    public static ?string $pluralModelLabel = 'Permissões de execução';


    public static ?string $slug = 'permissoes';

    public static ?int $navigationSort = 2;


    // public static function form(Form $form): Form
    // {
    //     return $form
    //         ->schema([
    //             Forms\Components\TextInput::make('name')
    //                 ->label('Permissão de execução')
    //                 ->required()
    //                 ->unique(ignoreRecord: true),
    //             Forms\Components\Select::make('role')
    //                 ->label('Nivel de acesso')
    //                 ->multiple()
    //                 ->relationship('roles', 'name')
    //                 ->preload(),
    //         ]);
    // }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated([10, 25, 50, 100])
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Permissão de execução')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePermissions::route('/'),
        ];
    }
}
