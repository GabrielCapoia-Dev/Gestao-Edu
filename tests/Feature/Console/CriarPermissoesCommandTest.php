<?php

namespace Tests\Feature\Console;

use App\Models\Enums\ListaPermissoes;
use App\Models\FuncaoAdministrativa;
use App\Models\Role;
use App\Services\PedidoService;
use App\Support\AssessoriaPedagogicaPermissionPreset;
use App\Support\EquipeGestoraPermissionPreset;
use App\Support\ManutencaoPermissionPreset;
use App\Support\ObrasPermissionPreset;
use App\Support\TransportePermissionPreset;
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

        $equipeGestoraRole = Role::findByName('Equipe Gestora', 'web');
        $this->assertTrue($equipeGestoraRole->hasPermissionTo(ListaPermissoes::ListarAvisos->label()));
        $this->assertTrue($equipeGestoraRole->hasPermissionTo(ListaPermissoes::ListarMeusEventos->label()));
        $this->assertTrue($equipeGestoraRole->hasPermissionTo(ListaPermissoes::VisualizarAgendaDeTodaARede->label()));

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

    public function test_it_creates_visitante_with_only_the_home_permission_and_preserves_manual_permissions(): void
    {
        Artisan::call('permissoes:criar');

        $visitante = Role::findByName('Visitante', 'web');

        $this->assertTrue($visitante->hasPermissionTo(ListaPermissoes::VisualizarTelaDeInicio->label()));
        $this->assertFalse($visitante->hasPermissionTo(ListaPermissoes::ListarAvisos->label()));
        $this->assertFalse($visitante->hasPermissionTo(ListaPermissoes::ListarMeusEventos->label()));

        $visitante->givePermissionTo(ListaPermissoes::ListarAvisos->label());

        Artisan::call('permissoes:criar');

        $this->assertTrue(
            Role::findByName('Visitante', 'web')->hasPermissionTo(ListaPermissoes::ListarAvisos->label()),
        );
    }

    public function test_it_synchronizes_transport_and_pedagogical_advisory_roles_with_exact_permissions(): void
    {
        Artisan::call('permissoes:criar');
        Artisan::call('permissoes:criar');

        $transporte = Role::findByName('Transporte', 'web');
        $assessoria = Role::findByName('Assessoria Pedagógica', 'web');

        $this->assertEqualsCanonicalizing(
            TransportePermissionPreset::all(),
            $transporte->permissions()->pluck('name')->all(),
        );
        $this->assertEqualsCanonicalizing(
            AssessoriaPedagogicaPermissionPreset::all(),
            $assessoria->permissions()->pluck('name')->all(),
        );

        $this->assertFalse($transporte->hasPermissionTo(ListaPermissoes::ListarEscolas->label()));
        $this->assertFalse($transporte->hasPermissionTo(ListaPermissoes::ListarTurmas->label()));
        $this->assertFalse($transporte->hasPermissionTo(ListaPermissoes::ListarAlunos->label()));
        $this->assertFalse($transporte->hasPermissionTo(ListaPermissoes::CriarEventos->label()));
        $this->assertFalse($transporte->hasPermissionTo(ListaPermissoes::GerenciarTransporteDeEventos->label()));
        $this->assertFalse($transporte->hasPermissionTo(ListaPermissoes::ListarReservasVeiculos->label()));
        $this->assertFalse($transporte->hasPermissionTo(ListaPermissoes::CriarReservasVeiculos->label()));
        $this->assertFalse($transporte->hasPermissionTo(ListaPermissoes::EditarReservasVeiculos->label()));
        $this->assertFalse($transporte->hasPermissionTo(ListaPermissoes::CancelarReservasVeiculos->label()));

        $this->assertTrue($assessoria->hasPermissionTo(ListaPermissoes::CriarEventos->label()));
        $this->assertTrue($assessoria->hasPermissionTo(ListaPermissoes::CriarEventosTransporte->label()));
        $this->assertTrue($assessoria->hasPermissionTo(ListaPermissoes::ListarReservasVeiculos->label()));
        $this->assertTrue($assessoria->hasPermissionTo(ListaPermissoes::CriarReservasVeiculos->label()));
        $this->assertTrue($assessoria->hasPermissionTo(ListaPermissoes::EditarReservasVeiculos->label()));
        $this->assertTrue($assessoria->hasPermissionTo(ListaPermissoes::CancelarReservasVeiculos->label()));
        $this->assertTrue($assessoria->hasPermissionTo(ListaPermissoes::GerenciarFrotaVeiculos->label()));
        $this->assertTrue($assessoria->hasPermissionTo(ListaPermissoes::EditarEventos->label()));
        $this->assertTrue($assessoria->hasPermissionTo(ListaPermissoes::PublicarEventos->label()));
        $this->assertTrue($assessoria->hasPermissionTo(ListaPermissoes::DesativarEventos->label()));
        $this->assertTrue($transporte->hasPermissionTo(ListaPermissoes::VisualizarAgendaDeTodaARede->label()));
        $this->assertTrue($assessoria->hasPermissionTo(ListaPermissoes::VisualizarAgendaDeTodaARede->label()));
        $this->assertFalse($assessoria->hasPermissionTo(ListaPermissoes::EditarAlunos->label()));
        $this->assertFalse($assessoria->hasPermissionTo(ListaPermissoes::ResponderAvaliacoes->label()));
        $this->assertFalse($assessoria->hasPermissionTo(ListaPermissoes::PublicarEventosTransporte->label()));
        $this->assertFalse($assessoria->hasPermissionTo(ListaPermissoes::DesativarEventosTransporte->label()));
        $this->assertFalse($assessoria->hasPermissionTo(ListaPermissoes::GerenciarTransporteDeEventos->label()));
    }

    public function test_it_creates_transport_role_function_and_exact_permissions(): void
    {
        Artisan::call('permissoes:criar');
        Artisan::call('permissoes:criar');

        $role = Role::findByName('Transporte', 'web');
        $funcao = FuncaoAdministrativa::query()->where('codigo', 'transporte')->firstOrFail();

        $this->assertEqualsCanonicalizing(
            TransportePermissionPreset::all(),
            $role->permissions()->pluck('name')->all(),
        );
        $this->assertFalse($role->hasPermissionTo(ListaPermissoes::CriarEventos->label()));
        $this->assertFalse($role->hasPermissionTo(ListaPermissoes::ListarAlunos->label()));
        $this->assertDatabaseHas('funcao_administrativa_role', [
            'funcao_administrativa_id' => $funcao->id,
            'role_id' => $role->id,
        ]);
        $this->assertTrue($funcao->concedeAcessoSistema());
        $this->assertFalse($funcao->exige_professor);
        $this->assertFalse($funcao->tem_relacao_turma);
    }

    public function test_it_creates_assessoria_pedagogica_role_function_and_exact_permissions(): void
    {
        Artisan::call('permissoes:criar');
        Artisan::call('permissoes:criar');

        $role = Role::findByName('Assessoria Pedagógica', 'web');
        $funcao = FuncaoAdministrativa::query()->where('codigo', 'assessoria-pedagogica')->firstOrFail();

        $this->assertEqualsCanonicalizing(
            AssessoriaPedagogicaPermissionPreset::all(),
            $role->permissions()->pluck('name')->all(),
        );
        $this->assertTrue($role->hasPermissionTo(ListaPermissoes::CriarEventos->label()));
        $this->assertTrue($role->hasPermissionTo(ListaPermissoes::EditarEventos->label()));
        $this->assertTrue($role->hasPermissionTo(ListaPermissoes::PublicarEventos->label()));
        $this->assertTrue($role->hasPermissionTo(ListaPermissoes::ListarMeusEventos->label()));
        $this->assertFalse($role->hasPermissionTo(ListaPermissoes::EditarAlunos->label()));
        $this->assertFalse($role->hasPermissionTo(ListaPermissoes::PublicarEventosTransporte->label()));
        $this->assertDatabaseHas('funcao_administrativa_role', [
            'funcao_administrativa_id' => $funcao->id,
            'role_id' => $role->id,
        ]);
        $this->assertTrue($funcao->concedeAcessoSistema());
        $this->assertFalse($funcao->exige_professor);
    }

    public function test_it_creates_additional_request_notification_permission(): void
    {
        Artisan::call('permissoes:criar');

        $this->assertDatabaseHas('permissions', [
            'name' => PedidoService::PERMISSAO_NOTIFICAR_PEDIDO_ADICIONAL_CRIADO,
            'guard_name' => 'web',
        ]);
    }

    public function test_it_creates_password_reset_permission_and_assigns_it_to_admin(): void
    {
        Artisan::call('permissoes:criar');

        $this->assertDatabaseHas('permissions', [
            'name' => 'Redefinir Senhas de Usuários',
            'guard_name' => 'web',
        ]);
        $this->assertTrue(
            Role::findByName('Admin', 'web')->hasPermissionTo('Redefinir Senhas de Usuários'),
        );
    }

    public function test_it_creates_maintenance_role_function_and_exact_permissions(): void
    {
        Artisan::call('permissoes:criar');

        $role = Role::findByName('Manutenção', 'web');
        $funcao = FuncaoAdministrativa::query()->where('codigo', 'manutencao')->firstOrFail();

        $this->assertEqualsCanonicalizing(
            ManutencaoPermissionPreset::all(),
            $role->permissions()->pluck('name')->all(),
        );
        $this->assertFalse($role->hasPermissionTo('Editar Tipos de Avaliações'));
        $this->assertFalse($role->hasPermissionTo('Criar Pedidos'));
        $this->assertFalse($role->hasPermissionTo('Excluir Pedidos'));
        $this->assertFalse($role->hasPermissionTo('Enviar Pedidos para Empresa'));
        $this->assertTrue($role->hasPermissionTo(ListaPermissoes::VisualizarAgendaDeTodaARede->label()));
        $this->assertTrue($role->hasPermissionTo(ListaPermissoes::ListarReservasVeiculos->label()));
        $this->assertTrue($role->hasPermissionTo(ListaPermissoes::CriarReservasVeiculos->label()));
        $this->assertTrue($role->hasPermissionTo(ListaPermissoes::EditarReservasVeiculos->label()));
        $this->assertTrue($role->hasPermissionTo(ListaPermissoes::CancelarReservasVeiculos->label()));
        $this->assertFalse($role->hasPermissionTo(ListaPermissoes::GerenciarFrotaVeiculos->label()));
        $this->assertDatabaseHas('funcao_administrativa_role', [
            'funcao_administrativa_id' => $funcao->id,
            'role_id' => $role->id,
        ]);
        $this->assertTrue($funcao->concedeAcessoSistema());
        $this->assertFalse($funcao->exige_professor);
        $this->assertFalse($funcao->tem_relacao_turma);
    }

    public function test_it_creates_obras_role_function_and_exact_scoped_permissions(): void
    {
        Artisan::call('permissoes:criar');
        Artisan::call('permissoes:criar');

        $role = Role::findByName('Obras', 'web');
        $funcao = FuncaoAdministrativa::query()->where('codigo', 'obras')->firstOrFail();

        $this->assertEqualsCanonicalizing(
            ObrasPermissionPreset::all(),
            $role->permissions()->pluck('name')->all(),
        );
        $this->assertFalse($role->hasPermissionTo('Listar Todos os Pedidos'));
        $this->assertFalse($role->hasPermissionTo('Editar Tipos de Avaliações'));
        $this->assertFalse($role->hasPermissionTo('Acessar Escopo Global de Setores'));
        $this->assertTrue($role->hasPermissionTo('Enviar Pedidos para Empresa'));
        $this->assertSame(1, $funcao->rolesPadrao()->whereKey($role->id)->count());
        $this->assertSame(1, FuncaoAdministrativa::query()->where('codigo', 'obras')->count());
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

    public function test_it_assigns_network_calendar_permission_to_authorized_roles(): void
    {
        Artisan::call('permissoes:criar');

        $permission = ListaPermissoes::VisualizarAgendaDeTodaARede->label();

        $this->assertDatabaseHas('permissions', [
            'name' => $permission,
            'guard_name' => 'web',
        ]);
        $this->assertTrue(Role::findByName('Admin', 'web')->hasPermissionTo($permission));
        $this->assertTrue(Role::findByName('Equipe Gestora', 'web')->hasPermissionTo($permission));
        $this->assertTrue(Role::findByName('Manutenção', 'web')->hasPermissionTo($permission));
        $this->assertTrue(Role::findByName('Transporte', 'web')->hasPermissionTo($permission));
        $this->assertTrue(Role::findByName('Assessoria Pedagógica', 'web')->hasPermissionTo($permission));
        $this->assertContains($permission, EquipeGestoraPermissionPreset::all());
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
