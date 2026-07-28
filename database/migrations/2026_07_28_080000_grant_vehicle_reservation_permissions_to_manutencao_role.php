<?php

use App\Models\Enums\ListaPermissoes;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect($this->permissions())
            ->map(fn (ListaPermissoes $permission): Permission => Permission::firstOrCreate([
                'name' => $permission->label(),
                'guard_name' => 'web',
            ]));

        Role::query()
            ->where('name', 'Manutenção')
            ->where('guard_name', 'web')
            ->get()
            ->each(fn (Role $role): mixed => $role->givePermissionTo($permissions));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', array_map(
                fn (ListaPermissoes $permission): string => $permission->label(),
                $this->permissions(),
            ))
            ->get();

        Role::query()
            ->where('name', 'Manutenção')
            ->where('guard_name', 'web')
            ->get()
            ->each(fn (Role $role): mixed => $role->revokePermissionTo($permissions));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @return list<ListaPermissoes> */
    private function permissions(): array
    {
        return [
            ListaPermissoes::ListarReservasVeiculos,
            ListaPermissoes::CriarReservasVeiculos,
            ListaPermissoes::EditarReservasVeiculos,
            ListaPermissoes::CancelarReservasVeiculos,
        ];
    }
};
