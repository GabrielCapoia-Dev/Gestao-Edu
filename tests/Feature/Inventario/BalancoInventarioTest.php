<?php

namespace Tests\Feature\Inventario;

use App\Exceptions\ItemEmBalancoException;
use App\Models\Contrato;
use App\Models\ContratoItem;
use App\Models\EmpresaContratada;
use App\Models\Enums\BalancoInventarioStatus;
use App\Models\Enums\TipoItem;
use App\Models\Enums\TipoItemContrato;
use App\Models\Enums\TipoMovimentacao;
use App\Models\Enums\UnidadeMedida;
use App\Models\Escola;
use App\Models\Inventario;
use App\Models\InventarioEstoque;
use App\Models\Item;
use App\Models\User;
use App\Services\Inventario\BalancoInventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BalancoInventarioTest extends TestCase
{
    use RefreshDatabase;

    protected BalancoInventarioService $service;

    protected int $sequenciaContrato = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(BalancoInventarioService::class);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_it_agenda_e_inicia_balanco_com_snapshot_do_inventario_da_escola(): void
    {
        $escola = $this->criarEscola('Escola Horizonte');
        $gestor = $this->criarUsuario($escola);
        $inventario = $this->criarInventario($escola, $gestor);

        $itemSelecionado = $this->criarItem('Arroz');
        $itemFora = $this->criarItem('Feijao');

        $this->criarContratoItemComPreco($itemSelecionado, 4.25);

        $estoqueSelecionado = InventarioEstoque::query()->create([
            'inventario_id' => $inventario->id,
            'item_id' => $itemSelecionado->id,
            'quantidade' => 12.000,
        ]);

        $estoqueFora = InventarioEstoque::query()->create([
            'inventario_id' => $inventario->id,
            'item_id' => $itemFora->id,
            'quantidade' => 3.500,
        ]);

        $balanco = $this->service->agendar($inventario, [
            'data_agendada' => '2026-04-10 09:00:00',
            'observacao_inicial' => 'Balanço semanal',
        ], $gestor);

        $balanco = $this->service->iniciar($balanco, [$itemSelecionado->id], $gestor);

        $this->assertSame(BalancoInventarioStatus::EmAndamento, $balanco->status);
        $this->assertSame(2, $balanco->itens()->count());
        $this->assertSame(1, $balanco->itensContagem()->count());

        $snapshotSelecionado = $balanco->itens()->where('item_id', $itemSelecionado->id)->first();
        $snapshotFora = $balanco->itens()->where('item_id', $itemFora->id)->first();

        $this->assertTrue($snapshotSelecionado->incluido_na_contagem);
        $this->assertFalse($snapshotFora->incluido_na_contagem);
        $this->assertSame($estoqueSelecionado->id, $snapshotSelecionado->inventario_estoque_id);
        $this->assertSame($estoqueFora->id, $snapshotFora->inventario_estoque_id);
        $this->assertSame('12.000', $snapshotSelecionado->saldo_sistema_antes);
        $this->assertSame('3.500', $snapshotFora->saldo_sistema_antes);
        $this->assertSame('4.25', $snapshotSelecionado->valor_unitario_referencia);
    }

    public function test_it_bloqueia_movimentacoes_apenas_no_mesmo_inventario_em_balanco(): void
    {
        $gestor = $this->criarUsuario();
        $escolaA = $this->criarEscola('Escola A');
        $escolaB = $this->criarEscola('Escola B');
        $inventarioA = $this->criarInventario($escolaA, $gestor);
        $inventarioB = $this->criarInventario($escolaB, $gestor);
        $item = $this->criarItem('Leite');

        $estoqueA = InventarioEstoque::query()->create([
            'inventario_id' => $inventarioA->id,
            'item_id' => $item->id,
            'quantidade' => 5.000,
        ]);

        $estoqueB = InventarioEstoque::query()->create([
            'inventario_id' => $inventarioB->id,
            'item_id' => $item->id,
            'quantidade' => 1.000,
        ]);

        $balanco = $this->service->agendar($inventarioA, ['data_agendada' => '2026-04-10 09:00:00'], $gestor);
        $this->service->iniciar($balanco, [$item->id], $gestor);

        try {
            $estoqueA->entrada(1.000, null, 'Entrada bloqueada');
            $this->fail('A movimentação do inventário em balanço deveria ter sido bloqueada.');
        } catch (ItemEmBalancoException $exception) {
            $this->assertStringContainsString('Leite', $exception->getMessage());
        }

        $estoqueB->entrada(2.000, null, 'Entrada liberada');
        $estoqueB->refresh();

        $this->assertSame('3.000', $estoqueB->quantidade);
    }

    public function test_it_conclui_balanco_e_gera_movimentacoes_de_reajuste_no_inventario(): void
    {
        $escola = $this->criarEscola('Escola Aurora');
        $gestor = $this->criarUsuario($escola);
        $this->actingAs($gestor);

        $inventario = $this->criarInventario($escola, $gestor);
        $itemEntrada = $this->criarItem('Aveia');
        $itemSaida = $this->criarItem('Farinha');

        $this->criarContratoItemComPreco($itemEntrada, 5.50);
        $this->criarContratoItemComPreco($itemSaida, 2.00);

        $estoqueEntrada = InventarioEstoque::query()->create([
            'inventario_id' => $inventario->id,
            'item_id' => $itemEntrada->id,
            'quantidade' => 10.000,
        ]);

        $estoqueSaida = InventarioEstoque::query()->create([
            'inventario_id' => $inventario->id,
            'item_id' => $itemSaida->id,
            'quantidade' => 8.000,
        ]);

        $balanco = $this->service->agendar($inventario, ['data_agendada' => '2026-04-10 09:00:00'], $gestor);
        $balanco = $this->service->iniciar($balanco, [$itemEntrada->id, $itemSaida->id], $gestor);

        $this->service->registrarContagem($balanco->itens()->where('item_id', $itemEntrada->id)->first(), 12.000, 'Encontrado a mais', $gestor);
        $this->service->registrarContagem($balanco->itens()->where('item_id', $itemSaida->id)->first(), 5.000, 'Perda operacional', $gestor);

        $balanco = $this->service->concluir($balanco, $gestor);

        $this->assertSame(BalancoInventarioStatus::Concluido, $balanco->status);

        $estoqueEntrada->refresh();
        $estoqueSaida->refresh();

        $this->assertSame('12.000', $estoqueEntrada->quantidade);
        $this->assertSame('5.000', $estoqueSaida->quantidade);

        $this->assertDatabaseHas('inventario_movimentacoes', [
            'inventario_estoque_id' => $estoqueEntrada->id,
            'tipo' => TipoMovimentacao::Entrada->value,
            'quantidade' => 2.000,
        ]);

        $this->assertDatabaseHas('inventario_movimentacoes', [
            'inventario_estoque_id' => $estoqueSaida->id,
            'tipo' => TipoMovimentacao::Saida->value,
            'quantidade' => 3.000,
        ]);

        $this->assertDatabaseHas('balanco_inventario_itens', [
            'balanco_inventario_id' => $balanco->id,
            'item_id' => $itemEntrada->id,
            'saldo_final' => 12.000,
            'diferenca' => 2.000,
            'valor_unitario_referencia' => 5.50,
            'valor_impacto' => 11.00,
        ]);

        $this->assertDatabaseHas('balanco_inventario_itens', [
            'balanco_inventario_id' => $balanco->id,
            'item_id' => $itemSaida->id,
            'saldo_final' => 5.000,
            'diferenca' => -3.000,
            'valor_unitario_referencia' => 2.00,
            'valor_impacto' => -6.00,
        ]);
    }

    public function test_rotas_de_visualizacao_e_relatorio_funcionam_para_gestor_da_escola(): void
    {
        $permissoes = [
            'Listar Balanços de Inventário',
            'Criar Balanços de Inventário',
            'Iniciar Balanços de Inventário',
            'Registrar Contagem de Balanços de Inventário',
            'Concluir Balanços de Inventário',
            'Adiar Balanços de Inventário',
            'Cancelar Balanços de Inventário',
        ];

        $escola = $this->criarEscola('Escola Solar');
        $gestor = $this->criarUsuario($escola, $permissoes);
        $inventario = $this->criarInventario($escola, $gestor);
        $item = $this->criarItem('Café');

        $this->criarContratoItemComPreco($item, 3.30);

        InventarioEstoque::query()->create([
            'inventario_id' => $inventario->id,
            'item_id' => $item->id,
            'quantidade' => 6.000,
        ]);

        $balanco = $this->service->agendar($inventario, ['data_agendada' => '2026-04-10 09:00:00'], $gestor);
        $balanco = $this->service->iniciar($balanco, [$item->id], $gestor);

        $this->actingAs($gestor)
            ->get(route('filament.admin.resources.balancos-inventario.view', ['record' => $balanco]))
            ->assertOk()
            ->assertSee($balanco->codigo);

        $this->actingAs($gestor)
            ->get(route('balancos-inventario.relatorio.pdf', ['balanco' => $balanco]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    protected function criarUsuario(?Escola $escola = null, array $permissoes = []): User
    {
        $user = User::factory()->create([
            'id_escola' => $escola?->id,
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        foreach ($permissoes as $permissao) {
            Permission::findOrCreate($permissao);
        }

        if ($permissoes !== []) {
            $user->givePermissionTo($permissoes);
        }

        return $user;
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

    protected function criarInventario(Escola $escola, User $user): Inventario
    {
        return Inventario::query()->create([
            'escola_id' => $escola->id,
            'criado_por_id' => $user->id,
        ]);
    }

    protected function criarItem(string $nome): Item
    {
        return Item::query()->create([
            'nome' => $nome,
            'descricao' => "Item {$nome}",
            'tipo_item' => TipoItem::CerealDerivado,
            'unidade_medida' => UnidadeMedida::Quilograma,
            'ativo' => true,
        ]);
    }

    protected function criarContratoItemComPreco(Item $item, float $precoUnitario): ContratoItem
    {
        $sequencia = $this->sequenciaContrato++;

        $empresa = EmpresaContratada::query()->create([
            'nome' => "Empresa {$sequencia}",
            'cnpj' => str_pad((string) $sequencia, 14, '0', STR_PAD_LEFT),
            'ativo' => true,
        ]);

        $contrato = Contrato::query()->create([
            'id_empresa_contratada' => $empresa->id,
            'numero_contrato' => 'CTR-' . str_pad((string) $sequencia, 5, '0', STR_PAD_LEFT),
            'data_inicio' => now()->subDay(),
            'data_vencimento' => now()->addYear(),
            'ativo' => true,
        ]);

        return ContratoItem::query()->create([
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
