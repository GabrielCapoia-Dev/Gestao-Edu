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

        $permission = Permission::firstOrCreate([
            'name' => $this->permissionName(),
            'guard_name' => 'web',
        ]);

        Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $this->roleNames())
            ->get()
            ->each(fn (Role $role): mixed => $role->givePermissionTo($permission));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::query()
            ->where('name', $this->permissionName())
            ->where('guard_name', 'web')
            ->first();

        if ($permission) {
            Role::query()
                ->where('guard_name', 'web')
                ->whereIn('name', $this->roleNames())
                ->get()
                ->each(fn (Role $role): mixed => $role->revokePermissionTo($permission));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @return list<string> */
    private function roleNames(): array
    {
        return [
            'Equipe Gestora',
            'Manutenção',
        ];
    }

    private function permissionName(): string
    {
        return ListaPermissoes::VisualizarAgendaDeTodaARede->label();
    }
};
