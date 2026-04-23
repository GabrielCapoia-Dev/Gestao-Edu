<?php

namespace App\Filament\Admin\Resources\Series;

use App\Filament\Admin\Resources\Series\Pages\ManageSeries;
use App\Models\Serie;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
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

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';
    protected static ?string $navigationParentItem = 'Turmas';



    public static function service(): Service
    {
        return app(Service::class);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Dados da Série')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('codigo')
                            ->label('Código')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->placeholder('Ex: SER001'),

                        TextInput::make('nome')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->placeholder('Ex: 1º Ano, 2º Ano, etc.'),
                    ])
                    ->columns(2),

                Section::make('Componentes Curriculares')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('componentesCurriculares')
                            ->label('Componentes')
                            ->relationship('componentesCurriculares', 'nome')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->placeholder('Selecione os componentes desta série')
                            ->helperText('Ex: Português, Matemática, História...')
                            ->columnSpanFull(),
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
