<?php

namespace Tests\Feature\Users;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AccessRoleSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_configurable_roles_are_created_empty_and_future_command_runs_preserve_configuration(): void
    {
        Artisan::call('permissoes:criar');

        $nomes = [
            'Visitante',
            'RH',
            'Documentação Escolar',
            'Educação Infantil',
            'Psicólogas',
        ];

        foreach ($nomes as $nome) {
            $role = Role::findByName($nome, 'web');
            $this->assertCount(0, $role->permissions, "A role {$nome} deveria nascer vazia.");
        }

        $permission = Permission::findOrCreate('Permissão configurada posteriormente', 'web');
        Role::findByName('Visitante', 'web')->givePermissionTo($permission);

        Artisan::call('permissoes:criar');

        $this->assertTrue(
            Role::findByName('Visitante', 'web')->hasPermissionTo($permission),
        );
    }

    public function test_non_admin_cannot_assign_role_or_permission_above_own_access(): void
    {
        $permissionAplicar = Permission::findOrCreate('Aplicar Permissoes', 'web');
        $permissionEditarRoles = Permission::findOrCreate("Editar N\u{00ED}veis de Acesso", 'web');
        $permissionPermitida = Permission::findOrCreate('Permissão permitida', 'web');
        $permissionElevada = Permission::findOrCreate('Permissão elevada', 'web');
        $permissionEscopoGlobal = Permission::findOrCreate('Acessar Escopo Global de Setores', 'web');

        $operador = User::factory()->create(['ativo' => true, 'email_approved' => true]);
        $operador->givePermissionTo([
            $permissionAplicar,
            $permissionEditarRoles,
            $permissionPermitida,
            $permissionEscopoGlobal,
        ]);

        $roleVazia = Role::query()->create(['name' => 'Role vazia', 'guard_name' => 'web']);
        $roleElevada = Role::query()->create(['name' => 'Role elevada', 'guard_name' => 'web']);
        $roleElevada->givePermissionTo($permissionElevada);

        $opcoes = app(UserService::class)->opcoesDeRolesParaSelect($operador);
        $this->assertArrayHasKey($roleVazia->id, $opcoes);
        $this->assertArrayNotHasKey($roleElevada->id, $opcoes);

        try {
            app(RoleService::class)->sincronizarPermissoes(
                $roleVazia,
                [$permissionElevada->name],
                $operador,
            );
            $this->fail('A permissão superior deveria ser rejeitada.');
        } catch (ValidationException) {
            $this->assertFalse($roleVazia->fresh()->hasPermissionTo($permissionElevada));
        }

        $target = User::factory()->create(['ativo' => true]);

        $this->expectException(ValidationException::class);
        app(UserService::class)->sincronizarPermissoesDiretas(
            $target,
            [$permissionElevada->name],
            operador: $operador,
        );
    }

    public function test_role_creation_rejects_reserved_name_and_rolls_back_invalid_permissions(): void
    {
        $permissionAplicar = Permission::findOrCreate('Aplicar Permissoes', 'web');
        $permissionCriar = Permission::findOrCreate("Criar N\u{00ED}veis de Acesso", 'web');
        $permissionElevada = Permission::findOrCreate('Permissão elevada', 'web');
        $operador = User::factory()->create(['ativo' => true, 'email_approved' => true]);
        $operador->givePermissionTo([$permissionAplicar, $permissionCriar]);

        try {
            app(RoleService::class)->criarRole([
                'name' => 'Admin',
            ], $operador);
            $this->fail('O nome reservado deveria ser rejeitado.');
        } catch (ValidationException) {
            $this->assertDatabaseMissing('roles', ['name' => 'Admin']);
        }

        try {
            app(RoleService::class)->criarRole([
                'name' => 'Role parcial',
                'permissions_Teste' => [$permissionElevada->name],
            ], $operador);
            $this->fail('A permissão forjada deveria ser rejeitada.');
        } catch (ValidationException) {
            $this->assertDatabaseMissing('roles', ['name' => 'Role parcial']);
        }
    }
}
