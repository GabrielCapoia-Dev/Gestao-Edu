<?php

namespace Tests\Feature\Seeders;

use App\Models\Alternativa;
use App\Models\Avaliacao;
use App\Models\AvaliacaoAlunoDocumento;
use App\Models\PeriodoAvaliacao;
use App\Models\Pauta;
use App\Models\TipoAvaliacao;
use App\Models\Turma;
use App\Models\User;
use Database\Seeders\AvaliacaoFluxoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvaliacaoFluxoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_popula_fluxo_com_dados_para_validacao(): void
    {
        $this->seed(AvaliacaoFluxoSeeder::class);

        $this->assertTrue(Avaliacao::query()->count() >= 3);
        $this->assertTrue(Pauta::query()->count() >= 5);
        $this->assertTrue(Alternativa::query()->count() >= 5);
        $this->assertTrue(Alternativa::query()->where('tem_observacao', true)->exists());
        $this->assertTrue(TipoAvaliacao::query()->count() >= 1);
        $this->assertTrue(PeriodoAvaliacao::query()->count() >= 1);
        $this->assertTrue(Turma::query()->count() >= 3);
        $this->assertTrue(AvaliacaoAlunoDocumento::query()->count() > 0);
        $this->assertTrue(AvaliacaoAlunoDocumento::query()->where('total_pautas_respondidas', '>', 0)->count() > 0);

        $professor = User::query()
            ->where('email', 'prof.matematica@edu.umuarama.pr.gov.br')
            ->first();

        $this->assertNotNull($professor);
        $this->assertTrue($professor->hasRole('Acessar Painel'));
        $this->assertTrue($professor->hasRole('Visualizar Turmas e Alunos'));
        $this->assertTrue($professor->hasPermissionTo('Responder Avaliações'));
    }
}
