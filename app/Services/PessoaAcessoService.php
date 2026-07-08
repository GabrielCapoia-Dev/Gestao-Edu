<?php

namespace App\Services;

use App\Models\FuncaoAdministrativa;
use App\Models\Professor;
use App\Models\Role;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class PessoaAcessoService
{
    public function rolesImutaveisProfessor(): Collection
    {
        return FuncaoAdministrativa::professorPadrao()
            ->rolesPadrao()
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);
    }

    public function usuarioEhProfessor(Servidor|User|null $referencia): bool
    {
        if ($referencia instanceof User) {
            return Servidor::query()
                ->where('user_id', $referencia->id)
                ->whereHas('professores', fn ($query) => $query->where('ativo', true))
                ->exists();
        }

        if ($referencia instanceof Servidor) {
            return $referencia->professores()->where('ativo', true)->exists();
        }

        return false;
    }

    public function provisionarUsuarioProfessor(Servidor $servidor, array $acesso = []): void
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
                    $professor->update(['user_id' => $user->id]);
                }
            }

            $this->aplicarRolesProfessor($user, $acesso, $criouUser);
            $this->aplicarPermissoesExtras($user, $acesso);

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }

    public function aplicarRolesProfessor(User $user, array $acesso = [], bool $forcarDefaults = false): void
    {
        $rolesImutaveis = Role::query()
            ->whereIn('id', $this->rolesImutaveisProfessor()->all())
            ->get();

        $rolesAdicionais = collect($acesso['roles_adicionais'] ?? $acesso['roles'] ?? [])
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->reject(fn (int $id): bool => $this->rolesImutaveisProfessor()->contains($id))
            ->unique()
            ->values();

        if ($forcarDefaults || ($rolesAdicionais->isEmpty() && ! $user->roles()->exists())) {
            $user->syncRoles($rolesImutaveis);

            return;
        }

        $rolesFinais = $rolesImutaveis
            ->pluck('id')
            ->merge($rolesAdicionais)
            ->merge($user->roles()->pluck('roles.id'))
            ->unique()
            ->values();

        $user->syncRoles(Role::query()->whereIn('id', $rolesFinais->all())->get());
    }

    public function mesclarRolesComProfessor(User $user, array $roleIds): array
    {
        if (! $this->usuarioEhProfessor($user)) {
            return $roleIds;
        }

        return collect($roleIds)
            ->merge($this->rolesImutaveisProfessor()->all())
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
    public function provisionarAcessosDoServidor(Servidor $servidor): void
    {
        $servidor = $servidor->fresh([
            'user',
            'vinculosAtivos.funcaoAdministrativa.rolesPadrao',
            'professores',
        ]);

        $vinculosComAcesso = $servidor->vinculosAtivos
            ->filter(fn (ServidorFuncaoAdministrativa $vinculo): bool => (bool) $vinculo->funcaoAdministrativa?->concede_acesso_sistema);

        if ($vinculosComAcesso->isEmpty()) {
            return;
        }

        if (blank($servidor->email)) {
            return;
        }

        DB::transaction(function () use ($servidor, $vinculosComAcesso): void {
            $criouUser = ! $servidor->user;
            $user = $this->resolverOuCriarUser($servidor);

            if (! $user) {
                return;
            }

            if ((int) ($servidor->user_id ?? 0) !== (int) $user->id) {
                $servidor->update(['user_id' => $user->id]);
            }

            foreach ($servidor->professores as $professor) {
                if ((int) ($professor->user_id ?? 0) !== (int) $user->id) {
                    $professor->update(['user_id' => $user->id]);
                }
            }

            if (! $criouUser && $user->roles()->exists()) {
                return;
            }

            $roleIds = $vinculosComAcesso
                ->flatMap(fn (ServidorFuncaoAdministrativa $vinculo) => $vinculo->funcaoAdministrativa?->rolesPadrao?->pluck('id') ?? collect())
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values();

            if ($roleIds->isEmpty()) {
                return;
            }

            $user->syncRoles(Role::query()->whereIn('id', $roleIds)->get());

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
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

    private function resolverOuCriarUser(Servidor $servidor, array $acesso = []): ?User
    {
        if ($servidor->user) {
            $this->atualizarDadosBasicosDoUser($servidor->user, $servidor, $acesso);

            return $servidor->user;
        }

        $user = User::query()->where('email', $servidor->email)->first();

        if ($user) {
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

    private function atualizarDadosBasicosDoUser(User $user, Servidor $servidor, array $acesso = []): void
    {
        $payload = [
            'name' => $servidor->nome ?? $user->name,
            'email' => $servidor->email ?? $user->email,
            'id_escola' => $servidor->id_escola ?? $user->id_escola,
            'setor_id' => $servidor->setor_id ?? $user->setor_id,
        ];

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