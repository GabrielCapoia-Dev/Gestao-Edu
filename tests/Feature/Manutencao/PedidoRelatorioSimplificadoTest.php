<?php

namespace Tests\Feature\Manutencao;

use App\Jobs\ProcessExportRequestJob;
use App\Models\Enums\NivelEmergenciaPedido;
use App\Models\Enums\TipoArquivoPedido;
use App\Models\Escola;
use App\Models\ExportRequest;
use App\Models\Pedido;
use App\Models\PedidoArquivo;
use App\Models\Setor;
use App\Models\TipoManutencao;
use App\Models\TipoStatus;
use App\Models\User;
use App\Services\Exports\ExportRequestService;
use App\Services\Exports\Handlers\PedidoRelatorioSimplificadoExportHandler;
use App\Services\PedidoService;
use App\Services\Relatorios\PedidoRelatorioSimplificadoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class PedidoRelatorioSimplificadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_simplificada_embute_apenas_fotos_do_problema_abaixo_do_cabecalho(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put(
            'pedidos/foto-problema.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=')
        );
        Storage::disk('public')->put('pedidos/laudo-tecnico.pdf', '%PDF-1.4 teste');

        $dados = $this->criarPedidoBase();
        $pedido = $dados['pedido'];
        $usuario = $dados['usuario'];

        PedidoArquivo::create([
            'pedido_id' => $pedido->id,
            'usuario_id' => $usuario->id,
            'tipo_arquivo' => TipoArquivoPedido::FOTOS_PROBLEMA,
            'caminho' => 'pedidos/foto-problema.png',
            'nome_original' => 'foto-problema.png',
            'mime_type' => 'image/png',
        ]);

        PedidoArquivo::create([
            'pedido_id' => $pedido->id,
            'usuario_id' => $usuario->id,
            'tipo_arquivo' => TipoArquivoPedido::LAUDO,
            'caminho' => 'pedidos/laudo-tecnico.pdf',
            'nome_original' => 'laudo-tecnico.pdf',
            'mime_type' => 'application/pdf',
            'descricao' => 'Laudo da manutencao',
        ]);

        $pedido->load([
            'tipoManutencao',
            'tipoStatus',
            'escola',
            'setor',
            'empresaContratada',
            'solicitante',
            'fotos.usuario',
        ]);

        $html = view('relatorios.Manutencao.pedidos-simplificado', [
            'pedidos' => collect([$pedido]),
            'usuarioExportacao' => $usuario,
            'dataExportacao' => now(),
            'reportTitle' => 'Relatorio Simplificado de Manutencao',
            'reportSubtitle' => '1 pedido selecionado',
        ])->render();

        $this->assertStringContainsString('Protocolo:', $html);
        $this->assertStringContainsString('Descrição do pedido', $html);
        $this->assertStringContainsString('Lampada queimada na sala 1.', $html);
        $this->assertStringContainsString('Imagens do problema', $html);
        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringNotContainsString('Historico de Alteracoes', $html);
        $this->assertStringNotContainsString('Arquivos Anexados', $html);
        $this->assertStringNotContainsString('laudo-tecnico.pdf', $html);
    }

    public function test_exportacao_simplificada_entra_na_fila_com_ids_selecionados(): void
    {
        Queue::fake();

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $exportRequest = app(ExportRequestService::class)->queue(
            user: $usuario,
            type: 'pedido_relatorio_simplificado',
            format: 'pdf',
            filters: ['pedido_ids' => [20, 10]],
            label: 'PDF simplificado de pedidos',
        );

        $this->assertSame('pedido_relatorio_simplificado', $exportRequest->type);
        $this->assertSame('pdf', $exportRequest->format);
        $this->assertSame([20, 10], $exportRequest->filters['pedido_ids']);

        Queue::assertPushed(ProcessExportRequestJob::class, 1);
    }

    public function test_selecao_simplificada_e_dividida_por_mes_e_tamanho_da_parte(): void
    {
        $dados = $this->criarPedidoBase();
        $primeiro = $dados['pedido'];
        $usuario = $dados['usuario'];
        $primeiro->update(['data_solicitacao' => '2026-08-10 09:00:00']);

        $segundo = $primeiro->replicate();
        $segundo->data_solicitacao = '2026-09-10 09:00:00';
        $segundo->save();

        app()->instance(PedidoService::class, tap(Mockery::mock(PedidoService::class), function ($mock): void {
            $mock->shouldReceive('aplicarEscopoConsulta')
                ->once()
                ->andReturnUsing(static fn ($query) => $query);
        }));

        $partes = app(PedidoRelatorioSimplificadoService::class)->particionarSelecionados(
            [$primeiro->id, $segundo->id],
            $usuario,
            1,
        );

        $this->assertSame(['2026-08', '2026-09'], array_column($partes, 'periodo'));
        $this->assertSame([[$primeiro->id], [$segundo->id]], array_column($partes, 'ids'));
    }

    public function test_handler_salva_pdf_simplificado_em_armazenamento_privado(): void
    {
        Storage::fake('local');

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $exportRequest = ExportRequest::query()->create([
            'user_id' => $usuario->id,
            'type' => 'pedido_relatorio_simplificado',
            'format' => 'pdf',
            'label' => 'PDF simplificado de pedidos',
            'filters' => ['pedido_ids' => [10, 20]],
            'metadata' => [],
            'fingerprint' => fake()->uuid(),
            'status' => ExportRequest::STATUS_QUEUED,
            'status_message' => 'Aguardando processamento.',
            'progress_current' => 0,
            'progress_total' => 100,
        ]);

        app()->instance(PedidoRelatorioSimplificadoService::class, tap(Mockery::mock(PedidoRelatorioSimplificadoService::class), function ($mock): void {
            $mock->shouldReceive('particionarSelecionados')
                ->once()
                ->with([10, 20], Mockery::type(User::class), Mockery::type('int'))
                ->andReturn([
                    ['periodo' => '2026-09', 'ids' => [10, 20]],
                ]);
            $mock->shouldReceive('gerar')
                ->once()
                ->with([10, 20], Mockery::type(User::class))
                ->andReturn(response('PDF CONTENT', 200, ['Content-Type' => 'application/pdf']));
        }));

        $result = app(PedidoRelatorioSimplificadoExportHandler::class)->handle($exportRequest->load('user'));

        Storage::disk('local')->assertExists($result->path);
        $this->assertSame('application/pdf', $result->mime);
        $this->assertSame(strlen('PDF CONTENT'), $result->sizeBytes);
    }

    public function test_handler_entrega_zip_com_todas_as_partes_da_selecao(): void
    {
        if (! class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('A extensão ZIP não está disponível no ambiente de testes.');
        }

        Storage::fake('local');

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $exportRequest = ExportRequest::query()->create([
            'user_id' => $usuario->id,
            'type' => 'pedido_relatorio_simplificado',
            'format' => 'pdf',
            'label' => 'PDF simplificado de pedidos',
            'filters' => ['pedido_ids' => [10, 20]],
            'metadata' => [],
            'fingerprint' => fake()->uuid(),
            'status' => ExportRequest::STATUS_QUEUED,
            'status_message' => 'Aguardando processamento.',
            'progress_current' => 0,
            'progress_total' => 100,
        ]);

        app()->instance(PedidoRelatorioSimplificadoService::class, tap(Mockery::mock(PedidoRelatorioSimplificadoService::class), function ($mock): void {
            $mock->shouldReceive('particionarSelecionados')
                ->once()
                ->andReturn([
                    ['periodo' => '2026-08', 'ids' => [10]],
                    ['periodo' => '2026-09', 'ids' => [20]],
                ]);
            $mock->shouldReceive('gerar')
                ->twice()
                ->andReturn(
                    response('PDF CONTENT 1', 200, ['Content-Type' => 'application/pdf']),
                    response('PDF CONTENT 2', 200, ['Content-Type' => 'application/pdf']),
                );
        }));

        $result = app(PedidoRelatorioSimplificadoExportHandler::class)->handle($exportRequest->load('user'));

        $this->assertSame('application/zip', $result->mime);
        $this->assertGreaterThan(0, $result->sizeBytes);
        Storage::disk('local')->assertExists($result->path);

        $zip = new \ZipArchive();
        $this->assertSame(true, $zip->open(Storage::disk('local')->path($result->path)));
        $this->assertSame(2, $zip->numFiles);
        $this->assertNotFalse($zip->locateName('pedidos-selecionados-2026-08-parte-01.pdf'));
        $this->assertNotFalse($zip->locateName('pedidos-selecionados-2026-09-parte-02.pdf'));
        $zip->close();
    }

    public function test_handler_cancela_entre_lotes_e_remove_temporarios(): void
    {
        Storage::fake('local');

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $exportRequest = ExportRequest::query()->create([
            'user_id' => $usuario->id,
            'type' => 'pedido_relatorio_simplificado',
            'format' => 'pdf',
            'label' => 'PDF simplificado de pedidos',
            'filters' => ['pedido_ids' => [10, 20]],
            'metadata' => [],
            'fingerprint' => fake()->uuid(),
            'status' => ExportRequest::STATUS_QUEUED,
            'status_message' => 'Aguardando processamento.',
            'progress_current' => 0,
            'progress_total' => 100,
        ]);

        app()->instance(PedidoRelatorioSimplificadoService::class, tap(Mockery::mock(PedidoRelatorioSimplificadoService::class), function ($mock) use ($exportRequest): void {
            $mock->shouldReceive('particionarSelecionados')
                ->once()
                ->andReturn([
                    ['periodo' => '2026-08', 'ids' => [10]],
                    ['periodo' => '2026-09', 'ids' => [20]],
                ]);
            $mock->shouldReceive('gerar')
                ->once()
                ->andReturnUsing(function () use ($exportRequest) {
                    $exportRequest->requestCancellation();

                    return response('PDF CONTENT', 200, ['Content-Type' => 'application/pdf']);
                });
        }));

        $this->expectException(\App\Exceptions\Exports\ExportCancelledException::class);

        try {
            app(PedidoRelatorioSimplificadoExportHandler::class)->handle($exportRequest->load('user'));
        } finally {
            $this->assertSame(ExportRequest::STATUS_CANCELLED, $exportRequest->refresh()->status);
            $this->assertSame([], Storage::disk('local')->allFiles('exports-tmp/'.$exportRequest->getKey()));
        }
    }

    public function test_exportacao_simplificada_renderiza_pdf_quando_ha_imagem_webp(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('pedidos/foto-problema.webp', $this->makeWebpImage());

        $dados = $this->criarPedidoBase();
        $pedido = $dados['pedido'];
        $usuario = $dados['usuario'];

        PedidoArquivo::create([
            'pedido_id' => $pedido->id,
            'usuario_id' => $usuario->id,
            'tipo_arquivo' => TipoArquivoPedido::FOTOS_PROBLEMA,
            'caminho' => 'pedidos/foto-problema.webp',
            'nome_original' => 'foto-problema.webp',
            'mime_type' => 'image/webp',
        ]);

        app()->instance(PedidoService::class, tap(Mockery::mock(PedidoService::class), function ($mock): void {
            $mock->shouldReceive('aplicarEscopoConsulta')
                ->once()
                ->andReturnUsing(static fn ($query) => $query);
        }));

        $response = app(PedidoRelatorioSimplificadoService::class)->gerar([$pedido->id], $usuario);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_exportacao_simplificada_ignora_fotos_legadas_com_conteudo_nao_suportado(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('pedidos/video-antigo.mp4', 'conteudo de video antigo');
        Storage::disk('public')->put('pedidos/arquivo-renomeado.jpg', '%PDF-1.4 arquivo antigo renomeado');
        Storage::disk('public')->put('pedidos/documento-antigo.docx', "PK\x03\x04conteudo-docx");

        $dados = $this->criarPedidoBase();
        $pedido = $dados['pedido'];
        $usuario = $dados['usuario'];

        PedidoArquivo::withoutEvents(function () use ($pedido, $usuario): void {
            foreach ([
                ['pedidos/video-antigo.mp4', 'video-antigo.mp4'],
                ['pedidos/arquivo-renomeado.jpg', 'arquivo-renomeado.jpg'],
                ['pedidos/documento-antigo.docx', 'documento-antigo.docx'],
            ] as [$caminho, $nomeOriginal]) {
                PedidoArquivo::create([
                    'pedido_id' => $pedido->id,
                    'usuario_id' => $usuario->id,
                    'tipo_arquivo' => TipoArquivoPedido::FOTOS_PROBLEMA,
                    'caminho' => $caminho,
                    'nome_original' => $nomeOriginal,
                    'mime_type' => 'application/octet-stream',
                ]);
            }
        });

        app()->instance(PedidoService::class, tap(Mockery::mock(PedidoService::class), function ($mock): void {
            $mock->shouldReceive('aplicarEscopoConsulta')
                ->once()
                ->andReturnUsing(static fn ($query) => $query);
        }));

        $response = app(PedidoRelatorioSimplificadoService::class)->gerar([$pedido->id], $usuario);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    /**
     * @return array{pedido: Pedido, usuario: User}
     */
    private function criarPedidoBase(): array
    {
        $setor = Setor::create([
            'nome' => 'Educacao',
            'status' => 'Ativo',
            'ativo' => true,
            'is_default_root' => true,
        ]);

        $escola = Escola::create([
            'codigo' => '001',
            'nome' => 'Escola Teste',
            'setor_id' => $setor->id,
            'ativo' => true,
        ]);

        $tipo = TipoManutencao::create([
            'nome' => 'Eletrica',
            'descricao' => 'Servicos eletricos',
            'ativo' => true,
        ]);

        $status = TipoStatus::create([
            'nome' => 'Em Aberto',
            'cor' => '#3b82f6',
            'finaliza_pedido' => false,
            'cancela_pedido' => false,
            'ativo' => true,
        ]);

        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $pedido = Pedido::create([
            'tipo_manutencao_id' => $tipo->id,
            'tipo_status_id' => $status->id,
            'descricao_pedido' => 'Lampada queimada na sala 1.',
            'nome_solicitante' => 'Direcao',
            'nivel_prioridade' => NivelEmergenciaPedido::INDEFINIDO,
            'escola_id' => $escola->id,
            'solicitante_id' => $usuario->id,
            'setor_id' => $setor->id,
            'setor_origem_id' => $setor->id,
            'data_solicitacao' => now(),
            'data_identificacao_problema' => now(),
            'ativo' => true,
        ]);

        return compact('pedido', 'usuario');
    }

    private function makeWebpImage(): string
    {
        $image = imagecreatetruecolor(2, 2);
        imagefill($image, 0, 0, imagecolorallocate($image, 20, 120, 200));

        ob_start();
        imagewebp($image);
        $contents = ob_get_clean();
        imagedestroy($image);

        $this->assertIsString($contents);

        return $contents;
    }
}
