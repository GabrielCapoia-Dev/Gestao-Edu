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
        if (! Schema::hasTable('permissions')) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = $this->permissionNames();

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        Role::query()
            ->where('name', 'Admin')
            ->where('guard_name', 'web')
            ->get()
            ->each(fn (Role $role): mixed => $role->givePermissionTo($permissions));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
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
                ListaPermissoes::ListarAvisos,
                ListaPermissoes::CriarAvisos,
                ListaPermissoes::EditarAvisos,
                ListaPermissoes::ExcluirAvisos,
                ListaPermissoes::PublicarAvisos,
                ListaPermissoes::GerenciarPublicoAlvoDeAvisos,
                ListaPermissoes::ListarEventos,
                ListaPermissoes::CriarEventos,
                ListaPermissoes::EditarEventos,
                ListaPermissoes::ExcluirEventos,
                ListaPermissoes::PublicarEventos,
                ListaPermissoes::GerenciarPublicoAlvoDeEventos,
                ListaPermissoes::ImportarEventosPorPlanilha,
                ListaPermissoes::ExportarModeloDeImportacaoDeEventos,
            ],
        );
    }
};
