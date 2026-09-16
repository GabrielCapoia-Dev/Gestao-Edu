<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate([
            'name' => 'Preencher Avaliações em Massa',
            'guard_name' => 'web',
        ]);

        foreach (['Professor', 'Equipe Gestora'] as $roleName) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $role->givePermissionTo($permission);
        }
    }

    public function down(): void
    {
        $permission = Permission::query()
            ->where('name', 'Preencher Avaliações em Massa')
            ->where('guard_name', 'web')
            ->first();

        if (! $permission) {
            return;
        }

        Role::query()
            ->whereIn('name', ['Professor', 'Equipe Gestora'])
            ->where('guard_name', 'web')
            ->get()
            ->each(fn (Role $role): bool => $role->revokePermissionTo($permission));
    }
};
