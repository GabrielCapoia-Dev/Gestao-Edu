<?php

namespace Tests\Feature\Dashboard;

use App\Models\Enums\ListaPermissoes;
use App\Models\Enums\ReservaVeiculoStatus;
use App\Models\Escola;
use App\Models\Permission;
use App\Models\ReservaVeiculo;
use App\Models\User;
use App\Models\VeiculoTransporte;
use App\Services\Dashboard\ReservaVeiculoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReservaVeiculoServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReservaVeiculoService $service;

    private User $usuario;

    private VeiculoTransporte $veiculo;

    private Escola $escola;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('permission.cache.store', 'array');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $this->darPermissoes(
            ListaPermissoes::ListarReservasVeiculos,
            ListaPermissoes::CriarReservasVeiculos,
            ListaPermissoes::EditarReservasVeiculos,
            ListaPermissoes::CancelarReservasVeiculos,
        );

        $this->veiculo = VeiculoTransporte::query()->create([
            'placa' => 'ABC1D23',
            'identificacao' => 'Carro 01',
            'capacidade_passageiros' => 5,
            'ativo' => true,
        ]);
        $this->escola = Escola::query()->create([
            'codigo' => 'ESC-RESERVA',
            'nome' => 'Escola Destino',
            'ativo' => true,
        ]);
        $this->service = app(ReservaVeiculoService::class);
    }

    public function test_cria_reservas_para_varios_dias_com_servidor_e_destino_automaticos(): void
    {
        $reservas = $this->service->criarEmLote($this->usuario, [
            'datas' => ['2026-07-28', '2026-07-30'],
            'hora_inicio' => '08:00',
            'hora_fim' => '10:00',
            'atividade' => 'Acompanhamento pedagógico',
            'tipo_local' => 'escola',
            'escola_id' => $this->escola->id,
            'local_outro' => null,
            'veiculo_transporte_id' => $this->veiculo->id,
        ]);

        $this->assertCount(2, $reservas);
        $this->assertNotNull($reservas->first()->grupo_recorrencia);
        $this->assertSame(
            $reservas->first()->grupo_recorrencia,
            $reservas->last()->grupo_recorrencia,
        );
        $this->assertTrue($reservas->every(
            fn (ReservaVeiculo $reserva): bool => $reserva->usuario_id === $this->usuario->id
                && $reserva->escola_id === $this->escola->id
                && $reserva->local_nome === 'Escola Destino'
                && $reserva->status === ReservaVeiculoStatus::ATIVA,
        ));
    }

    public function test_impede_conflito_de_horario_e_permite_intervalo_adjacente(): void
    {
        $this->service->criarEmLote($this->usuario, $this->dados([
            'hora_inicio' => '08:00',
            'hora_fim' => '10:00',
        ]));

        try {
            $this->service->criarEmLote($this->usuario, $this->dados([
                'hora_inicio' => '09:30',
                'hora_fim' => '11:00',
            ]));
            $this->fail('Era esperado um conflito de disponibilidade.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'O veículo já possui uma reserva neste dia e horário.',
                $exception->errors()['veiculo_transporte_id'][0],
            );
        }

        $adjacente = $this->service->criarEmLote($this->usuario, $this->dados([
            'hora_inicio' => '10:00',
            'hora_fim' => '11:00',
        ]));

        $this->assertCount(1, $adjacente);
        $this->assertDatabaseCount('reservas_veiculos', 2);
    }

    public function test_cancelamento_libera_o_veiculo_e_preserva_o_historico(): void
    {
        $reserva = $this->service->criarEmLote($this->usuario, $this->dados())->firstOrFail();

        $cancelada = $this->service->cancelar(
            $this->usuario,
            $reserva,
            'Mudança de planejamento.',
        );

        $this->assertSame(ReservaVeiculoStatus::CANCELADA, $cancelada->status);
        $this->assertNotNull($cancelada->cancelado_em);
        $this->assertSame($this->usuario->id, $cancelada->cancelado_por_id);

        $nova = $this->service->criarEmLote($this->usuario, $this->dados());

        $this->assertCount(1, $nova);
        $this->assertDatabaseCount('reservas_veiculos', 2);
    }

    /** @return array<string, mixed> */
    private function dados(array $sobrescrever = []): array
    {
        return [
            'datas' => ['2026-07-29'],
            'hora_inicio' => '08:00',
            'hora_fim' => '10:00',
            'atividade' => 'Visita técnica',
            'tipo_local' => 'outros',
            'escola_id' => null,
            'local_outro' => 'Secretaria Municipal',
            'veiculo_transporte_id' => $this->veiculo->id,
            ...$sobrescrever,
        ];
    }

    private function darPermissoes(ListaPermissoes ...$permissoes): void
    {
        foreach ($permissoes as $permissao) {
            Permission::findOrCreate($permissao->label(), 'web');
        }

        $this->usuario->givePermissionTo(array_map(
            fn (ListaPermissoes $permissao): string => $permissao->label(),
            $permissoes,
        ));
    }
}
