<?php

namespace App\Filament\Admin\Resources\BalancosEstoque;

use App\Filament\Admin\Resources\BalancosEstoque\Pages\ListBalancosEstoque;
use App\Filament\Admin\Resources\BalancosEstoque\Pages\ViewBalancoEstoque;
use App\Filament\Admin\Resources\BalancosEstoque\RelationManagers\ItensContagemRelationManager;
use App\Models\BalancoEstoque;
use App\Models\Enums\BalancoEstoqueStatus;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class BalancoEstoqueResource extends Resource
{
    protected static ?string $model = BalancoEstoque::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Alimentação Escolar';

    protected static ?string $navigationParentItem = 'Gestão de Estoque';

    protected static ?string $navigationLabel = 'Balancos';

    protected static ?string $modelLabel = 'Balanço de Estoque';

    protected static ?string $pluralModelLabel = 'Balancos de Estoque';

    protected static ?string $slug = 'balancos-estoque';

    protected static ?int $navigationSort = 8;

    protected static ?string $recordTitleAttribute = 'codigo';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DateTimePicker::make('data_agendada')
                    ->label('Data agendada')
                    ->required()
                    ->seconds(false)
                    ->native(false),
                Textarea::make('observacao_inicial')
                    ->label('Observação inicial')
                    ->rows(4)
                    ->maxLength(1500)
                    ->columnSpanFull(),
            ])
            ->columns(1);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Resumo')
                    ->schema([
                        TextEntry::make('codigo')
                            ->label('Código')
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->formatStateUsing(fn ($state) => $state?->label() ?? $state)
                            ->badge()
                            ->color(fn ($state) => $state?->color() ?? 'gray'),
                        TextEntry::make('data_agendada')
                            ->label('Agendado para')
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('criadoPor.name')
                            ->label('Criado por')
                            ->placeholder('-'),
                        TextEntry::make('iniciado_em')
                            ->label('Iniciado em')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('-'),
                        TextEntry::make('iniciadoPor.name')
                            ->label('Iniciado por')
                            ->placeholder('-'),
                        TextEntry::make('concluido_em')
                            ->label('Concluído em')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('-'),
                        TextEntry::make('cancelado_em')
                            ->label('Cancelado em')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('-'),
                    ])
                    ->columns(4),

                Section::make('Andamento')
                    ->schema([
                        TextEntry::make('itens_resumo')
                            ->label('Itens selecionados')
                            ->state(fn (BalancoEstoque $record): int => (int) ($record->itens_contagem_count ?? $record->itensContagem()->count())),
                        TextEntry::make('itens_pendentes_count')
                            ->label('Pendentes')
                            ->state(fn (BalancoEstoque $record): int => (int) ($record->itens_pendentes_count ?? $record->itensContagem()->whereNull('quantidade_contada')->count())),
                        TextEntry::make('divergencias_count')
                            ->label('Divergencias')
                            ->state(fn (BalancoEstoque $record): int => (int) ($record->divergencias_count ?? $record->itensContagem()->whereNotNull('diferenca')->where('diferenca', '<>', 0)->count())),
                        TextEntry::make('impacto_financeiro_total')
                            ->label('Impacto financeiro')
                            ->state(fn (BalancoEstoque $record): float => (float) ($record->impacto_financeiro_total ?? 0))
                            ->money('BRL')
                            ->color(fn (BalancoEstoque $record): string => $record->impacto_financeiro_total > 0 ? 'success' : ($record->impacto_financeiro_total < 0 ? 'danger' : 'gray')),
                    ])
                    ->columns(4),

                Section::make('Observação Inicial')
                    ->schema([
                        TextEntry::make('observacao_inicial')
                            ->label('')
                            ->placeholder('Sem observação inicial.')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (BalancoEstoque $record): bool => filled($record->observacao_inicial))
                    ->columns(1),

                Section::make('Timeline do Balanço')
                    ->schema([
                        RepeatableEntry::make('eventos')
                            ->label('')
                            ->placeholder('Nenhum evento registrado.')
                            ->table([
                                TableColumn::make('Data'),
                                TableColumn::make('Evento'),
                                TableColumn::make('Responsavel'),
                                TableColumn::make('Descrição'),
                            ])
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Data')
                                    ->dateTime('d/m/Y H:i'),
                                TextEntry::make('tipo')
                                    ->label('Evento')
                                    ->formatStateUsing(fn ($state) => $state?->label() ?? $state)
                                    ->badge()
                                    ->color(fn ($state) => $state?->color() ?? 'gray'),
                                TextEntry::make('usuario.name')
                                    ->label('Responsavel')
                                    ->placeholder('Sistema'),
                                TextEntry::make('descricao')
                                    ->label('Descrição'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['criadoPor']))
            ->defaultSort('data_agendada', 'desc')
            ->recordUrl(fn (BalancoEstoque $record): string => static::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => $state?->label() ?? $state)
                    ->badge()
                    ->color(fn ($state) => $state?->color() ?? 'gray')
                    ->sortable(),
                TextColumn::make('data_agendada')
                    ->label('Data agendada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('iniciado_em')
                    ->label('Iniciado em')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('concluido_em')
                    ->label('Concluído em')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('itens_contagem_count')
                    ->label('Itens')
                    ->badge()
                    ->sortable(),
                TextColumn::make('itens_pendentes_count')
                    ->label('Pendentes')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'success')
                    ->sortable(),
                TextColumn::make('divergencias_count')
                    ->label('Divergencias')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'success')
                    ->sortable(),
                TextColumn::make('impacto_financeiro_total')
                    ->label('Impacto financeiro')
                    ->state(fn (BalancoEstoque $record): float => (float) ($record->impacto_financeiro_total ?? 0))
                    ->money('BRL')
                    ->color(fn (BalancoEstoque $record): string => $record->impacto_financeiro_total > 0 ? 'success' : ($record->impacto_financeiro_total < 0 ? 'danger' : 'gray'))
                    ->sortable(),
                TextColumn::make('criadoPor.name')
                    ->label('Responsavel')
                    ->placeholder('-')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(
                        collect(BalancoEstoqueStatus::cases())
                            ->mapWithKeys(fn (BalancoEstoqueStatus $status) => [$status->value => $status->label()])
                            ->toArray()
                    ),
                SelectFilter::make('criado_por_id')
                    ->label('Responsavel')
                    ->relationship('criadoPor', 'name'),
                Filter::make('periodo')
                    ->label('Período agendado')
                    ->schema([
                        DatePicker::make('data_inicio')
                            ->label('De'),
                        DatePicker::make('data_fim')
                            ->label('Até'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['data_inicio'] ?? null),
                                fn (Builder $builder) => $builder->whereDate('data_agendada', '>=', $data['data_inicio'])
                            )
                            ->when(
                                filled($data['data_fim'] ?? null),
                                fn (Builder $builder) => $builder->whereDate('data_agendada', '<=', $data['data_fim'])
                            );
                    }),
            ])
            ->recordActions([]);
    }

    public static function getRelations(): array
    {
        return [
            ItensContagemRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBalancosEstoque::route('/'),
            'view' => ViewBalancoEstoque::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'criadoPor',
                'iniciadoPor',
                'concluidoPor',
                'canceladoPor',
                'eventos.usuario',
            ])
            ->withCount([
                'itens as itens_contagem_count' => fn (Builder $query) => $query->where('incluido_na_contagem', true),
                'itens as itens_pendentes_count' => fn (Builder $query) => $query
                    ->where('incluido_na_contagem', true)
                    ->whereNull('quantidade_contada'),
                'itens as divergencias_count' => fn (Builder $query) => $query
                    ->where('incluido_na_contagem', true)
                    ->whereNotNull('diferenca')
                    ->where('diferenca', '<>', 0),
            ])
            ->withSum([
                'itens as impacto_financeiro_total' => fn (Builder $query) => $query->where('incluido_na_contagem', true),
            ], 'valor_impacto');
    }
}
