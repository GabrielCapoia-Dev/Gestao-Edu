<?php

namespace Tests\Feature\Seeders;

use App\Models\BalancoEstoque;
use App\Models\BalancoInventario;
use App\Models\BaixasEstoques;
use App\Models\Enums\InventarioPedidoStatus;
use App\Models\Escola;
use App\Models\Estoque;
use App\Models\EstoqueMovimentacao;
use App\Models\Inventario;
use App\Models\InventarioBaixa;
use App\Models\InventarioMovimentacao;
use App\Models\InventarioPedido;
use App\Models\InventarioRomaneio;
use App\Models\Item;
use Database\Seeders\EstoqueInventarioOrganicoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstoqueInventarioOrganicoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_popula_as_areas_principais_do_fluxo_de_estoque_e_inventario(): void
    {
        $this->seed(EstoqueInventarioOrganicoSeeder::class);

        $this->assertGreaterThan(0, Escola::query()->count());
        $this->assertGreaterThan(0, Item::query()->count());
        $this->assertSame(
            Escola::query()->where('ativo', true)->count(),
            Inventario::query()->count(),
        );

        $this->assertGreaterThan(0, Estoque::query()->count());
        $this->assertGreaterThan(Item::query()->count(), EstoqueMovimentacao::query()->count());
        $this->assertGreaterThan(0, BaixasEstoques::query()->count());
        $this->assertTrue(Estoque::query()->where('quantidade_reservada', '>', 0)->exists());

        $this->assertGreaterThan(0, InventarioPedido::query()->count());
        $this->assertGreaterThan(0, InventarioRomaneio::query()->count());
        $this->assertGreaterThan(0, InventarioMovimentacao::query()->count());
        $this->assertGreaterThan(0, InventarioBaixa::query()->count());

        $this->assertDatabaseHas('inventario_pedidos', ['status' => InventarioPedidoStatus::Pendente->value]);
        $this->assertDatabaseHas('inventario_pedidos', ['status' => InventarioPedidoStatus::Aprovado->value]);
        $this->assertDatabaseHas('inventario_pedidos', ['status' => InventarioPedidoStatus::EmAndamento->value]);
        $this->assertDatabaseHas('inventario_pedidos', ['status' => InventarioPedidoStatus::Entregue->value]);
        $this->assertDatabaseHas('inventario_pedidos', ['status' => InventarioPedidoStatus::Recusado->value]);

        $this->assertGreaterThan(0, BalancoEstoque::query()->count());
        $this->assertGreaterThan(0, BalancoInventario::query()->count());
    }
}
