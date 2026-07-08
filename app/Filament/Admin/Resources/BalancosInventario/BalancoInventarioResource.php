<?php

namespace App\Filament\Admin\Resources\BalancosInventario;

use App\Filament\Admin\Resources\BalancosInventario\Pages\ListBalancosInventario;
use App\Filament\Admin\Resources\BalancosInventario\Pages\ViewBalancoInventario;
use App\Filament\Admin\Resources\BalancosInventario\RelationManagers\ItensContagemInventarioRelationManager;
use App\Models\BalancoInventario;
use App\Models\Enums\BalancoInventarioStatus;
use App\Services\Inventario\InventarioContextService;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class BalancoInventarioResource extends Resource
{
    protected static ?string $model = BalancoInventario::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|UnitEnum|null $navigationGroup = 'Alimentação Escolar';

    protected static ?string $navigationLabel = 'Balanços do Inventário';

    protected static ?string $modelLabel = 'Balanço de Inventário';

    protected static ?string $pluralModelLabel = 'Balanços de Inventário';

    protected static ?string $slug = 'balancos-inventario';

    protected static ?int $navigationSort = 13;

    protected static ?string $recordTitleAttribute = 'codigo';

    public static function form(Schema $schema): Schema
    {
        $ehGestorGeral = app(InventarioContextService::class)->ehGestorGeral(Auth::user());

        return $schema
            ->components([
                Select::make('inventario_id')
                    ->label('Inventário')
                    ->options(static::opcoesInventario())
                    ->searchable()
                    ->preload()
                    ->required($ehGestorGeral)
                    ->visible($ehGestorGeral),
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
                        TextEntry::make('inventario.escola.nome')
                            ->label('Escola')
                            ->placeholder('-'),
                        TextEntry::make('inventario.nome')
                            ->label('Inventário')
                            ->placeholder('-'),
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
                    ->columns(5),
                Section::make('Andamento')
                    ->schema([
                        TextEntry::make('itens_resumo')
                            ->label('Itens selecionados')
                            ->state(fn (BalancoInventario $record): int => (int) ($record->itens_contagem_count ?? $record->itensContagem()->count())),
                        TextEntry::make('itens_pendentes_count')
                            ->label('Pendentes')
                            ->state(fn (BalancoInventario $record): int => (int) ($record->itens_pendentes_count ?? $record->itensContagem()->whereNull('quantidade_contada')->count())),
                        TextEntry::make('divergencias_count')
                            ->label('Divergências')
                            ->state(fn (BalancoInventario $record): int => (int) ($record->divergencias_count ?? $record->itensContagem()->whereNotNull('diferenca')->where('diferenca', '<>', 0)->count())),
                        TextEntry::make('impacto_financeiro_total')
                            ->label('Impacto financeiro')
                            ->state(fn (BalancoInventario $record): float => (float) ($record->impacto_financeiro_total ?? 0))
                            ->money('BRL')
                            ->color(fn (BalancoInventario $record): string => $record->impacto_financeiro_total > 0 ? 'success' : ($record->impacto_financeiro_total < 0 ? 'danger' : 'gray')),
                    ])
                    ->columns(4),
                Section::make('Observação Inicial')
                    ->schema([
                        TextEntry::make('observacao_inicial')
                            ->label('')
                            ->placeholder('Sem observação inicial.')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (BalancoInventario $record): bool => filled($record->observacao_inicial))
                    ->columns(1),
                Section::make('Timeline do Balanço')
                    ->schema([
                        RepeatableEntry::make('eventos')
                            ->label('')
                            ->placeholder('Nenhum evento registrado.')
                            ->table([
                                TableColumn::make('Data'),
                                TableColumn::make('Evento'),
                                TableColumn::make('Responsável'),
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
                                    ->label('Responsável')
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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['inventario.escola', 'criadoPor']))
            ->defaultSort('data_agendada', 'desc')
            ->recordUrl(fn (BalancoInventario $record): string => static::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('inventario.escola.nome')
                    ->label('Escola')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('inventario.nome')
                    ->label('Inventário')
                    ->toggleable(),
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
                    ->label('Divergências')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'success')
                    ->sortable(),
                TextColumn::make('impacto_financeiro_total')
                    ->label('Impacto financeiro')
                    ->state(fn (BalancoInventario $record): float => (float) ($record->impacto_financeiro_total ?? 0))
                    ->money('BRL')
                    ->color(fn (BalancoInventario $record): string => $record->impacto_financeiro_total > 0 ? 'success' : ($record->impacto_financeiro_total < 0 ? 'danger' : 'gray'))
                    ->sortable(),
                TextColumn::make('criadoPor.name')
                    ->label('Responsável')
                    ->placeholder('-')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(
                        collect(BalancoInventarioStatus::cases())
                            ->mapWithKeys(fn (BalancoInventarioStatus $status) => [$status->value => $status->label()])
                            ->toArray()
                    ),
                SelectFilter::make('inventario_id')
                    ->label('Inventário')
                    ->options(static::opcoesInventario()),
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
            ItensContagemInventarioRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBalancosInventario::route('/'),
            'view' => ViewBalancoInventario::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();
        $policy = Gate::getPolicyFor(static::getModel());

        if ($user && $policy && method_exists($policy, 'applyViewAnyScope')) {
            $query = $policy->applyViewAnyScope($user, $query);
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query
            ->with([
                'inventario.escola',
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

    public static function opcoesInventario(): array
    {
        return app(InventarioContextService::class)
            ->queryInventariosVisiveis(Auth::user())
            ->with('escola')
            ->orderBy('nome')
            ->get()
            ->mapWithKeys(fn ($inventario): array => [
                $inventario->getKey() => ($inventario->escola?->nome ?? 'Escola') . ' - ' . ($inventario->nome ?? 'Inventário'),
            ])
            ->toArray();
    }
}
