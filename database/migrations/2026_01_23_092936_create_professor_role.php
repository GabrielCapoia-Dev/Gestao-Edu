<?php
// database/migrations/2026_01_23_000002_create_professor_role.php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        // Limpa cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Cria role
        $role = Role::firstOrCreate(['name' => 'Professor']);

        // Permissões do professor (ajuste conforme necessário)
        $permissions = [
            'Listar Turmas',
            // Adicione outras permissões específicas
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName]);
        }

        $role->syncPermissions($permissions);
    }

    public function down(): void
    {
        Role::where('name', 'Professor')->delete();
    }
};