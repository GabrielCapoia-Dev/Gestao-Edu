<?php

namespace App\Livewire\Pessoas;

use App\Filament\Admin\Resources\Servidores\ServidorResource;
use App\Filament\Admin\Resources\Servidores\Schemas\ServidorEquipeGestoraForm;
use App\Models\PessoaMatricula;
use App\Models\ProfessorComponenteSolicitacao;
use App\Models\Role;
use App\Models\Servidor;
use App\Models\User;
use App\Services\Exports\ExportRequestService;
use App\Services\ProfessorComponenteSolicitacaoService;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class ServidoresTable extends Component
{
    use WithPagination;

    public string $search = '';
    public array $cargo = [];
    public ?string $quantidadeMatriculas = null;
    public array $turnoMatricula = [];
    public array $status = [];
    public ?string $arquivados = 'sem';
    public array $escola = [];
    public array $nivelAcesso = [];
    public ?string $solicitacoesPendentes = null;
    public bool $emailDuplicado = false;
    public string $ordenarPor = 'updated_at';
    public string $ordenarDirecao = 'desc';
    public int $perPage = 5;
    public array $selecionados = [];
    public string $acaoEmMassa = '';

    public array $colunasVisiveis = [
        'identidade' => true,
        'cargo' => true,
        'escola' => true,
        'matricula' => true,
        'email' => true,
        'status' => true,
        'acesso' => false,
        'atualizado' => false,
    ];

    public array $opcoesCargos = [];
    public array $opcoesStatus = [];
    public array $opcoesTurnos = [];
    public array $opcoesEscolas = [];
    public array $opcoesNiveis = [];

    public function mount(): void
    {
        abort_unless(static::canView(), 403);

        $this->opcoesCargos = [
            ServidorResource::CARGO_PROFESSOR => 'Professor',
        ];
        if (ServidorEquipeGestoraForm::usuarioPodeAdministrar()) {
            $this->opcoesCargos += [
                'diretor' => 'Diretor',
                'coordenador' => 'Coordenador',
                'secretario' => 'Secretário',
                ServidorResource::CARGO_ASSESSORIA_PEDAGOGICA => 'Assessoria Pedagógica',
                ServidorResource::CARGO_MANUTENCAO => 'Manutenção',
                ServidorResource::CARGO_OBRAS => 'Obras',
                'sem_cargo' => 'Sem cargo ativo',
            ];
        }
        $this->opcoesStatus = Servidor::statusOptions();
        $this->opcoesTurnos = PessoaMatricula::turnosOptions();
        $this->opcoesEscolas = ServidorResource::escolasOptionsEscopadas();
        if (Gate::allows('viewAny', User::class)) {
            $this->opcoesNiveis = Role::query()->orderBy('name')->pluck('name', 'id')->mapWithKeys(
                fn (string $nome, int|string $id): array => [(int) $id => $nome],
            )->all();
        } else {
            $this->colunasVisiveis['acesso'] = false;
        }
    }

    public static function canView(): bool
    {
        return Gate::allows('viewAny', Servidor::class);
    }

    public function render()
    {
        $this->perPage = in_array($this->perPage, [5, 10, 25, 50, 100], true) ? $this->perPage : 5;

        return view('livewire.pessoas.servidores-table', [
            'servidores' => $this->servidoresQuery()->paginate($this->perPage, pageName: 'servidoresPage'),
            'colunas' => $this->colunasDisponiveis(),
        ]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage('servidoresPage');
    }

    public function updatedPerPage(): void
    {
        $this->resetPage('servidoresPage');
    }

    public function aplicarFiltros(): void
    {
        $this->selecionados = [];
        $this->resetPage('servidoresPage');
    }

    public function limparFiltros(): void
    {
        $this->search = '';
        $this->cargo = [];
        $this->quantidadeMatriculas = null;
        $this->turnoMatricula = [];
        $this->status = [];
        $this->arquivados = 'sem';
        $this->escola = [];
        $this->nivelAcesso = [];
        $this->solicitacoesPendentes = null;
        $this->emailDuplicado = false;
        $this->selecionados = [];
        $this->resetPage('servidoresPage');
    }

    public function ordenar(string $campo): void
    {
        $permitidos = ['nome', 'email', 'status', 'created_at', 'updated_at'];

        if (! in_array($campo, $permitidos, true)) {
            return;
        }

        if ($this->ordenarPor === $campo) {
            $this->ordenarDirecao = $this->ordenarDirecao === 'asc' ? 'desc' : 'asc';
        } else {
            $this->ordenarPor = $campo;
            $this->ordenarDirecao = $campo === 'nome' ? 'asc' : 'desc';
        }

        $this->resetPage('servidoresPage');
    }

    public function selecionarPagina(array $ids): void
    {
        $ids = $this->idsSelecionaveis($ids);
        $selecionados = collect($this->selecionados);
        $todosSelecionados = collect($ids)->every(fn (int $id): bool => $selecionados->contains($id));

        $this->selecionados = $todosSelecionados
            ? $selecionados->reject(fn (int $id): bool => in_array($id, $ids, true))->values()->all()
            : $selecionados->merge($ids)->unique()->values()->all();
    }

    public function alternarSelecionado(int $id): void
    {
        if (! in_array($id, $this->idsSelecionaveis([$id]), true)) {
            return;
        }

        $this->selecionados = in_array($id, $this->selecionados, true)
            ? array_values(array_diff($this->selecionados, [$id]))
            : [...$this->selecionados, $id];
    }

    public function abrirAcao(string $acao, int $id): void
    {
        abort_unless(static::canView(), 403);

        abort_unless(in_array($acao, [
            'view',
            'edit',
            'criar_acesso',
            'gerenciar_acesso',
            'redefinir_senha',
            'excluir_acesso',
            'analisar_solicitacoes_professor',
            'alterar_status',
            'delete',
            'restore',
        ], true), 404);

        $this->dispatch('servidor-acao', action: $acao, id: $id);
    }

    public function executarAcaoEmMassa(): void
    {
        $ids = collect($this->selecionados)->map(fn (mixed $id): int => (int) $id)->filter()->unique()->values()->all();

        if ($ids === [] || ! in_array($this->acaoEmMassa, [
            'exportar_selecionados',
            'alterar_status_em_massa',
            'criar_acessos_em_massa',
            'verificacao_acesso_em_massa',
            'redefinir_senha_em_massa',
            'niveis_em_massa',
            'permissoes_em_massa',
            'excluir_acessos_em_massa',
            'delete',
            'restore',
        ], true)) {
            return;
        }

        if ($this->acaoEmMassa === 'exportar_selecionados') {
            $this->enfileirarExportacao($ids, 'servidores.selected_livewire');
            return;
        }

        $this->dispatch('servidores-acao-massa', action: $this->acaoEmMassa, ids: $ids);
        $this->acaoEmMassa = '';
    }

    public function exportarFiltrados(): void
    {
        $ids = $this->servidoresQuery()->toBase()->pluck('servidores.id')->map(fn (mixed $id): int => (int) $id)->all();
        $this->enfileirarExportacao($ids, 'servidores.filtered_livewire');
    }

    public function limparSelecao(): void
    {
        $this->selecionados = [];
        $this->acaoEmMassa = '';
    }

    public function matriculasLabel(Servidor $servidor): string
    {
        $scope = app(\App\Services\PessoaScopeService::class);
        $matriculas = $scope->hasGlobalAccess(auth()->user())
            ? $servidor->matriculas
            : $servidor->professores->where('ativo', true)->map(fn ($professor): string => sprintf('%s (%s)', $professor->matricula, $professor->turnoLabel()));

        return $matriculas
            ->map(fn ($matricula): string => $matricula instanceof PessoaMatricula
                ? sprintf('%s (%s)', $matricula->matricula, $matricula->turnoLabel())
                : (string) $matricula)
            ->filter()
            ->unique()
            ->implode(', ') ?: '—';
    }

    public function statusLabel(Servidor $servidor): string
    {
        if ($servidor->trashed()) {
            return 'Arquivado';
        }

        return Servidor::statusOptions()[$servidor->status] ?? 'Não informado';
    }

    public function statusColor(Servidor $servidor): string
    {
        return $servidor->trashed() ? 'red' : match ($servidor->status) {
            Servidor::STATUS_ATIVO => 'green',
            Servidor::STATUS_INATIVO => 'gray',
            default => 'amber',
        };
    }

    #[On('pessoa-form-salvo')]
    public function atualizarAposSalvarPessoa(): void
    {
        $this->resetPage('servidoresPage');
    }

    #[On('pessoa-form-cancelado')]
    public function fecharFormularioPessoa(): void
    {
        // O modal é desmontado pelo componente pai.
    }

    /** @return array<string, string> */
    public function colunasDisponiveis(): array
    {
        $colunas = [
            'identidade' => 'Identidade',
            'cargo' => 'Cargo',
            'escola' => 'Escola',
            'matricula' => 'Matrícula',
            'email' => 'E-mail',
            'status' => 'Status',
            'acesso' => 'Acesso',
            'atualizado' => 'Atualizado em',
        ];

        if (! Gate::allows('viewAny', User::class)) {
            unset($colunas['acesso']);
        }

        return $colunas;
    }

    private function servidoresQuery(): Builder
    {
        $query = ServidorResource::getEloquentQuery()
            ->select('servidores.*')
            ->with([
                'escola:id,nome',
                'user:id,name,email,deleted_at',
                'user.roles:id,name',
                'professores.escola:id,nome',
                'matriculas:id,servidor_id,matricula,turno',
                'vinculosAtivos.funcaoAdministrativa:id,codigo,nome,direcao_escolar,coordenacao_pedagogica,secretaria_escolar',
                'vinculosAtivos.escola:id,nome',
                'vinculosAtivos.escolasAssessoradas:id,nome',
            ]);

        if ($this->search !== '') {
            $term = '%'.trim($this->search).'%';
            $query->where(function (Builder $searchQuery) use ($term): void {
                $searchQuery
                    ->where('servidores.nome', 'like', $term)
                    ->orWhere('servidores.cpf', 'like', $term)
                    ->orWhere('servidores.email', 'like', $term)
                    ->orWhereHas('user', fn (Builder $user): Builder => $user->where('email', 'like', $term))
                    ->orWhereHas('professores', fn (Builder $professores): Builder => $professores->where('matricula', 'like', $term))
                    ->orWhereHas('matriculas', fn (Builder $matriculas): Builder => $matriculas->where('matricula', 'like', $term));
            });
        }

        if ($this->cargo !== []) {
            $query = ServidorResource::aplicarFiltroCargos($query, $this->cargo);
        }

        if ($this->quantidadeMatriculas) {
            $query = ServidorResource::aplicarFiltroQuantidadeMatriculas($query, $this->quantidadeMatriculas);
        }

        if ($this->turnoMatricula !== []) {
            $query = ServidorResource::aplicarFiltroTurnosMatriculas($query, $this->turnoMatricula);
        }

        if ($this->status !== []) {
            $query->whereIn('servidores.status', $this->status);
        }

        if ($this->escola !== []) {
            $query->where(function (Builder $pessoas): void {
                $pessoas
                    ->whereIn('id_escola', $this->escola)
                    ->orWhereHas('professores', fn (Builder $professores): Builder => $professores->whereIn('id_escola', $this->escola))
                    ->orWhereHas('vinculosAtivos', fn (Builder $vinculos): Builder => $vinculos->whereIn('id_escola', $this->escola))
                    ->orWhereHas('vinculosAtivos.escolasAssessoradas', fn (Builder $escolas): Builder => $escolas->whereIn('escolas.id', $this->escola));
            });
        }

        if ($this->nivelAcesso !== [] && Gate::allows('viewAny', User::class)) {
            $query->whereHas('user.roles', fn (Builder $roles): Builder => $roles->whereIn('roles.id', $this->nivelAcesso));
        }

        if ($this->arquivados === 'com') {
            $query->withTrashed();
        } elseif ($this->arquivados === 'somente') {
            $query->onlyTrashed();
        }

        if ($this->emailDuplicado) {
            $query->comEmailDuplicado();
        }

        $usuario = auth()->user();
        if ($usuario && app(ProfessorComponenteSolicitacaoService::class)->podeAnalisar($usuario) && $this->solicitacoesPendentes !== null) {
            $table = $query->getModel()->getTable();
            $solicitacoes = fn ($pendentes) => $pendentes
                ->selectRaw('1')
                ->from('professor_componente_solicitacoes as solicitacoes')
                ->join('professores as professores_solicitantes', 'professores_solicitantes.id', '=', 'solicitacoes.professor_id')
                ->join('turma_componente_professor as vinculos_solicitados', 'vinculos_solicitados.id', '=', 'solicitacoes.turma_componente_professor_id')
                ->join('turmas as turmas_solicitadas', 'turmas_solicitadas.id', '=', 'vinculos_solicitados.turma_id')
                ->where('solicitacoes.status', ProfessorComponenteSolicitacao::STATUS_PENDENTE)
                ->where('professores_solicitantes.ativo', true)
                ->whereColumn('professores_solicitantes.servidor_id', "{$table}.id")
                ->whereColumn('professores_solicitantes.id_escola', 'turmas_solicitadas.id_escola')
                ->when(! app(\App\Services\PessoaScopeService::class)->hasGlobalAccess($usuario), function ($subquery) use ($usuario): void {
                    $subquery->whereIn(
                        'turmas_solicitadas.id_escola',
                        app(\App\Services\PessoaScopeService::class)->escolaIdsDosVinculos($usuario),
                    );
                });

            $this->solicitacoesPendentes === 'sim'
                ? $query->whereExists($solicitacoes)
                : $query->whereNotExists($solicitacoes);
        }

        $ordenarPor = in_array($this->ordenarPor, ['nome', 'email', 'status', 'created_at', 'updated_at'], true)
            ? $this->ordenarPor
            : 'updated_at';
        $ordenarDirecao = $this->ordenarDirecao === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($ordenarPor, $ordenarDirecao)->orderBy('servidores.id');
    }

    private function aplicarFiltroCargo(Builder $query): void
    {
        $query->where(function (Builder $pessoas): void {
            foreach (array_values($this->cargo) as $index => $cargo) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $pessoas->{$method}(function (Builder $pessoa) use ($cargo): void {
                    match ($cargo) {
                        ServidorResource::CARGO_PROFESSOR => $pessoa->whereHas('professores', fn (Builder $professores): Builder => $professores->where('ativo', true)),
                        'diretor' => $pessoa->whereHas('vinculosAtivos.funcaoAdministrativa', fn (Builder $funcoes): Builder => $funcoes->where('direcao_escolar', true)),
                        'coordenador' => $pessoa->whereHas('vinculosAtivos.funcaoAdministrativa', fn (Builder $funcoes): Builder => $funcoes->where('coordenacao_pedagogica', true)),
                        'secretario' => $pessoa->whereHas('vinculosAtivos.funcaoAdministrativa', fn (Builder $funcoes): Builder => $funcoes->where('secretaria_escolar', true)),
                        'sem_cargo' => $pessoa->whereDoesntHave('vinculosAtivos')->whereDoesntHave('professores', fn (Builder $professores): Builder => $professores->where('ativo', true)),
                        default => $pessoa->whereHas('vinculosAtivos.funcaoAdministrativa', fn (Builder $funcoes): Builder => $funcoes->where('codigo', $cargo)),
                    };
                });
            }
        });
    }

    private function aplicarFiltroQuantidade(Builder $query): void
    {
        match ($this->quantidadeMatriculas) {
            'uma' => $query->has('matriculas', '=', 1),
            'duas' => $query->has('matriculas', '=', 2),
            'tres_ou_mais' => $query->has('matriculas', '>=', 3),
            'sem' => $query->doesntHave('matriculas'),
            default => null,
        };
    }

    private function enfileirarExportacao(array $ids, string $origem): void
    {
        $ids = collect($ids)->map(fn (mixed $id): int => (int) $id)->filter()->unique()->values()->all();

        if ($origem === 'servidores.selected_livewire') {
            $ids = $this->idsSelecionaveis($ids);
        }

        $ids = $this->servidoresQuery()
            ->whereKey($ids)
            ->pluck('servidores.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        if ($ids === []) {
            Notification::make()->title('Nenhum servidor encontrado para exportação')->warning()->send();
            return;
        }

        $user = auth()->user();
        abort_unless($user instanceof User && Gate::forUser($user)->allows('viewAny', Servidor::class), 403);

        app(ExportRequestService::class)->queue(
            user: $user,
            type: 'servidores_filtrados',
            format: 'xlsx',
            filters: ['ids' => $ids],
            label: 'XLSX de servidores filtrados',
            metadata: ['source' => $origem, 'records_count' => count($ids)],
        );

        Notification::make()->title('Exportação enviada para a fila')->body('Acompanhe o progresso pelo ícone de downloads no topo.')->success()->send();
    }

    /** @param list<mixed> $ids
     *  @return list<int>
     */
    private function idsSelecionaveis(array $ids): array
    {
        $ids = collect($ids)->map(fn (mixed $id): int => (int) $id)->filter()->unique()->values()->all();

        if ($ids === []) {
            return [];
        }

        return ServidorResource::getEloquentQuery()
            ->with('user:id')
            ->whereKey($ids)
            ->get()
            ->filter(fn (Servidor $servidor): bool => ServidorResource::pessoaPodeSerSelecionada($servidor))
            ->map(fn (Servidor $servidor): int => (int) $servidor->getKey())
            ->values()
            ->all();
    }
}
