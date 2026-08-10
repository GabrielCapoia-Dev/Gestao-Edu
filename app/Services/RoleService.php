<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RoleService
{
    public function adminRole($record): bool
    {
        return $record->name === 'Admin';
    }

    public function bloquearCampo($record, $context): bool
    {
        if ($context === 'create') {
            return false;
        }

        return $this->roleProtegida($record, ['Admin', 'Secretário', 'SecretÃ¡rio', 'Administrativo']);
    }

    public function bloquearCampoEdit($record, $context): bool
    {
        if ($context === 'create') {
            return false;
        }

        return $this->roleProtegida($record, ['Admin']);
    }

    public function bloquearExclusao($record): bool
    {
        return $this->roleProtegida($record, ['Admin', 'Secretário', 'SecretÃ¡rio', 'Administrativo']);
    }

    public function bloquearSelecaoBulkActions($record): bool
    {
        return ! $this->roleProtegida($record, ['Admin', 'Secretário', 'SecretÃ¡rio', 'Administrativo']);
    }

    public function permissoesDisponiveisPara(?User $operador = null): Collection
    {
        $operador ??= Auth::user();

        if (! $operador) {
            return collect();
        }

        $query = Permission::query()->orderBy('name');

        if (! $operador->hasRole('Admin')) {
            $query->whereIn('name', $operador->getAllPermissions()->pluck('name'))
                ->where('name', '!=', 'Aplicar Permissoes');
        }

        return $query->get();
    }

    public function permissoesSelecionadas(array $data): Collection
    {
        return collect($data)
            ->filter(fn (mixed $value, string|int $key): bool => str_starts_with((string) $key, 'permissions_'))
            ->flatMap(fn (mixed $permissions): array => is_array($permissions) ? $permissions : [$permissions])
            ->filter(fn (mixed $permission): bool => filled($permission))
            ->map(fn (mixed $permission): string => (string) $permission)
            ->unique()
            ->values();
    }

    public function criarRole(array $data, ?User $operador = null): Role
    {
        $operador ??= Auth::user();

        if (
            ! $operador
            || ! Gate::forUser($operador)->allows('create', Role::class)
            || ! Gate::forUser($operador)->allows('applyPermissionsAny', User::class)
        ) {
            throw new AuthorizationException('Você não possui permissão para criar níveis de acesso.');
        }

        $validado = validator($data, [
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->where('guard_name', 'web')],
        ])->validate();
        $this->validarNomeNaoReservado($validado['name']);

        $selecionadas = $this->permissoesSelecionadas($data);
        $this->validarPermissoesSelecionadas($selecionadas, $operador);

        return DB::transaction(function () use ($validado, $selecionadas): Role {
            $role = Role::query()->create([
                'name' => $validado['name'],
                'guard_name' => 'web',
            ]);

            $role->syncPermissions($selecionadas->all());
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $role;
        });
    }

    public function atualizarRole(Role $record, array $data, ?User $operador = null): void
    {
        $operador ??= Auth::user();

        if (
            ! $operador
            || ! Gate::forUser($operador)->allows('update', $record)
            || ! Gate::forUser($operador)->allows('applyPermissionsAny', User::class)
            || $this->bloquearCampoEdit($record, 'edit')
        ) {
            throw new AuthorizationException('Você não possui permissão para editar este nível de acesso.');
        }

        $novoNome = (string) ($data['name'] ?? $record->name);

        if ($novoNome !== $record->name) {
            $this->validarNomeNaoReservado($novoNome);
        }

        validator(['name' => $novoNome], [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')
                    ->where('guard_name', 'web')
                    ->ignore($record->getKey()),
            ],
        ])->validate();

        DB::transaction(function () use ($record, $novoNome, $data, $operador): void {
            if ($novoNome !== $record->name) {
                $record->update(['name' => $novoNome]);
            }

            $this->sincronizarPermissoes($record, $this->permissoesSelecionadas($data), $operador);
        });
    }

    public function sincronizarPermissoes(
        Role $record,
        array|Collection $permissoes,
        ?User $operador = null,
    ): void {
        $operador ??= Auth::user();

        if (
            ! $operador
            || ! Gate::forUser($operador)->allows('update', $record)
            || ! Gate::forUser($operador)->allows('applyPermissionsAny', User::class)
            || $this->bloquearCampoEdit($record, 'edit')
        ) {
            throw new AuthorizationException('Você não possui permissão para alterar as permissões deste nível.');
        }

        $selecionadas = collect($permissoes)
            ->map(fn (mixed $permission): string => (string) $permission)
            ->filter()
            ->unique()
            ->values();
        $permitidas = $this->validarPermissoesSelecionadas($selecionadas, $operador);

        $atuais = $record->permissions()->pluck('name');
        $preservadas = $operador->hasRole('Admin')
            ? collect()
            : $atuais->diff($permitidas);

        $record->syncPermissions($preservadas->merge($selecionadas)->unique()->values()->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function validarPermissoesSelecionadas(Collection $selecionadas, User $operador): Collection
    {
        $permitidas = $this->permissoesDisponiveisPara($operador)->pluck('name');

        if ($selecionadas->diff($permitidas)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'permissions' => 'Uma ou mais permissões estão fora do seu escopo de administração.',
            ]);
        }

        return $permitidas;
    }

    private function validarNomeNaoReservado(string $nome): void
    {
        $normalizado = Str::lower(Str::ascii(trim($nome)));

        if (in_array($normalizado, ['admin', 'secretario', 'administrativo'], true)) {
            throw ValidationException::withMessages([
                'name' => 'Este nome é reservado e não pode ser criado ou reutilizado.',
            ]);
        }
    }

    /** @param list<string> $nomes */
    private function roleProtegida($record, array $nomes): bool
    {
        if (app(PessoaAcessoService::class)->roleFuncionalGerenciada($record)) {
            return true;
        }

        return in_array($record->name, $nomes, true);
    }
}
