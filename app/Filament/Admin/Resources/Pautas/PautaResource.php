<?php

namespace App\Filament\Admin\Resources\Pautas;

use App\Filament\Admin\Resources\Pautas\Pages\ManagePautas;
use App\Models\Alternativa;
use App\Models\Pauta;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class PautaResource extends Resource
{
    protected static ?string $model = Pauta::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';

    protected static ?string $navigationLabel = 'Pautas';

    protected static ?string $pluralModelLabel = 'Pautas';

    protected static ?string $modelLabel = 'Pauta';

    protected static ?string $slug = 'pautas';

    protected static ?string $navigationParentItem = 'Avaliações';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados da Pauta')
                    ->schema([
                        Textarea::make('texto')
                            ->label('Texto da pergunta')
                            ->required()
                            ->rows(4)
                            ->maxLength(2000)
                            ->columnSpanFull(),

                        Select::make('componente_curricular_id')
                            ->label('Componente (opcional)')
                            ->relationship('componente', 'nome')
                            ->searchable()
                            ->preload()
                            ->placeholder('Sem componente específico'),

                        Toggle::make('status')
                            ->label('Ativa')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('Alternativas')
                    ->description('Você pode vincular alternativas existentes ou criar novas durante o cadastro da pauta.')
                    ->schema([
                        Select::make('alternativas')
                            ->label('Alternativas')
                            ->relationship('alternativas', 'nome')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->required()
                            ->helperText('Selecione ao menos uma alternativa.')
                            ->createOptionForm([
                                Textarea::make('nome')
                                    ->label('Nome')
                                    ->required()
                                    ->rows(2)
                                    ->maxLength(255),
                                Textarea::make('observacao')
                                    ->label('Observação')
                                    ->rows(3)
                                    ->maxLength(1000),
                                Toggle::make('status')
                                    ->label('Ativa')
                                    ->default(true),
                            ])
                            ->createOptionUsing(function (array $data): int {
                                return Alternativa::query()->create([
                                    'nome' => Str::of((string) ($data['nome'] ?? ''))->trim()->toString(),
                                    'observacao' => Str::of((string) ($data['observacao'] ?? ''))->trim()->toString(),
                                    'status' => (bool) ($data['status'] ?? true),
                                ])->getKey();
                            })
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('texto')
                    ->label('Pauta')
                    ->wrap()
                    ->limit(120)
                    ->searchable(),

                TextColumn::make('componente.nome')
                    ->label('Componente')
                    ->placeholder('Geral')
                    ->sortable(),

                TextColumn::make('alternativas.nome')
                    ->label('Alternativas')
                    ->badge()
                    ->separator(',')
                    ->wrap(),

                IconColumn::make('status')
                    ->label('Ativa')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Atualizada em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('componente_curricular_id')
                    ->label('Componente')
                    ->relationship('componente', 'nome')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('status')
                    ->label('Status')
                    ->trueLabel('Ativas')
                    ->falseLabel('Inativas')
                    ->native(false),
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
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePautas::route('/'),
        ];
    }

    public static function canAccess(): bool
    {
        return false;
    }
}
