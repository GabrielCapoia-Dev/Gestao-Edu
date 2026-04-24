<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'Listar Empresas Inativas']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permission = Permission::query()
            ->where('name', 'Listar Empresas Inativas')
            ->first();

        if ($permission === null) {
            return;
        }

        $permission->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
