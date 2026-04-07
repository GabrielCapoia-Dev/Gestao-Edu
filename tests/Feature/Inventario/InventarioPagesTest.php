<?php

namespace Tests\Feature\Inventario;

use App\Models\Enums\InventarioPedidoStatus;
use App\Models\Enums\TipoItem;
use App\Models\Enums\UnidadeMedida;
use App\Models\Escola;
use App\Models\Inventario;
use App\Models\InventarioPedido;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class InventarioPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_gestor_geral_consegue_acessar_dashboard_macro_e_pedido(): void
    {
        $permissoes = [
            'Listar Inventários',
            'Listar Gestão de Inventário',
            'Listar Pedidos de Inventário',
            'Aprovar Pedidos de Inventário',
            'Gerar Romaneios de Inventário',
            'Exportar Relatórios',
        ];

        foreach ($permissoes as $permissao) {
            Permission::findOrCreate($permissao);
        }

        Role::findOrCreate('Admin');

        $gestor = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $gestor->assignRole('Admin');
        $gestor->givePermissionTo($permissoes);

        $escola = $this->criarEscola('Escola Horizonte');
        $inventario = Inventario::query()->create([
            'escola_id' => $escola->id,
            'criado_por_id' => $gestor->id,
        ]);

        $item = $this->criarItem('Farinha');

        $pedido = InventarioPedido::query()->create([
            'inventario_id' => $inventario->id,
            'escola_id' => $escola->id,
            'status' => InventarioPedidoStatus::Pendente,
            'solicitado_por_id' => $gestor->id,
        ]);

        $pedido->itens()->create([
            'item_id' => $item->id,
            'quantidade_solicitada' => 8,
            'status' => 'pendente',
        ]);

        $this->actingAs($gestor)
            ->get(route('filament.admin.pages.inventarios'))
            ->assertOk()
            ->assertSee('Panorama Geral dos Inventários');

        $this->actingAs($gestor)
            ->get(route('filament.admin.resources.pedidos-inventario.view', ['record' => $pedido]))
            ->assertOk()
            ->assertSee('Resumo do Pedido');
    }

    public function test_gestor_escolar_consegue_acessar_gestao_do_proprio_inventario(): void
    {
        $permissoes = [
            'Listar Gestão de Inventário',
            'Listar Pedidos de Inventário',
            'Criar Pedidos de Inventário',
            'Conferir Pedidos de Inventário',
            'Exportar Relatórios',
        ];

        foreach ($permissoes as $permissao) {
            Permission::findOrCreate($permissao);
        }

        $escola = $this->criarEscola('Escola Aurora');

        $gestor = User::factory()->create([
            'id_escola' => $escola->id,
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $gestor->givePermissionTo($permissoes);

        Inventario::query()->create([
            'escola_id' => $escola->id,
            'criado_por_id' => $gestor->id,
        ]);

        $this->actingAs($gestor)
            ->get(route('filament.admin.pages.gestao-inventario'))
            ->assertOk()
            ->assertSee('Inventário Escolar');

        $this->actingAs($gestor)
            ->get(route('filament.admin.resources.pedidos-inventario.index'))
            ->assertOk();
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
