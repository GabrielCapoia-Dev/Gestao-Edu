<?php

namespace App\Services;

use App\Models\Escola;
use App\Models\User;
use App\Models\IgnoredUser;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;

class UserService
{
    protected $user;

    public function __construct()
    {
        /** @var \App\Models\User */
        $user = Auth::user();
        $this->user = $user;
    }

    // =========================================================================
    // Verificações de permissão (públicas)
    // =========================================================================

    public function podeVisualizarPainelPersonalizado(?User $user): bool
    {
        return $user?->hasPermissionTo('Visualizar Painel Personalizado') ?? false;
    }
    public function podeExcluirItens(?User $user): bool
    {
        return $user?->hasPermissionTo('Excluir Itens') ?? false;
    }
    public function podeExcluirItensEmMassa(?User $user): bool
    {
        return $user?->hasPermissionTo('Excluir Itens em Massa') ?? false;
    }

    public function podeEditarMatriculaDoProfessor(?User $user, ?string $operation = null): bool
    {
        if ($operation === 'create') return true;
        return $user->hasPermissionTo('Editar Matricula do Professor');
    }

    public function podeEditarEscolaDoProfessor(?User $user): bool
    {
        return $user->hasPermissionTo('Editar Escola do Professor');
    }

    public function podeEditarNomeDoProfessor(?User $user, ?string $operation = null): bool
    {
        if ($operation === 'create') return true;
        return $user->hasPermissionTo('Editar Nome do Professor');
    }

    public function podeVisualizarEspecializacoesDeProfessores(?User $user): bool
    {
        return $user->hasPermissionTo('Visualizar Especializações de Professores');
    }

    public function podeEditarEspecializacoesDeProfessores(?User $user, ?string $operation = null): bool
    {
        if ($operation === 'create') return true;
        return $user->hasPermissionTo('Editar Especializações de Professores');
    }

    public function podeVisualizarAlunos(?User $user): bool
    {
        return $user->hasPermissionTo('Listar Alunos');
    }

    public function podeListarRetencoes(?User $user): bool
    {
        return $user->hasPermissionTo('Listar Retenção');
    }

    public function podeExcluirTurmas(?User $user): bool
    {
        return $user->hasPermissionTo('Excluir Turmas');
    }

    public function podeVisualizarSetor(?User $user): bool
    {
        return $user?->hasPermissionTo('Visualizar Setor do Usuário') ?? false;
    }

    public function podeEditarSetor(?User $user, string $context): bool
    {
        if (! $user) return false;
        return $user->hasPermissionTo('Editar Setor do Usuário');
    }

    public function ehAdmin(?User $user = null): bool
    {
        return Gate::allows('admin-only', $user);
    }

    public function podeVerLaudos(?User $user): bool
    {
        return $user->hasPermissionTo('Visualizar Laudos de Aluno');
    }

    public function podeVisualizarDetalhesProfessor(?User $user): bool
    {
        return $user->hasPermissionTo('Visualizar Detalhes de Professor');
    }

    public function podeVisualizarEspecializacoesProfessor(?User $user): bool
    {
        return $user->hasPermissionTo('Visualizar Especializações de Professores');
    }

    public function podeAnexarLaudos(?User $user): bool
    {
        return $user->hasPermissionTo('Anexar Laudos de Aluno');
    }

    public function podeExcluirProfessoresEmLote(?User $user): bool
    {
        return $user->hasPermissionTo('Excluir Professores em Massa');
    }

    public function podeExcluirLaudos(?User $user): bool
    {
        return $user->hasPermissionTo('Excluir Laudos');
    }

    public function podeExcluirLaudosEmLote(?User $user): bool
    {
        return $user->hasPermissionTo('Excluir Laudos em Massa');
    }

    public function podeBaixarLaudos(?User $user): bool
    {
        return $user->hasPermissionTo('Exportar Laudos de Aluno');
    }

    public function podeFiltrarProfessoresPorEscola(?User $user): bool
    {
        return $user->hasPermissionTo('Filtrar Professores por Escola');
    }

    public function podeExportarProfessores(?User $user): bool
    {
        return $user->hasPermissionTo('Exportar Professores');
    }

    // =========================================================================
    // Regras de formulário (usadas pelo UserForm)
    // =========================================================================

    public function opcoesDeRoles(Builder $base, ?User $user): Builder
    {
        return $this->ehAdmin($user) ? $base : $base->where('name', '!=', 'Admin');
    }

    public function desabilitarCampoRole(?User $user, ?User $record, string $context): bool
    {
        if ($context === 'create' || ! $record) return false;
        if ($record->hasRole('Admin') && $user->id == 1) return false;
        if ($record->hasRole('Admin')) return true;
        if ($user && $record->id === $user->id) return true;
        return false;
    }

    public function podeVerToggleAprovacaoEmail(?User $user, ?User $record, string $context): bool
    {
        if ($context === 'create') return true;
        if (! $user || ! $this->ehAdmin($user)) return false;
        if ($record && ($record->hasRole('Admin') || ($user && $record->id === $user->id))) return false;
        return true;
    }

    public function desabilitarToggleAprovacaoEmail(?User $user, ?User $record): bool
    {
        return $user && $record && $user->id === $record->id;
    }

    public function opcoesDeEscolasParaCampo(?User $currentUser): array
    {
        if ($this->ehAdmin($currentUser)) {
            return Escola::query()->orderBy('nome')->pluck('nome', 'id')->toArray();
        }

        if (filled($currentUser?->id_escola)) {
            return Escola::query()->whereKey($currentUser->id_escola)->pluck('nome', 'id')->toArray();
        }

        return [];
    }

    public function deveTravarCampoEscola(?User $currentUser, string $context): bool
    {
        if ($this->ehAdmin($currentUser)) return false;
        if ($context === 'create' && filled($currentUser?->id_escola)) return true;
        if ($context === 'edit') return true;
        return false;
    }

    public function escolaInicialParaForm(?User $record, ?User $currentUser, string $context): ?int
    {
        if ($record && filled($record->id_escola)) {
            return (int) $record->id_escola;
        }

        return $currentUser?->id_escola ?? null;
    }

    // =========================================================================
    // Regras de tabela (usadas pelo UsersTable)
    // =========================================================================

    public function podeSelecionarRegistro(?User $user, User $record): bool
    {
        if ($record->hasRole('Admin')) return false;
        if (! $this->ehAdmin($user) && $record->hasRole('Admin')) return false;
        return true;
    }

    public function podeDeletar(?User $user, User $record): bool
    {
        if (! $user) return false;
        if ($record->id === 1) return false;
        if ($record->id === $user->id) return false;
        return $this->ehAdmin($user);
    }

    public function podeDeletarEmLote(?User $user, iterable $records): bool
    {
        if (! $this->ehAdmin($user)) return false;
        foreach ($records as $record) {
            if ($record instanceof User && $record->hasRole('Admin')) return false;
        }
        return true;
    }

    // =========================================================================
    // Helpers de checkboxes de permissão (usados pelas duas classes acima)
    // =========================================================================

    public function checkboxesPermissoesComEstado(User $record, Get $get, User $userLogado): array
    {
        $busca = strtolower($get('buscar_permissao') ?? '');

        $permissoesDisponiveis = $userLogado->hasRole('Admin')
            ? Permission::query()
            : Permission::whereIn('name', $userLogado->getAllPermissions()->pluck('name'));

        $todas = $permissoesDisponiveis
            ->orderBy('name')
            ->when($busca, fn($q) => $q->whereRaw('LOWER(name) LIKE ?', ["%{$busca}%"]))
            ->when(! $userLogado->hasRole('Admin'), fn($q) => $q->where('name', '!=', 'Aplicar Permissoes'))
            ->get();

        $permissoesDaRole = $record->roles
            ->flatMap(fn($role) => $role->permissions)
            ->pluck('name')
            ->toArray();

        $permissoesDiretas = $record->getDirectPermissions()->pluck('name')->toArray();

        $porGrupo = $todas->groupBy(fn($p) => explode(' ', $p->name)[0]);

        $schema = [];

        foreach ($porGrupo as $grupo => $permissoes) {
            $filtradas = $permissoes->when(
                $busca,
                fn($collection) => $collection->filter(
                    fn($perm) => str_contains(strtolower($perm->name), $busca)
                )
            );

            if ($filtradas->isEmpty()) continue;

            $permissoesDoGrupoNaRole = collect($filtradas)
                ->filter(fn($p) => in_array($p->name, $permissoesDaRole))
                ->pluck('name')
                ->toArray();

            $helperText = '';
            if (! empty($permissoesDoGrupoNaRole)) {
                $helperText = '🔒 Herança da role: ' . implode(', ', $permissoesDoGrupoNaRole);
            }

            $schema[] = CheckboxList::make("permissions_{$grupo}")
                ->label($grupo)
                ->options($filtradas->pluck('name', 'name')->toArray())
                ->columns(3)
                ->helperText($helperText)
                ->default(
                    collect($permissoesDaRole)
                        ->merge($permissoesDiretas)
                        ->intersect($filtradas->pluck('name'))
                        ->values()
                        ->toArray()
                )
                ->dehydrated(true);
        }

        return $schema;
    }

    public function checkboxesPermissoesEmMassa(Get $get, User $userLogado): array
    {
        $busca = strtolower($get('buscar_permissao') ?? '');

        $permissoesDoUsuario = $userLogado->hasRole('Admin')
            ? Permission::query()
            : Permission::whereIn('name', $userLogado->getAllPermissions()->pluck('name'));

        $todas = $permissoesDoUsuario
            ->orderBy('name')
            ->when($busca, fn($q) => $q->whereRaw('LOWER(name) LIKE ?', ["%{$busca}%"]))
            ->when(! $userLogado->hasRole('Admin'), fn($q) => $q->where('name', '!=', 'Aplicar Permissoes'))
            ->get();

        $porGrupo = $todas->groupBy(fn($p) => explode(' ', $p->name)[0]);

        $schema = [];

        foreach ($porGrupo as $grupo => $permissoes) {
            $schema[] = CheckboxList::make("permissions_{$grupo}")
                ->label($grupo)
                ->options($permissoes->pluck('name', 'name')->toArray())
                ->columns(3);
        }

        return $schema;
    }

    // =========================================================================
    // Filtros de query (usados pelos Resources)
    // =========================================================================

    public function listarUsuariosQuery(Builder $base, ?User $user): Builder
    {
        if (! $this->ehAdmin($user)) {
            $base->whereDoesntHave('roles', fn($q) => $q->where('name', 'Admin'));
        }
        return $base;
    }

    public function badgeNavegacaoParaNovosUsuarios(?User $user): ?string
    {
        if (! $user || ! $this->ehAdmin($user)) return null;

        $ignorados = IgnoredUser::where('admin_id', $user->id)->pluck('user_id')->toArray();
        $count = User::where('email_approved', false)->whereNotIn('id', $ignorados)->count();

        return $count > 0 ? (string) $count : null;
    }

    public function sincronizarIgnoradosParaAdmin(User $admin): void
    {
        if (! $this->ehAdmin($admin)) return;

        $pendentes = User::where('email_approved', false)->pluck('id');
        foreach ($pendentes as $userId) {
            IgnoredUser::firstOrCreate([
                'admin_id' => $admin->id,
                'user_id'  => $userId,
            ]);
        }
    }

    public function aplicarFiltroPorEscolaDoUsuario(Builder $query, ?User $user): Builder
    {
        if ($user && ! $this->ehAdmin($user) && ! empty($user->id_escola)) {
            $query->whereHas('turma', function (Builder $turmaQuery) use ($user) {
                $turmaQuery->where('id_escola', $user->id_escola);
            });
        }
        return $query;
    }

    public function aplicarFiltroTurmasDoUsuario(Builder $query, ?User $user): Builder
    {
        if (! $user || $this->ehAdmin($user)) return $query;

        if ($user->ehProfessor()) {
            $professoresIds = $user->professores->pluck('id')->toArray();
            return $query->whereHas('componentes', function ($q) use ($professoresIds) {
                $q->whereIn('turma_componente_professor.professor_id', $professoresIds);
            });
        }

        if (! empty($user->id_escola)) {
            return $query->where('id_escola', $user->id_escola);
        }

        return $query;
    }

    public function aplicarFiltroAlunosDaEscolaDoUsuario(Builder $query, ?User $user): Builder
    {
        if (! $user || $this->ehAdmin($user)) return $query;

        if ($user->ehProfessor()) {
            $professoresIds = $user->professores->pluck('id')->toArray();
            return $query->whereHas('turma.componentes', function ($q) use ($professoresIds) {
                $q->whereIn('turma_componente_professor.professor_id', $professoresIds);
            });
        }

        if (! empty($user->id_escola)) {
            return $query->whereHas('turma', function ($q) use ($user) {
                $q->where('id_escola', $user->id_escola);
            });
        }

        return $query;
    }

    public function aplicarFiltroPorEscolaDoUsuarioEmTurma(Builder $query, ?User $user): Builder
    {
        if (! $user || $this->ehAdmin($user)) return $query;
        if (! empty($user->id_escola)) {
            return $query->where('id_escola', $user->id_escola);
        }
        return $query;
    }
}