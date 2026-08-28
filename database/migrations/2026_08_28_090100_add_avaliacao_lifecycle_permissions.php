<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $concluir = Permission::firstOrCreate(['name' => 'Concluir Avaliações', 'guard_name' => 'web']);
        $reabrir = Permission::firstOrCreate(['name' => 'Reabrir Avaliações', 'guard_name' => 'web']);

        Role::query()
            ->whereIn('name', ['Professor', 'Equipe Gestora', 'Admin'])
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($concluir));

        Role::query()
            ->whereIn('name', ['Equipe Gestora', 'Admin'])
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($reabrir));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::query()->whereIn('name', ['Concluir Avaliações', 'Reabrir Avaliações'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
