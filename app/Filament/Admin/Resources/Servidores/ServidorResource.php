<?php

namespace App\Filament\Admin\Resources\Servidores;

use App\Filament\Admin\Resources\Servidores\Pages\ManageServidores;
use App\Filament\Admin\Resources\Servidores\Schemas\ServidorAcessoForm;
use App\Models\Professor;
use App\Models\ProfessorMatricula;
use App\Models\Servidor;
use App\Models\Turma;
use App\Services\PessoaProfessorFormService;
use App\Services\ServidorService;
use App\Services\UserService;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Hidden;
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
    public const CARGO_PROFESSOR = 'professor';

    protected static ?string $model = Servidor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Briefcase;

    protected static string|UnitEnum|null $navigationGroup = 'Acesso';

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
                    ->description('Dados básicos usados em todo o sistema.')
                    ->icon('heroicon-o-user')
                    ->schema([
                        TextInput::make('nome')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),

                        TextInput::make('cpf')
                            ->label('CPF')
                            ->mask('999.999.999-99')
                            ->maxLength(14),

                        TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->required()
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Professor::normalizarEmail($state) : null)
                            ->rule(function (): Closure {
                                return function (string $attribute, mixed $value, Closure $fail): void {
                                    if (! Professor::emailInstitucionalValido((string) $value)) {
                                        $fail('Use somente e-mail institucional @edu.umuarama.pr.gov.br.');
                                    }
                                };
                            })
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
                    ->columns(2)
                    ->compact(),

                Section::make('Cargo')
                    ->icon('heroicon-o-briefcase')
                    ->schema([
                        Select::make('cargo')
                            ->label('Função / cargo')
                            ->options(static::cargoOptions())
                            ->default(self::CARGO_PROFESSOR)
                            ->required()
                            ->live()
                            ->dehydrated()
                            ->helperText('Nesta fase apenas Professor. Outros cargos poderão ser adicionados depois.'),
                    ])
                    ->columnSpanFull()
                    ->compact()
                    ->collapsible(),

                Section::make('Matrículas e lotações')
                    ->description('Turno pertence à matrícula. Cada matrícula pode ter várias escolas. Vínculos de turma são opcionais.')
                    ->icon('heroicon-o-academic-cap')
                    ->schema([
                        Repeater::make('matriculas_professor')
                            ->label('Matrículas')
                            ->schema([
                                Hidden::make('id'),

                                TextInput::make('matricula')
                                    ->label('Matrícula')
                                    ->required()
                                    ->maxLength(255),

                                Select::make('turno')
                                    ->label('Turno')
                                    ->options(Professor::turnosOptions())
                                    ->required()
                                    ->live(),

                                Repeater::make('escolas')
                                    ->label('Escolas desta matrícula')
                                    ->schema([
                                        Hidden::make('id'),

                                        Select::make('id_escola')
                                            ->label('Escola / CMEI')
                                            ->options(fn (): array => app(UserService::class)->opcoesDeEscolasParaCampo(Auth::user()))
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->live()
                                            ->columnSpanFull(),

                                        Repeater::make('vinculos_turma_componente')
                                            ->label('Turmas e componentes (opcional)')
                                            ->schema([
                                                Select::make('turma_id')
                                                    ->label('Turma')
                                                    ->options(function (Get $get): array {
                                                        return static::turmasOptions(
                                                            $get('../../id_escola') ?? $get('id_escola'),
                                                            $get('../../../turno') ?? $get('../../turno'),
                                                        );
                                                    })
                                                    ->searchable()
                                                    ->preload()
                                                    ->live(),

                                                Select::make('componente_curricular_id')
                                                    ->label('Componente')
                                                    ->options(fn (Get $get): array => static::componentesOptions($get('turma_id')))
                                                    ->searchable()
                                                    ->preload()
                                                    ->visible(fn (Get $get): bool => filled($get('turma_id'))),
                                            ])
                                            ->columns(2)
                                            ->defaultItems(0)
                                            ->addActionLabel('Vincular turma')
                                            ->collapsible()
                                            ->collapsed()
                                            ->itemLabel(fn (array $state): ?string => filled($state['turma_id'] ?? null)
                                                ? 'Vínculo de turma'
                                                : 'Novo vínculo')
                                            ->visible(fn (Get $get): bool => filled($get('id_escola')))
                                            ->columnSpanFull()
                                            ->reorderable(false),
                                    ])
                                    ->columns(1)
                                    ->defaultItems(0)
                                    ->minItems(0)
                                    ->addActionLabel('Adicionar escola')
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => filled($state['id_escola'] ?? null)
                                        ? 'Escola #'.($state['id_escola'] ?? '')
                                        : 'Nova escola')
                                    ->columnSpanFull()
                                    ->reorderable(false),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->minItems(0)
                            // NÃO usar maxItems(2) no form: legado pode ter 3+ matrículas e o save falhava em silêncio.
                            ->addActionLabel('Adicionar matrícula')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => filled($state['matricula'] ?? null)
                                ? sprintf('%s · %s', $state['matricula'], Professor::turnosOptions()[$state['turno'] ?? ''] ?? 'turno')
                                : 'Nova matrícula')
                            ->helperText('Cadastros novos: prefira no máximo 2 matrículas. Legado com mais matrículas é preservado e editável.')
                            ->columnSpanFull()
                            ->reorderable(false),

                        Textarea::make('observacoes')
                            ->label('Observações')
                            ->rows(2)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Get $get): bool => ($get('cargo') ?? self::CARGO_PROFESSOR) === self::CARGO_PROFESSOR)
                    ->columnSpanFull()
                    ->collapsible(),

                ServidorAcessoForm::section(),
            ]);
    }

    /** @return array<string, string> */
    public static function cargoOptions(): array
    {
        return [
            self::CARGO_PROFESSOR => 'Professor',
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'escola:id,nome,setor_id',
                'setor:id,nome',
                'user:id,name,email,email_approved',
                'user.roles:id,name',
                'professores.escola:id,nome',
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
                    ->getStateUsing(function (Servidor $record): string {
                        $record->loadMissing(['professorMatriculas', 'professores']);

                        if ($record->professorMatriculas->isNotEmpty()) {
                            return $record->professorMatriculas
                                ->map(fn ($m): string => sprintf('%s (%s)', $m->matricula, $m->turnoLabel()))
                                ->implode(', ');
                        }

                        return $record->professores
                            ->map(fn (Professor $p): string => sprintf('%s (%s)', $p->matricula, $p->turnoLabel()))
                            ->unique()
                            ->implode(', ') ?: '—';
                    })
                    ->wrap(),

                TextColumn::make('nome')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->copyable(),

                TextColumn::make('cargo_label')
                    ->label('Cargo')
                    ->getStateUsing(fn (Servidor $record): string => $record->professores->isNotEmpty() ? 'Professor' : '—')
                    ->badge(),

                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->wrap()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('acesso_ao_sistema')
                    ->label('Acesso')
                    ->badge()
                    ->getStateUsing(function (Servidor $record): string {
                        if (! $record->user_id) {
                            return 'Sem usuário';
                        }

                        return $record->user?->email_approved ? 'Liberado' : 'Pendente';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Liberado' => 'success',
                        'Pendente' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('user.roles')
                    ->label('Níveis de acesso')
                    ->getStateUsing(fn (Servidor $record): string => $record->user?->roles
                        ->pluck('name')
                        ->join(', ') ?: '—')
                    ->wrap()
                    ->toggleable(),

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

                TextColumn::make('updated_at')
                    ->label('Atualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('id_escola')
                    ->label('Escola')
                    ->options(fn (): array => app(UserService::class)->opcoesDeEscolasParaCampo(Auth::user()))
                    ->query(function (Builder $query, array $data): Builder {
                        if (! filled($data['value'] ?? null)) {
                            return $query;
                        }

                        return $query->whereHas('professores', fn (Builder $professores): Builder => $professores
                            ->where('id_escola', (int) $data['value']));
                    })
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

                TernaryFilter::make('eh_professor')
                    ->label('Cargo professor')
                    ->trueLabel('Professores')
                    ->falseLabel('Sem cargo professor')
                    ->placeholder('Todos')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas(
                            'professores',
                            fn (Builder $professores): Builder => $professores->where('ativo', true),
                        ),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('professores'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalHeading(fn (Servidor $record): string => "Detalhes - {$record->nome}")
                    ->modalWidth('3xl')
                    ->schema([
                        Section::make('Informações da pessoa')
                            ->schema([
                                TextEntry::make('nome')->label('Nome'),
                                TextEntry::make('cpf')->label('CPF')->placeholder('Não informado'),
                                TextEntry::make('email')->label('E-mail')->placeholder('Não informado'),
                                TextEntry::make('user.name')->label('Usuário')->placeholder('Sem acesso'),
                                TextEntry::make('user.email')->label('Login')->placeholder('Sem acesso'),
                                TextEntry::make('acesso_resumo')
                                    ->label('Situação do acesso')
                                    ->getStateUsing(function (Servidor $record): string {
                                        if (! $record->user_id) {
                                            return 'Sem usuário vinculado';
                                        }

                                        return $record->user?->email_approved
                                            ? 'Acesso liberado'
                                            : 'Aguardando verificação';
                                    }),
                                TextEntry::make('niveis_acesso')
                                    ->label('Níveis de acesso')
                                    ->getStateUsing(fn (Servidor $record): string => $record->user?->roles
                                        ->pluck('name')
                                        ->join(', ') ?: '—'),
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->formatStateUsing(fn (?string $state): string => Servidor::statusOptions()[$state] ?? 'Não informado'),
                            ])
                            ->columns(2),

                        Section::make('Registros do professor')
                            ->schema([
                                TextEntry::make('registros_resumo')
                                    ->label('Lotações')
                                    ->getStateUsing(fn (Servidor $record): array => $record->professores
                                        ->loadMissing('escola')
                                        ->map(fn (Professor $professor): string => sprintf(
                                            '%s — %s — %s',
                                            $professor->matricula,
                                            $professor->turnoLabel(),
                                            $professor->escola?->nome ?? 'Sem escola',
                                        ))
                                        ->all())
                                    ->badge()
                                    ->separator(','),
                            ]),
                    ]),

                EditAction::make()
                    ->model(Servidor::class)
                    ->slideOver()
                    ->modalWidth('5xl')
                    ->modalHeading(fn (Servidor $record): string => "Editar pessoa — {$record->nome}")
                    ->closeModalByClickingAway(false)
                    ->fillForm(fn (Servidor $record): array => app(PessoaProfessorFormService::class)->dadosParaFormulario($record))
                    ->using(function (Servidor $record, array $data): Servidor {
                        try {
                            $registros = static::extrairRegistrosProfessorDoForm($data);
                            unset($data['registros_professor'], $data['matriculas_professor']);
                            $data['cargo'] = $data['cargo'] ?? self::CARGO_PROFESSOR;

                            $atualizado = app(ServidorService::class)->atualizarServidorComFuncoes(
                                $record,
                                $data,
                                ['registros_professor' => $registros],
                            );

                            Notification::make()
                                ->title('Pessoa atualizada')
                                ->success()
                                ->send();

                            return $atualizado;
                        } catch (\Illuminate\Validation\ValidationException $e) {
                            Notification::make()
                                ->title('Não foi possível salvar')
                                ->body(collect($e->errors())->flatten()->take(5)->implode(' '))
                                ->danger()
                                ->persistent()
                                ->send();

                            throw $e;
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Erro ao salvar pessoa')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();

                            throw $e;
                        }
                    }),

                DeleteAction::make()
                    ->label('Excluir')
                    ->requiresConfirmation()
                    ->modalHeading('Excluir pessoa')
                    ->modalDescription(fn (Servidor $record): string => app(ServidorService::class)->motivoBloqueioExclusao($record)
                        ?? "Tem certeza que deseja excluir \"{$record->nome}\"? Lotações e vínculos de turma serão removidos. O usuário de login, se existir, não é apagado automaticamente.")
                    ->disabled(fn (Servidor $record): bool => ! Gate::allows('delete', $record))
                    ->visible(fn (): bool => Gate::allows('deleteAny', Servidor::class))
                    ->using(function (Servidor $record): void {
                        app(ServidorService::class)->excluirPessoa($record);
                    }),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()
                    ->label('Excluir selecionados')
                    ->requiresConfirmation()
                    ->modalHeading('Excluir pessoas em massa')
                    ->modalDescription('Pessoas com avaliações registradas serão ignoradas. Lotações e vínculos de turma dos demais serão limpos. Contas de usuário não são apagadas automaticamente.')
                    ->visible(fn (): bool => Gate::allows('deleteAny', Servidor::class))
                    ->deselectRecordsAfterCompletion()
                    ->using(function ($records): void {
                        $resultado = app(ServidorService::class)->excluirPessoasEmMassa($records);

                        if ($resultado['excluidos'] > 0) {
                            Notification::make()
                                ->title('Exclusão concluída')
                                ->body("{$resultado['excluidos']} pessoa(s) excluída(s).")
                                ->success()
                                ->send();
                        }

                        if ($resultado['bloqueados'] !== []) {
                            Notification::make()
                                ->title('Algumas pessoas não foram excluídas')
                                ->body(collect($resultado['bloqueados'])->take(5)->implode("\n"))
                                ->warning()
                                ->persistent()
                                ->send();
                        }
                    }),
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

    /**
     * Extrai payload de matrículas do form (hierárquico preferencial; flat legado como fallback).
     *
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    public static function extrairRegistrosProfessorDoForm(array $data): array
    {
        if (! empty($data['matriculas_professor']) && is_array($data['matriculas_professor'])) {
            return array_values($data['matriculas_professor']);
        }

        return array_values($data['registros_professor'] ?? []);
    }

    private static function turmasOptions(int|string|null $escolaId, int|string|null $turnoMatricula = null): array
    {
        if (! $escolaId) {
            return [];
        }

        $query = Turma::query()
            ->where('id_escola', $escolaId)
            ->with('serie')
            ->orderBy('nome');

        if (filled($turnoMatricula)) {
            $compat = ProfessorMatricula::turnosTurmaCompativeis((string) $turnoMatricula);
            $query->whereIn('turno', $compat);
        }

        return $query
            ->get()
            ->mapWithKeys(fn (Turma $turma): array => [
                $turma->id => trim(($turma->serie?->nome ? $turma->serie->nome.' - ' : '').$turma->nome.' ('.$turma->turno.')'),
            ])
            ->toArray();
    }

    private static function componentesOptions(int|string|null $turmaId): array
    {
        if (! $turmaId) {
            return [];
        }

        $turma = Turma::query()
            ->with('serie.componentesCurriculares')
            ->find($turmaId);

        if (! $turma?->serie) {
            return [];
        }

        return $turma->serie->componentesCurriculares
            ->sortBy('nome')
            ->mapWithKeys(fn ($componente): array => [
                $componente->id => $componente->nome,
            ])
            ->toArray();
    }
}