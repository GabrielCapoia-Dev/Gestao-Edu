<?php

namespace Tests\Feature\Inventario;

use App\Models\Contrato;
use App\Models\ContratoItem;
use App\Models\EmpresaContratada;
use App\Models\Enums\MotivoBaixa;
use App\Models\Enums\TipoItem;
use App\Models\Enums\TipoItemContrato;
use App\Models\Enums\UnidadeMedida;
use App\Models\Escola;
use App\Models\Inventario;
use App\Models\InventarioEstoque;
use App\Models\Item;
use App\Services\Inventario\InventarioDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventarioDataServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_comparativo_de_baixas_agrega_por_valor_e_filtra_por_motivo(): void
    {
        $service = app(InventarioDataService::class);

        $escolaA = $this->criarEscola('Escola Horizonte');
        $escolaB = $this->criarEscola('Escola Aurora');

        $inventarioA = Inventario::query()->create(['escola_id' => $escolaA->id]);
        $inventarioB = Inventario::query()->create(['escola_id' => $escolaB->id]);

        $itemArroz = $this->criarItem('Arroz');
        $itemLeite = $this->criarItem('Leite em po');

        $this->criarPrecoReferencia($itemArroz, 10.00);
        $this->criarPrecoReferencia($itemLeite, 6.50);

        $estoqueAArroz = InventarioEstoque::query()->create([
            'inventario_id' => $inventarioA->id,
            'item_id' => $itemArroz->id,
            'quantidade' => 20.000,
        ]);

        $estoqueALeite = InventarioEstoque::query()->create([
            'inventario_id' => $inventarioA->id,
            'item_id' => $itemLeite->id,
            'quantidade' => 10.000,
        ]);

        $estoqueBArroz = InventarioEstoque::query()->create([
            'inventario_id' => $inventarioB->id,
            'item_id' => $itemArroz->id,
            'quantidade' => 15.000,
        ]);

        $estoqueAArroz->registrarBaixa(2.000, MotivoBaixa::Avaria, 'Sacos rasgados');
        $estoqueALeite->registrarBaixa(1.000, MotivoBaixa::Perda, 'Divergencia na conferencia');
        $estoqueBArroz->registrarBaixa(1.500, MotivoBaixa::Avaria, 'Lote molhado');

        $inventarios = $service->inventariosResumo();

        $comparativoGeral = $service->comparativoBaixasPorEscola($inventarios);
        $comparativoAvaria = $service->comparativoBaixasPorEscola($inventarios, MotivoBaixa::Avaria->value);

        $this->assertCount(2, $comparativoGeral);
        $this->assertSame('Escola Horizonte', $comparativoGeral->first()['escola_nome']);
        $this->assertSame(26.50, $comparativoGeral->first()['valor_baixado']);
        $this->assertSame(2, $comparativoGeral->first()['total_baixas_filtradas']);

        $this->assertCount(2, $comparativoAvaria);
        $this->assertSame(20.00, $comparativoAvaria->first()['valor_baixado']);
        $this->assertSame(2.000, $comparativoAvaria->first()['quantidade_baixada_filtrada']);
        $this->assertSame('Avaria', $service->rotuloTipoBaixa(MotivoBaixa::Avaria->value));
        $this->assertSame('Todas as baixas', $service->rotuloTipoBaixa('todas'));
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
}
