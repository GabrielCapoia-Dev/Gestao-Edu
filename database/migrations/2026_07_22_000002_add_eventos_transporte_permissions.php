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
        $permissionTable = config('permission.table_names.permissions', 'permissions');

        if (! Schema::hasTable($permissionTable)) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissionNames() as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        Role::query()
            ->where('name', 'Admin')
            ->where('guard_name', 'web')
            ->get()
            ->each(fn (Role $role): mixed => $role->givePermissionTo($this->permissionNames()));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionTable = config('permission.table_names.permissions', 'permissions');

        if (! Schema::hasTable($permissionTable)) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $this->permissionNames())
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @return list<string> */
    private function permissionNames(): array
    {
        return array_map(
            static fn (ListaPermissoes $permission): string => $permission->label(),
            [
                ListaPermissoes::ListarEventosTransporte,
                ListaPermissoes::ListarEventosGeral,
                ListaPermissoes::ListarMeusEventos,
                ListaPermissoes::DesativarEventos,
                ListaPermissoes::DesativarEventosTransporte,
                ListaPermissoes::PublicarEventosTransporte,
                ListaPermissoes::RejeitarEventosTransporte,
                ListaPermissoes::GerenciarTransporteDeEventos,
            ],
        );
    }
};
