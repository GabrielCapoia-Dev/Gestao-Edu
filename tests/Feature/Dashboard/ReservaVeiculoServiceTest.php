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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
        $dataInicial = today()->addDay();
        $dataFinal = today()->addDays(3);
        $reservas = $this->service->criarEmLote($this->usuario, [
            'data_inicial' => $dataInicial->toDateString(),
            'reservar_varios_dias' => true,
            'data_final' => $dataFinal->toDateString(),
            'hora_inicio' => '08:00',
            'hora_fim' => '10:00',
            'atividade' => 'Acompanhamento pedagógico',
            'tipo_local' => 'escola',
            'escola_id' => $this->escola->id,
            'local_outro' => null,
            'veiculo_transporte_id' => $this->veiculo->id,
        ]);

        $this->assertCount(3, $reservas);
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
        $this->assertSame(
            [
                $dataInicial->toDateString(),
                $dataInicial->copy()->addDay()->toDateString(),
                $dataFinal->toDateString(),
            ],
            $reservas->map(fn (ReservaVeiculo $reserva): string => $reserva->data_inicio->toDateString())->all(),
        );
    }

    public function test_cria_repeticao_semanal_nos_dias_selecionados_e_agrupa_as_ocorrencias(): void
    {
        $inicio = today()->next(Carbon::MONDAY);
        $fim = $inicio->copy()->addDays(14);

        $reservas = $this->service->criarEmLote($this->usuario, $this->dados([
            'data_inicial' => $inicio->toDateString(),
            'data_final' => $fim->toDateString(),
            'repeticao' => 'personalizada',
            'repetir_a_cada' => 1,
            'unidade_repeticao' => 'semana',
            'dias_semana' => [1, 3],
            'fim_repeticao' => 'data',
        ]));

        $this->assertSame([
            $inicio->toDateString(),
            $inicio->copy()->addDays(2)->toDateString(),
            $inicio->copy()->addDays(7)->toDateString(),
            $inicio->copy()->addDays(9)->toDateString(),
            $inicio->copy()->addDays(14)->toDateString(),
        ], $reservas->map(fn (ReservaVeiculo $reserva): string => $reserva->data_inicio->toDateString())->all());
        $this->assertCount(1, $reservas->pluck('grupo_recorrencia')->unique());
    }

    public function test_todos_os_dias_usa_intervalo_e_ignora_configuracao_antiga_de_ocorrencias(): void
    {
        $inicio = today()->addDays(2);
        $reservas = $this->service->criarEmLote($this->usuario, $this->dados([
            'data_inicial' => $inicio->toDateString(),
            'data_final' => $inicio->copy()->addDays(3)->toDateString(),
            'repeticao' => 'diaria',
            'fim_repeticao' => 'ocorrencias',
            'quantidade_ocorrencias' => null,
        ]));

        $this->assertCount(4, $reservas);
        $this->assertSame(
            $inicio->copy()->addDays(3)->toDateString(),
            $reservas->last()->data_inicio->toDateString(),
        );
    }

    public function test_repeticao_personalizada_pode_terminar_por_numero_de_ocorrencias(): void
    {
        $inicio = today()->next(Carbon::MONDAY);
        $reservas = $this->service->criarEmLote($this->usuario, $this->dados([
            'data_inicial' => $inicio->toDateString(),
            'data_final' => null,
            'repeticao' => 'personalizada',
            'unidade_repeticao' => 'semana',
            'dias_semana' => [$inicio->dayOfWeekIso],
            'fim_repeticao' => 'ocorrencias',
            'quantidade_ocorrencias' => 4,
        ]));

        $this->assertCount(4, $reservas);
        $this->assertSame(
            $inicio->copy()->addWeeks(3)->toDateString(),
            $reservas->last()->data_inicio->toDateString(),
        );
    }

    public function test_conflito_em_uma_ocorrencia_da_repeticao_impede_a_serie_inteira(): void
    {
        $inicio = today()->addDays(2);
        $this->service->criarEmLote($this->usuario, $this->dados([
            'data_inicial' => $inicio->copy()->addDays(2)->toDateString(),
        ]));

        try {
            $this->service->criarEmLote($this->usuario, $this->dados([
                'data_inicial' => $inicio->toDateString(),
                'data_final' => $inicio->copy()->addDays(4)->toDateString(),
                'repeticao' => 'diaria',
                'fim_repeticao' => 'data',
            ]));
            $this->fail('A série deveria ser rejeitada por conflito em uma ocorrência.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('veiculo_transporte_id', $exception->errors());
        }

        $this->assertDatabaseCount('reservas_veiculos', 1);
    }

    public function test_nao_aceita_os_tipos_de_repeticao_removidos_da_interface(): void
    {
        foreach (['dias_uteis', 'anual'] as $repeticaoRemovida) {
            try {
                $this->service->criarEmLote($this->usuario, $this->dados([
                    'repeticao' => $repeticaoRemovida,
                    'data_final' => today()->addDays(7)->toDateString(),
                    'fim_repeticao' => 'data',
                ]));
                $this->fail("A repetição {$repeticaoRemovida} deveria ser inválida.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('repeticao', $exception->errors());
            }
        }
    }

    public function test_cria_uma_reserva_com_varias_escolas(): void
    {
        $segundaEscola = Escola::query()->create([
            'codigo' => 'ESC-RESERVA-2',
            'nome' => 'Segunda Escola',
            'ativo' => true,
        ]);

        $reservas = $this->service->criarEmLote($this->usuario, $this->dados([
            'tipo_local' => 'escola',
            'escola_ids' => [$this->escola->id, $segundaEscola->id],
        ]));

        $reserva = $reservas->firstOrFail()->load('escolas');
        $this->assertCount(1, $reservas);
        $this->assertSame(
            [$this->escola->id, $segundaEscola->id],
            $reserva->escolas->pluck('id')->sort()->values()->all(),
        );
        $this->assertSame('Escola Destino, Segunda Escola', $reserva->local_nome);
        $this->assertSame($this->escola->id, $reserva->escola_id);
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

    public function test_conflito_em_um_dia_impede_todo_o_intervalo(): void
    {
        $this->service->criarEmLote($this->usuario, $this->dados());

        try {
            $this->service->criarEmLote($this->usuario, $this->dados([
                'data_inicial' => today()->addDay()->toDateString(),
                'reservar_varios_dias' => true,
                'data_final' => today()->addDays(3)->toDateString(),
            ]));

            $this->fail('Era esperado um conflito em um dos dias do intervalo.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('veiculo_transporte_id', $exception->errors());
        }

        $this->assertDatabaseCount('reservas_veiculos', 1);
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

    public function test_usuario_nao_edita_nem_cancela_reserva_de_outro_usuario(): void
    {
        $reserva = $this->service->criarEmLote($this->usuario, $this->dados())->firstOrFail();
        $outroUsuario = User::factory()->create();

        foreach ([
            ListaPermissoes::EditarReservasVeiculos,
            ListaPermissoes::CancelarReservasVeiculos,
        ] as $permissao) {
            $outroUsuario->givePermissionTo($permissao->label());
        }

        $this->assertFalse($outroUsuario->can('update', $reserva));
        $this->assertFalse($outroUsuario->can('cancel', $reserva));

        $this->expectException(AuthorizationException::class);
        $this->service->cancelar($outroUsuario, $reserva);
    }

    public function test_reserva_e_concluida_e_nao_pode_ser_alterada_quando_chega_o_horario_inicial(): void
    {
        Carbon::setTestNow('2026-07-28 07:00:00');
        $reserva = $this->service->criarEmLote($this->usuario, $this->dados([
            'data_inicial' => '2026-07-28',
            'hora_inicio' => '08:00',
            'hora_fim' => '10:00',
        ]))->firstOrFail();

        Carbon::setTestNow('2026-07-28 08:00:00');
        ReservaVeiculo::concluirExpiradas();
        $reserva->refresh();

        $this->assertSame(ReservaVeiculoStatus::CONCLUIDA, $reserva->status);
        $this->assertFalse($this->usuario->can('update', $reserva));
        $this->assertFalse($this->usuario->can('cancel', $reserva));
    }

    public function test_nao_permite_criar_reserva_para_horario_que_ja_passou(): void
    {
        Carbon::setTestNow('2026-07-28 09:00:00');

        try {
            $this->service->criarEmLote($this->usuario, $this->dados([
                'data_inicial' => '2026-07-28',
                'hora_inicio' => '08:00',
                'hora_fim' => '10:00',
            ]));
            $this->fail('Era esperado o bloqueio do horário passado.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'O horário inicial da reserva deve ser posterior ao horário atual.',
                $exception->errors()['hora_inicio'][0],
            );
        }

        $this->assertDatabaseCount('reservas_veiculos', 0);
    }

    public function test_nao_permite_editar_reserva_para_data_e_horario_anteriores_ao_atual(): void
    {
        Carbon::setTestNow('2026-07-28 09:00:00');
        $reserva = $this->service->criarEmLote($this->usuario, $this->dados([
            'data_inicial' => '2026-07-29',
        ]))->firstOrFail();

        try {
            $this->service->atualizar($this->usuario, $reserva, [
                'data' => '2026-07-28',
                'hora_inicio' => '08:00',
                'hora_fim' => '10:00',
                'atividade' => 'Visita técnica',
                'tipo_local' => 'outros',
                'escola_id' => null,
                'local_outro' => 'Secretaria Municipal',
                'veiculo_transporte_id' => $this->veiculo->id,
            ]);
            $this->fail('Era esperado o bloqueio da edição para horário passado.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'O horário inicial da reserva deve ser posterior ao horário atual.',
                $exception->errors()['hora_inicio'][0],
            );
        }

        $this->assertSame('2026-07-29', $reserva->fresh()->data_inicio->toDateString());
    }

    /** @return array<string, mixed> */
    private function dados(array $sobrescrever = []): array
    {
        return [
            'data_inicial' => today()->addDays(2)->toDateString(),
            'reservar_varios_dias' => false,
            'data_final' => null,
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
