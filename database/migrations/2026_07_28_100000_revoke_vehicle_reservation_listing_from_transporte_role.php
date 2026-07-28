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
        $this->updateTransportRole(
            fn (Role $role, Permission $permission): mixed => $role->revokePermissionTo($permission),
        );
    }

    public function down(): void
    {
        $this->updateTransportRole(
            fn (Role $role, Permission $permission): mixed => $role->givePermissionTo($permission),
        );
    }

    private function updateTransportRole(callable $update): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::query()
            ->where('name', ListaPermissoes::ListarReservasVeiculos->label())
            ->where('guard_name', 'web')
            ->first();

        if ($permission) {
            Role::query()
                ->where('name', 'Transporte')
                ->where('guard_name', 'web')
                ->get()
                ->each(fn (Role $role): mixed => $update($role, $permission));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
