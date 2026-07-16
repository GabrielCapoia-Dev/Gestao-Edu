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

        if (blank($pessoa->email)) {
            throw ValidationException::withMessages([
                'email' => 'Informe um e-mail institucional antes de criar o acesso.',
            ]);
        }

        return DB::transaction(function () use ($pessoa, $senha, $aprovado): User {
            $pessoa = Servidor::query()->lockForUpdate()->findOrFail($pessoa->id);

            if ($pessoa->user) {
                throw ValidationException::withMessages([
                    'acesso' => 'Esta pessoa já possui uma conta de acesso vinculada.',
                ]);
            }

            $user = User::query()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower((string) $pessoa->email)])
                ->lockForUpdate()
                ->first();

            if ($user && Pessoa::query()->where('user_id', $user->id)->whereKeyNot($pessoa->id)->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'A conta encontrada para este e-mail já está vinculada a outra pessoa.',
                ]);
            }

            $senhaInicial = filled($senha) ? (string) $senha : Str::password(16);
            $user ??= User::query()->create([
                'name' => $pessoa->nome,
                'email' => $pessoa->email,
                'password' => Hash::make($senhaInicial),
                'email_approved' => $aprovado,
                'email_verified_at' => $aprovado ? now() : null,
                'must_change_password' => true,
            ]);

            $pessoa->update(['user_id' => $user->id]);
            $this->pessoaAcessoService->provisionarAcessosDoServidor($pessoa->fresh());

            $user->forceFill([
                'email_approved' => $aprovado,
                'email_verified_at' => $aprovado ? ($user->email_verified_at ?? now()) : $user->email_verified_at,
            ])->save();

            return $user->fresh(['roles.permissions', 'permissions']);
        });
    }

    public function excluirContaDaPessoa(Pessoa|Servidor $pessoa, User $operador): void
    {
        $user = $pessoa->user;

        if (! $user || ! $this->userService->podeDeletar($operador, $user)) {
            throw new AuthorizationException('Você não possui permissão para excluir a conta desta pessoa.');
        }

        $this->userService->excluirUsuario($user);
    }

    public function vincularContaExistente(Pessoa|Servidor $pessoa, User $conta, User $operador): User
    {
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
            $conta = User::query()->lockForUpdate()->findOrFail($conta->id);

            if ($pessoa->user_id || Pessoa::query()->where('user_id', $conta->id)->whereKeyNot($pessoa->id)->exists()) {
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
            ->whereDoesntHave('servidores')
            ->whereDoesntHave('professores');
    }
}
