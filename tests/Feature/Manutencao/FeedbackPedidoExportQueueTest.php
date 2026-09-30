<?php

namespace Tests\Feature\Manutencao;

use App\Jobs\ProcessExportRequestJob;
use App\Filament\Admin\Pages\FeedbackPedido as FeedbackPedidoPage;
use App\Models\EmpresaContratada;
use App\Models\Enums\ResultadoFeedbackPedido;
use App\Models\Escola;
use App\Models\ExportRequest;
use App\Models\FeedbackPedido;
use App\Models\Pedido;
use App\Models\TipoManutencao;
use App\Models\TipoManutencaoOpcao;
use App\Models\TipoStatus;
use App\Models\User;
use App\Services\Exports\Handlers\FeedbackPedidoExportHandler;
use App\Services\Relatorios\FeedbackPedidoAnalyticsService;
use App\Services\UserSetorAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FeedbackPedidoExportQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_rota_de_feedback_enfileira_pdf_com_periodo_obrigatorio(): void
    {
        Queue::fake();

        $user = $this->usuarioComPermissoes();

        $this->actingAs($user)
            ->get(route('feedback-pedidos.export-geral', [
                'data_inicio' => '2026-05-01',
                'data_fim' => '2026-05-31',
                'valor' => 5,
                'tipo_manutencao_opcao_id' => 1,
            ]))
            ->assertRedirect();

        $exportRequest = ExportRequest::query()->firstOrFail();

        $this->assertSame('feedback_pedido_relatorio', $exportRequest->type);
        $this->assertSame('pdf', $exportRequest->format);
        $this->assertSame('2026-05-01', $exportRequest->filters['data_inicio']);
        $this->assertSame('2026-05-31', $exportRequest->filters['data_fim']);
        $this->assertSame(FeedbackPedidoAnalyticsService::REPORT_GERAL, $exportRequest->filters['report_type']);
        $this->assertSame('1', (string) $exportRequest->filters['tipo_manutencao_opcao_id']);

        Queue::assertPushed(ProcessExportRequestJob::class, 1);
    }

    public function test_tela_de_feedback_renderiza_painel_dinamico(): void
    {
        $user = $this->usuarioComPermissoes();

        $this->actingAs($user)
            ->get(route('filament.admin.pages.feedback-pedidos'))
            ->assertOk()
            ->assertSee('Desempenho por contratada')
            ->assertSee('Satisfação por escola')
            ->assertSee('Distribuição das avaliações');
    }

    public function test_tela_de_feedback_abre_visualizacao_do_pedido_em_modal(): void
    {
        $dados = $this->criarFeedbacksParaFiltro();
        $user = $this->usuarioComPermissoes();
        $user->update(['id_escola' => $dados['escola']->id]);

        Livewire::actingAs($user)
            ->test(FeedbackPedidoPage::class)
            ->assertTableActionVisible('visualizar_pedido', $dados['feedback'])
            ->callTableAction('visualizar_pedido', $dados['feedback'])
            ->assertHasNoTableActionErrors();
    }

    public function test_rota_de_feedback_nao_enfileira_sem_data_fim(): void
    {
        Queue::fake();

        $user = $this->usuarioComPermissoes();

        $this->actingAs($user)
            ->from(route('filament.admin.pages.feedback-pedidos'))
            ->get(route('feedback-pedidos.export-geral', [
                'data_inicio' => '2026-05-01',
            ]))
            ->assertRedirect(route('filament.admin.pages.feedback-pedidos'));

        $this->assertDatabaseCount('export_requests', 0);
        Queue::assertNothingPushed();
    }

    public function test_service_aplica_filtros_reais_de_empresa_escola_tipo_nota_e_resultado(): void
    {
        $dados = $this->criarFeedbacksParaFiltro();

        $query = app(FeedbackPedidoAnalyticsService::class)->query([
            'data_inicio' => '2026-05-01',
            'data_fim' => '2026-05-31',
            'empresa_contratada_id' => $dados['empresa']->id,
            'escola_id' => $dados['escola']->id,
            'tipo_manutencao_id' => $dados['tipo']->id,
            'tipo_manutencao_opcao_id' => $dados['opcao']->id,
            'valor' => 5,
            'resultado' => ResultadoFeedbackPedido::Atendido->value,
        ]);

        $this->assertSame([$dados['feedback']->id], $query->pluck('id')->all());
    }

    public function test_satisfacao_reflete_a_media_das_notas_na_escala_de_cinco_pontos(): void
    {
        $this->criarFeedbacksParaFiltro();

        $service = app(FeedbackPedidoAnalyticsService::class);
        $query = $service->query([]);

        $this->assertSame(3.5, $service->metrics(clone $query)['media']);
        $this->assertSame(70, $service->metrics(clone $query)['satisfacao']);

        $empresas = $service->rankingEmpresas($query->get());

        $this->assertSame([100, 40], array_column($empresas, 'satisfacao'));
        $this->assertSame([100, 40], array_column($empresas, 'pct_barra'));

        $escolas = $service->rankingEscolas($query->get());

        $this->assertSame([100, 40], array_column($escolas, 'satisfacao'));
        $this->assertSame([100, 40], array_column($escolas, 'pct_barra'));
    }

    public function test_ranking_de_escolas_nao_oculta_escolas_com_satisfacao_menor_que_cem(): void
    {
        $dados = $this->criarFeedbacksParaFiltro();

        for ($i = 1; $i <= 10; $i++) {
            $escola = Escola::query()->create([
                'codigo' => '1' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'nome' => 'Escola 100 ' . $i,
                'ativo' => true,
            ]);

            $pedido = $this->pedido($dados['user'], $escola, $dados['tipo'], $dados['status'], $dados['empresa']);

            FeedbackPedido::query()->create([
                'pedido_id' => $pedido->id,
                'valor' => 5,
                'descricao' => 'Atendimento excelente.',
                'reabrir_pedido' => false,
            ]);
        }

        $service = app(FeedbackPedidoAnalyticsService::class);
        $ranking = $service->rankingEscolas($service->query([])->get());
        $escolaComNotaBaixa = collect($ranking)->firstWhere('nome', 'Escola Norte');

        $this->assertCount(12, $ranking);
        $this->assertNotNull($escolaComNotaBaixa);
        $this->assertSame(2.0, $escolaComNotaBaixa['media']);
        $this->assertSame(40, $escolaComNotaBaixa['satisfacao']);
        $this->assertSame(40, $escolaComNotaBaixa['pct_barra']);
    }

    public function test_criticas_consideram_pedidos_com_historico_reaberto(): void
    {
        $dados = $this->criarFeedbacksParaFiltro();
        $statusReaberto = TipoStatus::query()->create([
            'nome' => 'Reaberto',
            'ativo' => true,
            'finaliza_pedido' => false,
            'cancela_pedido' => false,
        ]);

        $dados['feedback']->pedido->historicos()->create([
            'status_anterior_id' => null,
            'status_novo_id' => $statusReaberto->id,
            'usuario_id' => User::factory()->create()->id,
            'setor_id' => null,
            'descricao_alteracao' => 'Pedido reaberto para refazer o servico.',
        ]);

        $service = app(FeedbackPedidoAnalyticsService::class);
        $query = $service->query([]);
        $metrics = $service->metrics(clone $query);
        $reabertos = $service->query(['reabrir_pedido' => true])->pluck('id')->all();

        $this->assertSame(1, $metrics['criticas']);
        $this->assertSame(1, $metrics['reabertos']);
        $this->assertSame([$dados['feedback']->id], $reabertos);
    }

    public function test_options_de_filtro_sao_baseadas_em_pedidos_avaliados(): void
    {
        $dados = $this->criarFeedbacksParaFiltro();
        $user = $this->usuarioComPermissoes();
        Permission::findOrCreate(UserSetorAccessService::GLOBAL_SCOPE_PERMISSION, 'web');
        $user->givePermissionTo(UserSetorAccessService::GLOBAL_SCOPE_PERMISSION);

        $empresaSemPedido = EmpresaContratada::query()->create(['nome' => 'Empresa Sem Pedido', 'cnpj' => '11.111.111/0001-11', 'ativo' => true]);
        $tipoSemPedido = TipoManutencao::query()->create(['nome' => 'Tipo Sem Pedido', 'ativo' => true]);
        $escolaSemPedido = Escola::query()->create(['codigo' => '999', 'nome' => 'Escola Sem Pedido', 'ativo' => true]);
        $statusSemFeedback = TipoStatus::query()->create(['nome' => 'Aberto Sem Feedback', 'ativo' => true, 'finaliza_pedido' => false]);
        $pedidoSemFeedback = $this->pedido($user, $dados['escola'], $dados['tipo'], $statusSemFeedback, $dados['empresa']);
        $pedidoSemFeedback->forceFill(['data_solicitacao' => '2026-04-01'])->save();

        $this->actingAs($user);

        $service = app(FeedbackPedidoAnalyticsService::class);

        $this->assertArrayHasKey($dados['empresa']->id, $service->empresaOptions());
        $this->assertArrayNotHasKey($empresaSemPedido->id, $service->empresaOptions());
        $this->assertArrayHasKey($dados['tipo']->id, $service->tipoManutencaoOptions());
        $this->assertArrayNotHasKey($tipoSemPedido->id, $service->tipoManutencaoOptions());
        $this->assertArrayHasKey($dados['escola']->id, $service->escolaOptions());
        $this->assertArrayNotHasKey($escolaSemPedido->id, $service->escolaOptions());
        $this->assertArrayHasKey($dados['opcao']->id, $service->tipoManutencaoOpcaoOptions($dados['tipo']->id));
        $this->assertSame('2026-04-01', $service->firstPedidoDate());
    }

    public function test_periodo_filtra_pela_data_do_pedido(): void
    {
        $dados = $this->criarFeedbacksParaFiltro();

        $foraDoPeriodo = app(FeedbackPedidoAnalyticsService::class)->query([
            'data_inicio' => '2026-05-02',
            'data_fim' => '2026-05-31',
            'tipo_manutencao_opcao_id' => $dados['opcao']->id,
        ]);

        $this->assertSame([], $foraDoPeriodo->pluck('id')->all());
    }

    public function test_handler_salva_pdf_privado_da_exportacao_de_feedback(): void
    {
        Storage::fake('local');

        $dados = $this->criarFeedbacksParaFiltro();

        $user = User::factory()->create([
            'id_escola' => $dados['escola']->id,
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $exportRequest = ExportRequest::query()->create([
            'user_id' => $user->id,
            'type' => 'feedback_pedido_relatorio',
            'format' => 'pdf',
            'label' => 'Feedback - Satisfacao geral',
            'filters' => [
                'report_type' => FeedbackPedidoAnalyticsService::REPORT_LISTAGEM,
                'data_inicio' => '2026-05-01',
                'data_fim' => '2026-05-31',
            ],
            'metadata' => [],
            'fingerprint' => fake()->uuid(),
            'status' => ExportRequest::STATUS_QUEUED,
            'status_message' => 'Aguardando processamento.',
            'progress_current' => 0,
            'progress_total' => 100,
        ]);

        $result = app(FeedbackPedidoExportHandler::class)->handle($exportRequest->load('user'));

        Storage::disk('local')->assertExists($result->path);
        $this->assertSame('application/pdf', $result->mime);
        $this->assertGreaterThan(0, $result->sizeBytes);
    }

    public function test_handler_gera_pdf_bi_privado_da_exportacao_de_feedback(): void
    {
        Storage::fake('local');

        $dados = $this->criarFeedbacksParaFiltro();
        $user = User::factory()->create([
            'id_escola' => $dados['escola']->id,
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $exportRequest = ExportRequest::query()->create([
            'user_id' => $user->id,
            'type' => 'feedback_pedido_relatorio',
            'format' => 'pdf',
            'label' => 'Feedback - BI',
            'filters' => [
                'report_type' => FeedbackPedidoAnalyticsService::REPORT_GERAL,
                'data_inicio' => '2026-05-01',
                'data_fim' => '2026-05-31',
            ],
            'metadata' => [],
            'fingerprint' => fake()->uuid(),
            'status' => ExportRequest::STATUS_QUEUED,
            'status_message' => 'Aguardando processamento.',
            'progress_current' => 0,
            'progress_total' => 100,
        ]);

        $result = app(FeedbackPedidoExportHandler::class)->handle($exportRequest->load('user'));

        Storage::disk('local')->assertExists($result->path);
        $this->assertSame('application/pdf', $result->mime);
        $this->assertGreaterThan(0, $result->sizeBytes);
    }

    public function test_listagem_e_analytics_respeitam_escola_e_admin_mantem_escopo_global(): void
    {
        $dados = $this->criarFeedbacksParaFiltro();
        $restrito = $this->usuarioComPermissoes();
        $restrito->update(['id_escola' => $dados['escola']->id]);

        $service = app(FeedbackPedidoAnalyticsService::class);

        $this->assertSame([$dados['feedback']->id], $service->query([], $restrito)->pluck('id')->all());

        Livewire::actingAs($restrito)
            ->test(FeedbackPedidoPage::class)
            ->assertCanSeeTableRecords([$dados['feedback']])
            ->assertCanNotSeeTableRecords([$dados['outroFeedback']]);

        $admin = $this->usuarioComPermissoes();
        $admin->assignRole(Role::findOrCreate('Admin', 'web'));

        $this->assertCount(2, $service->query([], $admin)->get());
    }

    private function usuarioComPermissoes(): User
    {
        Permission::findOrCreate('Visualizar Feedback de Pedidos', 'web');
        Permission::findOrCreate('Exportar Relatorios', 'web');

        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $user->givePermissionTo([
            'Visualizar Feedback de Pedidos',
            'Exportar Relatorios',
        ]);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function criarFeedbacksParaFiltro(): array
    {
        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole(Role::findOrCreate('Admin', 'web'));
        $this->actingAs($user);
        $escola = Escola::query()->create(['codigo' => '001', 'nome' => 'Escola Central', 'ativo' => true]);
        $outraEscola = Escola::query()->create(['codigo' => '002', 'nome' => 'Escola Norte', 'ativo' => true]);
        $tipo = TipoManutencao::query()->create(['nome' => 'Eletrica', 'ativo' => true]);
        $opcao = TipoManutencaoOpcao::query()->create([
            'tipo_manutencao_id' => $tipo->id,
            'texto' => 'Sem energia',
            'ativo' => true,
        ]);
        $outroTipo = TipoManutencao::query()->create(['nome' => 'Hidraulica', 'ativo' => true]);
        $status = TipoStatus::query()->create(['nome' => 'Concluido', 'ativo' => true, 'finaliza_pedido' => true]);
        $empresa = EmpresaContratada::query()->create(['nome' => 'Empresa A', 'cnpj' => '12.345.678/0001-90', 'ativo' => true]);
        $outraEmpresa = EmpresaContratada::query()->create(['nome' => 'Empresa B', 'cnpj' => '98.765.432/0001-10', 'ativo' => true]);

        $pedido = $this->pedido($user, $escola, $tipo, $status, $empresa);
        $problema = $pedido->problemas()->create([
            'tipo_manutencao_id' => $tipo->id,
            'tipo_manutencao_opcao_id' => $opcao->id,
            'texto_problema' => 'Sem energia',
        ]);

        $feedback = FeedbackPedido::query()->create([
            'pedido_id' => $pedido->id,
            'valor' => 5,
            'descricao' => 'Atendimento excelente.',
            'reabrir_pedido' => false,
        ]);
        $feedback->forceFill(['created_at' => '2026-05-10 10:00:00'])->save();
        $feedback->itens()->create([
            'pedido_id' => $pedido->id,
            'pedido_problema_id' => $problema->id,
            'valor' => 5,
            'resultado' => ResultadoFeedbackPedido::Atendido->value,
            'comentario' => 'Resolvido.',
        ]);

        $outroPedido = $this->pedido($user, $outraEscola, $outroTipo, $status, $outraEmpresa);
        $outroFeedback = FeedbackPedido::query()->create([
            'pedido_id' => $outroPedido->id,
            'valor' => 2,
            'descricao' => 'Nao resolveu.',
            'reabrir_pedido' => true,
        ]);
        $outroFeedback->forceFill(['created_at' => '2026-05-12 10:00:00'])->save();

        return compact('user', 'empresa', 'escola', 'outraEscola', 'tipo', 'opcao', 'status', 'feedback', 'outroFeedback');
    }

    private function pedido(User $user, Escola $escola, TipoManutencao $tipo, TipoStatus $status, EmpresaContratada $empresa): Pedido
    {
        return Pedido::query()->create([
            'descricao_pedido' => 'Problema de manutencao.',
            'tipo_manutencao_id' => $tipo->id,
            'tipo_status_id' => $status->id,
            'nome_solicitante' => 'Direcao',
            'nivel_prioridade' => 'Preventivo',
            'escola_id' => $escola->id,
            'solicitante_id' => $user->id,
            'empresa_contratada_id' => $empresa->id,
            'data_solicitacao' => '2026-05-01',
            'data_identificacao_problema' => '2026-05-01',
            'ativo' => true,
        ]);
    }
}
