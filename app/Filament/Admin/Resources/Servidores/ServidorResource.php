<?php

namespace App\Filament\Admin\Resources\Servidores;

use App\Filament\Admin\Resources\Servidores\Pages\ManageServidores;
use App\Filament\Admin\Resources\Servidores\Schemas\ServidorMatriculasForm;
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
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
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
                Tabs::make('Cadastro de pessoa')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Dados pessoais')
                            ->schema([
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
                                    ->compact(),
                            ]),

                        Tab::make('Matrículas e lotações')
                            ->schema([
                                ServidorMatriculasForm::section(),
                            ]),
                    ]),
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
                    })
                    ->tooltip('Gerencie login e permissões em Acesso → Usuários'),

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
                    ->label('Visualizar')
                    ->modalWidth('6xl')
                    ->modalIcon(null)
                    ->modalHeading(fn (Servidor $record): string => "Pessoa — {$record->nome}")
                    ->modalDescription('Ficha: identidade, cargo, matrículas e lotações. Acesso ao sistema em Usuários.')
                    ->extraModalWindowAttributes([
                        'class' => 'pessoa-modal-window pessoa-view-modal-window',
                    ])
                    ->stickyModalHeader()
                    ->schema(fn (Servidor $record): array => static::infolistDetalhesCompletos($record)),

                EditAction::make()
                    ->model(Servidor::class)
                    ->modalWidth('6xl')
                    ->modalIcon(null)
                    ->modalHeading(fn (Servidor $record): string => "Editar pessoa — {$record->nome}")
                    ->modalDescription('Login e permissões: menu Acesso → Usuários.')
                    ->modalCancelActionLabel('Cancelar')
                    ->modalSubmitActionLabel('Salvar alterações')
                    ->extraModalWindowAttributes([
                        'class' => 'pessoa-modal-window',
                    ])
                    ->stickyModalHeader()
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
                    ->modalDescription(fn (Servidor $record): string => "Excluir \"{$record->nome}\"? "
                        .'Matrículas e lotações serão removidas; vínculos de turma e a referência do professor em avaliações serão apenas desassociados (avaliações/alunos permanecem). '
                        .'A conta de login, se existir, não é apagada.')
                    ->visible(fn (Servidor $record): bool => Gate::allows('delete', $record))
                    ->using(function (Servidor $record): void {
                        app(ServidorService::class)->excluirPessoa($record);

                        Notification::make()
                            ->title('Pessoa excluída')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()
                    ->label('Excluir selecionados')
                    ->requiresConfirmation()
                    ->modalHeading('Excluir pessoas em massa')
                    ->modalDescription('Avaliações e alunos não são apagados: só se remove a ficha da pessoa e se desassocia o professor. Contas de login permanecem.')
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
     * Infolist rico para o slide-over de visualização.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function infolistDetalhesCompletos(Servidor $record): array
    {
        $record->loadMissing([
            'user.roles',
            'user.escola',
            'professores.escola',
            'professorMatriculas',
            'vinculosAtivos.funcaoAdministrativa',
            'vinculosAtivos.escola',
            'vinculosAtivos.setor',
        ]);

        $dadosPessoais = [
            Section::make('Identidade')
                ->icon('heroicon-o-user')
                ->schema([
                    TextEntry::make('nome')->label('Nome')->columnSpan(2),
                    TextEntry::make('cpf')
                        ->label('CPF')
                        ->formatStateUsing(fn (?string $state): string => \App\Models\Pessoa::formatarCpf($state) ?: 'Não informado'),
                    TextEntry::make('email')->label('E-mail')->placeholder('Não informado')->copyable(),
                    TextEntry::make('telefone')->label('Telefone')->placeholder('Não informado'),
                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (?string $state): string => Servidor::statusOptions()[$state] ?? '—')
                        ->color(fn (?string $state): string => match ($state) {
                            Servidor::STATUS_ATIVO => 'success',
                            Servidor::STATUS_INATIVO => 'gray',
                            default => 'warning',
                        }),
                    TextEntry::make('observacoes')
                        ->label('Observações')
                        ->placeholder('—')
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Cargo')
                ->icon('heroicon-o-briefcase')
                ->schema([
                    TextEntry::make('cargo_view')
                        ->label('Cargo')
                        ->badge()
                        ->getStateUsing(fn (): string => $record->professores->isNotEmpty() ? 'Professor' : 'Sem cargo pedagógico')
                        ->color(fn (string $state): string => $state === 'Professor' ? 'info' : 'gray'),
                    TextEntry::make('updated_at')->label('Atualizado em')->dateTime('d/m/Y H:i'),
                    TextEntry::make('acesso_hint')
                        ->label('Acesso ao sistema')
                        ->getStateUsing(function () use ($record): string {
                            if (! $record->user_id) {
                                return 'Sem usuário vinculado. Crie/edite em Acesso → Usuários se necessário.';
                            }

                            $status = $record->user?->email_approved ? 'Liberado' : 'Pendente';

                            return "Conta: {$record->user->email} ({$status}). Edite login e permissões em Acesso → Usuários.";
                        })
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ];

        $gruposTurmas = static::gruposTurmasComponentes($record);

        $turmasELotacoes = [
            Section::make('Turmas e componentes')
                ->icon('heroicon-o-academic-cap')
                ->description('Lotações organizadas por escola e turno.')
                ->schema([
                    View::make('filament.admin.resources.servidores.partials.turmas-componentes-groups')
                        ->viewData(['grupos' => $gruposTurmas])
                        ->columnSpanFull(),
                ]),
        ];

        return [
            Tabs::make('Ficha da pessoa')
                ->columnSpanFull()
                ->tabs([
                    Tab::make('Dados pessoais')
                        ->schema($dadosPessoais),
                    Tab::make('Turmas e componentes')
                        ->schema($turmasELotacoes),
                ]),
        ];
    }

    /**
     * Agrupa a apresentação pedagógica por escola e turno sem alterar os vínculos persistidos.
     *
     * @return array<int, array{
     *     escola: string,
     *     turno: string,
     *     matriculas: array<int, string>,
     *     turmas: array<int, array{nome: string, componentes: array<int, string>}>
     * }>
     */
    public static function gruposTurmasComponentes(Servidor $record): array
    {
        $record->loadMissing('professores.escola');

        $vinculosPorProfessor = \App\Models\TurmaComponenteProfessor::query()
            ->with([
                'turma:id,nome,turno,id_escola,id_serie',
                'turma.serie:id,nome',
                'componente:id,nome',
            ])
            ->whereIn('professor_id', $record->professores->pluck('id'))
            ->where('tem_professor', true)
            ->whereNotNull('professor_id')
            ->get()
            ->groupBy('professor_id');

        return $record->professores
            ->groupBy(fn (Professor $professor): string => implode(':', [
                $professor->id_escola ?: 'sem-escola',
                $professor->turno ?: 'sem-turno',
            ]))
            ->map(function ($professores) use ($vinculosPorProfessor): array {
                /** @var Professor $professor */
                $professor = $professores->first();
                $vinculosDoGrupo = $professores->flatMap(
                    fn (Professor $item) => $vinculosPorProfessor->get($item->id, collect()),
                );

                $turmas = $vinculosDoGrupo
                    ->groupBy('turma_id')
                    ->map(function ($vinculos): array {
                        $vinculo = $vinculos->first();
                        $nomeTurma = collect([
                            $vinculo->turma?->serie?->nome,
                            $vinculo->turma?->nome ?? ('Turma #'.$vinculo->turma_id),
                        ])->filter()->implode(' - ');

                        return [
                            'nome' => $nomeTurma,
                            'componentes' => $vinculos
                                ->map(fn ($item): string => $item->componente?->nome ?? ('Componente #'.$item->componente_curricular_id))
                                ->unique()
                                ->sort()
                                ->values()
                                ->all(),
                        ];
                    })
                    ->sortBy('nome')
                    ->values()
                    ->all();

                return [
                    'escola' => $professor->escola?->nome ?? 'Sem escola definida',
                    'turno' => $professor->turnoLabel(),
                    'matriculas' => $professores->pluck('matricula')->filter()->unique()->values()->all(),
                    'turmas' => $turmas,
                ];
            })
            ->sortBy(fn (array $grupo): string => $grupo['escola'].'|'.$grupo['turno'])
            ->values()
            ->all();
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

    public static function turmasOptionsPublic(int|string|null $escolaId, int|string|null $turnoMatricula = null): array
    {
        return static::turmasOptions($escolaId, $turnoMatricula);
    }

    public static function componentesOptionsPublic(int|string|null $turmaId): array
    {
        return static::componentesOptions($turmaId);
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
