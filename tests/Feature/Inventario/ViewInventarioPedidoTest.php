<?php

namespace Tests\Feature\Inventario;

use App\Filament\Admin\Resources\InventarioPedidos\Pages\ViewInventarioPedido;
use App\Models\Enums\InventarioPedidoItemStatus;
use App\Models\Enums\InventarioPedidoStatus;
use App\Models\Enums\TipoItem;
use App\Models\Enums\UnidadeMedida;
use App\Models\Escola;
use App\Models\Inventario;
use App\Models\InventarioPedido;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class ViewInventarioPedidoTest extends TestCase
{
    use RefreshDatabase;

    public function test_dados_da_conferencia_ignoram_itens_recusados_sem_erro_de_enum(): void
    {
        $gestor = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $escola = $this->criarEscola('Escola das Flores');
        $inventario = Inventario::query()->create([
            'escola_id' => $escola->id,
            'criado_por_id' => $gestor->id,
        ]);

        $pedido = InventarioPedido::query()->create([
            'inventario_id' => $inventario->id,
            'escola_id' => $escola->id,
            'status' => InventarioPedidoStatus::EmAndamento,
            'solicitado_por_id' => $gestor->id,
        ]);

        $itemAprovado = $this->criarItem('Arroz');
        $itemRecusado = $this->criarItem('Feijao');

        $pedido->itens()->create([
            'item_id' => $itemAprovado->id,
            'quantidade_solicitada' => 10,
            'quantidade_aprovada' => 8,
            'status' => InventarioPedidoItemStatus::Aprovado,
        ]);

        $pedido->itens()->create([
            'item_id' => $itemRecusado->id,
            'quantidade_solicitada' => 5,
            'quantidade_aprovada' => 0,
            'status' => InventarioPedidoItemStatus::Recusado,
        ]);

        $page = app(ViewInventarioPedido::class);

        $recordProperty = new ReflectionProperty($page, 'record');
        $recordProperty->setAccessible(true);
        $recordProperty->setValue($page, $pedido->fresh());

        $method = new ReflectionMethod($page, 'dadosConferenciaPedido');
        $method->setAccessible(true);

        $dados = $method->invoke($page);

        $this->assertCount(1, $dados['itens']);
        $this->assertSame($itemAprovado->id, $dados['itens'][0]['item_id']);
        $this->assertSame('Arroz - KG', $dados['itens'][0]['item_nome']);
        $this->assertSame(8.0, $dados['itens'][0]['quantidade_aprovada']);
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
