<?php

namespace Tests\Feature\Alunos;

use App\Models\Aluno;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\Professor;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AlunoResourceScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_vinculado_a_escola_so_ve_alunos_da_propria_escola(): void
    {
        Permission::findOrCreate('Listar Alunos');

        $escolaA = $this->criarEscola('Escola A');
        $escolaB = $this->criarEscola('Escola B');

        $turmaA = $this->criarTurma($escolaA, 'A');
        $turmaB = $this->criarTurma($escolaB, 'B');

        Aluno::query()->create([
            'nome' => 'Aluno Escola A',
            'cgm' => 'A001',
            'data_nascimento' => '2015-01-10',
            'id_turma' => $turmaA->id,
        ]);

        Aluno::query()->create([
            'nome' => 'Aluno Escola B',
            'cgm' => 'B001',
            'data_nascimento' => '2015-02-10',
            'id_turma' => $turmaB->id,
        ]);

        $usuario = User::factory()->create([
            'id_escola' => $escolaA->id,
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo('Listar Alunos');

        $this->actingAs($usuario)
            ->get(route('filament.admin.resources.alunos.index'))
            ->assertOk()
            ->assertSee('Aluno Escola A')
            ->assertDontSee('Aluno Escola B');
    }

    public function test_professor_so_ve_alunos_das_suas_turmas_e_respeita_o_contexto_da_turma(): void
    {
        Permission::findOrCreate('Listar Alunos');

        $escola = $this->criarEscola('Escola Professor');
        $outraEscola = $this->criarEscola('Escola Externa');

        $turmaA = $this->criarTurma($escola, 'A');
        $turmaB = $this->criarTurma($escola, 'B');
        $turmaC = $this->criarTurma($outraEscola, 'C');

        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP001',
            'nome' => 'Matemática',
        ]);

        $usuarioProfessor = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuarioProfessor->givePermissionTo('Listar Alunos');

        $professor = Professor::query()->create([
            'user_id' => $usuarioProfessor->id,
            'id_escola' => $escola->id,
            'matricula' => 'PROF001',
            'nome' => 'Professor 1',
            'email' => 'professor@teste.local',
        ]);

        $turmaA->componentes()->attach($componente->id, [
            'professor_id' => $professor->id,
            'tem_professor' => true,
        ]);

        $turmaB->componentes()->attach($componente->id, [
            'professor_id' => $professor->id,
            'tem_professor' => true,
        ]);

        $turmaC->componentes()->attach($componente->id, [
            'professor_id' => null,
            'tem_professor' => false,
        ]);

        Aluno::query()->create([
            'nome' => 'Aluno Turma A',
            'cgm' => 'TA001',
            'data_nascimento' => '2014-03-10',
            'id_turma' => $turmaA->id,
        ]);

        Aluno::query()->create([
            'nome' => 'Aluno Turma B',
            'cgm' => 'TB001',
            'data_nascimento' => '2014-04-10',
            'id_turma' => $turmaB->id,
        ]);

        Aluno::query()->create([
            'nome' => 'Aluno Turma C',
            'cgm' => 'TC001',
            'data_nascimento' => '2014-05-10',
            'id_turma' => $turmaC->id,
        ]);

        $this->actingAs($usuarioProfessor)
            ->get(route('filament.admin.resources.alunos.index'))
            ->assertOk()
            ->assertSee('Aluno Turma A')
            ->assertSee('Aluno Turma B')
            ->assertDontSee('Aluno Turma C');

        $this->actingAs($usuarioProfessor)
            ->get(route('filament.admin.resources.alunos.index', ['turma' => $turmaA->id]))
            ->assertOk()
            ->assertSee('Aluno Turma A')
            ->assertDontSee('Aluno Turma B')
            ->assertDontSee('Aluno Turma C');
    }

    public function test_acesso_de_criacao_respeita_permissao(): void
    {
        Permission::findOrCreate('Listar Alunos');
        Permission::findOrCreate('Criar Alunos');

        $usuarioSemPermissao = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuarioSemPermissao->givePermissionTo('Listar Alunos');

        $this->actingAs($usuarioSemPermissao)
            ->get(route('filament.admin.resources.alunos.create'))
            ->assertForbidden();

        $usuarioComPermissao = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuarioComPermissao->givePermissionTo(['Listar Alunos', 'Criar Alunos']);

        $this->actingAs($usuarioComPermissao)
            ->get(route('filament.admin.resources.alunos.create'))
            ->assertOk();
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

    private function criarTurma(Escola $escola, string $sufixo): Turma
    {
        $serie = Serie::query()->create([
            'codigo' => 'SER' . $sufixo,
            'nome' => 'Série ' . $sufixo,
        ]);

        return Turma::query()->create([
            'codigo' => 'TUR' . $sufixo . uniqid(),
            'nome' => $sufixo,
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
    }
}

