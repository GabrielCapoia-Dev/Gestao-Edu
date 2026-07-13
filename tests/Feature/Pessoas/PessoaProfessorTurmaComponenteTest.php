<?php

namespace Tests\Feature\Pessoas;

use App\Filament\Admin\Resources\Servidores\Schemas\ServidorMatriculasForm;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\FuncaoAdministrativa;
use App\Models\Role;
use App\Models\Serie;
use App\Models\Servidor;
use App\Models\Setor;
use App\Models\Turma;
use App\Models\TurmaComponenteProfessor;
use App\Services\ServidorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PessoaProfessorTurmaComponenteTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeater_turma_componente_grava_pivot_pedagogico(): void
    {
        $roleProfessor = Role::query()->firstOrCreate(['name' => 'Professor', 'guard_name' => 'web']);
        $funcao = FuncaoAdministrativa::professorPadrao();
        $funcao->rolesPadrao()->sync([$roleProfessor->id]);

        $setor = $this->criarSetor('Pedagógico');
        $escola = $this->criarEscola('Escola Turma', $setor);
        $serie = Serie::query()->create(['codigo' => 'SER1', 'nome' => '1º Ano']);
        $componente = ComponenteCurricular::query()->create(['codigo' => 'MAT', 'nome' => 'Matemática']);
        $serie->componentesCurriculares()->attach($componente->id);

        $turma = Turma::query()->create([
            'codigo' => 'TURMA1',
            'nome' => 'Turma A',
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);

        $servidor = app(ServidorService::class)->criarServidorComFuncoes([
            'cargo' => 'professor',
            'nome' => 'Professor Turma',
            'email' => 'prof.turma@edu.umuarama.pr.gov.br',
            'status' => Servidor::STATUS_ATIVO,
        ], [
            'registros_professor' => [[
                'matricula' => 'TURMA-001',
                'turno' => 'manha',
                'id_escola' => $escola->id,
                'vinculos_turma_componente' => [[
                    'turma_id' => $turma->id,
                    'componente_curricular_id' => $componente->id,
                ]],
            ]],
        ]);

        $professor = $servidor->professores()->first();

        $this->assertDatabaseHas('turma_componente_professor', [
            'turma_id' => $turma->id,
            'componente_curricular_id' => $componente->id,
            'professor_id' => $professor->id,
            'tem_professor' => true,
        ]);

        $this->assertSame(1, TurmaComponenteProfessor::query()->where('professor_id', $professor->id)->count());
        $this->assertSame(
            '1º Ano - Turma A (manhã) · Matemática',
            ServidorMatriculasForm::vinculoTurmaComponenteLabel([
                'turma_id' => $turma->id,
                'componente_curricular_id' => $componente->id,
            ]),
        );
    }

    private function criarSetor(string $nome): Setor
    {
        return Setor::query()->create([
            'nome' => $nome,
            'ativo' => true,
            'status' => 'Ativo',
            'is_default_root' => true,
            'contexto' => 'escolar',
            'exige_vinculo_escola' => true,
        ]);
    }

    private function criarEscola(string $nome, Setor $setor): Escola
    {
        return Escola::query()->create([
            'codigo' => strtoupper(substr(md5($nome), 0, 8)),
            'nome' => $nome,
            'setor_id' => $setor->id,
            'email' => strtolower(str_replace(' ', '.', $nome)).'@teste.local',
            'telefone' => '(44) 99999-9999',
            'ativo' => true,
        ]);
    }
}
