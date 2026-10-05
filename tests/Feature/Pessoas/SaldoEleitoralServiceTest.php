<?php

namespace Tests\Feature\Pessoas;

use App\Models\SaldoEleitoral;
use App\Models\Servidor;
use App\Models\User;
use App\Services\SaldoEleitoralService;
use App\Support\EquipeGestoraPermissionPreset;
use App\Support\RhPermissionPreset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SaldoEleitoralServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_saldo_considera_apenas_movimentacoes_aprovadas_e_reserva_usos_pendentes(): void
    {
        [$servidor, $solicitante] = $this->contexto();
        $service = app(SaldoEleitoralService::class);
        $this->lancamento($servidor, $solicitante, SaldoEleitoral::TIPO_ADICAO, 12, SaldoEleitoral::STATUS_APROVADO);

        $service->solicitar($servidor, $solicitante, SaldoEleitoral::TIPO_USO, 5, $this->datas(5));

        $this->assertSame(12, $service->saldoAprovado($servidor));
        $this->assertSame(7, $service->disponivelParaSolicitacao($servidor));
    }

    public function test_impede_solicitacao_de_uso_acima_do_saldo_disponivel(): void
    {
        [$servidor, $solicitante] = $this->contexto();
        $this->lancamento($servidor, $solicitante, SaldoEleitoral::TIPO_ADICAO, 4, SaldoEleitoral::STATUS_APROVADO);

        $this->expectException(ValidationException::class);
        app(SaldoEleitoralService::class)->solicitar($servidor, $solicitante, SaldoEleitoral::TIPO_USO, 5, $this->datas(5));
    }

    public function test_aprovacao_de_uso_desconta_saldo_e_desconto_manual_nao_permite_saldo_negativo(): void
    {
        [$servidor, $solicitante, $rh] = $this->contexto();
        $service = app(SaldoEleitoralService::class);
        $this->lancamento($servidor, $solicitante, SaldoEleitoral::TIPO_ADICAO, 8, SaldoEleitoral::STATUS_APROVADO);
        $pedidoUso = $service->solicitar($servidor, $solicitante, SaldoEleitoral::TIPO_USO, 3, $this->datas(3));
        $service->decidir($pedidoUso, $rh, true);

        $this->assertSame(5, $service->saldoAprovado($servidor));
        $service->descontar($servidor, $rh, 5, 'Uso registrado pelo RH', $this->datas(5, 3));
        $this->assertSame(0, $service->saldoAprovado($servidor));

        try {
            $service->descontar($servidor, $rh, 1, null, $this->datas(1, 8));
            $this->fail('O serviço deveria impedir saldo negativo.');
        } catch (ValidationException) {
            $this->assertSame(0, $service->saldoAprovado($servidor));
        }
    }

    public function test_adicao_solicitada_so_entra_no_saldo_depois_da_aprovacao_do_rh(): void
    {
        [$servidor, $solicitante, $rh] = $this->contexto();
        $service = app(SaldoEleitoralService::class);
        $pedido = $service->solicitar($servidor, $solicitante, SaldoEleitoral::TIPO_ADICAO, 9);

        $this->assertSame(0, $service->saldoAprovado($servidor));
        $decidido = $service->decidir($pedido, $rh, true);

        $this->assertSame(SaldoEleitoral::STATUS_APROVADO, $decidido->status);
        $this->assertSame(9, $service->saldoAprovado($servidor));
    }

    public function test_solicitacoes_pendentes_de_uso_nao_podem_reservar_os_mesmos_dias(): void
    {
        [$servidor, $solicitante] = $this->contexto();
        $service = app(SaldoEleitoralService::class);
        $this->lancamento($servidor, $solicitante, SaldoEleitoral::TIPO_ADICAO, 6, SaldoEleitoral::STATUS_APROVADO);
        $service->solicitar($servidor, $solicitante, SaldoEleitoral::TIPO_USO, 4, $this->datas(4));

        $this->expectException(ValidationException::class);
        $service->solicitar($servidor, $solicitante, SaldoEleitoral::TIPO_USO, 3, $this->datas(3));
    }

    public function test_desconto_manual_respeita_dias_reservados_por_solicitacoes_pendentes(): void
    {
        [$servidor, $solicitante, $rh] = $this->contexto();
        $service = app(SaldoEleitoralService::class);
        $this->lancamento($servidor, $solicitante, SaldoEleitoral::TIPO_ADICAO, 6, SaldoEleitoral::STATUS_APROVADO);
        $service->solicitar($servidor, $solicitante, SaldoEleitoral::TIPO_USO, 4, $this->datas(4));

        $this->expectException(ValidationException::class);
        $service->descontar($servidor, $rh, 3, null, $this->datas(3, 4));
    }

    public function test_presets_separam_solicitacao_da_aprovacao_do_rh(): void
    {
        $equipeGestora = EquipeGestoraPermissionPreset::all();
        $rh = RhPermissionPreset::all();

        $this->assertContains('Solicitar Adição de Saldo Eleitoral', $equipeGestora);
        $this->assertContains('Solicitar Uso de Saldo Eleitoral', $equipeGestora);
        $this->assertContains('Solicitar Estorno de Saldo Eleitoral', $equipeGestora);
        $this->assertNotContains('Gerenciar Saldo Eleitoral', $equipeGestora);
        $this->assertContains('Gerenciar Saldo Eleitoral', $rh);
    }

    public function test_nao_permite_fins_de_semana_feriados_ou_datas_ja_reservadas(): void
    {
        [$servidor, $solicitante] = $this->contexto();
        $service = app(SaldoEleitoralService::class);
        $this->lancamento($servidor, $solicitante, SaldoEleitoral::TIPO_ADICAO, 10, SaldoEleitoral::STATUS_APROVADO);
        $service->solicitar($servidor, $solicitante, SaldoEleitoral::TIPO_USO, 1, ['2027-01-04']);

        foreach ([['2027-01-04'], ['2027-01-02'], ['2027-01-01']] as $datas) {
            try {
                $service->solicitar($servidor, $solicitante, SaldoEleitoral::TIPO_USO, 1, $datas);
                $this->fail('O serviço deveria rejeitar data reservada, fim de semana ou feriado.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_estorno_pendente_reserva_dias_do_uso_e_so_restitui_saldo_apos_aprovacao(): void
    {
        [$servidor, $solicitante, $rh] = $this->contexto();
        $service = app(SaldoEleitoralService::class);
        $this->lancamento($servidor, $solicitante, SaldoEleitoral::TIPO_ADICAO, 10, SaldoEleitoral::STATUS_APROVADO);
        $uso = $service->solicitar($servidor, $solicitante, SaldoEleitoral::TIPO_USO, 4, $this->datas(4));
        $service->decidir($uso, $rh, true);

        $estorno = $service->solicitarEstorno($servidor, $solicitante, $uso->id, 2, 'Servidor impedido de comparecer.', ['2027-01-04', '2027-01-05']);

        $this->assertSame(SaldoEleitoral::STATUS_PENDENTE, $estorno->status);
        $this->assertSame($uso->id, $estorno->movimento_origem_id);
        $this->assertSame(6, $service->saldoAprovado($servidor));
        $this->assertSame(2, $service->diasRestantesParaEstorno($uso->fresh()->load('estornos')));

        $service->decidir($estorno, $rh, true);

        $this->assertSame(8, $service->saldoAprovado($servidor));
        $this->assertSame(SaldoEleitoral::STATUS_APROVADO, $uso->fresh()->status);
        $this->assertSame(['2027-01-06', '2027-01-07'], $service->datasDisponiveisParaEstorno($uso->fresh()->load('estornos')));
    }

    public function test_estorno_nao_pode_exceder_o_uso_original_nem_repetir_data_reservada(): void
    {
        [$servidor, $solicitante] = $this->contexto();
        $service = app(SaldoEleitoralService::class);
        $uso = $this->lancamento($servidor, $solicitante, SaldoEleitoral::TIPO_USO, 2, SaldoEleitoral::STATUS_APROVADO);
        $uso->forceFill(['datas' => ['2027-01-04', '2027-01-05']])->save();
        $service->solicitarEstorno($servidor, $solicitante, $uso->id, 1, 'Imprevisto.', ['2027-01-04']);

        try {
            $service->solicitarEstorno($servidor, $solicitante, $uso->id, 1, 'Mesmo dia.', ['2027-01-04']);
            $this->fail('A mesma data não pode ser reservada por dois estornos.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->expectException(ValidationException::class);
        $service->solicitarEstorno($servidor, $solicitante, $uso->id, 2, 'Excede uso original.', ['2027-01-05', '2027-01-06']);
    }

    public function test_estorno_rejeitado_preserva_o_saldo_e_o_uso_original(): void
    {
        [$servidor, $solicitante, $rh] = $this->contexto();
        $service = app(SaldoEleitoralService::class);
        $this->lancamento($servidor, $solicitante, SaldoEleitoral::TIPO_ADICAO, 7, SaldoEleitoral::STATUS_APROVADO);
        $uso = $service->solicitar($servidor, $solicitante, SaldoEleitoral::TIPO_USO, 2, $this->datas(2));
        $service->decidir($uso, $rh, true);
        $estorno = $service->solicitarEstorno($servidor, $solicitante, $uso->id, 1, 'Justificativa registrada.', ['2027-01-04']);

        $service->decidir($estorno, $rh, false);

        $this->assertSame(5, $service->saldoAprovado($servidor));
        $this->assertSame(SaldoEleitoral::STATUS_APROVADO, $uso->fresh()->status);
        $this->assertSame(SaldoEleitoral::STATUS_REJEITADO, $estorno->fresh()->status);
    }

    public function test_normaliza_datas_armazenadas_como_json_escalar_legado(): void
    {
        [$servidor, $solicitante] = $this->contexto();
        $movimento = $this->lancamento($servidor, $solicitante, SaldoEleitoral::TIPO_USO, 1, SaldoEleitoral::STATUS_APROVADO);
        DB::table('saldos_eleitorais')->where('id', $movimento->id)->update(['datas' => json_encode('2027-01-04')]);

        $this->assertSame(['2027-01-04'], $movimento->fresh()->datas);
    }

    private function contexto(): array
    {
        $servidor = Servidor::query()->create([
            'nome' => 'Servidor de teste de saldo',
            'email' => fake()->unique()->safeEmail(),
            'status' => Servidor::STATUS_ATIVO,
        ]);

        return [$servidor, User::factory()->create(), User::factory()->create()];
    }

    private function datas(int $count, int $offset = 0): array
    {
        return array_slice([
            '2027-01-04', '2027-01-05', '2027-01-06', '2027-01-07', '2027-01-08',
            '2027-01-11', '2027-01-12', '2027-01-13', '2027-01-14', '2027-01-15',
        ], $offset, $count);
    }

    private function lancamento(Servidor $servidor, User $usuario, string $tipo, int $dias, string $status): SaldoEleitoral
    {
        return SaldoEleitoral::query()->create([
            'servidor_id' => $servidor->id,
            'solicitante_id' => $usuario->id,
            'tipo' => $tipo,
            'dias' => $dias,
            'status' => $status,
        ]);
    }
}
