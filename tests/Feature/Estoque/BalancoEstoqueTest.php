<?php

namespace Tests\Feature\Estoque;

use App\Exceptions\ItemEmBalancoException;
use App\Models\BalancoEstoque;
use App\Models\Enums\BalancoEstoqueEventoTipo;
use App\Models\Enums\BalancoEstoqueStatus;
use App\Models\Enums\TipoItem;
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
            'observacao_inicial' => 'Balanço semanal',
        ], $user);

        $this->assertSame(BalancoEstoqueStatus::Agendado, $balanco->status);
        $this->assertSame('Balanço semanal', $balanco->observacao_inicial);
        $this->assertNotNull($balanco->codigo);
        $this->assertDatabaseHas('balanco_estoque_eventos', [
            'balanco_estoque_id' => $balanco->id,
            'tipo' => BalancoEstoqueEventoTipo::Criado->value,
            'usuario_id' => $user->id,
        ]);

        $balanco = $this->service->adiar($balanco, Carbon::parse('2026-04-12 14:30:00'), 'Equipe indisponível', $user);

        $this->assertSame('2026-04-12 14:30', $balanco->data_agendada?->format('Y-m-d H:i'));
        $this->assertDatabaseHas('balanco_estoque_eventos', [
            'balanco_estoque_id' => $balanco->id,
            'tipo' => BalancoEstoqueEventoTipo::Adiado->value,
            'descricao' => 'Equipe indisponível',
            'usuario_id' => $user->id,
        ]);
    }

    public function test_it_inicia_balanco_com_snapshot_de_itens_selecionados_e_fora(): void
    {
        $user = $this->criarUsuario();
        $itemSelecionado = $this->criarItem('Arroz');
        $itemFora = $this->criarItem('Feijão');
        $itemInativoComEstoque = $this->criarItem('Estoque legado', ativo: false);

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
    }

    public function test_it_impede_inicio_quando_item_ja_esta_em_outro_balanco_em_andamento(): void
    {
        $user = $this->criarUsuario();
        $item = $this->criarItem('Macarrão');

        $primeiro = $this->service->agendar(['data_agendada' => '2026-04-10 09:00:00'], $user);
        $this->service->iniciar($primeiro, [$item->id], $user);

        $segundo = $this->service->agendar(['data_agendada' => '2026-04-11 09:00:00'], $user);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('já estão em outro balanço em andamento');

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

    public function test_it_conclui_balanco_e_gera_movimentacoes_de_reajuste(): void
    {
        $user = $this->criarUsuario();
        $this->actingAs($user);

        $itemEntrada = $this->criarItem('Arroz Integral');
        $itemSaida = $this->criarItem('Farinha');
        $itemSemEstoque = $this->criarItem('Milho');

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
            'observacao' => "Reajustado via Balanço #{$balanco->codigo}",
        ]);

        $this->assertDatabaseHas('estoque_movimentacoes', [
            'estoque_id' => $estoqueSaida->id,
            'tipo' => TipoMovimentacao::Saida->value,
            'quantidade' => 3.000,
            'observacao' => "Reajustado via Balanço #{$balanco->codigo}",
        ]);

        $this->assertDatabaseHas('balanco_estoque_itens', [
            'balanco_estoque_id' => $balanco->id,
            'item_id' => $itemEntrada->id,
            'saldo_final' => 12.000,
            'diferenca' => 2.000,
        ]);
    }

    public function test_it_cancela_sem_ajustar_saldo_mesmo_com_contagens_lancadas(): void
    {
        $user = $this->criarUsuario();
        $item = $this->criarItem('Açúcar');
        $estoque = Estoque::query()->create([
            'item_id' => $item->id,
            'quantidade' => 7.000,
        ]);

        $balanco = $this->service->agendar(['data_agendada' => '2026-04-10 09:00:00'], $user);
        $balanco = $this->service->iniciar($balanco, [$item->id], $user);

        $this->service->registrarContagem($balanco->itens()->first(), 3.000, 'Contagem parcial', $user);
        $balanco = $this->service->cancelar($balanco, 'Estoque fechado para manutenção', $user);

        $estoque->refresh();

        $this->assertSame(BalancoEstoqueStatus::Cancelado, $balanco->status);
        $this->assertSame('7.000', $estoque->quantidade);
        $this->assertDatabaseHas('balanco_estoque_eventos', [
            'balanco_estoque_id' => $balanco->id,
            'tipo' => BalancoEstoqueEventoTipo::Cancelado->value,
            'descricao' => 'Estoque fechado para manutenção',
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

        $item = $this->criarItem('Café');
        $balanco = $this->service->agendar(['data_agendada' => '2026-04-10 09:00:00'], $user);
        $balanco = $this->service->iniciar($balanco, [$item->id], $user);

        $response = $this
            ->actingAs($user)
            ->get(route('filament.admin.resources.balancos-estoque.view', ['record' => $balanco]));

        $response->assertOk();
        $response->assertSee($balanco->codigo);
        $response->assertSee('Timeline do Balanço');
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
}
