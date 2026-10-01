<?php

namespace Tests\Feature\Pessoas;

use App\Models\FuncaoAdministrativa;
use App\Models\Professor;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Services\PessoaProfessorFormService;
use App\Services\ServidorHistoricoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServidorHistoricoTest extends TestCase
{
    use RefreshDatabase;

    public function test_formulario_prioriza_rh_quando_ha_registro_legado_de_professor_ativo(): void
    {
        $servidor = Servidor::query()->create([
            'nome' => 'Servidor convertido',
            'email' => 'convertido@teste.local',
            'status' => Servidor::STATUS_INATIVO,
        ]);

        ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $servidor->id,
            'funcao_administrativa_id' => FuncaoAdministrativa::rhPadrao()->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'origem' => 'pessoas',
        ]);

        Professor::query()->create([
            'servidor_id' => $servidor->id,
            'matricula' => 'LEGADO-001',
            'turno' => 'manha',
            'nome' => $servidor->nome,
            'email' => $servidor->email,
            'ativo' => true,
        ]);

        $dados = app(PessoaProfessorFormService::class)->dadosParaFormulario($servidor);

        $this->assertSame('rh', $dados['cargo']);
    }

    public function test_registra_delta_funcional_sem_sobrescrever_movimentacoes_anteriores(): void
    {
        $servidor = Servidor::query()->create([
            'nome' => 'Servidor com histórico',
            'email' => 'historico@teste.local',
            'status' => Servidor::STATUS_INATIVO,
        ]);
        $funcao = FuncaoAdministrativa::rhPadrao();
        $vinculo = ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $servidor->id,
            'funcao_administrativa_id' => $funcao->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'origem' => 'pessoas',
        ]);

        $historico = app(ServidorHistoricoService::class);
        $antes = $historico->capturar($servidor);
        $vinculo->forceFill(['status' => ServidorFuncaoAdministrativa::STATUS_INATIVO, 'data_fim' => now()->toDateString()])->save();
        $historico->registrarSeAlterou($servidor, $antes, null);
        $movimentacao = $servidor->movimentacoes()->firstOrFail();

        $this->assertArrayHasKey('cargo', $movimentacao->alteracoes);
        $this->assertSame('RH', $movimentacao->alteracoes['cargo']['antes'][0]['cargo']);
        $this->assertSame([], $movimentacao->alteracoes['cargo']['depois']);
    }
}
