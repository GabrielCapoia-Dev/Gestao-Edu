<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        $dashboardPermissionId = DB::table('permissions')
            ->where('name', 'Visualizar Tela de Inicio')
            ->where('guard_name', 'web')
            ->value('id');

        if ($dashboardPermissionId && Schema::hasTable('role_has_permissions')) {
            $roleIds = DB::table('roles')
                ->where('guard_name', 'web')
                ->whereIn('name', [
                    'Admin',
                    'Professor',
                    'Visualizar Turmas e Alunos',
                    'Equipe Gestora',
                    'Secretário',
                    'Secretario',
                    'Manutenção',
                    'Obras',
                    'Transporte',
                    'Assessoria Pedagógica',
                ])
                ->pluck('id');

            foreach ($roleIds as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $dashboardPermissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        $permissionIds = DB::table('permissions')
            ->where('name', 'Acessar Painel')
            ->where('guard_name', 'web')
            ->pluck('id');
        $roleIds = DB::table('roles')
            ->where('name', 'Acessar Painel')
            ->where('guard_name', 'web')
            ->pluck('id');

        if (Schema::hasTable('model_has_permissions') && $permissionIds->isNotEmpty()) {
            DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        }
        if (Schema::hasTable('role_has_permissions') && $permissionIds->isNotEmpty()) {
            DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        }
        if (Schema::hasTable('model_has_roles') && $roleIds->isNotEmpty()) {
            DB::table('model_has_roles')->whereIn('role_id', $roleIds)->delete();
        }

        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        DB::table('roles')->whereIn('id', $roleIds)->delete();
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        DB::table('permissions')->insertOrIgnore([
            'name' => 'Acessar Painel',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('roles')->insertOrIgnore([
            'name' => 'Acessar Painel',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
