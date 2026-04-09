<?php

namespace Tests\Feature\Inventario;

use App\Models\Contrato;
use App\Models\ContratoItem;
use App\Models\EmpresaContratada;
use App\Models\Enums\InventarioPedidoStatus;
use App\Models\Enums\TipoItem;
use App\Models\Enums\TipoItemContrato;
use App\Models\Enums\TipoMovimentacao;
use App\Models\Enums\UnidadeMedida;
use App\Models\Escola;
use App\Models\Inventario;
use App\Models\InventarioEstoque;
use App\Models\InventarioMovimentacao;
use App\Models\InventarioPedido;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class InventarioRelatorioControllerTest extends TestCase
{
    use RefreshDatabase;

    protected const PERMISSAO_EXPORTAR_RELATORIOS = "Exportar Relat\xC3\xB3rios";

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_gestor_geral_consegue_exportar_planilha_de_envios_para_escolas(): void
    {
        $gestor = $this->criarCenarioRelatorioEnvios();

        $response = $this->actingAs($gestor)
            ->get(route('inventarios.relatorio.envios.xlsx', [
                'periodo' => 'mensal',
                'mes' => 1,
                'ano' => 2026,
            ]));

        $response->assertOk();
        $this->assertStringContainsString(
            '.xlsx',
            (string) $response->headers->get('content-disposition')
        );
    }

    public function test_gestor_geral_consegue_exportar_pdf_de_envios_para_escolas(): void
    {
        $gestor = $this->criarCenarioRelatorioEnvios();

        $response = $this->actingAs($gestor)
            ->get(route('inventarios.relatorio.envios.pdf', [
                'periodo' => 'geral',
            ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
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

    protected function criarPrecoReferencia(Item $item, float $precoUnitario): void
    {
        $empresa = EmpresaContratada::query()->create([
            'nome' => 'Empresa ' . $item->id,
            'cnpj' => str_pad((string) $item->id, 14, '0', STR_PAD_LEFT),
            'ativo' => true,
        ]);

        $contrato = Contrato::query()->create([
            'id_empresa_contratada' => $empresa->id,
            'numero_contrato' => 'CTR-' . str_pad((string) $item->id, 5, '0', STR_PAD_LEFT),
            'data_inicio' => now()->subDay(),
            'data_vencimento' => now()->addYear(),
            'ativo' => true,
        ]);

        ContratoItem::query()->create([
            'contrato_id' => $contrato->id,
            'item_id' => $item->id,
            'tipo' => TipoItemContrato::Compra,
            'quantidade_total' => 100.000,
            'quantidade_utilizada' => 0,
            'quantidade_reservada' => 0,
            'preco_unitario' => $precoUnitario,
        ]);
    }

    protected function criarCenarioRelatorioEnvios(): User
    {
        Permission::findOrCreate(self::PERMISSAO_EXPORTAR_RELATORIOS);
        Role::findOrCreate('Admin');

        $gestor = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $gestor->assignRole('Admin');
        $gestor->givePermissionTo(self::PERMISSAO_EXPORTAR_RELATORIOS);

        $escola = $this->criarEscola('Escola Caminho do Sol');
        $inventario = Inventario::query()->create([
            'escola_id' => $escola->id,
            'criado_por_id' => $gestor->id,
        ]);

        $item = $this->criarItem('Farinha de milho');
        $this->criarPrecoReferencia($item, 4.70);

        $estoque = InventarioEstoque::query()->create([
            'inventario_id' => $inventario->id,
            'item_id' => $item->id,
            'quantidade' => 0,
        ]);

        $pedido = InventarioPedido::query()->create([
            'inventario_id' => $inventario->id,
            'escola_id' => $escola->id,
            'status' => InventarioPedidoStatus::Entregue,
            'solicitado_por_id' => $gestor->id,
        ]);

        $movimentacao = InventarioMovimentacao::query()->create([
            'inventario_estoque_id' => $estoque->id,
            'tipo' => TipoMovimentacao::Entrada,
            'quantidade' => 12.000,
            'inventario_pedido_id' => $pedido->id,
            'observacao' => 'Entrega de teste',
            'registrado_por' => $gestor->name,
        ]);
        $movimentacao->forceFill([
            'created_at' => now()->setDate(2026, 1, 12)->setTime(10, 15),
            'updated_at' => now()->setDate(2026, 1, 12)->setTime(10, 15),
        ])->saveQuietly();

        return $gestor;
    }
}
