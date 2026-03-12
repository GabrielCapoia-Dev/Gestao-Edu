<?php

namespace App\Filament\Admin\Resources\Series;

use App\Filament\Admin\Resources\Series\Pages\ManageSeries;
use App\Models\Serie;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use App\Services\SerieService as Service;
use Illuminate\Support\Facades\Auth;
use UnitEnum;


class SerieResource extends Resource
{
    protected static ?string $model = Serie::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentList;

    protected static ?string $recordTitleAttribute = 'nome';

    protected static ?string $navigationLabel = 'Series';

    protected static ?string $pluralModelLabel = 'Series';

    protected static ?string $modelLabel = 'Serie';

    protected static ?string $slug = 'series';

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';
    protected static ?string $navigationParentItem = 'Turmas';



    public static function service(): Service
    {
        return app(Service::class);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                TextInput::make('codigo')
                    ->label('Código')
                    ->required()
                    ->maxLength(3)
                    ->minLength(3),

                TextInput::make('nome')
                    ->label('Nome:')
                    ->required()
                    ->minLength(3)
                    ->maxLength(100)
                    ->rule('regex:/^[\p{L}\p{N}]+(?: [\p{L}\p{N}]+)*$/u')
                    ->validationMessages([
                        'regex' => 'Use apenas letras, sem caracteres especiais.',
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::service()->configurarTabela($table, Auth::user());
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSeries::route('/'),
        ];
    }
}
