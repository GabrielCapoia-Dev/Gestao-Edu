<?php

namespace Tests\Feature\Relatorios;

use App\Filament\Admin\Pages\Relatorios\RelatoriosDashboard;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\Professor;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\TurmaComponenteProfessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelatoriosDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_conta_turmas_completas_e_turmas_com_professor_faltando(): void
    {
        $escolaA = $this->criarEscola('Escola A');
        $escolaB = $this->criarEscola('Escola B');
        $serie = Serie::query()->create(['codigo' => 'SER-REL', 'nome' => 'Serie Relatorio']);

        $matematica = ComponenteCurricular::query()->create(['codigo' => 'MAT', 'nome' => 'Matematica']);
        $portugues = ComponenteCurricular::query()->create(['codigo' => 'POR', 'nome' => 'Portugues']);

        $professor = Professor::query()->create([
            'id_escola' => $escolaA->id,
            'matricula' => 'PROF-REL',
            'nome' => 'Professor Relatorio',
        ]);

        $turmaCompleta = $this->criarTurma($escolaA, $serie, 'Turma Completa');
        $turmaParcial = $this->criarTurma($escolaA, $serie, 'Turma Parcial');
        $turmaSemProfessores = $this->criarTurma($escolaB, $serie, 'Turma Sem Professores');
        $this->criarTurma($escolaB, $serie, 'Turma Sem Componentes');

        $this->vincular($turmaCompleta, $matematica, $professor);
        $this->vincular($turmaCompleta, $portugues, $professor);
        $this->vincular($turmaParcial, $matematica, $professor);
        $this->vincular($turmaParcial, $portugues);
        $this->vincular($turmaSemProfessores, $matematica);
        $this->vincular($turmaSemProfessores, $portugues);

        $page = new RelatoriosDashboard();
        $page->mount();

        $this->assertSame(4, $page->totalTurmas);
        $this->assertSame(6, $page->vinculos);
        $this->assertSame(1, $page->comProfessor);
        $this->assertSame(2, $page->semProfessor);

        $this->assertSame('Portugues', $page->topComponentes[0]->nome);
        $this->assertSame(2, (int) $page->topComponentes[0]->sem_professor);

        $faltasEscolaB = collect($page->faltasPorEscolaComponente)
            ->firstWhere('escola_nome', 'Escola B');

        $this->assertNotNull($faltasEscolaB);
        $this->assertSame(1, (int) $faltasEscolaB->professores_faltando);
        $this->assertContains($faltasEscolaB->componente_nome, ['Matematica', 'Portugues']);
    }

    private function criarEscola(string $nome): Escola
    {
        return Escola::query()->create([
            'codigo' => strtoupper(substr(md5($nome), 0, 8)),
            'nome' => $nome,
        ]);
    }

    private function criarTurma(Escola $escola, Serie $serie, string $nome): Turma
    {
        return Turma::query()->create([
            'codigo' => strtoupper(substr(md5($nome), 0, 8)),
            'nome' => $nome,
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
    }

    private function vincular(
        Turma $turma,
        ComponenteCurricular $componente,
        ?Professor $professor = null
    ): void {
        TurmaComponenteProfessor::query()->create([
            'turma_id' => $turma->id,
            'componente_curricular_id' => $componente->id,
            'professor_id' => $professor?->id,
            'tem_professor' => $professor !== null,
        ]);
    }
}
