<?php

namespace App\Services;

use App\Models\Pessoa;
use App\Models\Servidor;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PessoaUsuarioService
{
    public function __construct(
        private readonly PessoaAcessoService $pessoaAcessoService,
        private readonly UserService $userService,
    ) {}

    public function criarOuVincularAcesso(
        Pessoa|Servidor $pessoa,
        User $operador,
        ?string $senha = null,
        bool $aprovado = true,
    ): User {
        if (
            ! Gate::forUser($operador)->allows('createUserAccess', $pessoa)
            || ! Gate::forUser($operador)->allows('create', User::class)
        ) {
            throw new AuthorizationException('Você não possui permissão para criar o acesso desta pessoa.');
        }

        return $this->garantirUsuario($pessoa, $senha);
    }

    public function garantirUsuario(Pessoa|Servidor $pessoa, ?string $senha = null): User
    {
        if (blank($pessoa->email)) {
            throw ValidationException::withMessages([
                'email' => 'Toda pessoa precisa de um e-mail válido para possuir acesso.',
            ]);
        }

        return DB::transaction(function () use ($pessoa, $senha): User {
            $pessoa = Servidor::query()->lockForUpdate()->findOrFail($pessoa->id);

            if ($pessoa->user?->trashed()) {
                throw ValidationException::withMessages([
                    'acesso' => 'A conta vinculada está arquivada. Restaure-a na gestão de usuários antes de continuar.',
                ]);
            }

            if ($pessoa->user) {
                $pessoa->user->forceFill([
                    'name' => $pessoa->nome,
                    'email' => $pessoa->email,
                ])->save();
                $this->pessoaAcessoService->provisionarAcessosDoServidor($pessoa->fresh());

                return $pessoa->user->fresh(['roles.permissions', 'permissions']);
            }

            $user = User::withTrashed()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower((string) $pessoa->email)])
                ->lockForUpdate()
                ->first();

            if ($user?->trashed()) {
                throw ValidationException::withMessages([
                    'email' => 'Já existe uma conta arquivada com este e-mail. Restaure essa conta antes de vinculá-la.',
                ]);
            }

            if ($user && Pessoa::withTrashed()->where('user_id', $user->id)->whereKeyNot($pessoa->id)->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'A conta encontrada para este e-mail já está vinculada a outra pessoa.',
                ]);
            }

            $senhaInicial = filled($senha) ? (string) $senha : Str::password(16);
            $user ??= User::query()->create([
                'name' => $pessoa->nome,
                'email' => $pessoa->email,
                'password' => Hash::make($senhaInicial),
                'email_verified_at' => now(),
                'must_change_password' => true,
            ]);

            $pessoa->update(['user_id' => $user->id]);
            $this->pessoaAcessoService->provisionarAcessosDoServidor($pessoa->fresh());

            return $user->fresh(['roles.permissions', 'permissions']);
        });
    }

    public function garantirUsuarioInativoSemEmail(Pessoa|Servidor $pessoa): User
    {
        return DB::transaction(function () use ($pessoa): User {
            $pessoa = Servidor::withTrashed()->lockForUpdate()->findOrFail($pessoa->id);

            if ($pessoa->status !== Pessoa::STATUS_INATIVO) {
                throw ValidationException::withMessages([
                    'status' => 'Cadastros sem e-mail único precisam estar inativos.',
                ]);
            }

            if ($pessoa->user) {
                return $pessoa->user;
            }

            $user = User::query()->create([
                'name' => $pessoa->nome,
                'email' => null,
                'password' => Hash::make(Str::password(32)),
                'email_verified_at' => null,
                'must_change_password' => true,
            ]);

            $pessoa->forceFill(['user_id' => $user->id])->save();

            return $user;
        });
    }

    public function arquivarContaDaPessoa(Pessoa|Servidor $pessoa, User $operador): void
    {
        $user = $pessoa->user;

        if (! $user || ! $this->userService->podeDeletar($operador, $user)) {
            throw new AuthorizationException('Você não possui permissão para excluir a conta desta pessoa.');
        }

        $this->userService->excluirUsuario($user);
    }

    /** @deprecated Use arquivarContaDaPessoa(). */
    public function excluirContaDaPessoa(Pessoa|Servidor $pessoa, User $operador): void
    {
        $this->arquivarContaDaPessoa($pessoa, $operador);
    }

    public function vincularContaExistente(Pessoa|Servidor $pessoa, User $conta, User $operador): User
    {
        if ($conta->trashed()) {
            throw ValidationException::withMessages([
                'pessoa_id' => 'Restaure a conta arquivada antes de vinculá-la a uma pessoa.',
            ]);
        }

        if (
            ! Gate::forUser($operador)->allows('update', $pessoa)
            || ! Gate::forUser($operador)->allows('update', $conta)
        ) {
            throw new AuthorizationException('Você não possui permissão para vincular esta conta à pessoa.');
        }

        if (mb_strtolower((string) $pessoa->email) !== mb_strtolower((string) $conta->email)) {
            throw ValidationException::withMessages([
                'pessoa_id' => 'A conta e a pessoa precisam possuir o mesmo e-mail institucional.',
            ]);
        }

        return DB::transaction(function () use ($pessoa, $conta): User {
            $pessoa = Servidor::query()->lockForUpdate()->findOrFail($pessoa->id);
            $conta = User::withTrashed()->lockForUpdate()->findOrFail($conta->id);

            if ($conta->trashed()) {
                throw ValidationException::withMessages([
                    'pessoa_id' => 'Restaure a conta arquivada antes de vinculá-la a uma pessoa.',
                ]);
            }

            if ($pessoa->user_id || Pessoa::withTrashed()->where('user_id', $conta->id)->whereKeyNot($pessoa->id)->exists()) {
                throw ValidationException::withMessages([
                    'pessoa_id' => 'A pessoa ou a conta selecionada já possui outro vínculo de acesso.',
                ]);
            }

            $pessoa->update(['user_id' => $conta->id]);
            $this->pessoaAcessoService->provisionarAcessosDoServidor($pessoa->fresh());

            return $conta->fresh(['roles.permissions', 'permissions']);
        });
    }

    public function usuariosSemPessoaQuery(User $operador): Builder
    {
        return $this->userService
            ->listarUsuariosQuery(User::query(), $operador)
            ->whereKeyNot($operador->id)
            ->whereDoesntHave('roles', fn (Builder $roles): Builder => $roles->where('name', 'Admin'))
            ->whereDoesntHave('servidores', fn (Builder $pessoas): Builder => $pessoas->withTrashed())
            ->whereDoesntHave('professores');
    }
}
