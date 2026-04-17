<?php

namespace Tests\Feature\Console;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
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

        $this->assertTrue($inventarioRole->hasPermissionTo('Listar Gestão de Inventário'));
        $this->assertTrue($inventarioRole->hasPermissionTo('Aprovar Pedidos de Inventário'));
        $this->assertFalse($inventarioRole->hasPermissionTo('Iniciar Balanços de Estoque'));

        $this->assertTrue($pedidosRole->hasPermissionTo('Editar Pedidos'));
        $this->assertTrue($pedidosRole->hasPermissionTo('Visualizar Histórico de Pedidos'));
        $this->assertFalse($pedidosRole->hasPermissionTo('Aplicar Permissoes'));

        $this->assertTrue($accessRole->hasPermissionTo('Aplicar Permissoes'));
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
}
