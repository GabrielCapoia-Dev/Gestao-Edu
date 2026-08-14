<?php

namespace App\Filament\Admin\Resources\Lotacoes;

use App\Filament\Admin\Resources\Lotacoes\Pages\ManageLotacoes;
use App\Models\LocalTrabalho;
use App\Models\Lotacao;
use App\Policies\LocalTrabalhoPolicy;
use App\Policies\LotacaoPolicy;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class LotacaoResource extends Resource
{
    protected static ?string $model = Lotacao::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::RectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';

    protected static ?string $navigationParentItem = 'Locais de trabalho';

    protected static ?string $navigationLabel = 'Lotações';

    protected static ?string $modelLabel = 'Lotação';

    protected static ?string $pluralModelLabel = 'Lotações';

    protected static ?string $slug = 'lotacoes';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();
        $policy = Gate::getPolicyFor(static::getModel());

        if ($user && $policy instanceof LotacaoPolicy) {
            return $policy->applyViewAnyScope($user, $query);
        }

        return $query->whereRaw('1 = 0');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('escola_id')
                    ->label('Local de trabalho')
                    ->options(fn (): array => static::opcoesLocaisVisiveis())
                    ->searchable()
                    ->preload()
                    ->required(),

                TextInput::make('codigo')
                    ->label('Número da lotação')
                    ->required()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true),

                TextInput::make('nome')
                    ->label('Nome da lotação')
                    ->required()
                    ->maxLength(150),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'localTrabalho:id,nome,setor_id,nao_e_escola',
                'localTrabalho.setor:id,nome,parent_id,path',
            ]))
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->searchable(['codigo', 'nome', 'localTrabalho.nome'])
            ->searchPlaceholder('Buscar por número, nome ou local de trabalho')
            ->columns([
                TextColumn::make('codigo')
                    ->label('Número')
                    ->description(fn (Lotacao $record): string => $record->localTrabalho?->nome ?? 'Local não informado')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Número copiado')
                    ->weight('bold')
                    ->extraAttributes(['class' => 'local-card-name'], merge: true),

                Grid::make([
                    'default' => 1,
                    'sm' => 2,
                    'lg' => 3,
                    'xl' => 4,
                ])
                    ->schema([
                        TextColumn::make('nome')
                            ->label('Nome da lotação')
                            ->description('Nome da lotação', position: 'above')
                            ->searchable()
                            ->sortable()
                            ->wrap()
                            ->extraAttributes(['class' => 'local-card-field'], merge: true),

                        TextColumn::make('localTrabalho.nome')
                            ->label('Local de trabalho')
                            ->description('Local de trabalho', position: 'above')
                            ->icon('heroicon-o-building-office-2')
                            ->searchable()
                            ->sortable()
                            ->wrap()
                            ->extraAttributes(['class' => 'local-card-field local-card-field--local'], merge: true),

                        TextColumn::make('localTrabalho.nao_e_escola')
                            ->label('Tipo')
                            ->description('Tipo', position: 'above')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Local não escolar' : 'Escola')
                            ->color(fn (bool $state): string => $state ? 'warning' : 'info')
                            ->extraAttributes(['class' => 'local-card-field'], merge: true),

                        TextColumn::make('localTrabalho.setor.nome_completo')
                            ->label('Setor')
                            ->description('Setor', position: 'above')
                            ->icon('heroicon-o-map-pin')
                            ->placeholder('—')
                            ->wrap()
                            ->extraAttributes(['class' => 'local-card-field'], merge: true),

                        TextColumn::make('updated_at')
                            ->label('Atualizada em')
                            ->description('Atualizada em', position: 'above')
                            ->dateTime('d/m/Y H:i')
                            ->sortable()
                            ->extraAttributes(['class' => 'local-card-field'], merge: true),
                    ])
                    ->extraAttributes(['class' => 'local-card-main-grid']),
            ])
            ->filters([
                SelectFilter::make('escola_id')
                    ->label('Local de trabalho')
                    ->options(fn (): array => static::opcoesLocaisVisiveis())
                    ->searchable()
                    ->preload(),

                SelectFilter::make('tipo_local')
                    ->label('Tipo do local')
                    ->options([
                        '0' => 'Escola',
                        '1' => 'Local não escolar',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! array_key_exists('value', $data) || $data['value'] === null || $data['value'] === '') {
                            return $query;
                        }

                        return $query->whereHas(
                            'localTrabalho',
                            fn (Builder $localQuery): Builder => $localQuery->where('nao_e_escola', (bool) $data['value']),
                        );
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(2)
            ->recordAction(null)
            ->recordUrl(null)
            ->recordActions([
                EditAction::make()
                    ->mutateDataUsing(fn (array $data): array => static::validarLocalVisivel($data)),
                DeleteAction::make(),
            ], position: RecordActionsPosition::AfterContent)
            ->defaultSort('codigo')
            ->striped();
    }

    public static function validarLocalVisivel(array $data): array
    {
        $localId = (int) ($data['escola_id'] ?? 0);

        if (! array_key_exists($localId, static::opcoesLocaisVisiveis())) {
            throw ValidationException::withMessages([
                'escola_id' => 'O local de trabalho selecionado não está disponível no seu escopo de acesso.',
            ]);
        }

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLotacoes::route('/'),
        ];
    }

    /** @return array<int, string> */
    private static function opcoesLocaisVisiveis(): array
    {
        $user = Auth::user();

        if (! $user) {
            return [];
        }

        return app(LocalTrabalhoPolicy::class)
            ->applyViewAnyScope($user, LocalTrabalho::query())
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->all();
    }
}
