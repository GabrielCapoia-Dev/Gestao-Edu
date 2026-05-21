<?php

namespace Tests\Feature\Manutencao;

use App\Jobs\ProcessExportRequestJob;
use App\Models\EmpresaContratada;
use App\Models\Enums\ResultadoFeedbackPedido;
use App\Models\Escola;
use App\Models\ExportRequest;
use App\Models\FeedbackPedido;
use App\Models\Pedido;
use App\Models\TipoManutencao;
use App\Models\TipoStatus;
use App\Models\User;
use App\Services\Exports\Handlers\FeedbackPedidoExportHandler;
use App\Services\Relatorios\ChartRenderService;
use App\Services\Relatorios\FeedbackPedidoAnalyticsService;
use App\Services\Relatorios\FeedbackPedidoRelatorioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
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
            ]))
            ->assertRedirect();

        $exportRequest = ExportRequest::query()->firstOrFail();

        $this->assertSame('feedback_pedido_relatorio', $exportRequest->type);
        $this->assertSame('pdf', $exportRequest->format);
        $this->assertSame('2026-05-01', $exportRequest->filters['data_inicio']);
        $this->assertSame('2026-05-31', $exportRequest->filters['data_fim']);
        $this->assertSame(FeedbackPedidoAnalyticsService::REPORT_GERAL, $exportRequest->filters['report_type']);

        Queue::assertPushed(ProcessExportRequestJob::class, 1);
    }

    public function test_tela_de_feedback_renderiza_painel_dinamico(): void
    {
        $user = $this->usuarioComPermissoes();

        $this->actingAs($user)
            ->get(route('filament.admin.pages.feedback-pedidos'))
            ->assertOk()
            ->assertSee('Desempenho por contratada')
            ->assertSee('Satisfacao por escola')
            ->assertSee('Distribuicao das avaliacoes');
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
            'valor' => 5,
            'resultado' => ResultadoFeedbackPedido::Atendido->value,
        ]);

        $this->assertSame([$dados['feedback']->id], $query->pluck('id')->all());
    }

    public function test_handler_salva_pdf_privado_da_exportacao_de_feedback(): void
    {
        Storage::fake('local');

        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $exportRequest = ExportRequest::query()->create([
            'user_id' => $user->id,
            'type' => 'feedback_pedido_relatorio',
            'format' => 'pdf',
            'label' => 'Feedback - Satisfacao geral',
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

        app()->instance(ChartRenderService::class, tap(Mockery::mock(ChartRenderService::class), function ($mock): void {
            $mock->shouldReceive('renderizarGrafico')->andReturn(null);
            $mock->shouldReceive('renderizarGraficoLocal')->andReturn(null);
        }));

        app()->instance(FeedbackPedidoRelatorioService::class, tap(Mockery::mock(FeedbackPedidoRelatorioService::class), function ($mock): void {
            $mock->shouldReceive('gerarComGraficosEMatriz')
                ->once()
                ->andReturn(response('PDF CONTENT', 200, ['Content-Type' => 'application/pdf']));
        }));

        $result = app(FeedbackPedidoExportHandler::class)->handle($exportRequest->load('user'));

        Storage::disk('local')->assertExists($result->path);
        $this->assertSame('application/pdf', $result->mime);
        $this->assertSame(strlen('PDF CONTENT'), $result->sizeBytes);
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
        $escola = Escola::query()->create(['codigo' => '001', 'nome' => 'Escola Central', 'ativo' => true]);
        $outraEscola = Escola::query()->create(['codigo' => '002', 'nome' => 'Escola Norte', 'ativo' => true]);
        $tipo = TipoManutencao::query()->create(['nome' => 'Eletrica', 'ativo' => true]);
        $outroTipo = TipoManutencao::query()->create(['nome' => 'Hidraulica', 'ativo' => true]);
        $status = TipoStatus::query()->create(['nome' => 'Concluido', 'ativo' => true, 'finaliza_pedido' => true]);
        $empresa = EmpresaContratada::query()->create(['nome' => 'Empresa A', 'cnpj' => '12.345.678/0001-90', 'ativo' => true]);
        $outraEmpresa = EmpresaContratada::query()->create(['nome' => 'Empresa B', 'cnpj' => '98.765.432/0001-10', 'ativo' => true]);

        $pedido = $this->pedido($user, $escola, $tipo, $status, $empresa);
        $problema = $pedido->problemas()->create([
            'tipo_manutencao_id' => $tipo->id,
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

        return compact('empresa', 'escola', 'tipo', 'feedback');
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
