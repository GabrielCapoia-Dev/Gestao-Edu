<?php

use App\Models\Enums\ListaPermissoes;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect([
            ListaPermissoes::VisualizarTelaDeInicio,
            ListaPermissoes::ListarAvisos,
            ListaPermissoes::ListarMeusEventos,
            ListaPermissoes::VisualizarAgendaDeTodaARede,
        ])->map(function (ListaPermissoes $permission): Permission {
            return Permission::firstOrCreate([
                'name' => $permission->label(),
                'guard_name' => 'web',
            ]);
        });

        Role::query()
            ->where('guard_name', 'web')
            ->where('name', 'Professor')
            ->get()
            ->each(fn (Role $role): mixed => $role->givePermissionTo($permissions));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect([
            ListaPermissoes::VisualizarTelaDeInicio,
            ListaPermissoes::ListarAvisos,
            ListaPermissoes::ListarMeusEventos,
            ListaPermissoes::VisualizarAgendaDeTodaARede,
        ])->map(fn (ListaPermissoes $permission): string => $permission->label());

        Role::query()
            ->where('guard_name', 'web')
            ->where('name', 'Professor')
            ->get()
            ->each(fn (Role $role): mixed => $role->revokePermissionTo($permissions->all()));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
