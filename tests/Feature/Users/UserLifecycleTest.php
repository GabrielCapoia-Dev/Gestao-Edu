<?php

namespace Tests\Feature\Users;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_inativacao_arquivamento_e_restore_preservam_acessos_e_invalidam_credenciais(): void
    {
        $operador = $this->criarAdministradorDoCicloDeVida();
        $role = Role::query()->create([
            'name' => 'Visitante',
            'guard_name' => 'web',
        ]);
        $permissaoDireta = Permission::findOrCreate('Permissão individual de teste', 'web');
        $usuario = User::factory()->create([
            'email_approved' => true,
            'ativo' => true,
            'remember_token' => 'token-original',
            'last_seen_at' => now(),
        ]);
        $usuario->assignRole($role);
        $usuario->givePermissionTo($permissaoDireta);

        DB::table('sessions')->insert([
            'id' => 'sessao-do-usuario',
            'user_id' => $usuario->id,
            'ip_address' => null,
            'user_agent' => null,
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $service = app(UserService::class);
        $service->inativarUsuario($usuario, $operador);

        $usuario->refresh();
        $this->assertFalse($usuario->ativo);
        $this->assertFalse($usuario->isOperationallyActive());
        $this->assertNotSame('token-original', $usuario->remember_token);
        $this->assertNull($usuario->last_seen_at);
        $this->assertSame(1, $usuario->auth_version);
        $this->assertDatabaseMissing('sessions', ['id' => 'sessao-do-usuario']);
        $this->assertTrue($usuario->hasRole('Visitante'));
        $this->assertTrue($usuario->hasDirectPermission($permissaoDireta));

        $service->arquivarUsuario($usuario, $operador);

        $arquivado = User::withTrashed()->findOrFail($usuario->id);
        $this->assertTrue($arquivado->trashed());
        $this->assertFalse($arquivado->ativo);
        $this->assertTrue($arquivado->hasRole('Visitante'));
        $this->assertTrue($arquivado->hasDirectPermission($permissaoDireta));

        $service->restaurarUsuario($arquivado, $operador);

        $restaurado = User::query()->findOrFail($usuario->id);
        $this->assertFalse($restaurado->trashed());
        $this->assertFalse($restaurado->ativo);
        $this->assertFalse($restaurado->canAccessAdminPanel());
        $this->assertSame(3, $restaurado->auth_version);
        $this->assertTrue($restaurado->hasRole('Visitante'));
        $this->assertTrue($restaurado->hasDirectPermission($permissaoDireta));
    }

    public function test_admin_self_and_root_remain_protected_from_lifecycle_actions(): void
    {
        $operador = $this->criarAdministradorDoCicloDeVida();
        $outroAdmin = User::factory()->create(['ativo' => true]);
        $outroAdmin->assignRole(Role::findByName('Admin', 'web'));

        $service = app(UserService::class);

        $this->assertFalse($service->podeDeletar($operador, $operador));
        $this->assertFalse($service->podeDeletar($operador, $outroAdmin));

        $this->expectException(AuthorizationException::class);
        $service->inativarUsuario($outroAdmin, $operador);
    }

    private function criarAdministradorDoCicloDeVida(): User
    {
        $role = Role::query()->firstOrCreate([
            'name' => 'Admin',
            'guard_name' => 'web',
        ]);
        $editar = Permission::findOrCreate("Editar Usu\u{00E1}rios", 'web');
        $excluir = Permission::findOrCreate("Excluir Usu\u{00E1}rios", 'web');
        $operador = User::factory()->create([
            'email_approved' => true,
            'ativo' => true,
        ]);

        $operador->assignRole($role);
        $operador->givePermissionTo([$editar, $excluir]);

        return $operador;
    }
}
