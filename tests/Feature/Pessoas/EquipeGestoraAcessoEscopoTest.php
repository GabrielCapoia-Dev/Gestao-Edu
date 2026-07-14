<?php

namespace Tests\Feature\Pessoas;

use App\Filament\Admin\Pages\LogExportacoesAvaliacoes;
use App\Models\Aluno;
use App\Models\Avaliacao;
use App\Models\AvaliacaoExportacao;
use App\Models\Enums\ListaPermissoes;
use App\Models\Enums\NivelEmergenciaPedido;
use App\Models\Escola;
use App\Models\FuncaoAdministrativa;
use App\Models\Pedido;
use App\Models\Professor;
use App\Models\Role;
use App\Models\Serie;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\Setor;
use App\Models\TipoManutencao;
use App\Models\TipoStatus;
use App\Models\Turma;
use App\Models\User;
use App\Policies\AlunoPolicy;
use App\Policies\PedidoPolicy;
use App\Policies\ProfessorPolicy;
use App\Policies\TurmaPolicy;
use App\Notifications\SistemaNotification;
use App\Services\PedidoService;
use App\Services\PessoaAcessoService;
use App\Services\PessoaScopeService;
use App\Services\Relatorios\PedidoRelatorioService;
use App\Services\Relatorios\RelatorioPdfRenderer;
use App\Services\ServidorService;
use App\Services\UserService;
use App\Support\EquipeGestoraPermissionPreset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use LogicException;
use Mockery;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EquipeGestoraAcessoEscopoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_comando_cria_role_com_preset_restrito_e_vincula_funcoes_gestoras(): void
    {
        $direcaoExistente = FuncaoAdministrativa::query()->create([
            'codigo' => 'direcao-da-rede',
            'nome' => 'DireÃ§Ã£o da Rede',
            'categoria' => FuncaoAdministrativa::CATEGORIA_ADMINISTRATIVO,
            'ativo' => true,
            'exige_professor' => false,
            'concede_acesso_sistema' => false,
            'tem_relacao_turma' => false,
            'direcao_escolar' => true,
            'coordenacao_pedagogica' => false,
            'secretaria_escolar' => false,
        ]);

        Artisan::call('permissoes:criar');

        $funcoes = [
            FuncaoAdministrativa::query()->direcao()->firstOrFail(),
            FuncaoAdministrativa::query()->coordenacao()->firstOrFail(),
            FuncaoAdministrativa::query()->secretaria()->firstOrFail(),
        ];

        $role = Role::findByName('Equipe Gestora', 'web');

        $this->assertSame('direcao-da-rede', $direcaoExistente->fresh()->codigo);
        $this->assertSame('DireÃ§Ã£o da Rede', $direcaoExistente->fresh()->nome);

        $this->assertSame(
            collect(EquipeGestoraPermissionPreset::all())->sort()->values()->all(),
            $role->permissions()->pluck('name')->sort()->values()->all(),
        );
        $nomes = $role->permissions()->pluck('name');
        $this->assertNotContains('Listar Todos os Pedidos', $nomes);
        $this->assertNotContains('Acessar Escopo Global de Setores', $nomes);
        $this->assertNotContains('Visualizar Histórico dos Alunos', $nomes);

        foreach ($funcoes as $funcao) {
            $this->assertTrue((bool) $funcao->concede_acesso_sistema);
            $this->assertTrue($funcao->fresh()->rolesPadrao->contains('id', $role->id));
        }
    }

    public function test_provisionamento_reconcilia_role_e_escola_sem_apagar_role_independente(): void
    {
        Artisan::call('permissoes:criar');

        $roleExtra = Role::query()->create(['name' => 'Role Independente', 'guard_name' => 'web']);
        $roleFuncionalLegada = Role::query()->create(['name' => 'Direção Legada', 'guard_name' => 'web']);
        [$escola, $setor] = $this->criarEscola('Escola A');
        $user = User::factory()->create();
        $user->assignRole([$roleExtra, $roleFuncionalLegada]);
        $servidor = $this->criarServidor($user);
        $funcao = FuncaoAdministrativa::direcaoPadrao();
        $funcao->rolesPadrao()->syncWithoutDetaching([$roleFuncionalLegada->id]);
        $vinculo = $this->criarVinculoGestor($servidor, $funcao, $escola, $setor);

        app(PessoaAcessoService::class)->provisionarAcessosDoServidor($servidor);

        $user->refresh();
        $this->assertTrue($user->hasRole('Equipe Gestora'));
        $this->assertTrue($user->hasRole($roleExtra));
        $this->assertFalse($user->hasRole($roleFuncionalLegada));
        $this->assertSame($escola->id, (int) $user->id_escola);
        $this->assertSame([$escola->id], $user->idsEscolasVinculadas());
        $this->assertTrue($funcao->fresh()->rolesPadrao->contains('name', 'Equipe Gestora'));

        $vinculo->update([
            'status' => ServidorFuncaoAdministrativa::STATUS_INATIVO,
            'data_fim' => now()->toDateString(),
        ]);
        app(PessoaAcessoService::class)->provisionarAcessosDoServidor($servidor->fresh());

        $user->refresh();
        $this->assertFalse($user->hasRole('Equipe Gestora'));
        $this->assertTrue($user->hasRole($roleExtra));
        $this->assertNull($user->id_escola);
        $this->assertSame([], $user->idsEscolasVinculadas());
    }

    public function test_conversao_para_professor_remove_role_gestora_e_preserva_role_independente(): void
    {
        Artisan::call('permissoes:criar');

        $roleExtra = Role::query()->create(['name' => 'Role Independente Conversao', 'guard_name' => 'web']);
        $roleProfessor = Role::query()->firstOrCreate(['name' => 'Professor', 'guard_name' => 'web']);
        $funcaoProfessor = FuncaoAdministrativa::professorPadrao();
        $funcaoProfessor->rolesPadrao()->sync([$roleProfessor->id]);

        [$escola, $setor] = $this->criarEscola('Escola Conversao');
        $user = User::factory()->create();
        $user->assignRole($roleExtra);
        $servidor = $this->criarServidor($user);
        $vinculo = $this->criarVinculoGestor(
            $servidor,
            FuncaoAdministrativa::direcaoPadrao(),
            $escola,
            $setor,
        );

        $acesso = app(PessoaAcessoService::class);
        $acesso->provisionarAcessosDoServidor($servidor);
        $vinculo->update([
            'status' => ServidorFuncaoAdministrativa::STATUS_INATIVO,
            'data_fim' => now()->toDateString(),
        ]);
        $acesso->aplicarRolesProfessor($user->fresh(), [], forcarDefaults: true);

        $user->refresh();
        $this->assertTrue($user->hasRole('Professor'));
        $this->assertTrue($user->hasRole($roleExtra));
        $this->assertFalse($user->hasRole('Equipe Gestora'));
    }

    public function test_escopo_falha_fechado_quando_user_possui_mais_de_uma_pessoa_ativa(): void
    {
        Artisan::call('permissoes:criar');

        [$escolaA, $setorA] = $this->criarEscola('Escola Pessoa Ambigua A');
        [$escolaB, $setorB] = $this->criarEscola('Escola Pessoa Ambigua B');
        $user = User::factory()->create(['id_escola' => $escolaA->id]);
        $user->escolas()->sync([$escolaA->id]);

        $pessoaGestora = $this->criarServidor($user);
        $this->criarVinculoGestor(
            $pessoaGestora,
            FuncaoAdministrativa::direcaoPadrao(),
            $escolaA,
            $setorA,
        );
        Servidor::query()->create([
            'user_id' => $user->id,
            'nome' => 'Segunda Pessoa Ativa',
            'email' => 'segunda.pessoa@edu.umuarama.pr.gov.br',
            'status' => Servidor::STATUS_ATIVO,
            'id_escola' => $escolaB->id,
            'setor_id' => $setorB->id,
        ]);

        $scope = app(PessoaScopeService::class);

        $this->assertNull($scope->servidorDaPessoa($user));
        $this->assertTrue($scope->ehEquipeGestora($user));
        $this->assertTrue($scope->usaEscopoPorVinculos($user));
        $this->assertNull($scope->primarySetorId($user));
        $this->assertSame([], $scope->escolaIdsDosVinculos($user));
        $this->assertSame([], $scope->visibleSetorIds($user));
        $this->assertSame([], $user->fresh()->idsEscolasVinculadas());

        try {
            app(PessoaAcessoService::class)->provisionarAcessosDoServidor($pessoaGestora);
            $this->fail('O provisionamento deveria bloquear um User vinculado a mais de uma Pessoa.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('vinculada a outra Pessoa', $exception->getMessage());
        }
    }

    public function test_provisionamento_nao_reutiliza_user_de_outra_pessoa_pelo_email(): void
    {
        Artisan::call('permissoes:criar');

        [$escola, $setor] = $this->criarEscola('Escola Conflito Pessoa');
        $user = User::factory()->create();
        $this->criarServidor($user);
        $outraPessoa = Servidor::query()->create([
            'nome' => 'Outra Pessoa Mesmo Email',
            'email' => $user->email,
            'status' => Servidor::STATUS_ATIVO,
        ]);
        $this->criarVinculoGestor(
            $outraPessoa,
            FuncaoAdministrativa::direcaoPadrao(),
            $escola,
            $setor,
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('já está vinculada a outra Pessoa');

        app(PessoaAcessoService::class)->provisionarAcessosDoServidor($outraPessoa);
    }

    public function test_vinculo_gestor_sem_escola_nao_herda_escopo_de_outro_cargo(): void
    {
        [$escola, $setor] = $this->criarEscola('Escola Escopo Legado');
        $user = User::factory()->create(['id_escola' => $escola->id]);
        $user->escolas()->sync([$escola->id]);
        $servidor = $this->criarServidor($user);

        ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $servidor->id,
            'funcao_administrativa_id' => FuncaoAdministrativa::direcaoPadrao()->id,
            'id_escola' => null,
            'setor_id' => null,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'origem' => 'legado_inconsistente',
            'portaria' => 'PORT-LEGADO',
            'principal' => false,
            'data_inicio' => now()->toDateString(),
        ]);
        $funcaoComum = FuncaoAdministrativa::query()->create([
            'codigo' => 'apoio-escolar-legado',
            'nome' => 'Apoio Escolar Legado',
            'categoria' => FuncaoAdministrativa::CATEGORIA_ADMINISTRATIVO,
            'ativo' => true,
            'exige_professor' => false,
            'concede_acesso_sistema' => true,
            'tem_relacao_turma' => false,
            'direcao_escolar' => false,
            'coordenacao_pedagogica' => false,
            'secretaria_escolar' => false,
        ]);
        ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $servidor->id,
            'funcao_administrativa_id' => $funcaoComum->id,
            'id_escola' => $escola->id,
            'setor_id' => $setor->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'origem' => 'legado',
            'data_inicio' => now()->toDateString(),
        ]);

        $scope = app(PessoaScopeService::class);

        $this->assertTrue($scope->ehEquipeGestora($user));
        $this->assertNull($scope->primarySetorId($user));
        $this->assertSame([], $scope->escolaIdsDosVinculos($user));
        $this->assertSame([], $scope->visibleSetorIds($user));
        $this->assertSame([], $user->fresh()->idsEscolasVinculadas());
    }

    public function test_gestor_so_lista_e_opera_registros_da_escola_vinculada(): void
    {
        Artisan::call('permissoes:criar');

        [$escolaA, $setorA] = $this->criarEscola('Escola Escopo A');
        [$escolaB, $setorB] = $this->criarEscola('Escola Escopo B');
        $user = User::factory()->create();
        $servidor = $this->criarServidor($user);
        $vinculo = $this->criarVinculoGestor(
            $servidor,
            FuncaoAdministrativa::direcaoPadrao(),
            $escolaA,
            $setorA,
        );
        app(PessoaAcessoService::class)->provisionarAcessosDoServidor($servidor);
        $user->refresh();

        $serie = Serie::query()->create(['codigo' => 'SER-GESTAO', 'nome' => 'Serie Gestao']);
        $turmaA = $this->criarTurma($escolaA, $serie, 'TUR-GESTAO-A');
        $turmaB = $this->criarTurma($escolaB, $serie, 'TUR-GESTAO-B');
        $professorA = $this->criarProfessor($escolaA, 'Professor A');
        $professorB = $this->criarProfessor($escolaB, 'Professor B');
        $alunoA = $this->criarAluno($turmaA, 'CGM-GESTAO-A');
        $alunoB = $this->criarAluno($turmaB, 'CGM-GESTAO-B');

        $userService = app(UserService::class);

        $this->assertSame(
            [$turmaA->id],
            $userService->aplicarFiltroTurmasDoUsuario(Turma::query(), $user)->pluck('id')->all(),
        );
        $this->assertSame(
            [$alunoA->id],
            $userService->aplicarFiltroAlunosDoUsuario(Aluno::query(), $user)->pluck('id')->all(),
        );
        $this->assertTrue(app(TurmaPolicy::class)->update($user, $turmaA));
        $this->assertFalse(app(TurmaPolicy::class)->update($user, $turmaB));
        $this->assertTrue(app(ProfessorPolicy::class)->update($user, $professorA));
        $this->assertFalse(app(ProfessorPolicy::class)->update($user, $professorB));
        $this->assertTrue(app(AlunoPolicy::class)->remanejar($user, $alunoA));
        $this->assertFalse(app(AlunoPolicy::class)->remanejar($user, $alunoB));

        $servidorVisivel = Servidor::query()->create([
            'nome' => 'Pessoa Escola A',
            'status' => Servidor::STATUS_ATIVO,
            'id_escola' => $escolaA->id,
            'setor_id' => $setorA->id,
        ]);
        $servidorOutraEscolaMesmoSetor = Servidor::query()->create([
            'nome' => 'Pessoa Escola B no mesmo setor legado',
            'status' => Servidor::STATUS_ATIVO,
            'id_escola' => $escolaB->id,
            'setor_id' => $setorA->id,
        ]);
        $servidoresVisiveis = app(ServidorService::class)
            ->aplicarEscopoVisibilidade(Servidor::query(), $user)
            ->pluck('id');

        $this->assertContains($servidor->id, $servidoresVisiveis);
        $this->assertContains($servidorVisivel->id, $servidoresVisiveis);
        $this->assertNotContains($servidorOutraEscolaMesmoSetor->id, $servidoresVisiveis);

        $this->criarVinculoGestor(
            $servidor,
            FuncaoAdministrativa::coordenacaoPadrao(),
            $escolaB,
            $setorB,
        );

        $scope = app(PessoaScopeService::class);
        $this->assertSame([], $scope->escolaIdsDosVinculos($user));
        $this->assertSame([], $user->fresh()->idsEscolasVinculadas());
        $this->assertSame(
            [],
            $userService->aplicarFiltroTurmasDoUsuario(Turma::query(), $user)->pluck('id')->all(),
        );

        $vinculo->refresh();
    }

    public function test_pedidos_e_acoes_ficam_restritos_a_escola_do_gestor(): void
    {
        Artisan::call('permissoes:criar');

        [$escolaA, $setorA] = $this->criarEscola('Escola Pedido A');
        [$escolaB, $setorB] = $this->criarEscola('Escola Pedido B');
        $user = User::factory()->create();
        $servidor = $this->criarServidor($user);
        $this->criarVinculoGestor($servidor, FuncaoAdministrativa::secretariaPadrao(), $escolaA, $setorA);
        app(PessoaAcessoService::class)->provisionarAcessosDoServidor($servidor);
        $user->refresh();

        $userB = User::factory()->create();
        $servidorB = $this->criarServidor($userB);
        $this->criarVinculoGestor($servidorB, FuncaoAdministrativa::secretariaPadrao(), $escolaB, $setorB);
        app(PessoaAcessoService::class)->provisionarAcessosDoServidor($servidorB);
        $userB->refresh();

        $tipo = TipoManutencao::query()->create(['nome' => 'Manutencao']);
        $status = TipoStatus::query()->create(['nome' => 'Em Manutenção', 'ativo' => true]);
        $pedidoA = $this->criarPedido($user, $escolaA, $setorA, $tipo, $status, 'Pedido A');
        $pedidoB = $this->criarPedido($user, $escolaB, $setorA, $tipo, $status, 'Pedido B');

        $ids = app(PedidoService::class)
            ->queryTabela($user)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->assertSame([$pedidoA->id], $ids);
        $this->assertTrue(app(PedidoPolicy::class)->evaluate($user, $pedidoA));
        $this->assertFalse(app(PedidoPolicy::class)->evaluate($user, $pedidoB));

        Notification::fake();
        $user->givePermissionTo('Visualizar Notificação: Pedidos Emergenciais');
        $userB->givePermissionTo('Visualizar Notificação: Pedidos Emergenciais');
        $pedidoA->update(['nivel_prioridade' => NivelEmergenciaPedido::EMERGENCIAL]);

        Notification::assertSentTo($user, SistemaNotification::class);
        Notification::assertNotSentTo($userB, SistemaNotification::class);
    }

    public function test_notificacoes_agendadas_de_pedidos_respeitam_a_escola_do_gestor(): void
    {
        Artisan::call('permissoes:criar');
        Notification::fake();
        Cache::flush();

        [$escolaA, $setorA] = $this->criarEscola('Escola Notificacao A');
        [$escolaB, $setorB] = $this->criarEscola('Escola Notificacao B');

        $userA = User::factory()->create();
        $servidorA = $this->criarServidor($userA);
        $this->criarVinculoGestor($servidorA, FuncaoAdministrativa::secretariaPadrao(), $escolaA, $setorA);
        app(PessoaAcessoService::class)->provisionarAcessosDoServidor($servidorA);

        $userB = User::factory()->create();
        $servidorB = $this->criarServidor($userB);
        $this->criarVinculoGestor($servidorB, FuncaoAdministrativa::secretariaPadrao(), $escolaB, $setorB);
        app(PessoaAcessoService::class)->provisionarAcessosDoServidor($servidorB);

        foreach ([$userA, $userB] as $user) {
            $user->givePermissionTo([
                'Visualizar Notificação: Vencimento de Pedidos',
                'Visualizar Notificação: Pedidos Atrasados',
            ]);
        }

        $tipo = TipoManutencao::query()->create(['nome' => 'Manutencao Notificacao']);
        $status = TipoStatus::query()->create(['nome' => 'Em Manutenção', 'ativo' => true]);
        $pedidoAVencer = $this->criarPedido($userA, $escolaA, $setorA, $tipo, $status, 'Pedido a vencer');
        $pedidoAVencer->update(['data_prevista' => now()->addDay()->toDateString()]);
        $pedidoAtrasado = $this->criarPedido($userA, $escolaA, $setorA, $tipo, $status, 'Pedido atrasado');
        $pedidoAtrasado->update(['data_prevista' => now()->subDay()->toDateString()]);

        Artisan::call('app:notificar-pedidos-a-vencer');
        Artisan::call('app:notificar-pedidos-atrasados');

        Notification::assertSentTo(
            $userA,
            SistemaNotification::class,
            fn (SistemaNotification $notification): bool => $notification->titulo === 'Pedido Próximo do Vencimento',
        );
        Notification::assertSentTo(
            $userA,
            SistemaNotification::class,
            fn (SistemaNotification $notification): bool => $notification->titulo === 'Pedido Atrasado',
        );
        Notification::assertNotSentTo($userB, SistemaNotification::class);
    }

    public function test_pdf_de_pedido_respeita_escola_do_gestor_e_preserva_escopo_global(): void
    {
        Artisan::call('permissoes:criar');

        [$escolaA, $setorA] = $this->criarEscola('Escola PDF A');
        [$escolaB, $setorB] = $this->criarEscola('Escola PDF B');
        $user = User::factory()->create();
        $servidor = $this->criarServidor($user);
        $this->criarVinculoGestor($servidor, FuncaoAdministrativa::secretariaPadrao(), $escolaA, $setorA);
        app(PessoaAcessoService::class)->provisionarAcessosDoServidor($servidor);
        $user->refresh();

        $tipo = TipoManutencao::query()->create(['nome' => 'Manutencao PDF']);
        $status = TipoStatus::query()->create(['nome' => 'Em Manutencao PDF', 'ativo' => true]);
        $pedidoA = $this->criarPedido($user, $escolaA, $setorA, $tipo, $status, 'Pedido PDF A');
        $pedidoB = $this->criarPedido($user, $escolaB, $setorB, $tipo, $status, 'Pedido PDF B');

        $renderer = Mockery::mock(RelatorioPdfRenderer::class);
        $renderer->shouldReceive('stream')
            ->twice()
            ->andReturnUsing(fn () => response('pdf', 200, ['Content-Type' => 'application/pdf']));
        $this->app->instance(RelatorioPdfRenderer::class, $renderer);

        $this->actingAs($user)
            ->get(route('pedidos.pdf', $pedidoA))
            ->assertOk();

        $this->get(route('pedidos.pdf', $pedidoB))
            ->assertForbidden();

        try {
            app(PedidoRelatorioService::class)->gerar($pedidoB);
            $this->fail('O servico permitiu gerar o PDF de um pedido de outra escola.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $user->givePermissionTo('Listar Todos os Pedidos');

        $this->get(route('pedidos.pdf', $pedidoB))
            ->assertOk();
    }

    public function test_log_de_exportacoes_respeita_escola_do_gestor_e_preserva_escopo_global(): void
    {
        Artisan::call('permissoes:criar');

        [$escolaA, $setorA] = $this->criarEscola('Escola Log A');
        [$escolaB] = $this->criarEscola('Escola Log B');
        $user = User::factory()->create();
        $servidor = $this->criarServidor($user);
        $this->criarVinculoGestor($servidor, FuncaoAdministrativa::secretariaPadrao(), $escolaA, $setorA);
        app(PessoaAcessoService::class)->provisionarAcessosDoServidor($servidor);
        $user->refresh();

        $avaliacao = Avaliacao::query()->create([
            'nome' => 'Avaliacao do log',
            'data_inicio' => '2026-01-01',
            'data_fim' => '2026-12-31',
            'status' => Avaliacao::STATUS_ATIVA,
        ]);
        $logA = AvaliacaoExportacao::query()->create([
            'avaliacao_id' => $avaliacao->id,
            'escola_id' => $escolaA->id,
            'user_id' => $user->id,
            'escopo' => 'escola',
            'formato' => 'pdf',
            'quantidade_alunos' => 1,
            'quantidade_paginas' => 1,
            'parametros' => [],
            'exportado_em' => now(),
        ]);
        $logB = AvaliacaoExportacao::query()->create([
            'avaliacao_id' => $avaliacao->id,
            'escola_id' => $escolaB->id,
            'user_id' => $user->id,
            'escopo' => 'escola',
            'formato' => 'pdf',
            'quantidade_alunos' => 1,
            'quantidade_paginas' => 1,
            'parametros' => [],
            'exportado_em' => now()->addSecond(),
        ]);

        Livewire::actingAs($user)
            ->test(LogExportacoesAvaliacoes::class)
            ->assertCanSeeTableRecords([$logA])
            ->assertCanNotSeeTableRecords([$logB]);

        $global = User::factory()->create();
        $global->givePermissionTo([
            ListaPermissoes::ListarAvaliacoes->label(),
            ListaPermissoes::AcessarEscopoGlobalDeSetores->label(),
        ]);

        Livewire::actingAs($global)
            ->test(LogExportacoesAvaliacoes::class)
            ->assertCanSeeTableRecords([$logA, $logB]);
    }

    /** @return array{0: Escola, 1: Setor} */
    private function criarEscola(string $nome): array
    {
        $setor = Setor::query()->create([
            'nome' => 'Setor '.$nome,
            'ativo' => true,
            'status' => 'ativo',
            'contexto' => 'escolar',
            'exige_vinculo_escola' => true,
        ]);
        $escola = Escola::query()->create([
            'codigo' => strtoupper(substr(md5($nome), 0, 8)),
            'nome' => $nome,
            'setor_id' => $setor->id,
            'email' => strtolower(str_replace(' ', '.', $nome)).'@teste.local',
            'telefone' => '(44) 99999-9999',
            'ativo' => true,
        ]);

        return [$escola, $setor];
    }

    private function criarServidor(User $user): Servidor
    {
        return Servidor::query()->create([
            'user_id' => $user->id,
            'nome' => $user->name,
            'email' => $user->email,
            'status' => Servidor::STATUS_ATIVO,
        ]);
    }

    private function criarVinculoGestor(
        Servidor $servidor,
        FuncaoAdministrativa $funcao,
        Escola $escola,
        Setor $setor,
    ): ServidorFuncaoAdministrativa {
        return ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $servidor->id,
            'funcao_administrativa_id' => $funcao->id,
            'id_escola' => $escola->id,
            'setor_id' => $setor->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'origem' => 'teste',
            'portaria' => $funcao->secretaria_escolar ? null : 'PORT-2026',
            'principal' => (bool) $funcao->direcao_escolar,
            'data_inicio' => now()->toDateString(),
        ]);
    }

    private function criarTurma(Escola $escola, Serie $serie, string $codigo): Turma
    {
        return Turma::query()->create([
            'codigo' => $codigo,
            'nome' => $codigo,
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
    }

    private function criarProfessor(Escola $escola, string $nome): Professor
    {
        return Professor::query()->create([
            'id_escola' => $escola->id,
            'matricula' => strtoupper(str_replace(' ', '-', $nome)),
            'turno' => 'manha',
            'nome' => $nome,
            'email' => strtolower(str_replace(' ', '.', $nome)).'@teste.local',
            'ativo' => true,
        ]);
    }

    private function criarAluno(Turma $turma, string $cgm): Aluno
    {
        return Aluno::query()->create([
            'nome' => 'Aluno '.$cgm,
            'cgm' => $cgm,
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turma->id,
            'tipo_vinculo' => Aluno::TIPO_VINCULO_PRINCIPAL,
            'status' => Aluno::STATUS_MATRICULADO,
        ]);
    }

    private function criarPedido(
        User $user,
        Escola $escola,
        Setor $setor,
        TipoManutencao $tipo,
        TipoStatus $status,
        string $descricao,
    ): Pedido {
        return Pedido::query()->create([
            'descricao_pedido' => $descricao,
            'tipo_manutencao_id' => $tipo->id,
            'tipo_status_id' => $status->id,
            'nome_solicitante' => $user->name,
            'escola_id' => $escola->id,
            'solicitante_id' => $user->id,
            'setor_id' => $setor->id,
            'setor_origem_id' => $setor->id,
            'data_solicitacao' => now()->toDateString(),
            'data_identificacao_problema' => now()->toDateString(),
            'ativo' => true,
        ]);
    }
}
