<?php

namespace App\Filament\Admin\Resources\Alternativas;

use App\Filament\Admin\Resources\Alternativas\Pages\ManageAlternativas;
use App\Models\Alternativa;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class AlternativaResource extends Resource
{
    protected static ?string $model = Alternativa::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::QueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';

    protected static ?string $navigationLabel = 'Alternativas';

    protected static ?string $pluralModelLabel = 'Alternativas';

    protected static ?string $modelLabel = 'Alternativa';

    protected static ?string $slug = 'alternativas';

    protected static ?string $navigationParentItem = 'Avaliações';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados da Alternativa')
                    ->schema([
                        TextInput::make('nome')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),

                        Toggle::make('status')
                            ->label('Ativa')
                            ->default(true),

                        Toggle::make('vai_no_documento')
                            ->label('Vai no documento?')
                            ->default(true),

                        Textarea::make('observacao')
                            ->label('Observação')
                            ->rows(4)
                            ->maxLength(1000)
                            ->columnSpanFull(),

                        Textarea::make('descricao_documento')
                            ->label('Descrição no documento')
                            ->rows(4)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('nome')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('observacao')
                    ->label('Observação')
                    ->limit(80)
                    ->placeholder('Sem observação')
                    ->toggleable(),

                IconColumn::make('vai_no_documento')
                    ->label('Documento')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('descricao_documento')
                    ->label('Descrição no documento')
                    ->limit(80)
                    ->placeholder('Sem descrição')
                    ->toggleable(),

                IconColumn::make('status')
                    ->label('Ativa')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('pautas_count')
                    ->label('Qtd. Pautas')
                    ->counts('pautas')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Atualizada em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('nome');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAlternativas::route('/'),
        ];
    }

    public static function canAccess(): bool
    {
        return false;
    }
}
