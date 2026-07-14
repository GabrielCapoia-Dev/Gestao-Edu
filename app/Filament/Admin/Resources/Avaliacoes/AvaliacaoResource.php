<?php

namespace App\Filament\Admin\Resources\Avaliacoes;

use App\Filament\Admin\Resources\Avaliacoes\Pages\ManageAvaliacoes;
use App\Models\Avaliacao;
use App\Models\Pauta;
use App\Models\Turma;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

class AvaliacaoResource extends Resource
{
    protected static ?string $model = Avaliacao::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';

    protected static ?string $navigationLabel = 'Avaliações';

    protected static ?string $pluralModelLabel = 'Avaliações';

    protected static ?string $modelLabel = 'Avaliação';

    protected static ?string $slug = 'avaliacoes';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados da Avaliação')
                    ->schema([
                        TextInput::make('nome')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        DatePicker::make('data_inicio')
                            ->label('Data Início')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y'),

                        DatePicker::make('data_fim')
                            ->label('Data Fim')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->afterOrEqual('data_inicio'),

                        DatePicker::make('data_inicio_preenchimento')
                            ->label('Início do preenchimento')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y'),

                        DatePicker::make('data_fim_preenchimento')
                            ->label('Fim do preenchimento')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->afterOrEqual('data_inicio_preenchimento'),

                        Select::make('status')
                            ->label('Status')
                            ->required()
                            ->options(Avaliacao::statusOptions())
                            ->default(Avaliacao::STATUS_ATIVA),
                    ])
                    ->columns(3),

                Section::make('Vínculos')
                    ->description('A avaliação precisa ter pelo menos uma pauta e uma turma vinculadas.')
                    ->schema([
                        Select::make('pautas')
                            ->label('Pautas')
                            ->relationship('pautas', 'texto')
                            ->getOptionLabelFromRecordUsing(function (Pauta $record): string {
                                return Str::limit((string) $record->texto, 120);
                            })
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->required()
                            ->helperText('Somente pautas já cadastradas podem ser vinculadas.')
                            ->columnSpanFull(),

                        Select::make('turmas')
                            ->label('Turmas')
                            ->relationship(
                                name: 'turmas',
                                titleAttribute: 'nome',
                                modifyQueryUsing: fn (Builder $query) => $query->with(['escola:id,nome', 'serie:id,nome'])
                            )
                            ->getOptionLabelFromRecordUsing(function (Turma $record): string {
                                $label = collect([
                                    $record->escola?->nome,
                                    $record->serie?->nome,
                                    'Turma ' . $record->nome,
                                ])->filter()->join(' - ');

                                return $label !== '' ? $label : (string) $record->nome;
                            })
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->required()
                            ->helperText('Selecione as turmas que deverão ser avaliadas.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['pautas', 'turmas']))
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('nome')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('data_inicio')
                    ->label('Início')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('data_fim')
                    ->label('Fim')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Avaliacao::STATUS_ATIVA => 'success',
                        Avaliacao::STATUS_INATIVA => 'gray',
                        Avaliacao::STATUS_ENCERRADA => 'warning',
                        Avaliacao::STATUS_CANCELADA => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('pautas_count')
                    ->label('Pautas')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('turmas_count')
                    ->label('Turmas')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('updated_at')
                    ->label('Atualizada em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(Avaliacao::statusOptions()),
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
            ->defaultSort('data_inicio');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAvaliacoes::route('/'),
        ];
    }

    public static function canAccess(): bool
    {
        return false;
    }
}
