<?php

namespace Tests\Feature\Turmas;

use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\Professor;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TurmaResourceScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_vinculado_a_escola_so_ve_turmas_da_propria_escola(): void
    {
        Permission::findOrCreate('Listar Turmas');

        $escolaA = $this->criarEscola('Escola A');
        $escolaB = $this->criarEscola('Escola B');

        $turmaA = $this->criarTurma($escolaA, 'Turma A');
        $turmaB = $this->criarTurma($escolaB, 'Turma B');

        $usuario = User::factory()->create([
            'id_escola' => $escolaA->id,
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo('Listar Turmas');

        $turmasVisiveis = app(UserService::class)
            ->aplicarFiltroTurmasDoUsuario(Turma::query(), $usuario)
            ->pluck('id')
            ->all();

        $this->assertContains($turmaA->id, $turmasVisiveis);
        $this->assertNotContains($turmaB->id, $turmasVisiveis);
    }

    public function test_professor_so_ve_as_turmas_que_esta_vinculado(): void
    {
        Permission::findOrCreate('Listar Turmas');

        $escolaA = $this->criarEscola('Escola Professor A');
        $escolaB = $this->criarEscola('Escola Professor B');
        $escolaC = $this->criarEscola('Escola Externa');

        $turmaA = $this->criarTurma($escolaA, 'Turma Professor A');
        $turmaB = $this->criarTurma($escolaB, 'Turma Professor B');
        $turmaC = $this->criarTurma($escolaC, 'Turma Externa');

        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-TURMA-01',
            'nome' => 'Matematica',
        ]);

        $usuarioProfessor = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuarioProfessor->givePermissionTo('Listar Turmas');

        $professorA = Professor::query()->create([
            'user_id' => $usuarioProfessor->id,
            'id_escola' => $escolaA->id,
            'matricula' => 'PROF-A',
            'nome' => 'Professor A',
            'email' => 'professor@edu.umuarama.pr.gov.br',
        ]);

        $professorB = Professor::query()->create([
            'user_id' => $usuarioProfessor->id,
            'id_escola' => $escolaB->id,
            'matricula' => 'PROF-B',
            'nome' => 'Professor B',
            'email' => 'professor@edu.umuarama.pr.gov.br',
        ]);

        $turmaA->componentes()->attach($componente->id, [
            'professor_id' => $professorA->id,
            'tem_professor' => true,
        ]);

        $turmaB->componentes()->attach($componente->id, [
            'professor_id' => $professorB->id,
            'tem_professor' => true,
        ]);

        $turmaC->componentes()->attach($componente->id, [
            'professor_id' => null,
            'tem_professor' => false,
        ]);

        $turmasVisiveis = app(UserService::class)
            ->aplicarFiltroTurmasDoUsuario(Turma::query(), $usuarioProfessor)
            ->pluck('id')
            ->all();

        $this->assertContains($turmaA->id, $turmasVisiveis);
        $this->assertContains($turmaB->id, $turmasVisiveis);
        $this->assertNotContains($turmaC->id, $turmasVisiveis);
    }

    private function criarEscola(string $nome): Escola
    {
        return Escola::query()->create([
            'codigo' => strtoupper(substr(md5($nome), 0, 5)),
            'nome' => $nome,
            'email' => strtolower(str_replace(' ', '.', $nome)) . '@teste.local',
            'telefone' => '(44) 99999-9999',
        ]);
    }

    private function criarTurma(Escola $escola, string $nome): Turma
    {
        $serie = Serie::query()->create([
            'codigo' => 'SER' . strtoupper(substr(md5($nome), 0, 4)),
            'nome' => 'Serie ' . $nome,
        ]);

        return Turma::query()->create([
            'codigo' => 'TUR' . strtoupper(substr(md5($nome . microtime()), 0, 8)),
            'nome' => $nome,
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
    }
}
