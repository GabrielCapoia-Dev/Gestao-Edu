<?php

namespace Tests\Feature\Manutencao;

use App\Models\Enums\NivelEmergenciaPedido;
use App\Models\Enums\TipoArquivoPedido;
use App\Models\Escola;
use App\Models\Pedido;
use App\Models\PedidoArquivo;
use App\Models\Setor;
use App\Models\TipoManutencao;
use App\Models\TipoStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PedidoRelatorioViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_relatorio_embute_fotos_e_lista_arquivos_anexados(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put(
            'pedidos/foto-problema.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=')
        );
        Storage::disk('public')->put('pedidos/laudo-tecnico.pdf', '%PDF-1.4 teste');

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

        $usuario = User::factory()->create();

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
            'solicitante',
            'historicos.statusAnterior',
            'historicos.statusNovo',
            'historicos.usuario',
            'historicos.setor',
            'arquivos.usuario',
            'problemas',
            'pedidosAdicionais.arquivos.usuario',
            'ultimoFeedback.itens.problema',
        ]);

        $html = view('relatorios.Manutencao.pedido', [
            'pedido' => $pedido,
            'usuarioExportacao' => $usuario,
            'dataExportacao' => now(),
            'reportTitle' => 'Relatorio Tecnico de Manutencao',
            'reportSubtitle' => "Protocolo {$pedido->numero_protocolo}",
        ])->render();

        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringContainsString('Arquivos Anexados', $html);
        $this->assertStringContainsString('laudo-tecnico.pdf', $html);
        $this->assertStringContainsString('Laudo da manutencao', $html);
    }
}
