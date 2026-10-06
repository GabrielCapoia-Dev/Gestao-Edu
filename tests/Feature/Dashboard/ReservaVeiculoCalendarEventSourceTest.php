<?php

namespace Tests\Feature\Dashboard;

use App\Models\Enums\ListaPermissoes;
use App\Models\Enums\ReservaVeiculoStatus;
use App\Models\Escola;
use App\Models\Permission;
use App\Models\ReservaVeiculo;
use App\Models\User;
use App\Models\VeiculoTransporte;
use App\Services\Dashboard\Calendar\Sources\ReservaVeiculoCalendarEventSource;
use App\Support\Dashboard\Calendar\CalendarQueryContext;
use App\Support\Dashboard\DashboardUserContext;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReservaVeiculoCalendarEventSourceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_reserva_de_escola_aparece_para_a_unidade_e_outro_local_fica_fora_do_escopo_local(): void
    {
        config()->set('permission.cache.store', 'array');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $criador = User::factory()->create();
        $usuarioEscola = User::factory()->create();
        $escola = Escola::query()->create([
            'codigo' => 'ESC-AGENDA-RESERVA',
            'nome' => 'Escola da Agenda',
            'ativo' => true,
        ]);
        $veiculo = VeiculoTransporte::query()->create([
            'placa' => 'DEF4G56',
            'identificacao' => 'Van Pedagógica',
            'cor' => '#A855F7',
            'capacidade_passageiros' => 12,
            'ativo' => true,
        ]);

        $this->reserva($criador, $veiculo, $escola, 'Visita à escola', 'Escola da Agenda');
        $this->reserva($criador, $veiculo, null, 'Atividade externa', 'Prefeitura', '13:00', '14:00');

        $contexto = new CalendarQueryContext(
            user: $usuarioEscola,
            userContext: $this->contextoUsuario($usuarioEscola, [$escola->id]),
            inicio: CarbonImmutable::parse('2026-07-29 00:00:00'),
            fim: CarbonImmutable::parse('2026-07-29 23:59:59'),
        );

        $eventos = collect(app(ReservaVeiculoCalendarEventSource::class)->events($contexto));

        $this->assertCount(1, $eventos);
        $this->assertSame('Van Pedagógica', $eventos->first()->titulo);
        $this->assertSame('Escola da Agenda', $eventos->first()->escola);
        $this->assertSame('Visita à escola', $eventos->first()->resumo);
        $this->assertSame('#A855F7', $eventos->first()->corDestaque);
        $this->assertSame($criador->name, $eventos->first()->solicitante);
        $this->assertFalse($eventos->first()->reservadaPeloUsuario);
    }

    public function test_reserva_indica_se_foi_criada_pelo_usuario_efetivo_do_contexto(): void
    {
        $usuario = User::factory()->create();
        $veiculo = VeiculoTransporte::query()->create([
            'placa' => 'OWN1R23',
            'identificacao' => 'Veículo Próprio',
            'capacidade_passageiros' => 5,
            'ativo' => true,
        ]);
        $this->reserva($usuario, $veiculo, null, 'Reserva própria', 'Secretaria');

        $contexto = new CalendarQueryContext(
            user: $usuario,
            userContext: $this->contextoUsuario($usuario),
            inicio: CarbonImmutable::parse('2026-07-29 00:00:00'),
            fim: CarbonImmutable::parse('2026-07-29 23:59:59'),
        );

        $evento = collect(app(ReservaVeiculoCalendarEventSource::class)->events($contexto))->first();

        $this->assertTrue($evento?->reservadaPeloUsuario);
        $this->assertTrue($evento?->toArray()['reservada_pelo_usuario']);
    }

    public function test_filtro_de_veiculos_exibe_todas_as_reservas_para_usuario_autorizado(): void
    {
        config()->set('permission.cache.store', 'array');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $usuario = User::factory()->create();
        Permission::findOrCreate(ListaPermissoes::ListarReservasVeiculos->label(), 'web');
        $usuario->givePermissionTo(ListaPermissoes::ListarReservasVeiculos->label());
        $veiculo = VeiculoTransporte::query()->create([
            'placa' => 'GHI7J89',
            'identificacao' => 'Automóvel 02',
            'cor' => '#F97316',
            'capacidade_passageiros' => 5,
            'ativo' => true,
        ]);
        $solicitante = User::factory()->create();
        $this->reserva($solicitante, $veiculo, null, 'Reunião externa', 'Paço Municipal');

        $contexto = new CalendarQueryContext(
            user: $usuario,
            userContext: $this->contextoUsuario($usuario),
            inicio: CarbonImmutable::parse('2026-07-29 00:00:00'),
            fim: CarbonImmutable::parse('2026-07-29 23:59:59'),
            somenteReservasVeiculos: true,
        );

        $eventos = collect(app(ReservaVeiculoCalendarEventSource::class)->events($contexto));

        $this->assertCount(1, $eventos);
        $this->assertSame('veiculos', $eventos->first()->categoria);
        $this->assertSame('Paço Municipal', $eventos->first()->local);
        $this->assertSame('#F97316', $eventos->first()->corDestaque);
        $this->assertSame($solicitante->name, $eventos->first()->solicitante);
    }

    public function test_calendario_preserva_reserva_concluida_e_oculta_cancelada_no_historico(): void
    {
        $usuario = User::factory()->create();
        $veiculo = VeiculoTransporte::query()->create([
            'placa' => 'HIS1T23',
            'identificacao' => 'Veículo Histórico',
            'capacidade_passageiros' => 5,
            'ativo' => true,
        ]);

        $this->reserva(
            $usuario,
            $veiculo,
            null,
            'Reserva concluída',
            'Secretaria',
            status: ReservaVeiculoStatus::CONCLUIDA,
        );
        $this->reserva(
            $usuario,
            $veiculo,
            null,
            'Reserva cancelada',
            'Prefeitura',
            inicio: '13:00',
            fim: '14:00',
            status: ReservaVeiculoStatus::CANCELADA,
        );

        $contexto = new CalendarQueryContext(
            user: $usuario,
            userContext: $this->contextoUsuario($usuario),
            inicio: CarbonImmutable::parse('2026-07-29 00:00:00'),
            fim: CarbonImmutable::parse('2026-07-29 23:59:59'),
        );

        $eventos = collect(app(ReservaVeiculoCalendarEventSource::class)->events($contexto));

        $this->assertCount(1, $eventos);
        $this->assertSame('Reserva concluída', $eventos->first()->resumo);
        $this->assertSame(ReservaVeiculoStatus::CONCLUIDA->value, $eventos->first()->status);
        $this->assertSame(ReservaVeiculoStatus::CONCLUIDA->label(), $eventos->first()->statusLabel);
    }

    public function test_agenda_nao_atualiza_o_banco_e_mapeia_reserva_iniciada_como_concluida(): void
    {
        Carbon::setTestNow('2026-07-29 09:00:00');
        CarbonImmutable::setTestNow('2026-07-29 09:00:00');

        $usuario = User::factory()->create();
        $veiculo = VeiculoTransporte::query()->create([
            'placa' => 'PER1F23',
            'identificacao' => 'Veículo Performance',
            'capacidade_passageiros' => 5,
            'ativo' => true,
        ]);
        $reserva = $this->reserva($usuario, $veiculo, null, 'Atividade em andamento', 'Secretaria', '08:00', '10:00');
        $contexto = new CalendarQueryContext(
            user: $usuario,
            userContext: $this->contextoUsuario($usuario),
            inicio: CarbonImmutable::parse('2026-07-29 00:00:00'),
            fim: CarbonImmutable::parse('2026-07-29 23:59:59'),
        );

        $evento = collect(app(ReservaVeiculoCalendarEventSource::class)->events($contexto))->first();

        $this->assertSame(ReservaVeiculoStatus::CONCLUIDA->value, $evento?->status);
        $this->assertSame(ReservaVeiculoStatus::CONCLUIDA->label(), $evento?->statusLabel);
        $this->assertDatabaseHas('reservas_veiculos', [
            'id' => $reserva->id,
            'status' => ReservaVeiculoStatus::ATIVA->value,
        ]);
    }

    private function reserva(
        User $usuario,
        VeiculoTransporte $veiculo,
        ?Escola $escola,
        string $atividade,
        string $local,
        string $inicio = '08:00',
        string $fim = '10:00',
        ReservaVeiculoStatus $status = ReservaVeiculoStatus::ATIVA,
    ): ReservaVeiculo {
        return ReservaVeiculo::query()->create([
            'veiculo_transporte_id' => $veiculo->id,
            'usuario_id' => $usuario->id,
            'escola_id' => $escola?->id,
            'local_nome' => $local,
            'atividade' => $atividade,
            'data_inicio' => "2026-07-29 {$inicio}:00",
            'data_fim' => "2026-07-29 {$fim}:00",
            'status' => $status,
            'criado_por_id' => $usuario->id,
            'atualizado_por_id' => $usuario->id,
        ]);
    }

    /** @param list<int> $escolaIds */
    private function contextoUsuario(User $usuario, array $escolaIds = []): DashboardUserContext
    {
        return new DashboardUserContext(
            userId: $usuario->id,
            escopoGlobal: false,
            roleIds: [],
            permissionIds: [],
            funcaoAdministrativaIds: [],
            escolaIds: $escolaIds,
            setorIds: [],
            setorVisivelIds: [],
        );
    }
}
