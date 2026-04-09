<?php

namespace Database\Seeders;

use App\Models\DominioEmail;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $password = 'Senha@123';

        $adminRole = Role::firstOrCreate([
            'name' => 'Admin',
            'guard_name' => 'web',
        ]);

        $secretarioRole = Role::firstOrCreate([
            'name' => 'Secretário',
            'guard_name' => 'web',
        ]);

        $administrativoRole = Role::firstOrCreate([
            'name' => 'Administrativo',
            'guard_name' => 'web',
        ]);

        Artisan::call('permissoes:criar');

        if ($this->command) {
            $this->command->info(Artisan::output());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->syncRolePermissions($administrativoRole, [
            'Listar Pedidos',
            'Listar Tipo Manutenção',
            'Listar Todos os Pedidos',
            'Criar Tipo Manutenção',
            'Editar Pedidos',
            'Editar Tipo Manutenção',
            'Excluir Tipo Manutenção',
            'Excluir Tipos de Manutenção em Massa',
        ]);

        $adminUser = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'codigo' => 100,
                'name' => 'Admin',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'email_approved' => true,
            ]
        );

        $secretarioUser = User::firstOrCreate(
            ['email' => 'secretario@secretario.com'],
            [
                'codigo' => 101,
                'name' => 'Secretário',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'email_approved' => true,
            ]
        );

        $administrativoUser = User::firstOrCreate(
            ['email' => 'administrativo@administrativo.com'],
            [
                'codigo' => 102,
                'name' => 'Administrativo',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'email_approved' => true,
            ]
        );

        $adminUser->syncRoles([$adminRole]);
        $secretarioUser->syncRoles([$secretarioRole]);
        $administrativoUser->syncRoles([$administrativoRole]);

        $this->seedDominios();

        $this->call([
            SetorSeeder::class,
            TipoStatusSeeder::class,
            TipoManutencaoSeeder::class,
            EmpresaContratadaSeeder::class,
            PedidoSeeder::class,
            ItensSeeder::class,
            ContratoSeeder::class,
            PedidoMerendaSeeder::class,
            EstoqueInventarioOrganicoSeeder::class,
        ]);
    }

    private function syncRolePermissions(Role $role, array $permissions): void
    {
        $permissionNames = array_values(array_unique($permissions));

        foreach ($permissionNames as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        $role->syncPermissions($permissionNames);
    }

    private function seedDominios(): void
    {
        $dominios = [
            ['dominio' => 'gmail.com', 'setor' => 'Geral'],
            ['dominio' => 'edu.umuarama.pr.gov.br', 'setor' => 'Educação'],
            ['dominio' => 'umuarama.pr.gov.br', 'setor' => 'Administrativo'],
        ];

        foreach ($dominios as $item) {
            DominioEmail::firstOrCreate(
                ['dominio_email' => $item['dominio']],
                ['setor' => $item['setor'], 'status' => 1]
            );
        }
    }
}
