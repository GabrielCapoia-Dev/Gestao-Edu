<?php

namespace Tests\Feature\Estoque;

use App\Exceptions\ItemEmBalancoException;
use App\Models\Contrato;
use App\Models\ContratoItem;
use App\Models\EmpresaContratada;
use App\Models\Enums\BalancoEstoqueEventoTipo;
use App\Models\Enums\BalancoEstoqueStatus;
use App\Models\Enums\TipoItem;
use App\Models\Enums\TipoItemContrato;
use App\Models\Enums\TipoMovimentacao;
use App\Models\Enums\UnidadeMedida;
use App\Models\Estoque;
use App\Models\Item;
use App\Models\User;
use App\Services\Estoque\BalancoEstoqueService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BalancoEstoqueTest extends TestCase
{
    use RefreshDatabase;

    protected BalancoEstoqueService $service;

    protected int $sequenciaContrato = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(BalancoEstoqueService::class);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_it_agenda_e_adia_balanco_com_eventos(): void
    {
        $user = $this->criarUsuario();

        $balanco = $this->service->agendar([
            'data_agendada' => '2026-04-10 09:00:00',
            'observacao_inicial' => 'Balanco semanal',
        ], $user);

        $this->assertSame(BalancoEstoqueStatus::Agendado, $balanco->status);
        $this->assertSame('Balanco semanal', $balanco->observacao_inicial);
        $this->assertNotNull($balanco->codigo);
        $this->assertDatabaseHas('balanco_estoque_eventos', [
            'balanco_estoque_id' => $balanco->id,
            'tipo' => BalancoEstoqueEventoTipo::Criado->value,
            'usuario_id' => $user->id,
        ]);

        $balanco = $this->service->adiar($balanco, Carbon::parse('2026-04-12 14:30:00'), 'Equipe indisponivel', $user);

        $this->assertSame('2026-04-12 14:30', $balanco->data_agendada?->format('Y-m-d H:i'));
        $this->assertDatabaseHas('balanco_estoque_eventos', [
            'balanco_estoque_id' => $balanco->id,
            'tipo' => BalancoEstoqueEventoTipo::Adiado->value,
            'descricao' => 'Equipe indisponivel',
            'usuario_id' => $user->id,
        ]);
    }

    public function test_it_inicia_balanco_com_snapshot_de_itens_selecionados_e_fora(): void
    {
        $user = $this->criarUsuario();
        $itemSelecionado = $this->criarItem('Arroz');
        $itemFora = $this->criarItem('Feijao');
        $itemInativoComEstoque = $this->criarItem('Estoque legado', ativo: false);

        $this->criarContratoItemComPreco($itemSelecionado, 4.25);

        Estoque::query()->create([
            'item_id' => $itemSelecionado->id,
            'quantidade' => 12.000,
        ]);

        Estoque::query()->create([
            'item_id' => $itemInativoComEstoque->id,
            'quantidade' => 3.500,
        ]);

        $balanco = $this->service->agendar([
            'data_agendada' => '2026-04-10 09:00:00',
        ], $user);

        $balanco = $this->service->iniciar($balanco, [$itemSelecionado->id], $user);

        $this->assertSame(BalancoEstoqueStatus::EmAndamento, $balanco->status);
        $this->assertSame(3, $balanco->itens()->count());
        $this->assertSame(1, $balanco->itensContagem()->count());
        $this->assertSame(2, $balanco->itensFora()->count());

        $snapshotSelecionado = $balanco->itens()->where('item_id', $itemSelecionado->id)->first();
        $snapshotFora = $balanco->itens()->where('item_id', $itemFora->id)->first();
        $snapshotLegado = $balanco->itens()->where('item_id', $itemInativoComEstoque->id)->first();

        $this->assertTrue($snapshotSelecionado->incluido_na_contagem);
        $this->assertFalse($snapshotFora->incluido_na_contagem);
        $this->assertFalse($snapshotLegado->incluido_na_contagem);
        $this->assertSame('12.000', $snapshotSelecionado->saldo_sistema_antes);
        $this->assertSame('0.000', $snapshotFora->saldo_sistema_antes);
        $this->assertSame('3.500', $snapshotLegado->saldo_sistema_antes);
        $this->assertSame('4.25', $snapshotSelecionado->valor_unitario_referencia);
    }

    public function test_it_impede_inicio_quando_item_ja_esta_em_outro_balanco_em_andamento(): void
    {
        $user = $this->criarUsuario();
        $item = $this->criarItem('Macarrao');

        $primeiro = $this->service->agendar(['data_agendada' => '2026-04-10 09:00:00'], $user);
        $this->service->iniciar($primeiro, [$item->id], $user);

        $segundo = $this->service->agendar(['data_agendada' => '2026-04-11 09:00:00'], $user);

        $this->expectException(\DomainException::class);

        $this->service->iniciar($segundo, [$item->id], $user);
    }

    public function test_it_bloqueia_movimentacoes_para_item_em_balanco_ativo_e_permite_outro_item(): void
    {
        $user = $this->criarUsuario();
        $this->actingAs($user);

        $itemBloqueado = $this->criarItem('Leite');
        $itemLivre = $this->criarItem('Suco');

        $estoqueBloqueado = Estoque::query()->create([
            'item_id' => $itemBloqueado->id,
            'quantidade' => 5.000,
        ]);

        $estoqueLivre = Estoque::query()->create([
            'item_id' => $itemLivre->id,
            'quantidade' => 1.000,
        ]);

        $balanco = $this->service->agendar(['data_agendada' => '2026-04-10 09:00:00'], $user);
        $this->service->iniciar($balanco, [$itemBloqueado->id], $user);

        $this->expectException(ItemEmBalancoException::class);
        $estoqueBloqueado->entrada(1.000, null, 'Entrada manual');

        $estoqueLivre->refresh();
        $estoqueLivre->entrada(2.000, null, 'Entrada liberada');
        $estoqueLivre->refresh();

        $this->assertSame('3.000', $estoqueLivre->quantidade);
    }

    public function test_it_calculates_financial_impact_when_registering_count(): void
    {
        $user = $this->criarUsuario();
        $item = $this->criarItem('Aveia');

        $this->criarContratoItemComPreco($item, 4.20);

        Estoque::query()->create([
            'item_id' => $item->id,
            'quantidade' => 10.000,
        ]);

        $balanco = $this->service->agendar(['data_agendada' => '2026-04-10 09:00:00'], $user);
        $balanco = $this->service->iniciar($balanco, [$item->id], $user);

        $registro = $this->service->registrarContagem(
            $balanco->itens()->where('item_id', $item->id)->first(),
            7.000,
            'Contagem revisada',
            $user,
        );

        $this->assertSame('-3.000', $registro->diferenca);
        $this->assertSame('4.20', $registro->valor_unitario_referencia);
        $this->assertSame('-12.60', $registro->valor_impacto);
    }

    public function test_it_can_keep_current_balance_in_batch_for_selected_items(): void
    {
        $user = $this->criarUsuario();
        $itemA = $this->criarItem('Granola');
        $itemB = $this->criarItem('Mel');

        $this->criarContratoItemComPreco($itemA, 6.00);
        $this->criarContratoItemComPreco($itemB, 8.50);

        Estoque::query()->create([
            'item_id' => $itemA->id,
            'quantidade' => 15.000,
        ]);

        Estoque::query()->create([
            'item_id' => $itemB->id,
            'quantidade' => 4.000,
        ]);

        $balanco = $this->service->agendar(['data_agendada' => '2026-04-10 09:00:00'], $user);
        $balanco = $this->service->iniciar($balanco, [$itemA->id, $itemB->id], $user);

        $registroA = $balanco->itens()->where('item_id', $itemA->id)->first();
        $registroB = $balanco->itens()->where('item_id', $itemB->id)->first();

        $this->service->registrarContagem($registroA, 12.000, 'Contagem divergente', $user);

        $processados = $this->service->manterSaldoAtualEmLote([$registroA, $registroB], $user);

        $this->assertSame(2, $processados);

        $registroA->refresh();
        $registroB->refresh();

        $this->assertSame('15.000', $registroA->quantidade_contada);
        $this->assertSame('0.000', $registroA->diferenca);
        $this->assertSame('0.00', $registroA->valor_impacto);
        $this->assertSame('Mantido saldo atual', $registroA->observacao_contagem);

        $this->assertSame('4.000', $registroB->quantidade_contada);
        $this->assertSame('0.000', $registroB->diferenca);
        $this->assertSame('0.00', $registroB->valor_impacto);
        $this->assertSame('Mantido saldo atual', $registroB->observacao_contagem);
    }

    public function test_it_conclui_balanco_e_gera_movimentacoes_de_reajuste_e_impacto_financeiro(): void
    {
        $user = $this->criarUsuario();
        $this->actingAs($user);

        $itemEntrada = $this->criarItem('Arroz integral');
        $itemSaida = $this->criarItem('Farinha');
        $itemSemEstoque = $this->criarItem('Milho');

        $this->criarContratoItemComPreco($itemEntrada, 5.50);
        $this->criarContratoItemComPreco($itemSaida, 2.00);
        $this->criarContratoItemComPreco($itemSemEstoque, 1.25);

        $estoqueEntrada = Estoque::query()->create([
            'item_id' => $itemEntrada->id,
            'quantidade' => 10.000,
        ]);

        $estoqueSaida = Estoque::query()->create([
            'item_id' => $itemSaida->id,
            'quantidade' => 8.000,
        ]);

        $balanco = $this->service->agendar(['data_agendada' => '2026-04-10 09:00:00'], $user);
        $balanco = $this->service->iniciar($balanco, [$itemEntrada->id, $itemSaida->id, $itemSemEstoque->id], $user);

        $this->service->registrarContagem($balanco->itens()->where('item_id', $itemEntrada->id)->first(), 12.000, 'Encontrado a mais', $user);
        $this->service->registrarContagem($balanco->itens()->where('item_id', $itemSaida->id)->first(), 5.000, 'Perda operacional', $user);
        $this->service->registrarContagem($balanco->itens()->where('item_id', $itemSemEstoque->id)->first(), 4.000, 'Item sem saldo virtual', $user);

        $balanco = $this->service->concluir($balanco, $user);

        $this->assertSame(BalancoEstoqueStatus::Concluido, $balanco->status);
        $this->assertSame(10.00, $balanco->fresh()->impacto_financeiro_total);

        $estoqueEntrada->refresh();
        $estoqueSaida->refresh();
        $estoqueCriado = Estoque::query()->where('item_id', $itemSemEstoque->id)->first();

        $this->assertSame('12.000', $estoqueEntrada->quantidade);
        $this->assertSame('5.000', $estoqueSaida->quantidade);
        $this->assertSame('4.000', $estoqueCriado->quantidade);

        $this->assertDatabaseHas('estoque_movimentacoes', [
            'estoque_id' => $estoqueEntrada->id,
            'tipo' => TipoMovimentacao::Entrada->value,
            'quantidade' => 2.000,
        ]);

        $this->assertDatabaseHas('estoque_movimentacoes', [
            'estoque_id' => $estoqueSaida->id,
            'tipo' => TipoMovimentacao::Saida->value,
            'quantidade' => 3.000,
        ]);

        $this->assertDatabaseHas('balanco_estoque_itens', [
            'balanco_estoque_id' => $balanco->id,
            'item_id' => $itemEntrada->id,
            'saldo_final' => 12.000,
            'diferenca' => 2.000,
            'valor_unitario_referencia' => 5.50,
            'valor_impacto' => 11.00,
        ]);

        $this->assertDatabaseHas('balanco_estoque_itens', [
            'balanco_estoque_id' => $balanco->id,
            'item_id' => $itemSaida->id,
            'valor_unitario_referencia' => 2.00,
            'valor_impacto' => -6.00,
        ]);

        $this->assertDatabaseHas('balanco_estoque_itens', [
            'balanco_estoque_id' => $balanco->id,
            'item_id' => $itemSemEstoque->id,
            'valor_unitario_referencia' => 1.25,
            'valor_impacto' => 5.00,
        ]);
    }

    public function test_it_cancela_sem_ajustar_saldo_mesmo_com_contagens_lancadas(): void
    {
        $user = $this->criarUsuario();
        $item = $this->criarItem('Acucar');
        $estoque = Estoque::query()->create([
            'item_id' => $item->id,
            'quantidade' => 7.000,
        ]);

        $balanco = $this->service->agendar(['data_agendada' => '2026-04-10 09:00:00'], $user);
        $balanco = $this->service->iniciar($balanco, [$item->id], $user);

        $this->service->registrarContagem($balanco->itens()->first(), 3.000, 'Contagem parcial', $user);
        $balanco = $this->service->cancelar($balanco, 'Estoque fechado para manutencao', $user);

        $estoque->refresh();

        $this->assertSame(BalancoEstoqueStatus::Cancelado, $balanco->status);
        $this->assertSame('7.000', $estoque->quantidade);
        $this->assertDatabaseHas('balanco_estoque_eventos', [
            'balanco_estoque_id' => $balanco->id,
            'tipo' => BalancoEstoqueEventoTipo::Cancelado->value,
            'descricao' => 'Estoque fechado para manutencao',
        ]);
    }

    public function test_view_route_loads_balance_detail(): void
    {
        $user = $this->criarUsuario([
            'Listar Balanços de Estoque',
            'Iniciar Balanços de Estoque',
            'Registrar Contagem de Balanços de Estoque',
            'Concluir Balanços de Estoque',
            'Adiar Balanços de Estoque',
            'Cancelar Balanços de Estoque',
        ]);

        $item = $this->criarItem('Cafe');
        $this->criarContratoItemComPreco($item, 3.30);

        $balanco = $this->service->agendar(['data_agendada' => '2026-04-10 09:00:00'], $user);
        $balanco = $this->service->iniciar($balanco, [$item->id], $user);

        $response = $this
            ->actingAs($user)
            ->get(route('filament.admin.resources.balancos-estoque.view', ['record' => $balanco]));

        $response->assertOk();
        $response->assertSee($balanco->codigo);
    }

    public function test_export_route_downloads_balance_report_pdf(): void
    {
        $user = $this->criarUsuario(['Listar Balanços de Estoque']);
        $item = $this->criarItem('Aveia');

        $this->criarContratoItemComPreco($item, 4.20);

        Estoque::query()->create([
            'item_id' => $item->id,
            'quantidade' => 9.000,
        ]);

        $balanco = $this->service->agendar(['data_agendada' => '2026-04-10 09:00:00'], $user);
        $balanco = $this->service->iniciar($balanco, [$item->id], $user);
        $this->service->registrarContagem($balanco->itens()->first(), 10.000, 'Ajuste identificado', $user);

        $response = $this
            ->actingAs($user)
            ->get(route('balancos-estoque.relatorio.pdf', ['balanco' => $balanco]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader('content-disposition');
    }

    public function test_command_notifies_overdue_balances_once_per_day(): void
    {
        $user = $this->criarUsuario(['Visualizar Notificação: Balanço de Estoque']);

        $balanco = $this->service->agendar([
            'data_agendada' => now()->subHours(2)->format('Y-m-d H:i:s'),
        ], $user);

        Artisan::call('app:notificar-balancos-estoque-vencidos');
        Artisan::call('app:notificar-balancos-estoque-vencidos');

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'notifiable_type' => User::class,
        ]);

        $this->assertSame(BalancoEstoqueStatus::Agendado, $balanco->fresh()->status);
    }

    protected function criarUsuario(array $permissoes = []): User
    {
        $user = User::factory()->create([
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

    protected function criarItem(string $nome, bool $ativo = true): Item
    {
        return Item::query()->create([
            'nome' => $nome,
            'descricao' => "Item {$nome}",
            'tipo_item' => TipoItem::Fruta,
            'unidade_medida' => UnidadeMedida::Quilograma,
            'ativo' => $ativo,
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
