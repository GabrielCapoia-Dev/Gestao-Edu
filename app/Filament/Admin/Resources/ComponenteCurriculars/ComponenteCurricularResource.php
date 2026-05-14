<?php

namespace App\Filament\Admin\Resources\ComponenteCurriculars;

use App\Filament\Admin\Resources\ComponenteCurriculars\Pages\ManageComponenteCurriculars;
use App\Models\ComponenteCurricular;
use BackedEnum;
use UnitEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;

class ComponenteCurricularResource extends Resource
{
    protected static ?string $model = ComponenteCurricular::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BookmarkSquare;
    public static ?string $modelLabel = 'Componente Curricular';
    protected static ?string $navigationParentItem = 'Turmas';
    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';
    public static ?string $pluralModelLabel = 'Componentes Curriculares';
    public static ?string $slug = 'componentes-curriculares';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados do Componente')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('codigo')
                            ->label('Código')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (?string $operation): bool => $operation !== 'create')
                            ->helperText('Gerado automaticamente ao criar o componente.')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('nome')
                            ->label('Nome')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->placeholder('Ex: Matemática'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('nome')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('series.nome')
                    ->label('Séries Vinculadas')
                    ->badge()
                    ->separator(',')
                    ->wrap()
                    ->toggleable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Criado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Atualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageComponenteCurriculars::route('/'),
        ];
    }
}
