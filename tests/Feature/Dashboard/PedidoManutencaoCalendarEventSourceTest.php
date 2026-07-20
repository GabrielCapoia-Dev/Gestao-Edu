<?php

namespace Tests\Feature\Dashboard;

use App\Models\Enums\NivelEmergenciaPedido;
use App\Models\Escola;
use App\Models\Pedido;
use App\Models\Permission;
use App\Models\Setor;
use App\Models\TipoManutencao;
use App\Models\TipoStatus;
use App\Models\User;
use App\Services\Dashboard\Calendar\Sources\PedidoManutencaoCalendarEventSource;
use App\Services\Dashboard\DashboardUserContextFactory;
use App\Support\Dashboard\Calendar\CalendarEventData;
use App\Support\Dashboard\Calendar\CalendarQueryContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PedidoManutencaoCalendarEventSourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_fonte_de_pedidos_restringe_escopo_e_exclui_adicionais_entregues_inativos_e_status_finais(): void
    {
        $agora = CarbonImmutable::parse('2026-07-20 10:00:00');
        CarbonImmutable::setTestNow($agora);
        $setor = $this->criarSetor('Setor da agenda de manutenção');
        $escolaA = $this->criarEscola('Manutenção Agenda A', $setor);
        $escolaB = $this->criarEscola('Manutenção Agenda B', $setor);
        $userA = User::factory()->create([
            'id_escola' => $escolaA->id,
            'setor_id' => $setor->id,
        ]);
        $userB = User::factory()->create([
            'id_escola' => $escolaB->id,
            'setor_id' => $setor->id,
        ]);
        $this->darPermissaoDeListagem($userA);
        $this->darPermissaoDeListagem($userB);
        $tipo = TipoManutencao::query()->create([
            'nome' => 'Elétrica da agenda',
            'descricao' => 'Manutenção elétrica',
            'ativo' => true,
        ]);
        $aberto = $this->criarStatus('Em Manutenção', finaliza: false, cancela: false);
        $final = $this->criarStatus('Concluído', finaliza: true, cancela: false);
        $cancelado = $this->criarStatus('Cancelado', finaliza: false, cancela: true);
        $prevista = $agora->addDays(2);
        $elegivel = $this->criarPedido($userA, $escolaA, $setor, $tipo, $aberto, 'Pedido elegível', $prevista);

        $this->criarPedido($userA, $escolaA, $setor, $tipo, $aberto, 'Pedido adicional', $prevista, adicional: true, principal: $elegivel);
        $this->criarPedido($userA, $escolaA, $setor, $tipo, $aberto, 'Pedido entregue', $prevista, entregue: true);
        $this->criarPedido($userA, $escolaA, $setor, $tipo, $final, 'Pedido finalizado', $prevista);
        $this->criarPedido($userA, $escolaA, $setor, $tipo, $cancelado, 'Pedido cancelado', $prevista);
        $this->criarPedido($userA, $escolaA, $setor, $tipo, $aberto, 'Pedido inativo', $prevista, ativo: false);
        $this->criarPedido($userA, $escolaA, $setor, $tipo, $aberto, 'Pedido fora do período', $agora->addDays(20));
        $this->criarPedido($userB, $escolaB, $setor, $tipo, $aberto, 'Pedido de outra escola', $prevista);
        $this->criarPedido($userA, $escolaA, $setor, $tipo, $aberto, 'Pedido sem previsão', null);

        $contextA = $this->contexto(
            $userA,
            $agora,
            $agora->addDays(6)->endOfDay(),
            escolaId: $escolaA->id,
            setorId: $setor->id,
        );
        $contextB = $this->contexto($userB, $agora, $agora->addDays(6)->endOfDay());
        $source = app(PedidoManutencaoCalendarEventSource::class);

        $events = collect($source->events($contextA));

        $this->assertTrue($source->supports($contextA));
        $this->assertSame([$elegivel->id], $events->map(
            static fn (CalendarEventData $event): int => (int) $event->reference,
        )->all());
        $this->assertSame($escolaA->id, $events->first()->escolaId);
        $this->assertSame($setor->id, $events->first()->setorId);
        $this->assertSame('urgente', $events->first()->prioridade->value);
        $this->assertNull($events->first()->progresso);
        $this->assertSame($elegivel->numero_protocolo, $source->detail(
            $contextA,
            (string) $elegivel->id,
        )?->metadata['Protocolo']);
        $this->assertNull($source->detail($contextB, (string) $elegivel->id));
    }

    public function test_detail_rejeita_referencias_forjadas_de_pedidos_nao_elegiveis(): void
    {
        $agora = CarbonImmutable::parse('2026-07-20 10:00:00');
        CarbonImmutable::setTestNow($agora);
        $setor = $this->criarSetor('Setor de proteção do detalhe');
        $escola = $this->criarEscola('Escola de proteção do detalhe', $setor);
        $usuario = User::factory()->create([
            'id_escola' => $escola->id,
            'setor_id' => $setor->id,
        ]);
        $this->darPermissaoDeListagem($usuario);
        $tipo = TipoManutencao::query()->create([
            'nome' => 'Tipo protegido da agenda',
            'descricao' => 'Tipo usado na regressão de autorização.',
            'ativo' => true,
        ]);
        $aberto = $this->criarStatus('Aberto protegido', finaliza: false, cancela: false);
        $finalizado = $this->criarStatus('Finalizado protegido', finaliza: true, cancela: false);
        $prevista = $agora->addDays(2);
        $principal = $this->criarPedido(
            $usuario,
            $escola,
            $setor,
            $tipo,
            $aberto,
            'Pedido principal permitido',
            $prevista,
        );
        $adicional = $this->criarPedido(
            $usuario,
            $escola,
            $setor,
            $tipo,
            $aberto,
            'Pedido adicional forjado',
            $prevista,
            adicional: true,
            principal: $principal,
        );
        $entregue = $this->criarPedido(
            $usuario,
            $escola,
            $setor,
            $tipo,
            $aberto,
            'Pedido entregue forjado',
            $prevista,
            entregue: true,
        );
        $final = $this->criarPedido(
            $usuario,
            $escola,
            $setor,
            $tipo,
            $finalizado,
            'Pedido finalizado forjado',
            $prevista,
        );
        $contexto = $this->contexto($usuario, $agora, $agora->addDays(6)->endOfDay());
        $source = app(PedidoManutencaoCalendarEventSource::class);

        $this->assertNotNull($source->detail($contexto, (string) $principal->id));
        $this->assertNull($source->detail($contexto, (string) $adicional->id));
        $this->assertNull($source->detail($contexto, (string) $entregue->id));
        $this->assertNull($source->detail($contexto, (string) $final->id));
    }

    private function contexto(
        User $user,
        CarbonImmutable $inicio,
        CarbonImmutable $fim,
        ?int $escolaId = null,
        ?int $setorId = null,
    ): CalendarQueryContext {
        return new CalendarQueryContext(
            user: $user,
            userContext: app(DashboardUserContextFactory::class)->make($user),
            inicio: $inicio->startOfDay(),
            fim: $fim->endOfDay(),
            escolaId: $escolaId,
            setorId: $setorId,
        );
    }

    private function darPermissaoDeListagem(User $user): void
    {
        $user->givePermissionTo(Permission::findOrCreate('Listar Pedidos', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function criarSetor(string $nome): Setor
    {
        return Setor::query()->create([
            'nome' => $nome,
            'ativo' => true,
            'status' => 'ativo',
            'contexto' => 'administrativo',
            'exige_vinculo_escola' => false,
        ]);
    }

    private function criarEscola(string $nome, Setor $setor): Escola
    {
        return Escola::query()->create([
            'codigo' => strtoupper(substr(md5($nome), 0, 8)),
            'nome' => $nome,
            'email' => str($nome)->slug('.').'@teste.local',
            'setor_id' => $setor->id,
            'ativo' => true,
        ]);
    }

    private function criarStatus(string $nome, bool $finaliza, bool $cancela): TipoStatus
    {
        return TipoStatus::query()->create([
            'nome' => $nome,
            'cor' => '#64748b',
            'finaliza_pedido' => $finaliza,
            'cancela_pedido' => $cancela,
            'ativo' => true,
        ]);
    }

    private function criarPedido(
        User $solicitante,
        Escola $escola,
        Setor $setor,
        TipoManutencao $tipo,
        TipoStatus $status,
        string $descricao,
        ?CarbonImmutable $dataPrevista,
        bool $adicional = false,
        ?Pedido $principal = null,
        bool $entregue = false,
        bool $ativo = true,
    ): Pedido {
        return Pedido::query()->create([
            'pedido_principal_id' => $principal?->id,
            'is_pedido_adicional' => $adicional,
            'descricao_pedido' => $descricao,
            'nome_solicitante' => $solicitante->name,
            'tipo_manutencao_id' => $tipo->id,
            'tipo_status_id' => $status->id,
            'nivel_prioridade' => NivelEmergenciaPedido::EMERGENCIAL,
            'escola_id' => $escola->id,
            'solicitante_id' => $solicitante->id,
            'setor_id' => $setor->id,
            'setor_origem_id' => $setor->id,
            'data_solicitacao' => '2026-07-18',
            'data_identificacao_problema' => '2026-07-18',
            'data_prevista' => $dataPrevista?->toDateString(),
            'data_entrega' => $entregue ? '2026-07-20' : null,
            'ativo' => $ativo,
        ]);
    }
}
