<?php

namespace App\Services;

use App\Models\Aluno;
use App\Models\Escola;
use App\Models\IgnoredUser;
use App\Models\Item;
use App\Models\Professor;
use App\Models\Role;
use App\Models\Turma;
use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class UserService
{
    protected $user;

    /** @var array<int, array<int, int>> */
    private array $professorIdsByUser = [];

    public function __construct()
    {
        /** @var User */
        $user = Auth::user();
        $this->user = $user;
    }

    // =========================================================================
    // Verificações de permissão (públicas)
    // =========================================================================

    public function podeVisualizarPainelPersonalizado(?User $user): bool
    {
        return $user && Gate::forUser($user)->allows('viewPersonalizedPanel', User::class);
    }

    public function podeExcluirItens(?User $user): bool
    {
        return $user && Gate::forUser($user)->allows('delete', new Item);
    }

    public function podeExcluirItensEmMassa(?User $user): bool
    {
        return $user && Gate::forUser($user)->allows('deleteAny', Item::class);
    }

    public function podeEditarMatriculaDoProfessor(?User $user, ?string $operation = null): bool
    {
        if ($operation === 'create') {
            return true;
        }

        return $user && Gate::forUser($user)->allows('editMatricula', Professor::class);
    }

    public function podeEditarEscolaDoProfessor(?User $user): bool
    {
        return $user && Gate::forUser($user)->allows('editSchool', Professor::class);
    }

    public function podeEditarNomeDoProfessor(?User $user, ?string $operation = null): bool
    {
        if ($operation === 'create') {
            return true;
        }

        return $user && Gate::forUser($user)->allows('editName', Professor::class);
    }

    public function podeVisualizarEspecializacoesDeProfessores(?User $user): bool
    {
        return $user && Gate::forUser($user)->allows('viewSpecializations', Professor::class);
    }

    public function podeEditarEspecializacoesDeProfessores(?User $user, ?string $operation = null): bool
    {
        if ($operation === 'create') {
            return true;
        }

        return $user && Gate::forUser($user)->allows('editSpecializations', Professor::class);
    }

    public function podeExcluirTurmas(?User $user): bool
    {
        return $user && Gate::forUser($user)->allows('delete', new Turma);
    }

    public function podeVisualizarAlunos(?User $user): bool
    {
        return $user && Gate::forUser($user)->allows('viewAny', Aluno::class);
    }

    public function podeCriarAlunos(?User $user): bool
    {
        return $user && Gate::forUser($user)->allows('create', Aluno::class);
    }

    public function podeEditarAlunos(?User $user): bool
    {
        return $user && Gate::forUser($user)->allows('updateAny', Aluno::class);
    }

    public function podeExcluirAlunos(?User $user): bool
    {
        return $user && Gate::forUser($user)->allows('delete', new Aluno);
    }

    public function podeVisualizarSetor(?User $user): bool
    {
        return $user && Gate::forUser($user)->allows('viewSetor', User::class);
    }

    public function podeEditarSetor(?User $user, string $context): bool
    {
        if (! $user) {
            return false;
        }

        return Gate::forUser($user)->allows('editSetor', User::class);
    }

    public function ehAdmin(?User $user = null): bool
    {
        return Gate::allows('admin-only', $user);
    }

    public function podeVisualizarDetalhesProfessor(?User $user): bool
    {
        return $user && Gate::forUser($user)->allows('viewDetails', Professor::class);
    }

    public function podeVisualizarEspecializacoesProfessor(?User $user): bool
    {
        return $user && Gate::forUser($user)->allows('viewSpecializations', Professor::class);
    }

    public function podeExcluirProfessoresEmLote(?User $user): bool
    {
        return $user && Gate::forUser($user)->allows('deleteBulk', Professor::class);
    }

    public function podeFiltrarProfessoresPorEscola(?User $user): bool
    {
        return $user && Gate::forUser($user)->allows('filterBySchool', Professor::class);
    }

    public function podeExportarProfessores(?User $user): bool
    {
        return $user && Gate::forUser($user)->allows('export', Professor::class);
    }

    public function podeExcluirProfessores(?User $user): bool
    {
        return $user && Gate::forUser($user)->allows('delete', new Professor);
    }

    public function podeTransferirProfessores(?User $user): bool
    {
        return $user && Gate::forUser($user)->allows('transfer', Professor::class);
    }

    public function podeDesativarProfessores(?User $user): bool
    {
        return $user && Gate::forUser($user)->allows('deactivate', Professor::class);
    }

    // =========================================================================
    // Regras de formulário (usadas pelo UserForm)
    // =========================================================================

    public function opcoesDeRoles(Builder $base, ?User $user): Builder
    {
        $base->whereNotIn('id', app(PessoaAcessoService::class)->rolesFuncionaisGerenciadasIds()->all());

        return $this->ehAdmin($user) ? $base : $base->where('name', '!=', 'Admin');
    }

    public function opcoesDeRolesParaSelect(?User $user): array
    {
        return $this->opcoesDeRoles(Role::query(), $user)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    public function idsDeRolesSelecionadas(array $data): array
    {
        $roles = $data['roles'] ?? $data['role'] ?? [];

        return collect(is_array($roles) ? $roles : [$roles])
            ->filter(fn ($roleId) => filled($roleId))
            ->map(fn ($roleId) => (int) $roleId)
            ->unique()
            ->values()
            ->all();
    }

    public function permissoesSelecionadas(array $data): Collection
    {
        return collect($data)
            ->filter(fn ($_, $key) => str_starts_with($key, 'permissions_'))
            ->flatMap(fn ($permissions) => is_array($permissions) ? $permissions : [$permissions])
            ->filter(fn ($permission) => filled($permission))
            ->unique()
            ->values();
    }

    /** @return array<int, int> */
    public function idsNiveisAdicionais(User $user): array
    {
        $rolesFuncionais = app(PessoaAcessoService::class)->rolesFuncionaisGerenciadasIds();

        return $user->roles()
            ->pluck('roles.id')
            ->map(fn ($id): int => (int) $id)
            ->reject(fn (int $id): bool => $rolesFuncionais->contains($id))
            ->values()
            ->all();
    }

    public function sincronizarNiveisAdicionais(
        User $record,
        array $roleIds,
        string $modo = 'replace',
        ?User $operador = null,
        bool $autorizar = true,
    ): void {
        $operador ??= Auth::user();

        if ($autorizar && (! $operador || ! Gate::forUser($operador)->allows('applyPermissions', $record))) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'Você não possui permissão para alterar os níveis de acesso desta pessoa.',
            );
        }

        validator(
            ['modo' => $modo],
            ['modo' => [Rule::in(['add', 'replace', 'remove'])]],
        )->validate();

        $selecionadas = collect($roleIds)
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
        $permitidas = $this->opcoesDeRoles(Role::query(), $operador)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);

        if ($selecionadas->diff($permitidas)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'roles' => 'Um ou mais níveis selecionados são funcionais ou estão fora do seu escopo de administração.',
            ]);
        }

        $atuais = $record->roles()
            ->pluck('roles.id')
            ->map(fn ($id): int => (int) $id);
        $gerenciaveisAtuais = $atuais->intersect($permitidas);
        $preservadas = $atuais->diff($gerenciaveisAtuais);
        $gerenciaveisFinais = match ($modo) {
            'add' => $gerenciaveisAtuais->merge($selecionadas),
            'remove' => $gerenciaveisAtuais->diff($selecionadas),
            default => $selecionadas,
        };

        $record->syncRoles(Role::query()
            ->whereIn('id', $preservadas->merge($gerenciaveisFinais)->unique()->values()->all())
            ->get());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function sincronizarPermissoesDiretas(
        User $record,
        array|Collection $permissoes,
        string $modo = 'replace',
        ?User $operador = null,
        bool $autorizar = true,
    ): void {
        $operador ??= Auth::user();

        if ($autorizar && (! $operador || ! Gate::forUser($operador)->allows('applyPermissions', $record))) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'Você não possui permissão para alterar as permissões desta pessoa.',
            );
        }

        validator(
            ['modo' => $modo],
            ['modo' => [Rule::in(['add', 'replace', 'remove'])]],
        )->validate();

        $selecionadas = collect($permissoes)
            ->filter(fn ($permission): bool => filled($permission))
            ->map(fn ($permission): string => (string) $permission)
            ->unique()
            ->values();
        $permitidas = ((! $autorizar && ! $operador) || $operador?->hasRole('Admin')
            ? Permission::query()
            : Permission::query()->whereIn('name', $operador?->getAllPermissions()->pluck('name') ?? []))
            ->when($autorizar && ! $operador?->hasRole('Admin'), fn (Builder $query): Builder => $query->where('name', '!=', 'Aplicar Permissoes'))
            ->pluck('name');

        if ($selecionadas->diff($permitidas)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'permissions' => 'Uma ou mais permissões selecionadas estão fora do seu escopo de administração.',
            ]);
        }

        $herdadas = $record->roles()
            ->with('permissions:id,name')
            ->get()
            ->flatMap(fn (Role $role) => $role->permissions->pluck('name'))
            ->unique();
        $selecionadas = $selecionadas->diff($herdadas);
        $atuais = $record->getDirectPermissions()->pluck('name');
        $gerenciaveisAtuais = $atuais->intersect($permitidas);
        $preservadas = $atuais->diff($gerenciaveisAtuais);
        $gerenciaveisFinais = match ($modo) {
            'add' => $gerenciaveisAtuais->merge($selecionadas),
            'remove' => $gerenciaveisAtuais->diff($selecionadas),
            default => $selecionadas,
        };

        $record->syncPermissions($preservadas->merge($gerenciaveisFinais)->unique()->values()->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function redefinirSenha(User $record, string $senha, ?User $operador = null): void
    {
        $operador ??= Auth::user();

        if (! $operador || ! Gate::forUser($operador)->allows('resetPassword', $record)) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'Você não possui permissão para redefinir a senha desta pessoa.',
            );
        }

        validator(
            ['senha' => $senha],
            ['senha' => ['required', 'string', 'max:30', PasswordRule::min(8)->mixedCase()->numbers()->symbols()]],
        )->validate();

        $record->forceFill([
            'password' => Hash::make($senha),
            'must_change_password' => true,
        ])->save();
    }

    public function definirAprovacao(User $record, bool $aprovado, ?User $operador = null): void
    {
        $operador ??= Auth::user();

        if (! $operador || ! Gate::forUser($operador)->allows('toggleEmailApproval', [$record, 'table'])) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'Você não possui permissão para alterar a liberação de acesso desta pessoa.',
            );
        }

        $record->update(['email_approved' => $aprovado]);
    }

    public function sincronizarAcessosDoUsuario(User $record, array $data): void
    {
        // Impacto: esta rotina e o ponto central de sincronizacao Spatie. Alterar ordem de roles/permissoes pode deixar permissoes herdadas gravadas como diretas.
        if (array_key_exists('roles', $data) || array_key_exists('role', $data)) {
            $this->sincronizarNiveisAdicionais(
                $record,
                $this->idsDeRolesSelecionadas($data),
                operador: Auth::user(),
                autorizar: Auth::check(),
            );
        }

        $record->load('roles.permissions');

        // Impacto: quando o formulario nao envia permissoes extras, preservamos o estado direto atual. Trocar por syncPermissions([]) removeria acessos fora do formulario.
        if (! array_key_exists('usar_permissoes_extras', $data)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return;
        }

        // Impacto: desligar permissoes extras deve limpar somente permissoes diretas; as herdadas continuam vindo das roles do usuario.
        if (empty($data['usar_permissoes_extras'])) {
            $this->sincronizarPermissoesDiretas(
                $record,
                [],
                operador: Auth::user(),
                autorizar: Auth::check(),
            );

            return;
        }

        $this->sincronizarPermissoesDiretas(
            $record,
            $this->permissoesSelecionadas($data),
            operador: Auth::user(),
            autorizar: Auth::check(),
        );
    }

    public function desabilitarCampoRole(?User $user, ?User $record, string $context): bool
    {
        if ($context === 'create' || ! $record) {
            return false;
        }

        // Impacto: apenas o usuario raiz pode editar outro Admin. Alterar esta excecao muda a protecao contra perda acidental de administradores.
        if ($record->hasRole('Admin') && $user->id == 1) {
            return false;
        }

        if ($record->hasRole('Admin')) {
            return true;
        }

        if ($user && $record->id === $user->id) {
            return true;
        }

        return false;
    }

    public function podeVerToggleAprovacaoEmail(?User $user, ?User $record, string $context): bool
    {
        if ($context === 'create') {
            return true;
        }

        if (! $user || ! $this->ehAdmin($user)) {
            return false;
        }

        if ($record && ($record->hasRole('Admin') || ($user && $record->id === $user->id))) {
            return false;
        }

        return true;
    }

    public function desabilitarToggleAprovacaoEmail(?User $user, ?User $record): bool
    {
        return $user && $record && $user->id === $record->id;
    }

    public function opcoesDeEscolasParaCampo(?User $currentUser): array
    {
        $access = app(UserSetorAccessService::class);

        if ($access->hasGlobalAccess($currentUser)) {
            return Escola::query()->where('ativo', true)->orderBy('nome')->pluck('nome', 'id')->toArray();
        }

        $setorIds = $access->visibleSetorIds($currentUser);

        if ($setorIds !== []) {
            return Escola::query()
                ->where('ativo', true)
                ->whereIn('setor_id', $setorIds)
                ->orderBy('nome')
                ->pluck('nome', 'id')
                ->toArray();
        }

        return [];
    }

    public function deveTravarCampoEscola(?User $currentUser, string $context): bool
    {
        if ($this->ehAdmin($currentUser)) {
            return false;
        }

        if ($context === 'create' && filled($currentUser?->id_escola)) {
            return true;
        }

        if ($context === 'edit') {
            return true;
        }

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
        if ($record->hasRole('Admin')) {
            return false;
        }

        if (! $this->ehAdmin($user) && $record->hasRole('Admin')) {
            return false;
        }

        return true;
    }

    public function podeDeletar(?User $user, User $record): bool
    {
        if (! $user) {
            return false;
        }

        if ($record->id === 1) {
            return false;
        }

        if ($record->id === $user->id) {
            return false;
        }

        if ($record->hasRole('Admin') && ! $this->ehAdmin($user)) {
            return false;
        }

        // Alinha com UserPolicy (permissão Excluir Usuários), não só role Admin.
        return Gate::forUser($user)->allows('delete', $record);
    }

    public function podeDeletarEmLote(?User $user, iterable $records): bool
    {
        if (! $user) {
            return false;
        }

        foreach ($records as $record) {
            if (! $record instanceof User) {
                continue;
            }

            if (! $this->podeDeletar($user, $record)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Exclui usuário desvinculando FKs amigáveis (pessoa/professor permanecem).
     */
    public function excluirUsuario(User $record): void
    {
        if ($record->id === 1) {
            throw new \RuntimeException('O usuário raiz não pode ser excluído.');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($record): void {
            if (Schema::hasTable('servidores') && Schema::hasColumn('servidores', 'user_id')) {
                \App\Models\Servidor::query()->where('user_id', $record->id)->update(['user_id' => null]);
            }

            if (Schema::hasTable('professores') && Schema::hasColumn('professores', 'user_id')) {
                Professor::query()->where('user_id', $record->id)->update(['user_id' => null]);
            }

            if (Schema::hasTable('escola_user')) {
                \Illuminate\Support\Facades\DB::table('escola_user')->where('user_id', $record->id)->delete();
            }

            if (Schema::hasTable('socialite_users')) {
                \Illuminate\Support\Facades\DB::table('socialite_users')->where('user_id', $record->id)->delete();
            }

            $record->roles()->detach();
            $record->permissions()->detach();
            $record->delete();
        });
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
            ->when($busca, fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ["%{$busca}%"]))
            ->when(! $userLogado->hasRole('Admin'), fn ($q) => $q->where('name', '!=', 'Aplicar Permissoes'))
            ->get();

        $permissoesDaRole = $record->roles
            ->flatMap(fn ($role) => $role->permissions)
            ->pluck('name')
            ->toArray();

        $permissoesDiretas = $record->getDirectPermissions()->pluck('name')->toArray();

        $porGrupo = $todas->groupBy(fn ($p) => explode(' ', $p->name)[0]);

        $schema = [];

        foreach ($porGrupo as $grupo => $permissoes) {
            $filtradas = $permissoes->when(
                $busca,
                fn ($collection) => $collection->filter(
                    fn ($perm) => str_contains(strtolower($perm->name), $busca)
                )
            );

            if ($filtradas->isEmpty()) {
                continue;
            }

            $permissoesDoGrupoNaRole = collect($filtradas)
                ->filter(fn ($p) => in_array($p->name, $permissoesDaRole))
                ->pluck('name')
                ->toArray();

            $helperText = '';
            if (! empty($permissoesDoGrupoNaRole)) {
                $helperText = 'Herdadas dos níveis: '.implode(', ', $permissoesDoGrupoNaRole);
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
            ->when($busca, fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ["%{$busca}%"]))
            ->when(! $userLogado->hasRole('Admin'), fn ($q) => $q->where('name', '!=', 'Aplicar Permissoes'))
            ->get();

        $porGrupo = $todas->groupBy(fn ($p) => explode(' ', $p->name)[0]);

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
        $access = app(UserSetorAccessService::class);

        if (! $access->hasGlobalAccess($user)) {
            $base->whereDoesntHave('roles', fn ($q) => $q->where('name', 'Admin'));

            $setorIds = $access->visibleSetorIds($user);

            if ($setorIds === []) {
                return $base->whereRaw('1 = 0');
            }

            $base->where(function (Builder $query) use ($setorIds): void {
                $query->whereIn('setor_id', $setorIds)
                    ->orWhereHas('escola', fn (Builder $escola): Builder => $escola->whereIn('setor_id', $setorIds));
            });
        }

        return $base;
    }

    public function badgeNavegacaoParaNovosUsuarios(?User $user): ?string
    {
        if (! $user || ! $this->ehAdmin($user)) {
            return null;
        }

        $ignorados = IgnoredUser::where('admin_id', $user->id)->pluck('user_id')->toArray();
        $count = User::where('email_approved', false)->whereNotIn('id', $ignorados)->count();

        return $count > 0 ? (string) $count : null;
    }

    public function sincronizarIgnoradosParaAdmin(User $admin): void
    {
        if (! $this->ehAdmin($admin)) {
            return;
        }

        $pendentes = User::where('email_approved', false)->pluck('id');

        foreach ($pendentes as $userId) {
            IgnoredUser::firstOrCreate([
                'admin_id' => $admin->id,
                'user_id' => $userId,
            ]);
        }
    }

    public function aplicarFiltroPorEscolaDoUsuario(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        $scope = app(PessoaScopeService::class);

        if ($scope->hasGlobalAccess($user)) {
            return $query;
        }

        $escolaIds = $scope->escolaIdsDosVinculos($user);

        if ($escolaIds === []) {
            return $query->whereRaw('1 = 0');
        }

        $model = $query->getModel();
        $table = $model->getTable();

        if (Schema::hasColumn($table, 'id_escola')) {
            return $query->whereIn("{$table}.id_escola", $escolaIds);
        }

        if (! method_exists($model, 'turma')) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas(
            'turma',
            fn (Builder $turmaQuery): Builder => $turmaQuery->whereIn('id_escola', $escolaIds),
        );
    }

    public function aplicarFiltroTurmasDoUsuario(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        $scope = app(PessoaScopeService::class);

        if ($scope->hasGlobalAccess($user)) {
            return $query;
        }

        if ($scope->ehEquipeGestora($user)) {
            $escolaIds = $scope->escolaIdsDosVinculos($user);

            return $escolaIds === []
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('id_escola', $escolaIds);
        }

        // Impacto: professor ve turmas pelo vinculo componente-professor, nao por id_escola. Trocar para escola amplia ou restringe indevidamente avaliacoes e alunos visiveis.
        if ($user->ehProfessor()) {
            $professoresIds = $this->professorIds($user);

            return $query->whereHas('componentes', function ($q) use ($professoresIds) {
                $q->whereIn('turma_componente_professor.professor_id', $professoresIds)
                    ->where('turma_componente_professor.tem_professor', true);
            });
        }

        $escolaIds = $scope->escolaIdsDosVinculos($user);

        return $escolaIds === []
            ? $query->whereRaw('1 = 0')
            : $query->whereIn('id_escola', $escolaIds);
    }

    public function aplicarFiltroAlunosDoUsuario(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        $scope = app(PessoaScopeService::class);

        if ($scope->hasGlobalAccess($user)) {
            return $query;
        }

        if ($scope->ehEquipeGestora($user)) {
            $escolaIds = $scope->escolaIdsDosVinculos($user);

            return $escolaIds === []
                ? $query->whereRaw('1 = 0')
                : $query->whereHas('turma', fn ($q) => $q->whereIn('id_escola', $escolaIds));
        }

        // Impacto: este filtro protege alunos por turmas lecionadas. Alterar para filtrar so por escola pode expor alunos de turmas sem vinculo com o professor.
        if ($user->ehProfessor()) {
            $professoresIds = $this->professorIds($user);

            return $query->whereHas('turma.componentes', function ($q) use ($professoresIds) {
                $q->whereIn('turma_componente_professor.professor_id', $professoresIds)
                    ->where('turma_componente_professor.tem_professor', true);
            });
        }

        $escolaIds = $scope->escolaIdsDosVinculos($user);

        return $escolaIds === []
            ? $query->whereRaw('1 = 0')
            : $query->whereHas('turma', fn ($q) => $q->whereIn('id_escola', $escolaIds));
    }

    public function podeAcessarTurma(User $user, Turma $turma): bool
    {
        $scope = app(PessoaScopeService::class);

        if ($scope->hasGlobalAccess($user)) {
            return true;
        }

        if ($scope->ehEquipeGestora($user)) {
            return $scope->canAccessEscola($user, (int) $turma->id_escola);
        }

        if ($user->ehProfessor()) {
            return $turma->componentes()
                ->whereIn('turma_componente_professor.professor_id', $this->professorIds($user))
                ->where('turma_componente_professor.tem_professor', true)
                ->exists();
        }

        return $scope->canAccessEscola($user, (int) $turma->id_escola);
    }

    public function podeAcessarProfessor(User $user, Professor $professor): bool
    {
        return app(PessoaScopeService::class)->canAccessEscola($user, (int) $professor->id_escola);
    }

    public function aplicarFiltroPorEscolaDoUsuarioEmTurma(Builder $query, ?User $user): Builder
    {
        return $this->aplicarFiltroTurmasDoUsuario($query, $user);
    }

    /**
     * @return array<int, int>
     */
    private function professorIds(User $user): array
    {
        $userId = (int) $user->getKey();

        if (! array_key_exists($userId, $this->professorIdsByUser)) {
            $this->professorIdsByUser[$userId] = $user->professores()
                ->where('ativo', true)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        return $this->professorIdsByUser[$userId];
    }
}
