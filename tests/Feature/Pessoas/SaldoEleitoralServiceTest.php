<?php

namespace Tests\Feature\Pessoas;

use App\Models\SaldoEleitoral;
use App\Models\Servidor;
use App\Models\User;
use App\Services\SaldoEleitoralService;
use App\Support\EquipeGestoraPermissionPreset;
use App\Support\RhPermissionPreset;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $service->solicitar($servidor, $solicitante, SaldoEleitoral::TIPO_USO, 5);

        $this->assertSame(12, $service->saldoAprovado($servidor));
        $this->assertSame(7, $service->disponivelParaSolicitacao($servidor));
    }

    public function test_impede_solicitacao_de_uso_acima_do_saldo_disponivel(): void
    {
        [$servidor, $solicitante] = $this->contexto();
        $this->lancamento($servidor, $solicitante, SaldoEleitoral::TIPO_ADICAO, 4, SaldoEleitoral::STATUS_APROVADO);

        $this->expectException(ValidationException::class);
        app(SaldoEleitoralService::class)->solicitar($servidor, $solicitante, SaldoEleitoral::TIPO_USO, 5);
    }

    public function test_aprovacao_de_uso_desconta_saldo_e_desconto_manual_nao_permite_saldo_negativo(): void
    {
        [$servidor, $solicitante, $rh] = $this->contexto();
        $service = app(SaldoEleitoralService::class);
        $this->lancamento($servidor, $solicitante, SaldoEleitoral::TIPO_ADICAO, 8, SaldoEleitoral::STATUS_APROVADO);
        $pedidoUso = $service->solicitar($servidor, $solicitante, SaldoEleitoral::TIPO_USO, 3);
        $service->decidir($pedidoUso, $rh, true);

        $this->assertSame(5, $service->saldoAprovado($servidor));
        $service->descontar($servidor, $rh, 5, 'Uso registrado pelo RH');
        $this->assertSame(0, $service->saldoAprovado($servidor));

        try {
            $service->descontar($servidor, $rh, 1);
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
        $service->decidir($pedido, $rh, true);
        $this->assertSame(9, $service->saldoAprovado($servidor));
    }

    public function test_solicitacoes_pendentes_de_uso_nao_podem_reservar_os_mesmos_dias(): void
    {
        [$servidor, $solicitante] = $this->contexto();
        $service = app(SaldoEleitoralService::class);
        $this->lancamento($servidor, $solicitante, SaldoEleitoral::TIPO_ADICAO, 6, SaldoEleitoral::STATUS_APROVADO);
        $service->solicitar($servidor, $solicitante, SaldoEleitoral::TIPO_USO, 4);

        $this->expectException(ValidationException::class);
        $service->solicitar($servidor, $solicitante, SaldoEleitoral::TIPO_USO, 3);
    }

    public function test_desconto_manual_respeita_dias_reservados_por_solicitacoes_pendentes(): void
    {
        [$servidor, $solicitante, $rh] = $this->contexto();
        $service = app(SaldoEleitoralService::class);
        $this->lancamento($servidor, $solicitante, SaldoEleitoral::TIPO_ADICAO, 6, SaldoEleitoral::STATUS_APROVADO);
        $service->solicitar($servidor, $solicitante, SaldoEleitoral::TIPO_USO, 4);

        $this->expectException(ValidationException::class);
        $service->descontar($servidor, $rh, 3);
    }

    public function test_presets_separam_solicitacao_da_aprovacao_do_rh(): void
    {
        $equipeGestora = EquipeGestoraPermissionPreset::all();
        $rh = RhPermissionPreset::all();

        $this->assertContains('Solicitar Adição de Saldo Eleitoral', $equipeGestora);
        $this->assertContains('Solicitar Uso de Saldo Eleitoral', $equipeGestora);
        $this->assertNotContains('Gerenciar Saldo Eleitoral', $equipeGestora);
        $this->assertContains('Gerenciar Saldo Eleitoral', $rh);
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
