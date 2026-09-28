<?php

namespace App\Filament\Admin\Resources\Servidores;

use App\Filament\Admin\Actions\ExportSelectedRecordsBulkAction;
use App\Filament\Admin\Pages\GerenciarEventos;
use App\Filament\Admin\Resources\Servidores\Actions\PessoaAcessoActions;
use App\Filament\Admin\Resources\Servidores\Pages\ManageServidores;
use App\Filament\Admin\Resources\Servidores\Schemas\ServidorEquipeGestoraForm;
use App\Models\Escola;
use App\Models\EventoCalendario;
use App\Models\Pessoa;
use App\Models\PessoaMatricula;
use App\Models\Professor;
use App\Models\ProfessorComponenteSolicitacao;
use App\Models\ProfessorMatricula;
use App\Models\Role;
use App\Models\Servidor;
use App\Models\Setor;
use App\Models\Turma;
use App\Models\TurmaComponenteProfessor;
use App\Models\User;
use App\Services\PessoaExclusaoDefinitivaService;
use App\Services\PessoaScopeService;
use App\Services\ProfessorComponenteSolicitacaoService;
use App\Services\ServidorService;
use App\Services\UserService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\View;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class ServidorResource extends Resource
{
    public const CARGO_PROFESSOR = 'professor';

    public const CARGO_EQUIPE_GESTORA = 'equipe_gestora';

    public const CARGO_MANUTENCAO = 'manutencao';

    public const CARGO_OBRAS = 'obras';

    public const CARGO_MOTORISTA = 'motorista';

    public const CARGO_TRANSPORTE = 'transporte';

    public const CARGO_ASSESSORIA_PEDAGOGICA = 'assessoria_pedagogica';

    protected static ?string $model = Servidor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Briefcase;

    protected static string|UnitEnum|null $navigationGroup = 'Acesso';

    protected static ?string $navigationLabel = 'Servidores';

    protected static ?int $navigationSort = 1;

    protected static ?string $pluralModelLabel = 'Servidores';

    protected static ?string $modelLabel = 'Servidor';

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
            $options[self::CARGO_OBRAS] = 'Obras';
            $options[self::CARGO_TRANSPORTE] = 'Transporte';
            $options[self::CARGO_ASSESSORIA_PEDAGOGICA] = 'Assessoria Pedagógica';
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
            ->modifyQueryUsing(function (Builder $query): Builder {
                $table = $query->getModel()->getTable();

                $query
                    ->select("{$table}.*")
                    ->selectSub(function ($duplicados) use ($table): void {
                        $duplicados
                            ->selectRaw('1')
                            ->from("{$table} as pessoa_email_duplicado")
                            ->whereColumn('pessoa_email_duplicado.email_normalizado', "{$table}.email_normalizado")
                            ->whereColumn('pessoa_email_duplicado.id', '<>', "{$table}.id")
                            ->whereNull('pessoa_email_duplicado.deleted_at')
                            ->limit(1);
                    }, 'email_duplicado')
                    ->with([
                        'escola:id,nome,setor_id',
                        'setor:id,nome',
                        'user:id,name,email,email_approved,ativo,deleted_at',
                        'user.roles:id,name',
                        'professores.escola:id,nome',
                        'matriculas:id,servidor_id,matricula,turno',
                        'vinculosAtivos.funcaoAdministrativa:id,codigo,nome,direcao_escolar,coordenacao_pedagogica,secretaria_escolar',
                        'vinculosAtivos.escola:id,nome',
                        'vinculosAtivos.escolasAssessoradas:id,nome',
                    ]);

                $user = Auth::user();
                if (! $user || ! app(ProfessorComponenteSolicitacaoService::class)->podeAnalisar($user)) {
                    return $query->selectRaw('0 as solicitacoes_pendentes_count');
                }

                $scope = app(PessoaScopeService::class);
                $acessoGlobal = $scope->hasGlobalAccess($user);
                $escolaIds = $acessoGlobal ? [] : $scope->escolaIdsDosVinculos($user);

                return $query->selectSub(function ($pendentes) use ($table, $acessoGlobal, $escolaIds): void {
                    $pendentes
                        ->selectRaw('count(*)')
                        ->from('professor_componente_solicitacoes as solicitacoes')
                        ->join('professores as professores_solicitantes', 'professores_solicitantes.id', '=', 'solicitacoes.professor_id')
                        ->join('turma_componente_professor as vinculos_solicitados', 'vinculos_solicitados.id', '=', 'solicitacoes.turma_componente_professor_id')
                        ->join('turmas as turmas_solicitadas', 'turmas_solicitadas.id', '=', 'vinculos_solicitados.turma_id')
                        ->where('solicitacoes.status', ProfessorComponenteSolicitacao::STATUS_PENDENTE)
                        ->where('professores_solicitantes.ativo', true)
                        ->whereColumn('professores_solicitantes.servidor_id', "{$table}.id")
                        ->whereColumn('professores_solicitantes.id_escola', 'turmas_solicitadas.id_escola');

                    if (! $acessoGlobal) {
                        $pendentes->whereIn('turmas_solicitadas.id_escola', $escolaIds);
                    }
                }, 'solicitacoes_pendentes_count');
            })
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(5)
            ->checkIfRecordIsSelectableUsing(fn (Servidor $record): bool => static::pessoaPodeSerSelecionada($record))
            ->searchable(static::camposBuscaTabela())
            ->searchPlaceholder(static::placeholderBuscaTabela())
            ->columns([
                TextColumn::make('nome')
                    ->label('Nome')
                    ->description(fn (Servidor $record): string => trim(collect([
                        filled($record->cpf) ? 'CPF: '.(Servidor::formatarCpf($record->cpf) ?? $record->cpf) : 'CPF não informado',
                    ])->join(' | ')))
                    ->searchable(['nome', 'cpf'])
                    ->sortable()
                    ->wrap()
                    ->copyable()
                    ->copyMessage('Nome copiado')
                    ->copyMessageDuration(1500)
                    ->icon(fn (Servidor $record): ?string => (int) $record->solicitacoes_pendentes_count > 0 ? 'heroicon-o-bell-alert' : null)
                    ->iconColor('warning')
                    ->tooltip(function (Servidor $record): string {
                        $quantidade = (int) $record->solicitacoes_pendentes_count;

                        return $quantidade > 0
                            ? "{$quantidade} ".($quantidade === 1 ? 'solicitação pendente' : 'solicitações pendentes').' de vínculo. Clique para copiar o nome.'
                            : 'Clique para copiar o nome';
                    })
                    ->weight('bold')
                    ->extraAttributes(['class' => 'pessoa-card-name'], merge: true),

                Grid::make([
                    'default' => 1,
                    'sm' => 2,
                    'lg' => 3,
                    'xl' => 5,
                ])
                    ->schema([
                        TextColumn::make('cargo_label')
                            ->label('Cargo')
                            ->description('Cargo', position: 'above')
                            ->getStateUsing(fn (Servidor $record): string => static::cargoLabel($record))
                            ->badge()
                            ->copyable()
                            ->copyMessage('Cargo copiado')
                            ->tooltip('Clique para copiar o cargo')
                            ->color(fn (string $state): string => $state !== '—' ? 'info' : 'gray')
                            ->wrap()
                            ->extraAttributes(['class' => 'pessoa-card-field pessoa-card-field--cargo'], merge: true),

                        TextColumn::make('escolas_resumo')
                            ->label('Escolas')
                            ->description(
                                fn (?Servidor $record): ?string => $record instanceof Servidor && static::exibeEscolaNaListagem($record) ? 'Escolas' : null,
                                position: 'above',
                            )
                            ->getStateUsing(fn (?Servidor $record): ?string => $record instanceof Servidor && static::exibeEscolaNaListagem($record)
                                ? static::escolasLabel($record)
                                : null)
                            ->icon(fn (?Servidor $record): ?string => $record instanceof Servidor && static::exibeEscolaNaListagem($record)
                                ? 'heroicon-o-building-library'
                                : null)
                            ->copyable()
                            ->copyMessage('Escolas copiadas')
                            ->tooltip('Clique para copiar as escolas')
                            ->wrap()
                            ->extraAttributes(['class' => 'pessoa-card-field pessoa-card-field--escolas'], merge: true),

                        TextColumn::make('vinculos_resumo')
                            ->label('Matrículas')
                            ->description('Matrículas', position: 'above')
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
                            ->icon('heroicon-o-identification')
                            ->copyable()
                            ->copyMessage('Matrícula copiada')
                            ->tooltip('Clique para copiar as matrículas')
                            ->wrap()
                            ->extraAttributes(['class' => 'pessoa-card-field pessoa-card-field--matriculas'], merge: true),

                        TextColumn::make('email')
                            ->label('E-mail')
                            ->description('E-mail', position: 'above')
                            ->searchable()
                            ->icon('heroicon-o-envelope')
                            ->copyable()
                            ->copyMessage('E-mail copiado')
                            ->tooltip('Clique para copiar o e-mail')
                            ->wrap()
                            ->placeholder('—')
                            ->toggleable()
                            ->extraAttributes(['class' => 'pessoa-card-field pessoa-card-field--email'], merge: true),

                        TextColumn::make('status')
                            ->label('Status')
                            ->description('Status', position: 'above')
                            ->badge()
                            ->getStateUsing(fn (Servidor $record): string => $record->trashed() ? 'arquivado' : (string) $record->status)
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'arquivado' => 'Arquivado',
                                default => Servidor::statusOptions()[$state] ?? 'Não informado',
                            })
                            ->color(fn (?string $state): string => match ($state) {
                                Servidor::STATUS_ATIVO => 'success',
                                Servidor::STATUS_INATIVO => 'gray',
                                'arquivado' => 'danger',
                                default => 'warning',
                            })
                            ->sortable()
                            ->copyable()
                            ->copyMessage('Status copiado')
                            ->tooltip('Clique para copiar o status')
                            ->extraAttributes(['class' => 'pessoa-card-field pessoa-card-field--status'], merge: true),

                        TextColumn::make('user.roles.name')
                            ->label('Níveis de acesso')
                            ->description('Níveis de acesso', position: 'above')
                            ->badge()
                            ->separator(',')
                            ->copyable()
                            ->copyMessage('Nível de acesso copiado')
                            ->tooltip('Clique para copiar os níveis de acesso')
                            ->wrap()
                            ->placeholder('—')
                            ->columnSpan([
                                'default' => 1,
                                'sm' => 2,
                                'lg' => 2,
                                'xl' => 2,
                            ])
                            ->toggleable()
                            ->visible(fn (): bool => Gate::allows('viewAny', User::class))
                            ->extraAttributes(['class' => 'pessoa-card-field pessoa-card-field--niveis'], merge: true),

                        TextColumn::make('updated_at')
                            ->label('Atualizado em')
                            ->description('Atualizado em', position: 'above')
                            ->dateTime('d/m/Y H:i')
                            ->copyable()
                            ->copyMessage('Data copiada')
                            ->tooltip('Clique para copiar a data')
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true)
                            ->extraAttributes(['class' => 'pessoa-card-field pessoa-card-field--data'], merge: true),
                    ])
                    ->extraAttributes(['class' => 'pessoa-card-main-grid']),
            ])
            ->filters([
                TernaryFilter::make('solicitacoes_pendentes')
                    ->label('Solicitações de vínculo')
                    ->trueLabel('Com solicitações pendentes')
                    ->falseLabel('Sem solicitações pendentes')
                    ->placeholder('Todas')
                    ->columnSpan(4)
                    ->visible(fn (): bool => ($user = Auth::user()) !== null
                        && app(ProfessorComponenteSolicitacaoService::class)->podeAnalisar($user))
                    ->queries(
                        true: function (Builder $query): Builder {
                            $user = Auth::user();
                            $scope = app(PessoaScopeService::class);
                            $acessoGlobal = $scope->hasGlobalAccess($user);
                            $escolaIds = $acessoGlobal ? [] : $scope->escolaIdsDosVinculos($user);
                            $table = $query->getModel()->getTable();

                            return $query->whereExists(function ($pendentes) use ($table, $acessoGlobal, $escolaIds): void {
                                $pendentes
                                    ->selectRaw('1')
                                    ->from('professor_componente_solicitacoes as solicitacoes')
                                    ->join('professores', 'professores.id', '=', 'solicitacoes.professor_id')
                                    ->join('turma_componente_professor as vinculos', 'vinculos.id', '=', 'solicitacoes.turma_componente_professor_id')
                                    ->join('turmas', 'turmas.id', '=', 'vinculos.turma_id')
                                    ->where('solicitacoes.status', ProfessorComponenteSolicitacao::STATUS_PENDENTE)
                                    ->where('professores.ativo', true)
                                    ->whereColumn('professores.servidor_id', "{$table}.id")
                                    ->when(! $acessoGlobal, fn ($subquery) => $subquery->whereIn('turmas.id_escola', $escolaIds));
                            });
                        },
                        false: function (Builder $query): Builder {
                            $user = Auth::user();
                            $scope = app(PessoaScopeService::class);
                            $acessoGlobal = $scope->hasGlobalAccess($user);
                            $escolaIds = $acessoGlobal ? [] : $scope->escolaIdsDosVinculos($user);
                            $table = $query->getModel()->getTable();

                            return $query->whereNotExists(function ($pendentes) use ($table, $acessoGlobal, $escolaIds): void {
                                $pendentes
                                    ->selectRaw('1')
                                    ->from('professor_componente_solicitacoes as solicitacoes')
                                    ->join('professores', 'professores.id', '=', 'solicitacoes.professor_id')
                                    ->join('turma_componente_professor as vinculos', 'vinculos.id', '=', 'solicitacoes.turma_componente_professor_id')
                                    ->join('turmas', 'turmas.id', '=', 'vinculos.turma_id')
                                    ->where('solicitacoes.status', ProfessorComponenteSolicitacao::STATUS_PENDENTE)
                                    ->where('professores.ativo', true)
                                    ->whereColumn('professores.servidor_id', "{$table}.id")
                                    ->when(! $acessoGlobal, fn ($subquery) => $subquery->whereIn('turmas.id_escola', $escolaIds));
                            });
                        },
                        blank: fn (Builder $query): Builder => $query,
                    ),

                SelectFilter::make('cargo')
                    ->label('Cargo')
                    ->columnSpan(4)
                    ->placeholder('Todos os cargos')
                    ->options([
                        self::CARGO_PROFESSOR => 'Professor',
                        ServidorEquipeGestoraForm::CARGO_DIRETOR => 'Diretor',
                        ServidorEquipeGestoraForm::CARGO_COORDENADOR => 'Coordenador',
                        ServidorEquipeGestoraForm::CARGO_SECRETARIO => 'Secretário',
                        self::CARGO_ASSESSORIA_PEDAGOGICA => 'Assessoria Pedagógica',
                        self::CARGO_MANUTENCAO => 'Manutenção',
                        self::CARGO_OBRAS => 'Obras',
                        'sem_cargo' => 'Sem cargo ativo',
                    ])
                    ->multiple()
                    ->query(function (Builder $query, array $data): Builder {
                        $cargos = collect($data['values'] ?? [])
                            ->filter()
                            ->map(fn ($cargo): string => (string) $cargo)
                            ->values()
                            ->all();

                        return $cargos === [] ? $query : static::aplicarFiltroCargos($query, $cargos);
                    })
                    ->preload(),

                SelectFilter::make('quantidade_matriculas')
                    ->label('Quantidade de matrículas')
                    ->columnSpan(4)
                    ->placeholder('Todas as quantidades')
                    ->options([
                        'uma' => 'Uma matrícula',
                        'duas' => 'Duas matrículas',
                        'tres_ou_mais' => 'Três ou mais matrículas',
                        'sem' => 'Sem matrícula',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => static::aplicarFiltroQuantidadeMatriculas(
                        $query,
                        $data['value'] ?? null,
                    )),

                SelectFilter::make('turno_matricula')
                    ->label('Turno da matrícula')
                    ->columnSpan(4)
                    ->placeholder('Todos os turnos')
                    ->options(PessoaMatricula::turnosOptions())
                    ->multiple()
                    ->query(function (Builder $query, array $data): Builder {
                        $turnos = collect($data['values'] ?? [])->filter()->values()->all();

                        return $turnos === [] ? $query : static::aplicarFiltroTurnosMatriculas($query, $turnos);
                    })
                    ->preload(),

                SelectFilter::make('status')
                    ->label('Status')
                    ->columnSpan(4)
                    ->placeholder('Todos os status')
                    ->options(Servidor::statusOptions())
                    ->multiple(),

                TrashedFilter::make()
                    ->label('Cadastros arquivados')
                    ->columnSpan(4)
                    ->placeholder('Sem arquivados')
                    ->trueLabel('Com arquivados')
                    ->falseLabel('Somente arquivados'),

                SelectFilter::make('id_escola')
                    ->label('Escola')
                    ->columnSpan(4)
                    ->placeholder('Todas as escolas')
                    ->options(fn (): array => static::escolasOptionsEscopadas())
                    ->multiple()
                    ->query(function (Builder $query, array $data): Builder {
                        $escolaIds = collect($data['values'] ?? [])
                            ->filter()
                            ->map(fn ($id): int => (int) $id)
                            ->values()
                            ->all();

                        if ($escolaIds === []) {
                            return $query;
                        }

                        return $query->where(function (Builder $pessoas) use ($escolaIds): void {
                            $pessoas
                                ->whereIn('id_escola', $escolaIds)
                                ->orWhereHas('professores', fn (Builder $professores): Builder => $professores
                                    ->whereIn('id_escola', $escolaIds))
                                ->orWhereHas('vinculosAtivos', fn (Builder $vinculos): Builder => $vinculos
                                    ->whereIn('id_escola', $escolaIds));
                        });
                    })
                    ->searchable()
                    ->preload(),

                SelectFilter::make('nivel_acesso')
                    ->label('Nível de acesso')
                    ->columnSpan(4)
                    ->placeholder('Todos os níveis')
                    ->options(fn (): array => Role::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->multiple()
                    ->query(function (Builder $query, array $data): Builder {
                        $ids = collect($data['values'] ?? [])
                            ->filter()
                            ->map(fn ($id): int => (int) $id)
                            ->all();

                        return $ids === []
                            ? $query
                            : $query->whereHas('user.roles', fn (Builder $roles): Builder => $roles->whereIn('roles.id', $ids));
                    })
                    ->searchable()
                    ->preload()
                    ->visible(fn (): bool => Gate::allows('viewAny', User::class)),

                Filter::make('email_duplicado')
                    ->label('E-mail duplicado')
                    ->columnSpan(4)
                    ->query(fn (Builder $query): Builder => $query->comEmailDuplicado()),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(12)
            ->recordAction(null)
            ->recordUrl(null)
            ->recordActions([
                static::excluirDefinitivamenteAction(),

                ActionGroup::make([
                    ViewAction::make()
                        ->label('Visualizar')
                        ->modalWidth('6xl')
                        ->modalIcon(null)
                        ->modalHeading('Ficha da pessoa')
                        ->modalDescription('Visão consolidada de dados pessoais, vínculos, lotações e acesso ao sistema.')
                        ->extraModalWindowAttributes([
                            'class' => 'pessoa-modal-window pessoa-view-modal-window',
                        ])
                        ->stickyModalHeader()
                        ->schema(fn (Servidor $record): array => static::infolistDetalhesCompletos($record)),

                    Action::make('edit')
                        ->label('Editar')
                        ->icon('heroicon-o-pencil-square')
                        ->color('primary')
                        ->visible(fn (Servidor $record): bool => ! $record->trashed() && Gate::allows('update', $record))
                        ->modalWidth('6xl')
                        ->modalIcon(null)
                        ->modalHeading(fn (Servidor $record): string => "Editar pessoa — {$record->nome}")
                        ->modalDescription('Edite os dados funcionais; login, níveis e permissões ficam nas ações da pessoa.')
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

                    ...PessoaAcessoActions::recordActions(),

                    Action::make('analisar_solicitacoes_professor')
                        ->label('Analisar solicitações')
                        ->icon('heroicon-o-bell-alert')
                        ->color('warning')
                        ->visible(fn (Servidor $record): bool => (int) $record->solicitacoes_pendentes_count > 0
                            && app(ProfessorComponenteSolicitacaoService::class)->podeAnalisar(Auth::user()))
                        ->modalWidth('5xl')
                        ->extraModalWindowAttributes(['class' => 'professor-request-review-modal'], merge: true)
                        ->modalHeading(fn (Servidor $record): string => "Solicitações de {$record->nome}")
                        ->modalDescription('Confirme ou recuse cada turma e componente solicitado pelo professor.')
                        ->modalSubmitActionLabel('Salvar decisões')
                        ->schema(function (Servidor $record): array {
                            $solicitacoes = app(ProfessorComponenteSolicitacaoService::class)
                                ->solicitacoesParaAnaliseDoServidor(Auth::user(), $record->getKey());
                            $turmas = $solicitacoes
                                ->groupBy(fn (ProfessorComponenteSolicitacao $solicitacao): string => (string) ($solicitacao->vinculo?->turma_id ?: 'solicitacao-'.$solicitacao->getKey()))
                                ->map(function ($solicitacoesDaTurma): array {
                                    $primeira = $solicitacoesDaTurma->first();
                                    $turmaLabel = collect([
                                        'Turma '.$primeira?->vinculo?->turma?->nome,
                                        $primeira?->vinculo?->turma?->serie?->nome,
                                        $primeira?->vinculo?->turma?->escola?->nome,
                                    ])->filter()->implode(' · ');

                                    return [
                                        'turma_label' => $turmaLabel,
                                        'turma_descricao' => $turmaLabel,
                                        'decisao_turma' => 'pendente',
                                        'componentes' => $solicitacoesDaTurma->map(fn (ProfessorComponenteSolicitacao $solicitacao): array => [
                                            'solicitacao_id' => $solicitacao->getKey(),
                                            'descricao' => collect([
                                                $solicitacao->vinculo?->componente?->nome,
                                                $solicitacao->vinculo?->professor_id
                                                    ? 'Substitui '.($solicitacao->vinculo->professor?->nomeCanonico() ?: 'professor atual')
                                                    : 'Sem professor vinculado',
                                            ])->filter()->implode(' · '),
                                            'decisao' => 'pendente',
                                        ])->values()->all(),
                                    ];
                                })->values()->all();

                            return [
                                Select::make('decisao_geral')
                                    ->label('Decisão em massa')
                                    ->options([
                                        'pendente' => 'Decidir individualmente',
                                        'aprovar' => 'Aprovar todas as solicitações',
                                        'recusar' => 'Recusar todas as solicitações',
                                    ])
                                    ->default('pendente')
                                    ->helperText('Use esta opção para aplicar a mesma decisão em todas as turmas deste professor.')
                                    ->required(),
                                Repeater::make('turmas')
                                    ->label('Turmas pendentes')
                                    ->default($turmas)
                                    ->schema([
                                        Hidden::make('turma_label'),
                                        Textarea::make('turma_descricao')
                                            ->label('Turma')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->rows(2)
                                            ->columnSpan(2),
                                        Select::make('decisao_turma')
                                            ->label('Decisão da turma')
                                            ->options([
                                                'pendente' => 'Decidir componentes',
                                                'aprovar' => 'Aprovar turma inteira',
                                                'recusar' => 'Recusar turma inteira',
                                            ])
                                            ->required(),
                                        Repeater::make('componentes')
                                            ->label('Componentes solicitados')
                                            ->schema([
                                                Textarea::make('descricao')
                                                    ->label('Componente')
                                                    ->disabled()
                                                    ->dehydrated(false)
                                                    ->rows(2)
                                                    ->columnSpan(2),
                                                Hidden::make('solicitacao_id'),
                                                Select::make('decisao')
                                                    ->label('Decisão')
                                                    ->options([
                                                        'pendente' => 'Não decidir agora',
                                                        'aprovar' => 'Aprovar componente',
                                                        'recusar' => 'Recusar componente',
                                                    ])
                                                    ->required(),
                                            ])
                                            ->columns(3)
                                            ->columnSpanFull()
                                            ->addable(false)
                                            ->deletable(false)
                                            ->reorderable(false),
                                    ])
                                    ->columns(3)
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => $state['turma_label'] ?? $state['turma_descricao'] ?? null)
                                    ->addable(false)
                                    ->deletable(false)
                                    ->reorderable(false),
                            ];
                        })
                        ->action(function (Servidor $record, array $data): void {
                            $service = app(ProfessorComponenteSolicitacaoService::class);
                            $processadas = 0;
                            $decisaoGeral = $data['decisao_geral'] ?? 'pendente';

                            foreach ($data['turmas'] ?? [] as $turma) {
                                $decisaoTurma = $turma['decisao_turma'] ?? 'pendente';

                                foreach ($turma['componentes'] ?? [] as $componente) {
                                    $solicitacaoId = (int) ($componente['solicitacao_id'] ?? 0);
                                    $tipo = $decisaoGeral !== 'pendente'
                                        ? $decisaoGeral
                                        : ($decisaoTurma !== 'pendente' ? $decisaoTurma : ($componente['decisao'] ?? 'pendente'));

                                    if (! $solicitacaoId || ! in_array($tipo, ['aprovar', 'recusar'], true)) {
                                        continue;
                                    }

                                    $solicitacao = ProfessorComponenteSolicitacao::query()
                                        ->whereKey($solicitacaoId)
                                        ->where('status', ProfessorComponenteSolicitacao::STATUS_PENDENTE)
                                        ->first();

                                    if (! $solicitacao) {
                                        continue;
                                    }

                                    $tipo === 'aprovar'
                                        ? $service->aprovar(Auth::user(), $solicitacaoId)
                                        : $service->rejeitar(Auth::user(), $solicitacaoId);
                                    $processadas++;
                                }
                            }

                            Notification::make()
                                ->title($processadas ? 'Decisões salvas' : 'Nenhuma decisão alterada')
                                ->body($processadas ? "{$processadas} solicitação(ões) analisada(s)." : 'Selecione aprovar ou recusar em pelo menos uma solicitação.')
                                ->{$processadas ? 'success' : 'info'}()
                                ->send();
                        }),

                    Action::make('alterar_status')
                        ->label('Alterar status')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->visible(fn (Servidor $record): bool => ! $record->trashed()
                            && static::usuarioPodeGerenciarEstrutura($record))
                        ->modalHeading('Alterar status da pessoa')
                        ->modalDescription(fn (Servidor $record): string => "Selecione o novo status de {$record->nome}.")
                        ->fillForm(fn (Servidor $record): array => [
                            'status' => $record->status,
                        ])
                        ->schema([
                            Select::make('status')
                                ->label('Status')
                                ->options(Servidor::statusOptions())
                                ->required(),
                        ])
                        ->action(function (Servidor $record, array $data): void {
                            Gate::authorize('manageStructure', $record);

                            app(ServidorService::class)->alterarStatus(
                                $record,
                                (string) ($data['status'] ?? ''),
                            );

                            Notification::make()
                                ->title('Status atualizado')
                                ->success()
                                ->send();
                        }),

                    DeleteAction::make()
                        ->label('Arquivar')
                        ->requiresConfirmation()
                        ->modalHeading('Arquivar pessoa')
                        ->modalDescription(function (Servidor $record): string {
                            $motivo = app(ServidorService::class)->motivoBloqueioExclusao($record);

                            if ($motivo) {
                                return $motivo;
                            }

                            return "Arquivar \"{$record->nome}\"? "
                                .'A pessoa ficará inativa e oculta da listagem padrão. Matrículas, lotações, vínculos, histórico e conta de login serão preservados.';
                        })
                        ->visible(fn (Servidor $record): bool => ! $record->trashed()
                            && Gate::allows('delete', $record)
                            && (app(ServidorService::class)->pessoaPodeSerExcluida($record)
                                || ServidorEquipeGestoraForm::usuarioPodeAdministrar()))
                        ->before(function (DeleteAction $action, Servidor $record): void {
                            $motivo = app(ServidorService::class)->motivoBloqueioExclusao($record);

                            if (! $motivo) {
                                return;
                            }

                            $notification = Notification::make()
                                ->title('Pessoa não pode ser arquivada')
                                ->body($motivo)
                                ->warning()
                                ->persistent();

                            if (app(ServidorService::class)->possuiEventosFuturosComoMotorista($record)
                                && Gate::allows('manageTransport', EventoCalendario::class)) {
                                $notification->actions([
                                    Action::make('corrigirVinculos')
                                        ->label('Corrigir vínculos nos eventos')
                                        ->button()
                                        ->url(GerenciarEventos::getUrl([
                                            'tableFilters' => [
                                                'motorista_id' => ['value' => $record->getKey()],
                                            ],
                                        ])),
                                ]);
                            }

                            $notification->send();

                            $action->halt();
                        })
                        ->using(function (Servidor $record): bool {
                            app(ServidorService::class)->arquivarPessoa($record);

                            Notification::make()
                                ->title('Pessoa arquivada')
                                ->success()
                                ->send();

                            return true;
                        }),

                    RestoreAction::make()
                        ->label('Restaurar')
                        ->modalHeading('Restaurar pessoa')
                        ->modalDescription('A pessoa voltará à listagem como inativa. Cargos, lotações e acessos não serão reativados automaticamente.')
                        ->visible(fn (Servidor $record): bool => $record->trashed() && Gate::allows('restore', $record))
                        ->using(fn (Servidor $record): Servidor => app(ServidorService::class)->restaurarPessoa($record))
                        ->successNotificationTitle('Pessoa restaurada como inativa'),

                ])
                    ->label('Ações')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->button()
                    ->color('gray')
                    ->dropdownPlacement('bottom-end')
                    ->dropdownOffset(6)
                    ->dropdownTeleport(),
            ], position: RecordActionsPosition::AfterContent)
            ->toolbarActions([
                ExportSelectedRecordsBulkAction::make(
                    'servidores_selecionados',
                    'XLSX de servidores selecionados',
                    'servidores.bulk_action',
                ),

                ...PessoaAcessoActions::bulkActions(),

                BulkAction::make('alterar_status_em_massa')
                    ->label('Alterar status')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (): bool => static::usuarioPodeGerenciarEstrutura())
                    ->modalHeading('Alterar status das pessoas selecionadas')
                    ->modalDescription('O novo status será aplicado a todas as pessoas selecionadas.')
                    ->schema([
                        Select::make('status')
                            ->label('Status')
                            ->options(Servidor::statusOptions())
                            ->required(),
                    ])
                    ->action(function ($records, array $data): void {
                        $records = collect($records);
                        $autorizados = $records
                            ->filter(fn ($record): bool => $record instanceof Servidor
                                && ! $record->trashed()
                                && Gate::allows('manageStructure', $record))
                            ->values();
                        $ignorados = $records->count() - $autorizados->count();

                        $alterados = app(ServidorService::class)->alterarStatusEmMassa(
                            $autorizados,
                            (string) ($data['status'] ?? ''),
                        );

                        $notification = Notification::make()
                            ->title('Status atualizado em massa')
                            ->body($ignorados > 0
                                ? "{$alterados} pessoa(s) alterada(s); {$ignorados} registro(s) protegido(s) ignorado(s)."
                                : "{$alterados} pessoa(s) alterada(s).");

                        ($ignorados > 0 ? $notification->warning() : $notification->success())->send();
                    })
                    ->deselectRecordsAfterCompletion(),

                DeleteBulkAction::make()
                    ->label('Arquivar selecionados')
                    ->modalSubmitActionLabel('Arquivar')
                    ->requiresConfirmation()
                    ->modalHeading('Arquivar pessoas em massa')
                    ->modalDescription('As pessoas ficarão inativas e ocultas da listagem padrão. Matrículas, vínculos, histórico e contas de login serão preservados.')
                    ->visible(fn (): bool => Gate::allows('deleteAny', Servidor::class))
                    ->deselectRecordsAfterCompletion()
                    ->using(function ($records): void {
                        foreach ($records as $record) {
                            if (! $record instanceof Servidor || ! Gate::allows('delete', $record)) {
                                throw new AuthorizationException(
                                    'Você não possui permissão para arquivar uma ou mais pessoas selecionadas.',
                                );
                            }
                        }

                        if (! ServidorEquipeGestoraForm::usuarioPodeAdministrar()
                            && $records->contains(
                                fn (Servidor $record): bool => ! app(ServidorService::class)->pessoaPodeSerExcluida($record),
                            )) {
                            throw new AuthorizationException(
                                'Você não possui permissão para arquivar pessoas da Equipe Gestora.',
                            );
                        }

                        $resultado = app(ServidorService::class)->excluirPessoasEmMassa($records);

                        if ($resultado['excluidos'] > 0) {
                            Notification::make()
                                ->title('Arquivamento concluído')
                                ->body("{$resultado['excluidos']} pessoa(s) arquivada(s).")
                                ->success()
                                ->send();
                        }

                        if ($resultado['bloqueados'] !== []) {
                            Notification::make()
                                ->title('Algumas pessoas não foram arquivadas')
                                ->body(collect($resultado['bloqueados'])->take(5)->implode("\n"))
                                ->warning()
                                ->persistent()
                                ->send();
                        }
                    }),

                RestoreBulkAction::make()
                    ->label('Restaurar selecionados')
                    ->modalHeading('Restaurar pessoas')
                    ->modalDescription('As pessoas voltarão como inativas; cargos, lotações e acessos permanecerão inativos.')
                    ->using(function ($records): void {
                        foreach ($records as $record) {
                            if (! $record instanceof Servidor || ! Gate::allows('restore', $record)) {
                                throw new AuthorizationException(
                                    'Você não possui permissão para restaurar uma ou mais pessoas selecionadas.',
                                );
                            }

                            app(ServidorService::class)->restaurarPessoa($record);
                        }
                    })
                    ->successNotificationTitle('Pessoas restauradas como inativas'),
            ])
            ->defaultSort('updated_at', 'desc')
            ->striped();
    }

    public static function pessoaPodeSerSelecionada(Servidor $record): bool
    {
        if (! $record->user) {
            return true;
        }

        if ($record->user->id === 1 || $record->user->id === Auth::id()) {
            return false;
        }

        return app(UserService::class)->podeSelecionarRegistro(Auth::user(), $record->user);
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

    public static function exibeEscolaNaListagem(Servidor $record): bool
    {
        $record->loadMissing(['professores', 'vinculosAtivos.funcaoAdministrativa']);

        return static::ehEquipeGestora($record)
            || $record->professores->where('ativo', true)->isNotEmpty();
    }

    public static function ehManutencao(Servidor $record): bool
    {
        $record->loadMissing('vinculosAtivos.funcaoAdministrativa');

        return $record->vinculosAtivos
            ->contains(fn ($vinculo): bool => (bool) $vinculo->funcaoAdministrativa?->ehManutencao());
    }

    public static function ehObras(Servidor $record): bool
    {
        $record->loadMissing('vinculosAtivos.funcaoAdministrativa');

        return $record->vinculosAtivos
            ->contains(fn ($vinculo): bool => (bool) $vinculo->funcaoAdministrativa?->ehObras());
    }

    public static function ehMotorista(Servidor $record): bool
    {
        $record->loadMissing('vinculosAtivos.funcaoAdministrativa');

        return $record->vinculosAtivos
            ->contains(fn ($vinculo): bool => (bool) $vinculo->funcaoAdministrativa?->ehMotorista());
    }

    public static function ehTransporte(Servidor $record): bool
    {
        $record->loadMissing('vinculosAtivos.funcaoAdministrativa');

        return $record->vinculosAtivos
            ->contains(fn ($vinculo): bool => (bool) $vinculo->funcaoAdministrativa?->ehTransporte());
    }

    public static function ehAssessoriaPedagogica(Servidor $record): bool
    {
        $record->loadMissing('vinculosAtivos.funcaoAdministrativa');

        return $record->vinculosAtivos
            ->contains(fn ($vinculo): bool => (bool) $vinculo->funcaoAdministrativa?->ehAssessoriaPedagogica());
    }

    public static function cargoLabel(Servidor $record): string
    {
        $record->loadMissing(['professores', 'vinculosAtivos.funcaoAdministrativa']);

        if (static::ehObras($record)) {
            return 'Obras';
        }

        if (static::ehManutencao($record)) {
            return 'Manutenção';
        }

        if (static::ehMotorista($record)) {
            return 'Motorista';
        }

        if (static::ehTransporte($record)) {
            return 'Transporte';
        }

        if (static::ehAssessoriaPedagogica($record)) {
            return 'Assessoria Pedagógica';
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

    public static function escolasLabel(Servidor $record): string
    {
        $record->loadMissing([
            'escola:id,nome',
            'professores.escola:id,nome',
            'vinculosAtivos.escola:id,nome',
            'vinculosAtivos.escolasAssessoradas:id,nome',
        ]);

        $scope = app(PessoaScopeService::class);
        $user = Auth::user();
        $escolas = collect();

        if (filled($record->id_escola) && $scope->canAccessEscola($user, (int) $record->id_escola)) {
            $nomeEscolaPrincipal = $record->escola?->nome
                ?? Escola::query()->whereKey($record->id_escola)->value('nome');

            $escolas->push($nomeEscolaPrincipal);
        }

        return $escolas
            ->merge(static::professoresVisiveis($record)->pluck('escola.nome'))
            ->merge(static::vinculosVisiveis($record)->pluck('escola.nome'))
            ->merge(static::vinculosVisiveis($record)->flatMap->escolasAssessoradas->pluck('nome'))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->implode(', ') ?: '—';
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

    public static function setoresOptionsEscopados(): array
    {
        $ids = app(PessoaScopeService::class)->visibleSetorIds(Auth::user());

        if ($ids === []) {
            return [];
        }

        return Setor::query()
            ->where('ativo', true)
            ->whereIn('id', $ids)
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->toArray();
    }

    /** @return array<int, string|callable> */
    private static function camposBuscaTabela(): array
    {
        $scope = app(PessoaScopeService::class);
        $acessoGlobal = $scope->hasGlobalAccess(Auth::user());
        $campos = [
            'nome',
            'cpf',
            'email',
            'setor.nome',
        ];

        if ($acessoGlobal) {
            $campos[] = 'matriculas.matricula';
            $campos[] = 'professores.escola.nome';
        } else {
            $campos[] = function (Builder $query, string $search): Builder {
                return $query->whereHas('matriculas', function (Builder $matriculas) use ($search): Builder {
                    return static::restringirMatriculasAoEscopo($matriculas)
                        ->where('matricula', 'like', "%{$search}%");
                });
            };
        }

        if (Gate::allows('viewAny', User::class)) {
            $campos[] = 'user.roles.name';
        }

        return $campos;
    }

    private static function placeholderBuscaTabela(): string
    {
        $itens = ['nome', 'CPF', 'e-mail', 'matrícula', 'setor'];

        if (app(PessoaScopeService::class)->hasGlobalAccess(Auth::user())) {
            $itens[] = 'escola';
        }

        if (Gate::allows('viewAny', User::class)) {
            $itens[] = 'nível de acesso';
        }

        return 'Buscar por '.collect($itens)->join(', ', ' ou ');
    }

    public static function aplicarFiltroQuantidadeMatriculas(Builder $query, mixed $quantidade): Builder
    {
        if (! in_array($quantidade, ['uma_ou_mais', 'uma', 'duas', 'tres_ou_mais', 'sem'], true)) {
            return $query;
        }

        if (app(PessoaScopeService::class)->hasGlobalAccess(Auth::user())) {
            return match ($quantidade) {
                'uma_ou_mais' => $query->has('matriculas', '>=', 1),
                'uma' => $query->has('matriculas', '=', 1),
                'duas' => $query->has('matriculas', '=', 2),
                'tres_ou_mais' => $query->has('matriculas', '>=', 3),
                'sem' => $query->doesntHave('matriculas'),
            };
        }

        return match ($quantidade) {
            'uma_ou_mais' => $query->whereHas(
                'matriculas',
                fn (Builder $matriculas): Builder => static::restringirMatriculasAoEscopo($matriculas),
                '>=',
                1,
            ),
            'uma' => $query->whereHas(
                'matriculas',
                fn (Builder $matriculas): Builder => static::restringirMatriculasAoEscopo($matriculas),
                '=',
                1,
            ),
            'duas' => $query->whereHas(
                'matriculas',
                fn (Builder $matriculas): Builder => static::restringirMatriculasAoEscopo($matriculas),
                '=',
                2,
            ),
            'tres_ou_mais' => $query->whereHas(
                'matriculas',
                fn (Builder $matriculas): Builder => static::restringirMatriculasAoEscopo($matriculas),
                '>=',
                3,
            ),
            'sem' => $query->whereDoesntHave(
                'matriculas',
                fn (Builder $matriculas): Builder => static::restringirMatriculasAoEscopo($matriculas),
            ),
        };
    }

    /** @param list<string> $turnos */
    public static function aplicarFiltroTurnosMatriculas(Builder $query, array $turnos): Builder
    {
        return $query->whereHas('matriculas', function (Builder $matriculas) use ($turnos): Builder {
            $matriculas->whereIn('turno', $turnos);

            return app(PessoaScopeService::class)->hasGlobalAccess(Auth::user())
                ? $matriculas
                : static::restringirMatriculasAoEscopo($matriculas);
        });
    }

    private static function restringirMatriculasAoEscopo(Builder $matriculas): Builder
    {
        $escolaIds = app(PessoaScopeService::class)->escolaIdsDosVinculos(Auth::user());

        if ($escolaIds === []) {
            return $matriculas->whereRaw('1 = 0');
        }

        return $matriculas->whereHas(
            'professores',
            fn (Builder $professores): Builder => $professores
                ->where('ativo', true)
                ->whereIn('id_escola', $escolaIds),
        );
    }

    /** @param list<string> $cargos */
    public static function aplicarFiltroCargos(Builder $query, array $cargos): Builder
    {
        return $query->where(function (Builder $pessoas) use ($cargos): void {
            foreach (array_values($cargos) as $index => $cargo) {
                $metodo = $index === 0 ? 'where' : 'orWhere';

                $pessoas->{$metodo}(function (Builder $pessoasDoCargo) use ($cargo): void {
                    if ($cargo === self::CARGO_PROFESSOR) {
                        $pessoasDoCargo->whereHas(
                            'professores',
                            fn (Builder $professores): Builder => $professores->where('ativo', true),
                        );

                        return;
                    }

                    if ($cargo === ServidorEquipeGestoraForm::CARGO_DIRETOR) {
                        $pessoasDoCargo->whereHas(
                            'vinculosAtivos.funcaoAdministrativa',
                            fn (Builder $funcoes): Builder => $funcoes->direcao(),
                        );

                        return;
                    }

                    if ($cargo === ServidorEquipeGestoraForm::CARGO_COORDENADOR) {
                        $pessoasDoCargo->whereHas(
                            'vinculosAtivos.funcaoAdministrativa',
                            fn (Builder $funcoes): Builder => $funcoes->coordenacao(),
                        );

                        return;
                    }

                    if ($cargo === ServidorEquipeGestoraForm::CARGO_SECRETARIO) {
                        $pessoasDoCargo->whereHas(
                            'vinculosAtivos.funcaoAdministrativa',
                            fn (Builder $funcoes): Builder => $funcoes->secretaria(),
                        );

                        return;
                    }

                    if ($cargo === self::CARGO_MANUTENCAO) {
                        $pessoasDoCargo->whereHas(
                            'vinculosAtivos.funcaoAdministrativa',
                            fn (Builder $funcoes): Builder => $funcoes->manutencao(),
                        );

                        return;
                    }

                    if ($cargo === self::CARGO_OBRAS) {
                        $pessoasDoCargo->whereHas(
                            'vinculosAtivos.funcaoAdministrativa',
                            fn (Builder $funcoes): Builder => $funcoes->obras(),
                        );

                        return;
                    }

                    if ($cargo === self::CARGO_MOTORISTA) {
                        $pessoasDoCargo->whereHas(
                            'vinculosAtivos.funcaoAdministrativa',
                            fn (Builder $funcoes): Builder => $funcoes->motorista(),
                        );

                        return;
                    }

                    if ($cargo === self::CARGO_TRANSPORTE) {
                        $pessoasDoCargo->whereHas(
                            'vinculosAtivos.funcaoAdministrativa',
                            fn (Builder $funcoes): Builder => $funcoes->transporte(),
                        );

                        return;
                    }

                    if ($cargo === self::CARGO_ASSESSORIA_PEDAGOGICA) {
                        $pessoasDoCargo->whereHas(
                            'vinculosAtivos.funcaoAdministrativa',
                            fn (Builder $funcoes): Builder => $funcoes->assessoriaPedagogica(),
                        );

                        return;
                    }

                    $pessoasDoCargo
                        ->whereDoesntHave(
                            'professores',
                            fn (Builder $professores): Builder => $professores->where('ativo', true),
                        )
                        ->whereDoesntHave(
                            'vinculosAtivos.funcaoAdministrativa',
                            function (Builder $funcoes): Builder {
                                return $funcoes->where(function (Builder $cargosReconhecidos): void {
                                    static::aplicarFiltroFuncaoGestora($cargosReconhecidos);
                                    $cargosReconhecidos
                                        ->orWhere('codigo', 'manutencao')
                                        ->orWhere('codigo', 'obras')
                                        ->orWhere('codigo', 'motorista')
                                        ->orWhere('codigo', 'transporte')
                                        ->orWhere('codigo', 'assessoria-pedagogica');
                                });
                            },
                        );
                });
            }
        });
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
     * @return array<int, Component>
     */
    public static function infolistDetalhesCompletos(Servidor $record): array
    {
        return [
            View::make('filament.admin.resources.servidores.partials.pessoa-detalhes')
                ->viewData([
                    'detalhes' => static::detalhesVisualizacaoPersonalizada($record),
                ])
                ->columnSpanFull(),
        ];
    }

    /**
     * Prepara a ficha personalizada sem ampliar o escopo escolar ou de acesso do usuário autenticado.
     *
     * @return array<string, mixed>
     */
    public static function detalhesVisualizacaoPersonalizada(Servidor $record): array
    {
        $record->loadMissing([
            'user.roles',
            'user.escola',
            'professores.escola',
            'matriculas',
            'lotacao.escola',
            'vinculosAtivos.funcaoAdministrativa',
            'vinculosAtivos.escola',
            'vinculosAtivos.escolasAssessoradas',
            'vinculosAtivos.setor',
            'vinculosAtivos.vinculosTurmaAtivos.turma.serie',
        ]);

        $professores = static::professoresVisiveis($record);
        $vinculos = static::vinculosVisiveis($record);
        $scope = app(PessoaScopeService::class);
        $usuario = Auth::user();

        $escolas = collect();

        if (filled($record->id_escola) && $scope->canAccessEscola($usuario, (int) $record->id_escola)) {
            $escolas->push($record->escola?->nome
                ?? Escola::query()->whereKey($record->id_escola)->value('nome'));
        }

        $escolas = $escolas
            ->merge($professores->pluck('escola.nome'))
            ->merge($vinculos->pluck('escola.nome'))
            ->merge($vinculos->flatMap->escolasAssessoradas->pluck('nome'))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $acessoGlobal = $scope->hasGlobalAccess($usuario);
        $numerosVinculosVisiveis = $professores
            ->pluck('matricula')
            ->merge($vinculos->pluck('matricula'))
            ->filter()
            ->map(fn (mixed $matricula): string => (string) $matricula)
            ->unique()
            ->values();

        $matriculasPersistidas = $acessoGlobal
            ? $record->matriculas
            : $record->matriculas->filter(
                fn (PessoaMatricula $matricula): bool => $numerosVinculosVisiveis
                    ->contains((string) $matricula->matricula),
            );

        $matriculas = $matriculasPersistidas
            ->map(fn (PessoaMatricula $matricula): array => [
                'numero' => (string) $matricula->matricula,
                'turno' => $matricula->turnoLabel(),
            ]);

        foreach ($professores as $professor) {
            if (filled($professor->matricula)) {
                $matriculas->push([
                    'numero' => (string) $professor->matricula,
                    'turno' => $professor->turnoLabel(),
                ]);
            }
        }

        foreach ($vinculos as $vinculo) {
            if (blank($vinculo->matricula)) {
                continue;
            }

            $matriculaPersistida = $record->matriculas->firstWhere('matricula', $vinculo->matricula);
            $matriculas->push([
                'numero' => (string) $vinculo->matricula,
                'turno' => $matriculaPersistida?->turnoLabel() ?? 'Não informado',
            ]);
        }

        $matriculaLegadaVisivel = $acessoGlobal
            || $numerosVinculosVisiveis->contains((string) $record->matricula)
            || (filled($record->id_escola) && $scope->canAccessEscola($usuario, (int) $record->id_escola));

        if (filled($record->matricula) && $matriculaLegadaVisivel) {
            $professorLegado = $professores->firstWhere('matricula', $record->matricula);

            $matriculas->prepend([
                'numero' => (string) $record->matricula,
                'turno' => $professorLegado?->turnoLabel() ?? 'Não informado',
            ]);
        }

        $matriculas = $matriculas
            ->filter(fn (array $matricula): bool => filled($matricula['numero']))
            ->unique('numero')
            ->values();

        $partesNome = collect(preg_split('/\\s+/', trim((string) $record->nome)) ?: [])
            ->filter()
            ->values();
        $iniciais = mb_strtoupper(
            mb_substr((string) $partesNome->first(), 0, 1)
            .mb_substr((string) ($partesNome->count() > 1 ? $partesNome->last() : ''), 0, 1),
        ) ?: 'P';

        $status = $record->trashed()
            ? ['label' => 'Arquivada', 'tone' => 'danger']
            : [
                'label' => Servidor::statusOptions()[$record->status] ?? 'Não informado',
                'tone' => match ($record->status) {
                    Servidor::STATUS_ATIVO => 'success',
                    Servidor::STATUS_INATIVO => 'gray',
                    default => 'warning',
                },
            ];

        $jornada = [
            'label' => $record->jornadaLabel(),
            'tone' => $record->jornadaLabel() === 'Sim' ? 'success' : 'gray',
        ];

        $lotacaoVisivel = static::lotacaoPodeSerVista($record);
        $setorVisivel = static::ehObras($record) || static::ehManutencao($record);
        $portariaVisivel = static::possuiFuncaoGestora($record, 'direcao_escolar')
            || static::possuiFuncaoGestora($record, 'coordenacao_pedagogica');

        $pedagogico = null;

        if ($professores->isNotEmpty()) {
            $grupos = static::gruposTurmasComponentes($record);
            $pedagogico = [
                'tipo' => 'professor',
                'label' => 'Turmas e componentes',
                'descricao' => 'Vínculos pedagógicos organizados por escola, turno e matrícula.',
                'grupos' => $grupos,
                'total_turmas' => collect($grupos)->sum(fn (array $grupo): int => count($grupo['turmas'] ?? [])),
            ];
        } elseif (static::possuiFuncaoGestora($record, 'coordenacao_pedagogica')) {
            $grupos = static::gruposTurmasCoordenacao($record);
            $pedagogico = [
                'tipo' => 'coordenacao',
                'label' => 'Turmas coordenadas',
                'descricao' => 'Turmas vinculadas à atuação desta pessoa na coordenação pedagógica.',
                'grupos' => $grupos,
                'total_turmas' => collect($grupos)->sum(fn (array $grupo): int => count($grupo['turmas'] ?? [])),
            ];
        }

        $acesso = null;

        if (Gate::allows('viewAny', User::class)) {
            $conta = $record->user;

            if (! $conta) {
                $acesso = [
                    'possui_conta' => false,
                    'status' => 'Sem conta vinculada',
                    'tone' => 'gray',
                ];
            } else {
                $acessoPermitido = ! $conta->trashed()
                    && (bool) $conta->ativo
                    && (bool) $conta->email_approved
                    && $conta->canAuthenticate();

                [$statusAcesso, $toneAcesso] = match (true) {
                    $conta->trashed() => ['Conta arquivada', 'danger'],
                    ! $conta->ativo => ['Conta inativa', 'gray'],
                    ! $conta->email_approved => ['Aguardando aprovação', 'warning'],
                    $acessoPermitido => ['Acesso liberado', 'success'],
                    default => ['Acesso indisponível', 'warning'],
                };

                $acesso = [
                    'possui_conta' => true,
                    'status' => $statusAcesso,
                    'tone' => $toneAcesso,
                    'nome' => $conta->name ?: 'Não informado',
                    'email' => $conta->email ?: 'Não informado',
                    'email_aprovado' => $conta->email_approved ? 'Sim' : 'Não',
                    'email_verificado' => $conta->email_verified_at ? 'Sim' : 'Não',
                    'troca_senha' => $conta->must_change_password ? 'Pendente' : 'Não exigida',
                    'escola' => $conta->escola?->nome ?: 'Não informada',
                    'perfis' => $conta->roles->pluck('name')->filter()->sort()->values()->all(),
                    'ultimo_login' => $conta->last_login_at?->format('d/m/Y H:i') ?? 'Nunca acessou',
                    'ultima_atividade' => $conta->last_seen_at?->format('d/m/Y H:i') ?? 'Não registrada',
                ];
            }
        }

        return [
            'iniciais' => $iniciais,
            'nome' => (string) $record->nome,
            'cpf' => Pessoa::formatarCpf($record->cpf) ?: 'Não informado',
            'email' => $record->email ?: 'Não informado',
            'telefone' => $record->telefone ?: 'Não informado',
            'observacoes' => $record->observacoes ?: 'Nenhuma observação cadastrada.',
            'status' => $status,
            'cargo' => static::cargoLabel($record),
            'carga_horaria' => $record->cargaHorariaLabel(),
            'jornada' => $jornada,
            'lotacao_visivel' => $lotacaoVisivel,
            'lotacao' => $lotacaoVisivel ? $record->lotacaoLabel() : null,
            'lotacao_escola' => $lotacaoVisivel
                ? ($record->lotacao?->escola?->nome ?? 'Não informada')
                : null,
            'setor' => $setorVisivel ? static::setorOperacionalLabel($record) : null,
            'portaria' => $portariaVisivel
                ? (static::portariasGestoras($record) ?: 'Não informada')
                : null,
            'matriculas' => $matriculas->all(),
            'escolas' => $escolas->all(),
            'vinculos_ativos' => $professores->count() + $vinculos->count(),
            'criado_em' => $record->created_at?->format('d/m/Y H:i') ?? 'Não informado',
            'atualizado_em' => $record->updated_at?->format('d/m/Y H:i') ?? 'Não informado',
            'acesso' => $acesso,
            'pedagogico' => $pedagogico,
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

        $vinculosPorProfessor = TurmaComponenteProfessor::query()
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

        return $gruposProfessor->all();
    }

    /** @return array<int, array{escola: string, turmas: array<int, string>}> */
    public static function gruposTurmasCoordenacao(Servidor $record): array
    {
        $record->loadMissing([
            'vinculosAtivos.funcaoAdministrativa',
            'vinculosAtivos.escola',
            'vinculosAtivos.vinculosTurmaAtivos.turma.serie',
        ]);

        return static::vinculosVisiveis($record)
            ->filter(fn ($vinculo): bool => (bool) $vinculo->funcaoAdministrativa?->coordenacao_pedagogica)
            ->groupBy(fn ($vinculo): string => (string) ($vinculo->id_escola ?: 'sem-escola'))
            ->map(function ($vinculos): array {
                $primeiro = $vinculos->first();

                return [
                    'escola' => $primeiro?->escola?->nome ?? 'Sem escola definida',
                    'turmas' => $vinculos
                        ->flatMap(fn ($vinculo) => $vinculo->vinculosTurmaAtivos)
                        ->map(function ($vinculoTurma): string {
                            $turma = $vinculoTurma->turma;

                            return collect([$turma?->serie?->nome, $turma?->nome ?? ('Turma #'.$vinculoTurma->turma_id)])
                                ->filter()
                                ->implode(' - ');
                        })
                        ->filter()
                        ->unique()
                        ->sort()
                        ->values()
                        ->all(),
                ];
            })
            ->sortBy('escola')
            ->values()
            ->all();
    }

    private static function possuiFuncaoGestora(Servidor $record, string $atributo): bool
    {
        return static::vinculosVisiveis($record)
            ->contains(fn ($vinculo): bool => (bool) $vinculo->funcaoAdministrativa?->{$atributo});
    }

    private static function portariasGestoras(Servidor $record): string
    {
        return static::vinculosVisiveis($record)
            ->filter(fn ($vinculo): bool => (bool) (
                $vinculo->funcaoAdministrativa?->direcao_escolar
                || $vinculo->funcaoAdministrativa?->coordenacao_pedagogica
            ))
            ->pluck('portaria')
            ->filter()
            ->unique()
            ->implode(' / ');
    }

    private static function lotacaoPodeSerVista(Servidor $record): bool
    {
        if (blank($record->lotacao_id)) {
            return true;
        }

        return app(PessoaScopeService::class)->canAccessEscola(
            Auth::user(),
            filled($record->lotacao?->escola_id) ? (int) $record->lotacao->escola_id : null,
        );
    }

    private static function setorOperacionalLabel(Servidor $record): string
    {
        $record->loadMissing('vinculosAtivos.funcaoAdministrativa', 'vinculosAtivos.setor');

        return $record->vinculosAtivos
            ->first(fn ($vinculo): bool => (bool) (
                $vinculo->funcaoAdministrativa?->ehManutencao()
                || $vinculo->funcaoAdministrativa?->ehObras()
            ))?->setor?->nome_completo ?? 'Não informado';
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
        $recordEraObras = $record ? static::ehObras($record) : false;
        $recordEraMotorista = $record ? static::ehMotorista($record) : false;
        $recordEraTransporte = $record ? static::ehTransporte($record) : false;
        $recordEraAssessoriaPedagogica = $record ? static::ehAssessoriaPedagogica($record) : false;

        if (($cargo === self::CARGO_EQUIPE_GESTORA
            || $cargo === self::CARGO_MANUTENCAO
            || $cargo === self::CARGO_OBRAS
            || $cargo === self::CARGO_MOTORISTA
            || $cargo === self::CARGO_TRANSPORTE
            || $cargo === self::CARGO_ASSESSORIA_PEDAGOGICA
            || $recordEraGestor
            || $recordEraManutencao
            || $recordEraObras
            || $recordEraMotorista
            || $recordEraTransporte
            || $recordEraAssessoriaPedagogica)
            && ! ServidorEquipeGestoraForm::usuarioPodeAdministrar()) {
            throw new AuthorizationException(
                'Apenas Admin ou usuário com a permissão Gerenciar Vínculos Estruturais de Pessoas pode administrar cargos funcionais.',
            );
        }

        unset($data['registros_professor'], $data['matriculas_professor']);
        $data['cargo'] = $cargo;

        if ($cargo === self::CARGO_MOTORISTA) {
            $data['matricula'] = filled($data['matricula_motorista'] ?? null)
                ? trim((string) $data['matricula_motorista'])
                : null;
            $data['id_escola'] = null;
            $data['setor_id'] = null;

            return [$data, ['motorista' => [
                'matricula' => $data['matricula'],
            ]]];
        }

        if ($cargo === self::CARGO_TRANSPORTE) {
            $data['matricula'] = filled($data['matricula_operacional'] ?? null)
                ? trim((string) $data['matricula_operacional'])
                : null;
            $data['id_escola'] = null;
            $data['setor_id'] = null;

            return [$data, ['transporte' => [
                'matricula' => $data['matricula'],
            ]]];
        }

        if ($cargo === self::CARGO_ASSESSORIA_PEDAGOGICA) {
            $data['matricula'] = filled($data['matricula_operacional'] ?? null)
                ? trim((string) $data['matricula_operacional'])
                : null;
            $data['id_escola'] = null;
            $data['setor_id'] = null;

            return [$data, ['assessoria_pedagogica' => [
                'matricula' => $data['matricula'],
                'turno' => $data['turno_operacional'] ?? null,
                'escola_ids' => collect($data['escola_ids_assessoria'] ?? [])
                    ->filter(fn (mixed $id): bool => filled($id))
                    ->map(fn (mixed $id): int => (int) $id)
                    ->unique()
                    ->values()
                    ->all(),
            ]]];
        }

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

        if ($cargo === self::CARGO_OBRAS) {
            return [$data, ['obras' => [
                'setor_id' => $data['setor_obras_id'] ?? null,
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

    private static function excluirDefinitivamenteAction(): Action
    {
        return Action::make('excluir_definitivamente')
            ->label('Excluir definitivamente')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->button()
            ->requiresConfirmation()
            ->modalHeading('Excluir pessoa definitivamente')
            ->modalDescription('A Pessoa, o Professor, as matrículas e a conta de acesso serão excluídos de fato. Alunos e avaliações não serão apagados; o nome do professor permanecerá nas avaliações já preenchidas. CPF, e-mail e matrícula poderão ser usados em um novo cadastro.')
            ->modalSubmitActionLabel('Excluir definitivamente')
            ->schema([
                TextInput::make('confirmacao')
                    ->label('Confirmação')
                    ->helperText('Digite EXCLUIR para confirmar a exclusão definitiva.')
                    ->required()
                    ->rules(['in:EXCLUIR'])
                    ->validationMessages([
                        'in' => 'Digite EXCLUIR para confirmar a exclusão definitiva.',
                    ]),
            ])
            ->visible(fn (Servidor $record): bool => $record->trashed()
                && Gate::allows('forceDelete', $record))
            ->action(function (Servidor $record): void {
                $operador = Auth::user();
                if (! $operador instanceof User) {
                    throw new AuthorizationException('Usuário não autenticado.');
                }

                app(PessoaExclusaoDefinitivaService::class)->excluir($record, $operador);

                Notification::make()
                    ->title('Pessoa excluída definitivamente')
                    ->body('O cadastro foi removido definitivamente. Alunos e avaliações foram preservados, inclusive o nome histórico do professor nas avaliações preenchidas.')
                    ->success()
                    ->send();
            });
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
