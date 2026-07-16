<?php

namespace App\Filament\Admin\Resources\Servidores;

use App\Filament\Admin\Resources\Servidores\Pages\ManageServidores;
use App\Filament\Admin\Resources\Servidores\Schemas\ServidorEquipeGestoraForm;
use App\Models\Escola;
use App\Models\Professor;
use App\Models\ProfessorMatricula;
use App\Models\Servidor;
use App\Models\Turma;
use App\Services\PessoaScopeService;
use App\Services\ServidorService;
use App\Services\UserService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
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

    public const CARGO_EQUIPE_GESTORA = 'equipe_gestora';

    public const CARGO_MANUTENCAO = 'manutencao';

    protected static ?string $model = Servidor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Briefcase;

    protected static string|UnitEnum|null $navigationGroup = 'Acesso';

    protected static ?string $navigationLabel = 'Pessoas';

    protected static ?int $navigationSort = 1;

    protected static ?string $pluralModelLabel = 'Pessoas';

    protected static ?string $modelLabel = 'Pessoa';

    protected static ?string $slug = 'servidores';

    /** @return array<string, string> */
    public static function cargoOptions(): array
    {
        $options = [
            self::CARGO_PROFESSOR => 'Professor',
        ];

        if (ServidorEquipeGestoraForm::usuarioPodeAdministrar()) {
            $options[self::CARGO_EQUIPE_GESTORA] = 'Equipe Gestora';
            $options[self::CARGO_MANUTENCAO] = 'Manutenção';
        }

        return $options;
    }

    public static function usuarioPodeGerenciarEstrutura(?Servidor $record = null): bool
    {
        return $record
            ? Gate::allows('manageStructure', $record)
            : Gate::allows('manageStructure', Servidor::class);
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
                'matriculas:id,servidor_id,matricula,turno',
                'vinculosAtivos.funcaoAdministrativa:id,codigo,nome,direcao_escolar,coordenacao_pedagogica,secretaria_escolar',
                'vinculosAtivos.escola:id,nome',
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
                        $record->loadMissing(['matriculas', 'professores']);

                        if (app(PessoaScopeService::class)->hasGlobalAccess(Auth::user()) && $record->matriculas->isNotEmpty()) {
                            return $record->matriculas
                                ->map(fn ($m): string => sprintf('%s (%s)', $m->matricula, $m->turnoLabel()))
                                ->implode(', ');
                        }

                        return static::professoresVisiveis($record)
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
                    ->getStateUsing(fn (Servidor $record): string => static::cargoLabel($record))
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
                    ->options(fn (): array => static::escolasOptionsEscopadas())
                    ->query(function (Builder $query, array $data): Builder {
                        if (! filled($data['value'] ?? null)) {
                            return $query;
                        }

                        return $query->where(function (Builder $pessoas) use ($data): void {
                            $escolaId = (int) $data['value'];

                            $pessoas
                                ->whereHas('professores', fn (Builder $professores): Builder => $professores
                                    ->where('id_escola', $escolaId))
                                ->orWhereHas('vinculosAtivos', fn (Builder $vinculos): Builder => $vinculos
                                    ->where('id_escola', $escolaId));
                        });
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

                TernaryFilter::make('eh_equipe_gestora')
                    ->label('Equipe Gestora')
                    ->trueLabel('Somente Equipe Gestora')
                    ->falseLabel('Sem cargo gestor')
                    ->placeholder('Todos')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas(
                            'vinculosAtivos.funcaoAdministrativa',
                            fn (Builder $funcoes): Builder => static::aplicarFiltroFuncaoGestora($funcoes),
                        ),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave(
                            'vinculosAtivos.funcaoAdministrativa',
                            fn (Builder $funcoes): Builder => static::aplicarFiltroFuncaoGestora($funcoes),
                        ),
                        blank: fn (Builder $query): Builder => $query,
                    ),

                TernaryFilter::make('eh_manutencao')
                    ->label('Cargo Manutenção')
                    ->trueLabel('Somente Manutenção')
                    ->falseLabel('Sem cargo de Manutenção')
                    ->placeholder('Todos')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas(
                            'vinculosAtivos.funcaoAdministrativa',
                            fn (Builder $funcoes): Builder => $funcoes->manutencao(),
                        ),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave(
                            'vinculosAtivos.funcaoAdministrativa',
                            fn (Builder $funcoes): Builder => $funcoes->manutencao(),
                        ),
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

                Action::make('edit')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->visible(fn (Servidor $record): bool => Gate::allows('update', $record))
                    ->modalWidth('6xl')
                    ->modalIcon(null)
                    ->modalHeading(fn (Servidor $record): string => "Editar pessoa — {$record->nome}")
                    ->modalDescription('Login e permissões: menu Acesso → Usuários.')
                    ->formWrapper(false)
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false)
                    ->extraModalWindowAttributes([
                        'class' => 'pessoa-modal-window',
                    ])
                    ->stickyModalHeader()
                    ->closeModalByClickingAway(false)
                    ->closeModalByEscaping(false)
                    ->modalContent(fn (Servidor $record) => view('components.pessoas.form-modal', [
                        'pessoaId' => $record->getKey(),
                    ])),

                DeleteAction::make()
                    ->label('Excluir')
                    ->requiresConfirmation()
                    ->modalHeading('Excluir pessoa')
                    ->modalDescription(function (Servidor $record): string {
                        $motivo = app(ServidorService::class)->motivoBloqueioExclusao($record);

                        if ($motivo) {
                            return $motivo;
                        }

                        return "Excluir \"{$record->nome}\"? "
                            .'Matrículas e lotações serão removidas; vínculos de turma e a referência do professor em avaliações serão apenas desassociados (avaliações/alunos permanecem). '
                            .'A conta de login, se existir, não é apagada.';
                    })
                    ->visible(fn (Servidor $record): bool => Gate::allows('delete', $record)
                        && (app(ServidorService::class)->pessoaPodeSerExcluida($record)
                            || ServidorEquipeGestoraForm::usuarioPodeAdministrar()))
                    ->before(function (DeleteAction $action, Servidor $record): void {
                        $motivo = app(ServidorService::class)->motivoBloqueioExclusao($record);

                        if (! $motivo) {
                            return;
                        }

                        Notification::make()
                            ->title('Pessoa não pode ser excluída')
                            ->body($motivo)
                            ->warning()
                            ->persistent()
                            ->send();

                        $action->halt();
                    })
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
                        foreach ($records as $record) {
                            if (! $record instanceof Servidor || ! Gate::allows('delete', $record)) {
                                throw new \Illuminate\Auth\Access\AuthorizationException(
                                    'Você não possui permissão para excluir uma ou mais Pessoas selecionadas.',
                                );
                            }
                        }

                        if (! ServidorEquipeGestoraForm::usuarioPodeAdministrar()
                            && $records->contains(
                                fn (Servidor $record): bool => ! app(ServidorService::class)->pessoaPodeSerExcluida($record),
                            )) {
                            throw new \Illuminate\Auth\Access\AuthorizationException(
                                'Você não possui permissão para excluir Pessoas da Equipe Gestora.',
                            );
                        }

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

    public static function ehEquipeGestora(Servidor $record): bool
    {
        $record->loadMissing('vinculosAtivos.funcaoAdministrativa');

        return $record->vinculosAtivos
            ->contains(fn ($vinculo): bool => (bool) (
                $vinculo->funcaoAdministrativa?->direcao_escolar
                || $vinculo->funcaoAdministrativa?->coordenacao_pedagogica
                || $vinculo->funcaoAdministrativa?->secretaria_escolar
            ));
    }

    public static function ehManutencao(Servidor $record): bool
    {
        $record->loadMissing('vinculosAtivos.funcaoAdministrativa');

        return $record->vinculosAtivos
            ->contains(fn ($vinculo): bool => (bool) $vinculo->funcaoAdministrativa?->ehManutencao());
    }

    public static function cargoLabel(Servidor $record): string
    {
        $record->loadMissing(['professores', 'vinculosAtivos.funcaoAdministrativa']);

        if (static::ehManutencao($record)) {
            return 'Manutenção';
        }

        $cargosGestores = static::vinculosVisiveis($record)
            ->filter(fn ($vinculo): bool => (bool) (
                $vinculo->funcaoAdministrativa?->direcao_escolar
                || $vinculo->funcaoAdministrativa?->coordenacao_pedagogica
                || $vinculo->funcaoAdministrativa?->secretaria_escolar
            ))
            ->pluck('funcaoAdministrativa.nome')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        if ($cargosGestores->isNotEmpty()) {
            return $cargosGestores->implode(' + ');
        }

        return static::professoresVisiveis($record)->isNotEmpty() ? 'Professor' : '—';
    }

    public static function escolasOptionsEscopadas(): array
    {
        $user = Auth::user();
        $scope = app(PessoaScopeService::class);

        if ($scope->hasGlobalAccess($user)) {
            return app(UserService::class)->opcoesDeEscolasParaCampo($user);
        }

        $ids = $scope->escolaIdsDosVinculos($user);

        if ($ids === []) {
            return [];
        }

        return Escola::query()
            ->where('ativo', true)
            ->whereIn('id', $ids)
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->toArray();
    }

    private static function professoresVisiveis(Servidor $record)
    {
        $record->loadMissing('professores');
        $user = Auth::user();
        $scope = app(PessoaScopeService::class);

        if ($scope->hasGlobalAccess($user)) {
            return $record->professores->where('ativo', true)->values();
        }

        $escolaIds = $scope->escolaIdsDosVinculos($user);

        return $record->professores
            ->where('ativo', true)
            ->filter(fn (Professor $professor): bool => in_array((int) $professor->id_escola, $escolaIds, true))
            ->values();
    }

    private static function vinculosVisiveis(Servidor $record)
    {
        $record->loadMissing('vinculosAtivos.funcaoAdministrativa');
        $user = Auth::user();
        $scope = app(PessoaScopeService::class);

        if ($scope->hasGlobalAccess($user)) {
            return $record->vinculosAtivos;
        }

        $escolaIds = $scope->escolaIdsDosVinculos($user);

        return $record->vinculosAtivos
            ->filter(fn ($vinculo): bool => filled($vinculo->id_escola)
                && in_array((int) $vinculo->id_escola, $escolaIds, true))
            ->values();
    }

    private static function aplicarFiltroFuncaoGestora(Builder $query): Builder
    {
        return $query->where(function (Builder $funcoes): void {
            $funcoes
                ->where('direcao_escolar', true)
                ->orWhere('coordenacao_pedagogica', true)
                ->orWhere('secretaria_escolar', true);
        });
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
            'matriculas',
            'vinculosAtivos.funcaoAdministrativa',
            'vinculosAtivos.escola',
            'vinculosAtivos.setor',
            'vinculosAtivos.vinculosTurmaAtivos.turma.serie',
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
                        ->getStateUsing(fn (): string => static::cargoLabel($record))
                        ->color(fn (string $state): string => $state !== '—' ? 'info' : 'gray'),
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
     *     turmas: array<int, array{nome: string, componentes: array<int, string>}>,
     *     cargos?: array<int, string>,
     *     portaria?: string,
     *     vigencia?: string
     * }>
     */
    public static function gruposTurmasComponentes(Servidor $record): array
    {
        $record->loadMissing([
            'professores.escola',
            'matriculas',
            'vinculosAtivos.funcaoAdministrativa',
            'vinculosAtivos.escola',
            'vinculosAtivos.vinculosTurmaAtivos.turma.serie',
        ]);

        $professoresAtivos = static::professoresVisiveis($record);

        $vinculosPorProfessor = \App\Models\TurmaComponenteProfessor::query()
            ->with([
                'turma:id,nome,turno,id_escola,id_serie',
                'turma.serie:id,nome',
                'componente:id,nome',
            ])
            ->whereIn('professor_id', $professoresAtivos->pluck('id'))
            ->where('tem_professor', true)
            ->whereNotNull('professor_id')
            ->get()
            ->groupBy('professor_id');

        $gruposProfessor = $professoresAtivos
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
            ->values();

        $vinculosGestores = static::vinculosVisiveis($record)
            ->filter(fn ($vinculo): bool => (bool) (
                $vinculo->funcaoAdministrativa?->direcao_escolar
                || $vinculo->funcaoAdministrativa?->coordenacao_pedagogica
                || $vinculo->funcaoAdministrativa?->secretaria_escolar
            ));
        $matriculas = $record->matriculas->pluck('matricula')->filter()->unique()->values()->all();
        $turnos = $record->matriculas
            ->map(fn ($matricula): string => $matricula->turnoLabel())
            ->filter()
            ->unique()
            ->implode(' + ') ?: 'Sem turno definido';

        $gruposGestores = $vinculosGestores
            ->groupBy(fn ($vinculo): string => (string) ($vinculo->id_escola ?: 'sem-escola'))
            ->map(function ($vinculos) use ($matriculas, $turnos): array {
                $primeiro = $vinculos->first();
                $cargos = $vinculos
                    ->map(function ($vinculo): string {
                        return (string) ($vinculo->funcaoAdministrativa?->nome ?? 'Função gestora');
                    })
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();
                $turmas = $vinculos
                    ->filter(fn ($vinculo): bool => (bool) $vinculo->funcaoAdministrativa?->coordenacao_pedagogica)
                    ->flatMap(fn ($vinculo) => $vinculo->vinculosTurmaAtivos)
                    ->map(function ($vinculoTurma): array {
                        $turma = $vinculoTurma->turma;
                        $nome = collect([$turma?->serie?->nome, $turma?->nome ?? ('Turma #'.$vinculoTurma->turma_id)])
                            ->filter()
                            ->implode(' - ');

                        return [
                            'nome' => $nome,
                            'componentes' => [
                                'Coordenação',
                            ],
                        ];
                    })
                    ->sortBy('nome')
                    ->values()
                    ->all();

                return [
                    'escola' => $primeiro?->escola?->nome ?? 'Sem escola definida',
                    'turno' => $turnos,
                    'matriculas' => $matriculas,
                    'turmas' => $turmas,
                    'cargos' => $cargos,
                    'portaria' => $vinculos->pluck('portaria')->filter()->unique()->implode(' / '),
                ];
            })
            ->values();

        return $gruposProfessor
            ->concat($gruposGestores)
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
        if (array_key_exists('matriculas_professor', $data)) {
            return is_array($data['matriculas_professor'])
                ? array_values($data['matriculas_professor'])
                : [];
        }

        return array_values($data['registros_professor'] ?? []);
    }

    /**
     * Converte o estado visual do hub no contrato dos serviços de domínio.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public static function prepararDadosPersistencia(array $data, ?Servidor $record = null): array
    {
        $cargo = (string) ($data['cargo'] ?? self::CARGO_PROFESSOR);
        $matriculas = static::extrairRegistrosProfessorDoForm($data);
        $recordEraGestor = $record ? static::ehEquipeGestora($record) : false;
        $recordEraManutencao = $record ? static::ehManutencao($record) : false;

        if (($cargo === self::CARGO_EQUIPE_GESTORA
            || $cargo === self::CARGO_MANUTENCAO
            || $recordEraGestor
            || $recordEraManutencao)
            && ! ServidorEquipeGestoraForm::usuarioPodeAdministrar()) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'Apenas Admin ou usuário com a permissão Gerenciar Vínculos Estruturais de Pessoas pode administrar cargos funcionais.',
            );
        }

        unset($data['registros_professor'], $data['matriculas_professor']);
        $data['cargo'] = $cargo;

        if ($cargo === self::CARGO_MANUTENCAO) {
            return [$data, ['manutencao' => [
                'setor_id' => $data['setor_manutencao_id'] ?? null,
                'matriculas' => collect($matriculas)
                    ->filter(fn (mixed $item): bool => is_array($item))
                    ->map(fn (array $item): array => collect($item)
                        ->only(['id', 'matricula', 'turno'])
                        ->all())
                    ->values()
                    ->all(),
            ]]];
        }

        if ($cargo !== self::CARGO_EQUIPE_GESTORA) {
            return [$data, ['matriculas_professor' => $matriculas]];
        }

        $cargos = collect($data['cargos_gestores'] ?? [])
            ->filter()
            ->map(fn (mixed $cargoGestor): string => (string) $cargoGestor)
            ->unique()
            ->values();
        $vinculosAtivos = $record?->vinculosAtivos ?? collect();
        $diretor = false;
        if ($cargos->contains(ServidorEquipeGestoraForm::CARGO_DIRETOR)) {
            $diretor = ['ativo' => true];
        }

        $coordenador = false;
        if ($cargos->contains(ServidorEquipeGestoraForm::CARGO_COORDENADOR)) {
            $turmasSelecionadas = collect($data['turma_ids'] ?? [])
                ->map(fn (mixed $id): int => (int) $id)
                ->filter()
                ->unique()
                ->values();
            $coordenador = [
                'ativo' => true,
                'turma_ids' => $turmasSelecionadas->all(),
            ];
        }

        $equipeGestora = [
            'id_escola' => $data['id_escola'] ?? null,
            'matriculas' => collect($matriculas)
                ->filter(fn (mixed $item): bool => is_array($item))
                ->map(fn (array $item): array => collect($item)
                    ->only(['id', 'matricula', 'turno'])
                    ->all())
                ->values()
                ->all(),
            'cargos' => $cargos->all(),
            'diretor' => $diretor,
            'coordenador' => $coordenador,
            'secretario' => $cargos->contains(ServidorEquipeGestoraForm::CARGO_SECRETARIO),
            'portaria' => $data['portaria'] ?? null,
        ];

        return [$data, ['equipe_gestora' => $equipeGestora]];
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
