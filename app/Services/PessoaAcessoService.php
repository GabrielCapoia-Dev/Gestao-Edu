<?php

namespace App\Services;

use App\Models\FuncaoAdministrativa;
use App\Models\Pessoa;
use App\Models\Professor;
use App\Models\Role;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LogicException;
use Spatie\Permission\PermissionRegistrar;

class PessoaAcessoService
{
    public function rolesImutaveisProfessor(): Collection
    {
        return FuncaoAdministrativa::professorPadrao()
            ->rolesPadrao()
            ->get()
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);
    }

    public function rolesFuncionaisGerenciadasIds(): Collection
    {
        $idsPivot = DB::table('funcao_administrativa_role')
            ->pluck('role_id')
            ->map(fn ($id): int => (int) $id);

        $idsPorNome = Role::query()
            ->whereIn('name', ['Professor', 'Equipe Gestora', 'SecretÃ¡rio', 'Secretário'])
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);

        $idsOperacionais = Role::query()
            ->whereIn('name', ['Manutenção', 'Obras', 'Transporte', 'Assessoria Pedagógica'])
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);

        return $idsPivot
            ->merge($idsPorNome)
            ->merge($idsOperacionais)
            ->unique()
            ->values();
    }

    public function roleFuncionalGerenciada(Role|int|string|null $role): bool
    {
        if ($role instanceof Role) {
            return $this->rolesFuncionaisGerenciadasIds()->contains((int) $role->id);
        }

        if (is_numeric($role)) {
            return $this->rolesFuncionaisGerenciadasIds()->contains((int) $role);
        }

        if (in_array((string) $role, ['Manutenção', 'Obras', 'Transporte', 'Assessoria Pedagógica'], true)) {
            return true;
        }

        return in_array((string) $role, ['Professor', 'Equipe Gestora', 'SecretÃ¡rio', 'Secretário'], true);
    }

    public function usuarioEhProfessor(Pessoa|Servidor|User|null $referencia): bool
    {
        if ($referencia instanceof User) {
            return Pessoa::query()
                ->where('user_id', $referencia->id)
                ->whereHas('professores', fn ($query) => $query->where('ativo', true))
                ->exists();
        }

        if ($referencia instanceof Pessoa) {
            return $referencia->professores()->where('ativo', true)->exists();
        }

        return false;
    }

    public function provisionarUsuarioProfessor(Pessoa|Servidor $servidor, array $acesso = []): void
    {
        $servidor = $servidor->fresh(['user', 'professores']);

        if ($servidor->professores->where('ativo', true)->isEmpty() || blank($servidor->email)) {
            return;
        }

        DB::transaction(function () use ($servidor, $acesso): void {
            $criouUser = ! $servidor->user;
            $user = $this->resolverOuCriarUser($servidor, $acesso);

            if (! $user) {
                return;
            }

            if ((int) ($servidor->user_id ?? 0) !== (int) $user->id) {
                $servidor->update(['user_id' => $user->id]);
            }

            foreach ($servidor->professores as $professor) {
                if ((int) ($professor->user_id ?? 0) !== (int) $user->id) {
                    $professor->updateQuietly(['user_id' => $user->id]);
                }
            }

            $this->aplicarRolesProfessor($user, $acesso, $criouUser);
            $this->aplicarPermissoesExtras($user, $acesso);
            app(ProfessorEscolaVinculoService::class)->sincronizarPorUsuario($user);

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }

    public function aplicarRolesProfessor(User $user, array $acesso = [], bool $forcarDefaults = false): void
    {
        $rolesImutaveis = Role::query()
            ->whereIn('id', $this->rolesImutaveisProfessor()->all())
            ->get();

        if ($forcarDefaults || $this->usuarioEhProfessor($user)) {
            $extrasPermitidos = collect($acesso['roles_adicionais'] ?? $acesso['roles'] ?? [])
                ->filter(fn ($id): bool => filled($id))
                ->map(fn ($id): int => (int) $id)
                ->reject(fn (int $id): bool => $this->rolesImutaveisProfessor()->contains($id))
                ->unique()
                ->values();

            $rolesFuncionaisAtivas = $rolesImutaveis
                ->pluck('id')
                ->when(! $forcarDefaults, fn (Collection $ids) => $ids->merge($extrasPermitidos))
                ->unique()
                ->values();

            // Remove apenas roles provenientes de cargos encerrados. Admin e roles
            // independentes permanecem durante a conversão Equipe Gestora -> Professor.
            $this->reconciliarRolesFuncionais($user, $rolesFuncionaisAtivas);

            return;
        }

        $rolesAdicionais = collect($acesso['roles_adicionais'] ?? $acesso['roles'] ?? [])
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($rolesAdicionais->isEmpty() && ! $user->roles()->exists()) {
            $user->syncRoles($rolesImutaveis);

            return;
        }

        if ($rolesAdicionais->isNotEmpty()) {
            $user->syncRoles(Role::query()->whereIn('id', $rolesAdicionais->all())->get());
        }
    }

    /**
     * Reconcilia em massa as roles funcionais dos professores sem apagar roles independentes.
     *
     * @return array{usuarios: int}
     */
    public function sanitizarAcessosSomenteProfessor(): array
    {
        $count = 0;

        User::query()
            ->where(function ($q): void {
                $q->whereHas('professores', fn ($p) => $p->where('ativo', true))
                    ->orWhereHas('servidores.professores', fn ($p) => $p->where('ativo', true));
            })
            ->orderBy('id')
            ->chunkById(100, function ($users) use (&$count): void {
                foreach ($users as $user) {
                    if ((int) $user->id === 1 || $user->hasRole('Admin')) {
                        continue;
                    }

                    $this->aplicarRolesProfessor($user, [], forcarDefaults: true);
                    $count++;
                }
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return ['usuarios' => $count];
    }

    public function mesclarRolesComProfessor(User $user, array $roleIds): array
    {
        $forjadas = collect($roleIds)
            ->map(fn ($id): int => (int) $id)
            ->intersect($this->rolesFuncionaisGerenciadasIds())
            ->diff($user->roles()->pluck('roles.id')->map(fn ($id): int => (int) $id));

        if ($forjadas->isNotEmpty()) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'Roles funcionais devem ser alteradas apenas pelo fluxo de Pessoas.'
            );
        }

        if (! $this->usuarioEhProfessor($user)) {
            $funcionaisAtuais = $user->roles()
                ->pluck('roles.id')
                ->map(fn ($id): int => (int) $id)
                ->intersect($this->rolesFuncionaisGerenciadasIds());

            return collect($roleIds)
                ->map(fn ($id): int => (int) $id)
                ->reject(fn (int $id): bool => $this->rolesFuncionaisGerenciadasIds()->contains($id))
                ->merge($funcionaisAtuais)
                ->unique()
                ->values()
                ->all();
        }

        return collect($roleIds)
            ->merge($this->rolesImutaveisProfessor()->all())
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
    public function provisionarAcessosDoServidor(Pessoa|Servidor $servidor): void
    {
        $servidor = $servidor->fresh([
            'user',
            'vinculosAtivos.funcaoAdministrativa.rolesPadrao',
            'professores',
        ]);

        $vinculosComAcesso = $servidor->vinculosAtivos
            ->filter(fn (ServidorFuncaoAdministrativa $vinculo): bool => (bool) $vinculo->funcaoAdministrativa?->concede_acesso_sistema);

        if (! $servidor->user && ($vinculosComAcesso->isEmpty() || blank($servidor->email))) {
            return;
        }

        DB::transaction(function () use ($servidor, $vinculosComAcesso): void {
            $user = $servidor->user ?: $this->resolverOuCriarUser($servidor);

            if (! $user) {
                return;
            }

            $this->garantirUserExclusivoDaPessoa($user, $servidor);

            if ($servidor->user) {
                $this->atualizarDadosBasicosDoUser($user, $servidor);
            }

            if ((int) ($servidor->user_id ?? 0) !== (int) $user->id) {
                $servidor->update(['user_id' => $user->id]);
            }

            foreach ($servidor->professores as $professor) {
                if ((int) ($professor->user_id ?? 0) !== (int) $user->id) {
                    $professor->updateQuietly(['user_id' => $user->id]);
                }
            }

            $roleEquipeGestora = $this->garantirRoleEquipeGestoraNosVinculos($vinculosComAcesso);

            $roleIds = $vinculosComAcesso
                ->flatMap(function (ServidorFuncaoAdministrativa $vinculo) use ($roleEquipeGestora): Collection {
                    $funcao = $vinculo->funcaoAdministrativa;

                    if ($funcao?->ehEquipeGestora()) {
                        return $roleEquipeGestora
                            ? collect([$roleEquipeGestora->id])
                            : collect();
                    }

                    return $funcao?->rolesPadrao?->pluck('id') ?? collect();
                })
                ->when($roleEquipeGestora, fn (Collection $ids) => $ids->push($roleEquipeGestora->id))
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values();

            $this->reconciliarRolesFuncionais($user, $roleIds);
            $this->sincronizarEscopoEscolarDosVinculos($user, $servidor->vinculosAtivos);

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }

    private function garantirRoleEquipeGestoraNosVinculos(Collection $vinculos): ?Role
    {
        $funcoesGestoras = $vinculos
            ->map(fn (ServidorFuncaoAdministrativa $vinculo) => $vinculo->funcaoAdministrativa)
            ->filter(fn (?FuncaoAdministrativa $funcao): bool => $funcao?->tipoEquipeGestora() !== null)
            ->unique('id');

        if ($funcoesGestoras->isEmpty()) {
            return null;
        }

        $role = Role::query()
            ->where('name', 'Equipe Gestora')
            ->where('guard_name', 'web')
            ->first();

        if (! $role) {
            throw new LogicException(
                'A role Equipe Gestora ainda não foi criada. Execute o comando permissoes:criar antes de provisionar gestores.'
            );
        }

        foreach ($funcoesGestoras as $funcao) {
            $funcao->rolesPadrao()->syncWithoutDetaching([$role->id]);
        }

        return $role;
    }

    private function reconciliarRolesFuncionais(User $user, Collection $roleIdsAtivas): void
    {
        $rolesGerenciadas = $this->rolesFuncionaisGerenciadasIds();

        $adminIds = Role::query()
            ->where('name', 'Admin')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);

        $rolesPreservadas = $user->roles()
            ->pluck('roles.id')
            ->map(fn ($id): int => (int) $id)
            ->reject(fn (int $id): bool => $rolesGerenciadas->contains($id) && ! $adminIds->contains($id));

        $rolesFinais = $rolesPreservadas
            ->merge($roleIdsAtivas)
            ->unique()
            ->values();

        $user->syncRoles(Role::query()->whereIn('id', $rolesFinais->all())->get());
    }

    private function sincronizarEscopoEscolarDosVinculos(User $user, Collection $vinculosAtivos): void
    {
        $escolaIds = $vinculosAtivos
            ->pluck('id_escola')
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $setorIds = $vinculosAtivos
            ->pluck('setor_id')
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $user->escolas()->sync($escolaIds->all());
        $user->forceFill([
            'id_escola' => $escolaIds->count() === 1 ? $escolaIds->first() : null,
            'setor_id' => $setorIds->count() === 1 ? $setorIds->first() : null,
        ])->save();
    }

    public function vincularProfessorAoVinculo(ServidorFuncaoAdministrativa $vinculo): void
    {
        $vinculo->loadMissing('funcaoAdministrativa', 'servidor.professores');

        if (! $vinculo->funcaoAdministrativa?->exige_professor) {
            return;
        }

        $servidor = $vinculo->servidor;

        if (! $servidor) {
            return;
        }

        $professor = $servidor->professores
            ->first(fn (Professor $item): bool => filled($vinculo->id_escola)
                ? (int) $item->id_escola === (int) $vinculo->id_escola
                : filled($vinculo->matricula) && (string) $item->matricula === (string) $vinculo->matricula)
            ?? $servidor->professores->first();

        if (! $professor) {
            return;
        }

        if ((int) ($professor->servidor_funcao_administrativa_id ?? 0) !== (int) $vinculo->id) {
            $professor->update(['servidor_funcao_administrativa_id' => $vinculo->id]);
        }
    }

    private function resolverOuCriarUser(Pessoa|Servidor $servidor, array $acesso = []): ?User
    {
        if ($servidor->user) {
            $this->garantirUserExclusivoDaPessoa($servidor->user, $servidor);
            $this->atualizarDadosBasicosDoUser($servidor->user, $servidor, $acesso);

            return $servidor->user;
        }

        $user = User::query()
            ->where('email', $servidor->email)
            ->lockForUpdate()
            ->first();

        if ($user) {
            $this->garantirUserExclusivoDaPessoa($user, $servidor);
            $this->atualizarDadosBasicosDoUser($user, $servidor, $acesso);

            return $user;
        }

        return User::query()->create([
            'name' => $servidor->nome,
            'email' => $servidor->email,
            'password' => Hash::make(Str::password(16)),
            'email_approved' => $acesso['email_approved'] ?? true,
            'email_verified_at' => now(),
            'must_change_password' => true,
            'id_escola' => $servidor->id_escola,
            'setor_id' => $servidor->setor_id,
        ]);
    }

    private function garantirUserExclusivoDaPessoa(User $user, Pessoa|Servidor $servidor): void
    {
        $vinculadoAOutraPessoa = Pessoa::query()
            ->where('user_id', $user->getKey())
            ->where('id', '!=', $servidor->getKey())
            ->exists();

        if ($vinculadoAOutraPessoa) {
            throw new LogicException(
                'A conta encontrada para este e-mail já está vinculada a outra Pessoa. O conflito deve ser saneado antes de provisionar o acesso.'
            );
        }
    }

    private function atualizarDadosBasicosDoUser(User $user, Pessoa|Servidor $servidor, array $acesso = []): void
    {
        $payload = [
            'name' => $servidor->nome ?? $user->name,
            'id_escola' => $servidor->id_escola ?? $user->id_escola,
            'setor_id' => $servidor->setor_id ?? $user->setor_id,
        ];

        $email = filled($servidor->email)
            ? Professor::normalizarEmail((string) $servidor->email)
            : null;

        if ($email && $email !== $user->email) {
            $conflito = User::query()
                ->where('email', $email)
                ->where('id', '!=', $user->id)
                ->exists();

            if (! $conflito) {
                $payload['email'] = $email;
            }
        }

        if (array_key_exists('email_approved', $acesso)) {
            $payload['email_approved'] = (bool) $acesso['email_approved'];
        }

        $user->update($payload);
    }

    private function aplicarPermissoesExtras(User $user, array $acesso): void
    {
        if (! array_key_exists('usar_permissoes_extras', $acesso)) {
            return;
        }

        if (empty($acesso['usar_permissoes_extras'])) {
            $user->syncPermissions([]);

            return;
        }

        $permissoes = collect($acesso['permissoes_extras'] ?? [])
            ->filter(fn ($permission): bool => filled($permission))
            ->unique()
            ->values()
            ->all();

        if ($permissoes !== []) {
            $user->syncPermissions($permissoes);
        }
    }
}
