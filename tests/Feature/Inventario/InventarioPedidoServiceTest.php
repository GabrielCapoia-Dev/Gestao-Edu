<?php

namespace Tests\Feature\Inventario;

use App\Models\Enums\InventarioPedidoStatus;
use App\Models\Enums\TipoItem;
use App\Models\Enums\UnidadeMedida;
use App\Models\Escola;
use App\Models\Estoque;
use App\Models\Inventario;
use App\Models\InventarioEstoque;
use App\Models\Item;
use App\Models\User;
use App\Services\Inventario\InventarioPedidoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventarioPedidoServiceTest extends TestCase
{
    use RefreshDatabase;

    protected InventarioPedidoService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(InventarioPedidoService::class);
    }

    public function test_fluxo_completo_do_pedido_ao_recebimento_atualiza_matriz_e_inventario(): void
    {
        $escola = $this->criarEscola('Escola Jardim das Flores');
        $gestorEscola = $this->criarUsuario(idEscola: $escola->id);
        $gestorGeral = $this->criarUsuario();
        Role::findOrCreate('Admin');
        $gestorGeral->assignRole('Admin');

        $inventario = Inventario::query()->create([
            'escola_id' => $escola->id,
            'criado_por_id' => $gestorGeral->id,
        ]);

        $itemArroz = $this->criarItem('Arroz');
        $itemFeijao = $this->criarItem('Feijao');

        Estoque::query()->create([
            'item_id' => $itemArroz->id,
            'quantidade' => 120.000,
            'quantidade_reservada' => 0,
        ]);

        Estoque::query()->create([
            'item_id' => $itemFeijao->id,
            'quantidade' => 45.000,
            'quantidade_reservada' => 0,
        ]);

        $pedido = $this->service->criarPedido($gestorEscola, [
            'observacao_escola' => 'Reposicao semanal da unidade.',
            'itens' => [
                [
                    'item_id' => $itemArroz->id,
                    'quantidade_solicitada' => 30,
                    'observacao_solicitacao' => 'Uso no almoco.',
                ],
                [
                    'item_id' => $itemFeijao->id,
                    'quantidade_solicitada' => 15,
                    'observacao_solicitacao' => 'Separar por lote novo.',
                ],
            ],
        ]);

        $this->assertSame(InventarioPedidoStatus::Pendente, $pedido->status);
        $this->assertCount(2, $pedido->itens);

        $pedido = $this->service->aprovarPedido($pedido, [
            [
                'item_id' => $itemArroz->id,
                'quantidade_aprovada' => 25,
                'observacao_aprovacao' => 'Ajustado pela disponibilidade atual.',
            ],
            [
                'item_id' => $itemFeijao->id,
                'quantidade_aprovada' => 10,
                'observacao_aprovacao' => 'Liberado parcialmente.',
            ],
        ], 'Pedido aprovado com ajuste por saldo da matriz.', $gestorGeral);

        $this->assertSame(InventarioPedidoStatus::Aprovado, $pedido->status);

        $romaneio = $this->service->gerarRomaneio([$pedido->id], 'Carga da semana 15.', $gestorGeral);

        $pedido->refresh();

        $this->assertNotNull($romaneio->codigo);
        $this->assertSame(InventarioPedidoStatus::EmAndamento, $pedido->status);
        $this->assertSame($romaneio->id, $pedido->inventario_romaneio_id);
        $this->assertDatabaseHas('estoque', [
            'item_id' => $itemArroz->id,
            'quantidade_reservada' => 25.000,
        ]);
        $this->assertDatabaseHas('estoque', [
            'item_id' => $itemFeijao->id,
            'quantidade_reservada' => 10.000,
        ]);

        $pedido = $this->service->conferirEntrega($pedido, [
            [
                'item_id' => $itemArroz->id,
                'quantidade_recebida' => 25,
            ],
            [
                'item_id' => $itemFeijao->id,
                'quantidade_recebida' => 8,
            ],
        ], 'Feijao chegou com divergencia no volume entregue.', $gestorEscola);

        $this->assertSame(InventarioPedidoStatus::Entregue, $pedido->status);
        $this->assertSame('Feijao chegou com divergencia no volume entregue.', $pedido->observacao_conferencia);

        $this->assertDatabaseHas('estoque', [
            'item_id' => $itemArroz->id,
            'quantidade' => 95.000,
            'quantidade_reservada' => 0.000,
        ]);

        $this->assertDatabaseHas('estoque', [
            'item_id' => $itemFeijao->id,
            'quantidade' => 37.000,
            'quantidade_reservada' => 0.000,
        ]);

        $estoqueArrozInventario = InventarioEstoque::query()
            ->where('inventario_id', $inventario->id)
            ->where('item_id', $itemArroz->id)
            ->first();

        $estoqueFeijaoInventario = InventarioEstoque::query()
            ->where('inventario_id', $inventario->id)
            ->where('item_id', $itemFeijao->id)
            ->first();

        $this->assertNotNull($estoqueArrozInventario);
        $this->assertNotNull($estoqueFeijaoInventario);
        $this->assertSame('25.000', $estoqueArrozInventario->quantidade);
        $this->assertSame('8.000', $estoqueFeijaoInventario->quantidade);
        $this->assertDatabaseCount('inventario_movimentacoes', 2);
    }

    public function test_nao_permite_conferencia_com_diferenca_sem_observacao(): void
    {
        $escola = $this->criarEscola('Escola Esperanca');
        $gestorEscola = $this->criarUsuario(idEscola: $escola->id);
        $gestorGeral = $this->criarUsuario();
        Role::findOrCreate('Admin');
        $gestorGeral->assignRole('Admin');

        $inventario = Inventario::query()->create([
            'escola_id' => $escola->id,
            'criado_por_id' => $gestorGeral->id,
        ]);

        $item = $this->criarItem('Macarrao');

        Estoque::query()->create([
            'item_id' => $item->id,
            'quantidade' => 20.000,
            'quantidade_reservada' => 0,
        ]);

        $pedido = $this->service->criarPedido($gestorEscola, [
            'itens' => [
                [
                    'item_id' => $item->id,
                    'quantidade_solicitada' => 10,
                ],
            ],
        ]);

        $pedido = $this->service->aprovarPedido($pedido, [
            [
                'item_id' => $item->id,
                'quantidade_aprovada' => 10,
            ],
        ], null, $gestorGeral);

        $this->service->gerarRomaneio([$pedido->id], null, $gestorGeral);

        $pedido->refresh();

        $this->expectException(\DomainException::class);

        $this->service->conferirEntrega($pedido, [
            [
                'item_id' => $item->id,
                'quantidade_recebida' => 9,
            ],
        ], null, $gestorEscola);
    }

    protected function criarEscola(string $nome): Escola
    {
        return Escola::query()->create([
            'codigo' => strtoupper(substr(md5($nome), 0, 5)),
            'nome' => $nome,
            'email' => strtolower(str_replace(' ', '.', $nome)) . '@teste.local',
            'telefone' => '(44) 99999-9999',
            'logradouro' => 'Rua Principal',
            'numero' => '100',
            'bairro' => 'Centro',
            'cep' => '87500-000',
            'cidade' => 'Umuarama',
            'estado' => 'PR',
            'ativo' => true,
        ]);
    }

    protected function criarUsuario(?int $idEscola = null): User
    {
        return User::factory()->create([
            'id_escola' => $idEscola,
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
    }

    protected function criarItem(string $nome): Item
    {
        return Item::query()->create([
            'nome' => $nome,
            'descricao' => 'Item ' . $nome,
            'tipo_item' => TipoItem::CerealDerivado,
            'unidade_medida' => UnidadeMedida::Quilograma,
            'ativo' => true,
        ]);
    }
}
