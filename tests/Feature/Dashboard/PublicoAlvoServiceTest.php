<?php

namespace Tests\Feature\Dashboard;

use App\Models\Enums\ListaPermissoes;
use App\Models\Enums\PublicoAlvoModoCorrespondencia;
use App\Models\Escola;
use App\Models\FuncaoAdministrativa;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\Setor;
use App\Models\User;
use App\Services\Dashboard\DashboardUserContextFactory;
use App\Services\Dashboard\PublicoAlvoOptionsService;
use App\Services\Dashboard\PublicoAlvoService;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PublicoAlvoServiceTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication(): Application
    {
        putenv('PERMISSION_CACHE_STORE=array');
        $_ENV['PERMISSION_CACHE_STORE'] = 'array';
        $_SERVER['PERMISSION_CACHE_STORE'] = 'array';

        return parent::createApplication();
    }

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_correspondencia_combina_ou_dentro_da_categoria_e_qualquer_ou_todos_entre_categorias(): void
    {
        $service = app(PublicoAlvoService::class);
        $admin = $this->criarAdmin();
        $destinatario = User::factory()->create();
        $roleCorreta = Role::findOrCreate('Equipe de Teste', 'web');
        $roleOutra = Role::findOrCreate('Outra Equipe', 'web');
        $permissao = Permission::findOrCreate('Permissão Herdada de Teste', 'web');
        $roleCorreta->givePermissionTo($permissao);
        $destinatario->assignRole($roleCorreta);

        $qualquer = $service->criar($admin, [
            'modo_correspondencia' => PublicoAlvoModoCorrespondencia::Qualquer,
            'todos_usuarios' => false,
            'roles_ids' => [$roleOutra->id, $roleCorreta->id],
        ]);
        $todos = $service->criar($admin, [
            'modo_correspondencia' => PublicoAlvoModoCorrespondencia::Todos,
            'todos_usuarios' => false,
            'roles_ids' => [$roleOutra->id, $roleCorreta->id],
            'permissoes_ids' => [$permissao->id],
        ]);
        $funcaoSemVinculo = $this->criarFuncao('Função sem vínculo');
        $todosSemCargo = $service->criar($admin, [
            'modo_correspondencia' => PublicoAlvoModoCorrespondencia::Todos,
            'todos_usuarios' => false,
            'roles_ids' => [$roleCorreta->id],
            'funcoes_administrativas_ids' => [$funcaoSemVinculo->id],
        ]);

        $this->assertTrue($service->corresponde($qualquer, $destinatario));
        $this->assertTrue($service->corresponde($todos, $destinatario));
        $this->assertFalse($service->corresponde($todosSemCargo, $destinatario));
        $this->assertEqualsCanonicalizing(
            [$qualquer->id, $todos->id],
            $service->queryPara($destinatario)->pluck('id')->all(),
        );
    }

    public function test_role_nao_e_tratada_como_cargo_e_permissao_de_role_e_considerada(): void
    {
        $service = app(PublicoAlvoService::class);
        $admin = $this->criarAdmin();
        $funcao = $this->criarFuncao('Direção de Teste');
        $roleMesmoNome = Role::findOrCreate($funcao->nome, 'web');
        $permissao = Permission::findOrCreate('Permissão via nível de acesso', 'web');
        $roleMesmoNome->givePermissionTo($permissao);
        $apenasRole = User::factory()->create();
        $apenasRole->assignRole($roleMesmoNome);
        [$comCargo] = $this->criarUsuarioVinculado(
            nome: 'Usuário com cargo',
            funcao: $funcao,
        );

        $porCargo = $service->criar($admin, [
            'todos_usuarios' => false,
            'funcoes_administrativas_ids' => [$funcao->id],
        ]);
        $porPermissao = $service->criar($admin, [
            'todos_usuarios' => false,
            'permissoes_ids' => [$permissao->id],
        ]);

        $this->assertFalse($service->corresponde($porCargo, $apenasRole));
        $this->assertTrue($service->corresponde($porCargo, $comCargo));
        $this->assertTrue($service->corresponde($porPermissao, $apenasRole));
        $this->assertFalse($service->corresponde($porPermissao, $comCargo));
    }

    public function test_envelope_com_escola_prevalece_sobre_setor_compartilhado(): void
    {
        $service = app(PublicoAlvoService::class);
        $setorCompartilhado = $this->criarSetor('Setor compartilhado');
        $escolaA = $this->criarEscola('Escola A', $setorCompartilhado);
        $escolaB = $this->criarEscola('Escola B', $setorCompartilhado);
        $funcao = $this->criarFuncao('Função escolar');
        [$atorA] = $this->criarUsuarioVinculado('Autor A', $funcao, $escolaA, $setorCompartilhado);
        [$usuarioA] = $this->criarUsuarioVinculado('Destino A', $funcao, $escolaA, $setorCompartilhado);
        [$usuarioB] = $this->criarUsuarioVinculado('Destino B', $funcao, $escolaB, $setorCompartilhado);

        $publico = $service->criar($atorA, ['todos_usuarios' => true]);

        $this->assertSame([$escolaA->id], $publico->escopoEscolas->modelKeys());
        $this->assertContains($setorCompartilhado->id, $publico->escopoSetores->modelKeys());
        $this->assertTrue($service->corresponde($publico, $usuarioA));
        $this->assertFalse($service->corresponde($publico, $usuarioB));
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->assertTrue($service->podeGerenciar($publico, $atorA));
        $this->assertCount(0, DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertFalse($service->podeGerenciar($publico, $usuarioB));
        $this->assertTrue($service->podeGerenciar($publico, $this->criarAdmin()));

        $duplicado = $service->duplicar($publico, $atorA);
        $this->assertNotSame($publico->id, $duplicado->id);
        $this->assertTrue($duplicado->todos_usuarios);
        $this->assertSame([$escolaA->id], $duplicado->escopoEscolas->modelKeys());

        try {
            $service->criar($atorA, [
                'todos_usuarios' => false,
                'usuarios_ids' => [$usuarioB->id],
            ]);
            $this->fail('O usuário de outra escola não poderia ser selecionado pelo setor compartilhado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('usuarios_ids', $exception->errors());
        }
    }

    public function test_setor_e_usado_como_fallback_quando_envelope_nao_possui_escola(): void
    {
        $service = app(PublicoAlvoService::class);
        $setorA = $this->criarSetor('Setor A');
        $setorB = $this->criarSetor('Setor B');
        $funcao = $this->criarFuncao('Função setorial');
        [$ator] = $this->criarUsuarioVinculado('Autor setorial', $funcao, null, $setorA);
        $mesmoSetor = User::factory()->create(['setor_id' => $setorA->id]);
        $outroSetor = User::factory()->create(['setor_id' => $setorB->id]);

        $publico = $service->criar($ator, ['todos_usuarios' => true]);

        $this->assertSame([], $publico->escopoEscolas->modelKeys());
        $this->assertTrue($service->corresponde($publico, $mesmoSetor));
        $this->assertFalse($service->corresponde($publico, $outroSetor));
    }

    public function test_acesso_global_nao_equivale_a_pertencer_a_todas_as_escolas(): void
    {
        $service = app(PublicoAlvoService::class);
        $admin = $this->criarAdmin();
        $setor = $this->criarSetor('Setor escolar');
        $escola = $this->criarEscola('Escola segmentada', $setor);
        $funcao = $this->criarFuncao('Função da escola');
        [$usuarioEscola] = $this->criarUsuarioVinculado('Usuário escolar', $funcao, $escola, $setor);

        $publico = $service->criar($admin, [
            'todos_usuarios' => false,
            'escolas_ids' => [$escola->id],
        ]);

        $this->assertTrue($service->corresponde($publico, $usuarioEscola));
        $this->assertFalse($service->corresponde($publico, $admin));
    }

    public function test_opcoes_reutilizaveis_respeitam_o_escopo_do_autor(): void
    {
        $options = app(PublicoAlvoOptionsService::class);
        $setor = $this->criarSetor('Setor das opções');
        $escolaA = $this->criarEscola('Escola Opções A', $setor);
        $escolaB = $this->criarEscola('Escola Opções B', $setor);
        $funcao = $this->criarFuncao('Função das opções');
        [$ator] = $this->criarUsuarioVinculado('Autor das opções', $funcao, $escolaA, $setor);
        [$usuarioA] = $this->criarUsuarioVinculado('Alice permitida', $funcao, $escolaA, $setor);
        [$usuarioB] = $this->criarUsuarioVinculado('Alice bloqueada', $funcao, $escolaB, $setor);
        $role = Role::findOrCreate('Role das opções', 'web');
        $permissao = Permission::findOrCreate('Permissão das opções', 'web');

        $contextFactory = app(DashboardUserContextFactory::class);
        $contextoAtor = $contextFactory->make($ator);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $usuarios = $options->buscarUsuarios($ator, 'Alice');
        $this->assertCount(1, DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertArrayHasKey($usuarioA->id, $usuarios);
        $this->assertArrayNotHasKey($usuarioB->id, $usuarios);
        $this->assertSame([$escolaA->id], array_keys($options->escolas($ator)));
        $this->assertArrayHasKey($setor->id, $options->setores($ator));
        $this->assertArrayHasKey($role->id, $options->roles());
        $this->assertArrayHasKey($permissao->id, $options->permissoes());
        $this->assertArrayHasKey($funcao->id, $options->funcoesAdministrativas());
        $this->assertArrayHasKey(
            $usuarioA->id,
            $options->rotulosUsuarios($ator, [$usuarioA->id, $usuarioB->id]),
        );
        $this->assertArrayNotHasKey(
            $usuarioB->id,
            $options->rotulosUsuarios($ator, [$usuarioA->id, $usuarioB->id]),
        );
        $this->assertSame($contextoAtor, $contextFactory->make($ator));
        $this->assertNotSame($contextoAtor, $contextFactory->make($usuarioA));
    }

    public function test_catalogo_e_comando_criam_as_permissoes_do_dashboard_para_admin(): void
    {
        $esperadas = [
            ListaPermissoes::ListarAvisos,
            ListaPermissoes::CriarAvisos,
            ListaPermissoes::EditarAvisos,
            ListaPermissoes::ExcluirAvisos,
            ListaPermissoes::PublicarAvisos,
            ListaPermissoes::GerenciarPublicoAlvoDeAvisos,
            ListaPermissoes::ListarEventos,
            ListaPermissoes::CriarEventos,
            ListaPermissoes::EditarEventos,
            ListaPermissoes::ExcluirEventos,
            ListaPermissoes::PublicarEventos,
            ListaPermissoes::GerenciarPublicoAlvoDeEventos,
            ListaPermissoes::ImportarEventosPorPlanilha,
            ListaPermissoes::ExportarModeloDeImportacaoDeEventos,
        ];

        $this->artisan('permissoes:criar')->assertSuccessful();
        $admin = Role::query()->where('name', 'Admin')->firstOrFail();

        foreach ($esperadas as $permissao) {
            $this->assertDatabaseHas('permissions', [
                'name' => $permissao->label(),
                'guard_name' => 'web',
            ]);
            $this->assertTrue($admin->hasPermissionTo($permissao->label()));
        }
    }

    private function criarAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('Admin', 'web'));

        return $admin;
    }

    private function criarSetor(string $nome): Setor
    {
        return Setor::query()->create([
            'nome' => $nome,
            'ativo' => true,
            'status' => 'ativo',
            'contexto' => 'administrativo',
            'exige_vinculo_escola' => false,
        ]);
    }

    private function criarEscola(string $nome, Setor $setor): Escola
    {
        return Escola::query()->create([
            'codigo' => strtoupper(substr(md5($nome), 0, 8)),
            'nome' => $nome,
            'setor_id' => $setor->id,
            'email' => strtolower(str_replace(' ', '.', $nome)).'@teste.local',
            'ativo' => true,
        ]);
    }

    private function criarFuncao(string $nome): FuncaoAdministrativa
    {
        return FuncaoAdministrativa::query()->create([
            'codigo' => str($nome)->ascii()->slug()->toString(),
            'nome' => $nome,
            'categoria' => FuncaoAdministrativa::CATEGORIA_ADMINISTRATIVO,
            'ativo' => true,
            'exige_professor' => false,
            'concede_acesso_sistema' => true,
            'tem_relacao_turma' => false,
            'direcao_escolar' => false,
            'coordenacao_pedagogica' => false,
            'secretaria_escolar' => false,
        ]);
    }

    /** @return array{0: User, 1: ServidorFuncaoAdministrativa} */
    private function criarUsuarioVinculado(
        string $nome,
        FuncaoAdministrativa $funcao,
        ?Escola $escola = null,
        ?Setor $setor = null,
    ): array {
        $user = User::factory()->create(['name' => $nome]);
        $servidor = Servidor::query()->create([
            'user_id' => $user->id,
            'nome' => $nome,
            'email' => $user->email,
            'status' => Servidor::STATUS_ATIVO,
        ]);
        $vinculo = ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $servidor->id,
            'funcao_administrativa_id' => $funcao->id,
            'id_escola' => $escola?->id,
            'setor_id' => $setor?->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'origem' => 'teste',
            'principal' => true,
            'data_inicio' => now()->toDateString(),
        ]);

        return [$user, $vinculo];
    }
}
