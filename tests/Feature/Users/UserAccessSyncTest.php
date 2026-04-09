<?php

namespace Tests\Feature\Users;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserAccessSyncTest extends TestCase
{
    use RefreshDatabase;

    protected UserService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(UserService::class);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_it_syncs_multiple_roles_and_only_persists_non_inherited_direct_permissions(): void
    {
        $permissionInventario = Permission::create([
            'name' => 'Gerenciar Inventario',
            'guard_name' => 'web',
        ]);

        $permissionEstoque = Permission::create([
            'name' => 'Gerenciar Estoque',
            'guard_name' => 'web',
        ]);

        $permissionPainel = Permission::create([
            'name' => 'Visualizar Painel Especial',
            'guard_name' => 'web',
        ]);

        $roleInventario = Role::create([
            'name' => 'Inventario',
            'guard_name' => 'web',
        ]);

        $roleEstoque = Role::create([
            'name' => 'Estoque',
            'guard_name' => 'web',
        ]);

        $roleInventario->givePermissionTo($permissionInventario);
        $roleEstoque->givePermissionTo($permissionEstoque);

        $user = User::factory()->create();

        $this->service->sincronizarAcessosDoUsuario($user, [
            'roles' => [$roleInventario->id, $roleEstoque->id],
            'usar_permissoes_extras' => true,
            'permissions_Gerenciar' => [
                $permissionInventario->name,
                $permissionEstoque->name,
            ],
            'permissions_Visualizar' => [
                $permissionPainel->name,
            ],
        ]);

        $user->refresh();

        $this->assertEqualsCanonicalizing(
            ['Inventario', 'Estoque'],
            $user->roles->pluck('name')->all()
        );
        $this->assertTrue($user->hasPermissionTo($permissionInventario->name));
        $this->assertTrue($user->hasPermissionTo($permissionEstoque->name));
        $this->assertSame(
            [$permissionPainel->name],
            $user->getDirectPermissions()->pluck('name')->all()
        );
    }

    public function test_it_preserves_existing_access_when_role_and_permission_fields_are_not_sent(): void
    {
        $permissionPainel = Permission::create([
            'name' => 'Visualizar Painel Especial',
            'guard_name' => 'web',
        ]);

        $roleInventario = Role::create([
            'name' => 'Inventario',
            'guard_name' => 'web',
        ]);

        $user = User::factory()->create();
        $user->syncRoles([$roleInventario]);
        $user->givePermissionTo($permissionPainel);

        $this->service->sincronizarAcessosDoUsuario($user, []);

        $user->refresh();

        $this->assertSame(['Inventario'], $user->roles->pluck('name')->all());
        $this->assertSame(
            [$permissionPainel->name],
            $user->getDirectPermissions()->pluck('name')->all()
        );
    }
}
