<?php

namespace App\Filament\Admin\Resources\Servidores;

use App\Filament\Admin\Resources\Servidores\Pages\ManageServidores;
use App\Models\FuncaoAdministrativa;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\Turma;
use App\Models\User;
use App\Services\ServidorService;
use App\Services\UserService;
use App\Services\UserSetorAccessService;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class ServidorResource extends Resource
{
    protected static ?string $model = Servidor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Briefcase;

    protected static string|UnitEnum|null $navigationGroup = 'Cadastros';

    protected static ?string $navigationLabel = 'Pessoas';

    protected static ?int $navigationSort = 1;

    protected static ?string $pluralModelLabel = 'Pessoas';

    protected static ?string $modelLabel = 'Pessoa';

    protected static ?string $slug = 'servidores';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identidade da pessoa')
                    ->schema([
                        TextInput::make('nome')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('cpf')
                            ->label('CPF')
                            ->mask('999.999.999-99')
                            ->maxLength(14),

                        TextInput::make('matricula')
                            ->label('Matrícula legada')
                            ->helperText('Preferir informar a matrícula em cada vínculo abaixo.')
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->maxLength(255),

                        TextInput::make('telefone')
                            ->label('Telefone')
                            ->tel()
                            ->mask('(99) 99999-9999')
                            ->maxLength(255),

                        Select::make('status')
                            ->label('Status')
                            ->options(Servidor::statusOptions())
                            ->default(Servidor::STATUS_ATIVO)
                            ->required(),
                    ])
                    ->columnSpanFull()
                    ->columns(2),

                Section::make('Vínculos e acesso')
                    ->schema([
                        Select::make('user_id')
                            ->label('Usuário de acesso')
                            ->options(fn (): array => app(UserService::class)
                                ->listarUsuariosQuery(User::query(), Auth::user())
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->toArray())
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('A função do servidor não concede acesso ao sistema automaticamente.'),

                        Repeater::make('vinculos_funcionais')
                            ->label('Matrículas e funções')
                            ->schema([
                                TextInput::make('matricula')
                                    ->label('Matrícula')
                                    ->required()
                                    ->maxLength(255),

                                Select::make('funcao_administrativa_id')
                                    ->label('Função / cargo')
                                    ->options(fn (): array => FuncaoAdministrativa::query()
                                        ->where('ativo', true)
                                        ->orderBy('nome')
                                        ->pluck('nome', 'id')
                                        ->toArray())
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live(),

                                Select::make('setor_id')
                                    ->label('Setor')
                                    ->options(fn (): array => app(UserSetorAccessService::class)->optionsForSelect(Auth::user()))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live(),

                                Select::make('id_escola')
                                    ->label('Escola / CMEI')
                                    ->options(fn (): array => app(UserService::class)->opcoesDeEscolasParaCampo(Auth::user()))
                                    ->searchable()
                                    ->preload()
                                    ->live(),

                                TextInput::make('portaria')
                                    ->label('Portaria')
                                    ->maxLength(255),

                                Select::make('turma_ids')
                                    ->label('Turmas vinculadas')
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->options(fn (Get $get): array => static::turmasOptions($get('id_escola')))
                                    ->visible(fn (Get $get): bool => static::funcaoTemRelacaoTurma($get('funcao_administrativa_id')))
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->required()
                            ->minItems(1)
                            ->addActionLabel('Adicionar função')
                            ->helperText('Selecione Professor apenas quando este servidor também precisar existir no cadastro pedagógico.')
                            ->afterStateHydrated(function (Repeater $component, ?Servidor $record): void {
                                if (! $record) {
                                    return;
                                }

                                $component->state(
                                    $record->servidorFuncoesAtivas()
                                        ->with('turmas:id')
                                        ->get()
                                        ->map(fn (ServidorFuncaoAdministrativa $vinculo): array => [
                                            'matricula' => $vinculo->matricula,
                                            'funcao_administrativa_id' => $vinculo->funcao_administrativa_id,
                                            'setor_id' => $vinculo->setor_id,
                                            'id_escola' => $vinculo->id_escola,
                                            'portaria' => $vinculo->portaria,
                                            'turma_ids' => $vinculo->turmas->pluck('id')->map(fn ($id): int => (int) $id)->all(),
                                        ])
                                        ->all()
                                );
                            })
                            ->columnSpanFull(),

                        Textarea::make('observacoes')
                            ->label('Observações')
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull()
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'escola:id,nome,setor_id',
                'setor:id,nome',
                'user:id,name,email',
                'funcoesAtivas:id,nome,codigo,categoria',
                'servidorFuncoesAtivas.turmas:id',
            ]))
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('cpf')
                    ->label('CPF')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('vinculos_resumo')
                    ->label('Matrículas')
                    ->getStateUsing(fn (Servidor $record): string => $record->servidorFuncoesAtivas
                        ->pluck('matricula')
                        ->filter()
                        ->unique()
                        ->implode(', ') ?: ($record->matricula ?? '—'))
                    ->wrap(),

                TextColumn::make('nome')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->copyable(),

                TextColumn::make('funcoes_ativas')
                    ->label('Funções')
                    ->getStateUsing(fn (Servidor $record): array => $record->funcoesAtivas->pluck('nome')->sort()->values()->all())
                    ->badge()
                    ->separator(',')
                    ->wrap(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Servidor::statusOptions()[$state] ?? 'Não informado')
                    ->color(fn (?string $state): string => match ($state) {
                        Servidor::STATUS_ATIVO => 'success',
                        Servidor::STATUS_INATIVO => 'gray',
                        default => 'warning',
                    })
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Usuário')
                    ->placeholder('Sem acesso')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Atualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('funcao_administrativa_id')
                    ->label('Função')
                    ->options(fn (): array => FuncaoAdministrativa::query()
                        ->where('ativo', true)
                        ->orderBy('nome')
                        ->pluck('nome', 'id')
                        ->toArray())
                    ->query(function (Builder $query, array $data): Builder {
                        if (! filled($data['value'] ?? null)) {
                            return $query;
                        }

                        return $query->whereHas('servidorFuncoes', function (Builder $funcoes) use ($data): void {
                            $funcoes
                                ->where('funcao_administrativa_id', (int) $data['value'])
                                ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO);
                        });
                    }),

                SelectFilter::make('id_escola')
                    ->label('Escola')
                    ->relationship('escola', 'nome', modifyQueryUsing: fn (Builder $query): Builder => $query
                        ->where('ativo', true)
                        ->orderBy('nome'))
                    ->searchable()
                    ->preload(),

                SelectFilter::make('setor_id')
                    ->label('Setor')
                    ->options(fn (): array => app(UserSetorAccessService::class)->optionsForSelect(Auth::user()))
                    ->searchable()
                    ->preload(),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(Servidor::statusOptions()),

                TernaryFilter::make('user_id')
                    ->label('Usuário vinculado')
                    ->trueLabel('Com usuário')
                    ->falseLabel('Sem usuário')
                    ->placeholder('Todos')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('user_id'),
                        false: fn (Builder $query): Builder => $query->whereNull('user_id'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalHeading(fn (Servidor $record): string => "Detalhes - {$record->nome}")
                    ->modalWidth('3xl')
                    ->schema([
                        Section::make('Informações do servidor')
                            ->schema([
                                TextEntry::make('nome')->label('Nome'),
                                TextEntry::make('matricula')->label('Matrícula')->placeholder('Não informada'),
                                TextEntry::make('escola.nome')->label('Escola')->placeholder('Não vinculada'),
                                TextEntry::make('setor.nome')->label('Setor')->placeholder('Não vinculado'),
                                TextEntry::make('user.name')->label('Usuário')->placeholder('Sem acesso'),
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->formatStateUsing(fn (?string $state): string => Servidor::statusOptions()[$state] ?? 'Não informado'),
                            ])
                            ->columns(2),

                        Section::make('Funções')
                            ->schema([
                                TextEntry::make('funcoes_lista')
                                    ->label('Funções ativas')
                                    ->getStateUsing(fn (Servidor $record): array => $record->funcoesAtivas()->pluck('nome')->sort()->values()->all())
                                    ->badge()
                                    ->separator(','),
                            ]),
                    ]),

                EditAction::make()
                    ->using(function (Servidor $record, array $data): Servidor {
                        $vinculos = $data['vinculos_funcionais'] ?? [];
                        unset($data['vinculos_funcionais']);

                        return app(ServidorService::class)->atualizarServidorComFuncoes($record, $data, $vinculos);
                    }),

                DeleteAction::make(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageServidores::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();
        $policy = Gate::getPolicyFor(static::getModel());

        if ($user && $policy && method_exists($policy, 'applyViewAnyScope')) {
            return $policy->applyViewAnyScope($user, $query);
        }

        return $query->whereRaw('1 = 0');
    }

    private static function funcaoTemRelacaoTurma(mixed $funcaoId): bool
    {
        return filled($funcaoId)
            && (bool) FuncaoAdministrativa::query()
                ->whereKey($funcaoId)
                ->value('tem_relacao_turma');
    }

    private static function turmasOptions(int|string|null $escolaId): array
    {
        if (! $escolaId) {
            return [];
        }

        return Turma::query()
            ->where('id_escola', $escolaId)
            ->with('serie')
            ->orderBy('nome')
            ->get()
            ->mapWithKeys(fn (Turma $turma): array => [
                $turma->id => trim(($turma->serie?->nome ? $turma->serie->nome.' - ' : '').$turma->nome.' ('.$turma->turno.')'),
            ])
            ->toArray();
    }
}
