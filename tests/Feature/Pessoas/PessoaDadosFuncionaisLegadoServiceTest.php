<?php

namespace Tests\Feature\Pessoas;

use App\Models\Pessoa;
use App\Models\PessoaMatricula;
use App\Models\Servidor;
use App\Services\PessoaDadosFuncionaisLegadoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PessoaDadosFuncionaisLegadoServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_infere_dados_inequivocos_sem_gravar_no_dry_run_e_e_idempotente(): void
    {
        $pamela = $this->criarPessoa('Pamela Alessandra Dalcin', 'pamela.dalcin@edu.umuarama.pr.gov.br');
        $this->criarMatricula($pamela, '1082650', 'manha');
        $this->criarMatricula($pamela, '1083176', 'tarde');

        $integral = $this->criarPessoa('Pessoa Integral', 'integral@edu.umuarama.pr.gov.br');
        $this->criarMatricula($integral, 'INT-001', 'integral');

        $manha = $this->criarPessoa('Pessoa Manhã', 'manha@edu.umuarama.pr.gov.br');
        $this->criarMatricula($manha, 'MAN-001', 'manha');

        $service = app(PessoaDadosFuncionaisLegadoService::class);
        $dryRun = $service->executar();

        $this->assertSame(3, $dryRun['elegiveis']);
        $this->assertSame(0, $dryRun['normalizadas']);
        $this->assertNull($pamela->fresh()->carga_horaria);

        $aplicacao = $service->executar(aplicar: true);
        $this->assertSame(3, $aplicacao['normalizadas']);
        $this->assertSame(20, $pamela->fresh()->carga_horaria);
        $this->assertTrue($pamela->fresh()->jornada);
        $this->assertSame(40, $integral->fresh()->carga_horaria);
        $this->assertFalse($integral->fresh()->jornada);
        $this->assertSame(20, $manha->fresh()->carga_horaria);
        $this->assertFalse($manha->fresh()->jornada);

        $segundaExecucao = $service->executar(aplicar: true);
        $this->assertSame(0, $segundaExecucao['normalizadas']);
    }

    public function test_nao_sobrescreve_dado_explicito_nem_infere_combinacao_ambigua(): void
    {
        $explicita = Servidor::query()->create([
            'nome' => 'Pessoa Explícita',
            'email' => 'explicita@edu.umuarama.pr.gov.br',
            'status' => Servidor::STATUS_INATIVO,
            'carga_horaria' => Pessoa::CARGA_HORARIA_40,
            'jornada' => null,
        ]);
        $this->criarMatricula($explicita, 'EXP-001', 'manha');

        $ambigua = $this->criarPessoa('Pessoa Ambígua', 'ambigua@edu.umuarama.pr.gov.br');
        $this->criarMatricula($ambigua, 'AMB-001', 'manha');
        $this->criarMatricula($ambigua, 'AMB-002', 'manha');

        $stats = app(PessoaDadosFuncionaisLegadoService::class)->executar(aplicar: true);

        $this->assertSame(2, $stats['ambiguas']);
        $this->assertSame(40, $explicita->fresh()->carga_horaria);
        $this->assertNull($explicita->fresh()->jornada);
        $this->assertNull($ambigua->fresh()->carga_horaria);
    }

    private function criarPessoa(string $nome, string $email): Servidor
    {
        return Servidor::query()->create([
            'nome' => $nome,
            'email' => $email,
            'status' => Servidor::STATUS_INATIVO,
        ]);
    }

    private function criarMatricula(Servidor $pessoa, string $matricula, string $turno): void
    {
        PessoaMatricula::query()->create([
            'servidor_id' => $pessoa->id,
            'matricula' => $matricula,
            'turno' => $turno,
        ]);
    }
}
