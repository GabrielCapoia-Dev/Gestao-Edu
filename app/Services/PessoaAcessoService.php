<?php

namespace App\Services;

use App\Models\Professor;
use App\Models\Role;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class PessoaAcessoService
{
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

    private function resolverOuCriarUser(Servidor $servidor): ?User
    {
        if ($servidor->user) {
            $this->atualizarDadosBasicosDoUser($servidor->user, $servidor);

            return $servidor->user;
        }

        $user = User::query()->where('email', $servidor->email)->first();

        if ($user) {
            $this->atualizarDadosBasicosDoUser($user, $servidor);

            return $user;
        }

        return User::query()->create([
            'name' => $servidor->nome,
            'email' => $servidor->email,
            'password' => Hash::make(Str::password(16)),
            'email_approved' => true,
            'email_verified_at' => now(),
            'must_change_password' => true,
            'id_escola' => $servidor->id_escola,
            'setor_id' => $servidor->setor_id,
        ]);
    }

    private function atualizarDadosBasicosDoUser(User $user, Servidor $servidor): void
    {
        $user->update([
            'name' => $servidor->nome ?? $user->name,
            'id_escola' => $servidor->id_escola ?? $user->id_escola,
            'setor_id' => $servidor->setor_id ?? $user->setor_id,
        ]);
    }
}