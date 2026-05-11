<?php

use App\Services\UserPresenceService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('email_verified_at')->index();
            }

            if (! Schema::hasColumn('users', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable()->after('last_login_at')->index();
            }
        });

        if (Schema::hasTable('permissions')) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $permission = Permission::firstOrCreate([
                'name' => UserPresenceService::PERMISSION,
                'guard_name' => 'web',
            ]);

            Role::query()
                ->where('name', 'Admin')
                ->where('guard_name', 'web')
                ->get()
                ->each(fn (Role $role): mixed => $role->givePermissionTo($permission));

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            Permission::query()
                ->where('name', UserPresenceService::PERMISSION)
                ->where('guard_name', 'web')
                ->first()
                ?->delete();

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'last_seen_at')) {
                $table->dropIndex(['last_seen_at']);
                $table->dropColumn('last_seen_at');
            }

            if (Schema::hasColumn('users', 'last_login_at')) {
                $table->dropIndex(['last_login_at']);
                $table->dropColumn('last_login_at');
            }
        });
    }
};
