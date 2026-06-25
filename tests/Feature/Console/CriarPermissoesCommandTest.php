<?php

namespace Tests\Feature\Console;

use App\Models\Role;
use App\Services\PedidoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CriarPermissoesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_it_creates_contextual_access_levels(): void
    {
        Artisan::call('permissoes:criar');

        $inventarioRole = Role::findByName('Gestao de Inventario', 'web');
        $pedidosRole = Role::findByName('Gestao de Pedidos', 'web');
        $accessRole = Role::findByName('Gestao de Usuarios e Acessos', 'web');
        $pedagogicaRole = Role::findByName('Gestao Pedagogica', 'web');
        $painelRole = Role::findByName('Acessar Painel', 'web');
        $professorViewRole = Role::findByName('Visualizar Turmas e Alunos', 'web');
        $turmasRole = Role::findByName('Pedagógico: Gerenciar Turmas', 'web');

        $this->assertTrue($inventarioRole->hasPermissionTo('Listar Gestão de Inventário'));
        $this->assertTrue($inventarioRole->hasPermissionTo('Aprovar Pedidos de Inventário'));
        $this->assertFalse($inventarioRole->hasPermissionTo('Iniciar Balanços de Estoque'));

        $this->assertTrue($pedidosRole->hasPermissionTo('Editar Pedidos'));
        $this->assertTrue($pedidosRole->hasPermissionTo('Visualizar Histórico de Pedidos'));
        $this->assertFalse($pedidosRole->hasPermissionTo('Aplicar Permissoes'));

        $this->assertTrue($accessRole->hasPermissionTo('Aplicar Permissoes'));
        $this->assertTrue($accessRole->hasPermissionTo('Visualizar Usuarios Online'));
        $this->assertTrue($accessRole->hasPermissionTo('Editar Usuários'));
        $this->assertFalse($accessRole->hasPermissionTo('Editar Pedidos'));

        $this->assertTrue($pedagogicaRole->hasPermissionTo('Criar Avaliações'));
        $this->assertTrue($pedagogicaRole->hasPermissionTo('Editar Pautas'));
        $this->assertFalse($pedagogicaRole->hasPermissionTo('Aplicar Permissoes'));

        $this->assertTrue($painelRole->hasPermissionTo('Visualizar Tela de Inicio'));
        $this->assertFalse($painelRole->hasPermissionTo('Listar Turmas'));

        $this->assertTrue($professorViewRole->hasPermissionTo('Listar Turmas'));
        $this->assertTrue($professorViewRole->hasPermissionTo('Listar Alunos'));
        $this->assertTrue($professorViewRole->hasPermissionTo('Responder Avaliações'));
        $this->assertFalse($professorViewRole->hasPermissionTo('Editar Turmas'));

        $this->assertTrue($turmasRole->hasPermissionTo('Listar Turmas'));
        $this->assertTrue($turmasRole->hasPermissionTo('Criar Turmas'));
        $this->assertTrue($turmasRole->hasPermissionTo('Editar Turmas'));
        $this->assertTrue($turmasRole->hasPermissionTo('Editar Dados da Turma'));
        $this->assertTrue($turmasRole->hasPermissionTo('Filtrar Turmas por Escola'));
        $this->assertFalse($turmasRole->hasPermissionTo('Listar Pedidos'));
    }

    public function test_it_creates_additional_request_notification_permission(): void
    {
        Artisan::call('permissoes:criar');

        $this->assertDatabaseHas('permissions', [
            'name' => PedidoService::PERMISSAO_NOTIFICAR_PEDIDO_ADICIONAL_CRIADO,
            'guard_name' => 'web',
        ]);
    }

    public function test_it_creates_acompanhamento_avaliacoes_permission_and_assigns_to_admin(): void
    {
        Artisan::call('permissoes:criar');

        $this->assertDatabaseHas('permissions', [
            'name' => 'Acompanhar Avaliações',
            'guard_name' => 'web',
        ]);

        $admin = Role::findByName('Admin', 'web');

        $this->assertTrue($admin->hasPermissionTo('Acompanhar Avaliações'));
    }

    public function test_it_migrates_legacy_equipe_gestora_permissions_to_servidores(): void
    {
        $listar = Permission::findOrCreate('Listar Equipe Gestora', 'web');
        $editar = Permission::findOrCreate('Editar Equipe Gestora', 'web');
        $excluir = Permission::findOrCreate('Excluir Equipe Gestora', 'web');
        $excluirEmMassa = Permission::findOrCreate('Excluir Equipe Gestora em Massa', 'web');

        $role = Role::query()->create([
            'name' => 'Equipe Gestora Legado',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo([$listar, $editar, $excluir, $excluirEmMassa]);

        Artisan::call('permissoes:criar');

        $role = Role::findByName('Equipe Gestora Legado', 'web');

        $this->assertDatabaseMissing('permissions', ['name' => 'Listar Equipe Gestora']);
        $this->assertDatabaseMissing('permissions', ['name' => 'Editar Equipe Gestora']);
        $this->assertDatabaseMissing('permissions', ['name' => 'Excluir Equipe Gestora']);
        $this->assertDatabaseMissing('permissions', ['name' => 'Excluir Equipe Gestora em Massa']);
        $this->assertTrue($role->hasPermissionTo('Listar Servidores'));
        $this->assertTrue($role->hasPermissionTo('Editar Servidores'));
        $this->assertTrue($role->hasPermissionTo('Gerenciar Funções de Servidores'));
        $this->assertFalse($role->hasPermissionTo('Excluir Servidores'));
    }

    public function test_it_normalizes_legacy_mojibake_permission_and_role_names(): void
    {
        $legacyPermissionName = $this->mojibake('Visualizar Notificações');
        $legacyRoleName = $this->mojibake('Secretário');

        $permission = Permission::query()->create([
            'name' => $legacyPermissionName,
            'guard_name' => 'web',
        ]);

        $role = Role::query()->create([
            'name' => $legacyRoleName,
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo($permission);

        Artisan::call('permissoes:criar');

        $this->assertDatabaseMissing('permissions', [
            'name' => $legacyPermissionName,
            'guard_name' => 'web',
        ]);

        $this->assertDatabaseMissing('roles', [
            'name' => $legacyRoleName,
            'guard_name' => 'web',
        ]);

        $this->assertDatabaseHas('permissions', [
            'name' => 'Visualizar Notificações',
            'guard_name' => 'web',
        ]);

        $secretario = Role::findByName('Secretário', 'web');

        $this->assertTrue($secretario->hasPermissionTo('Visualizar Notificações'));
    }

    public function test_it_keeps_legacy_default_roles_available(): void
    {
        Artisan::call('permissoes:criar');

        $this->assertDatabaseHas('roles', [
            'name' => 'Admin',
            'guard_name' => 'web',
        ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'Secretário',
            'guard_name' => 'web',
        ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'Administrativo',
            'guard_name' => 'web',
        ]);
    }

    private function mojibake(string $value): string
    {
        return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
    }
}
