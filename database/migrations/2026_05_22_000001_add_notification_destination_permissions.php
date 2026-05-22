<?php

use App\Services\NotificationCenterService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionNames = array_values(NotificationCenterService::DESTINATION_PERMISSIONS);

        collect($permissionNames)
            ->each(fn (string $permission): Permission => Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]));

        Role::query()
            ->where('name', 'Admin')
            ->where('guard_name', 'web')
            ->get()
            ->each(fn (Role $role): mixed => $role->givePermissionTo($permissionNames));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::query()
            ->whereIn('name', array_values(NotificationCenterService::DESTINATION_PERMISSIONS))
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
