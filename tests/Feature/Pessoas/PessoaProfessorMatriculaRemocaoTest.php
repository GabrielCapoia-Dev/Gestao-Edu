<?php

namespace Tests\Feature\Pessoas;

use App\Filament\Admin\Resources\Servidores\ServidorResource;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\FuncaoAdministrativa;
use App\Models\PessoaMatricula;
use App\Models\Professor;
use App\Models\Role;
use App\Models\Serie;
use App\Models\Servidor;
use App\Models\Setor;
use App\Models\Turma;
use App\Models\TurmaComponenteProfessor;
use App\Services\PessoaProfessorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PessoaProfessorMatriculaRemocaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_remove_matricula_e_preserva_historico_sem_apagar_slot_pedagogico(): void
    {
        $cenario = $this->criarCenarioComDuasMatriculas();
        $roleIndependente = Role::query()->create([
            'name' => 'Role independente da remoção',
            'guard_name' => 'web',
        ]);
        $cenario['servidor']->user->assignRole($roleIndependente);
        $vinculoFuncaoTurmaId = DB::table('servidor_funcao_turma')->insertGetId([
            'servidor_funcao_administrativa_id' => $cenario['vinculoFuncionalRemovidoId'],
            'turma_id' => $cenario['turma']->id,
            'principal' => true,
            'status' => 'ativo',
            'data_inicio' => now()->subMonth()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertEqualsCanonicalizing(
            [$cenario['escolaRemovida']->id, $cenario['escolaMantida']->id],
            $cenario['servidor']->user->escolas()->pluck('escolas.id')->all(),
        );

        $this->manterSomenteMatricula($cenario, $cenario['matriculaMantida'], $cenario['professorMantido']);

        $professorRemovido = $cenario['professorRemovido']->fresh();
        $professorMantido = $cenario['professorMantido']->fresh();
        $vinculo = $cenario['vinculo']->fresh();

        $this->assertDatabaseMissing('professor_matriculas', [
            'id' => $cenario['matriculaRemovida']->id,
        ]);
        $this->assertDatabaseHas('professor_matriculas', [
            'id' => $cenario['matriculaMantida']->id,
            'servidor_id' => $cenario['servidor']->id,
            'matricula' => 'MAT-MANTER',
            'turno' => 'tarde',
        ]);

        $this->assertFalse($professorRemovido->ativo);
        $this->assertNull($professorRemovido->professor_matricula_id);
        $this->assertNotNull($professorRemovido->desativado_em);
        $this->assertSame('MAT-REMOVER', $professorRemovido->matricula);
        $this->assertSame('manha', $professorRemovido->turno);
        $this->assertSame($cenario['escolaRemovida']->id, $professorRemovido->id_escola);
        $this->assertSame('Professor com duas matrículas', $professorRemovido->nome);

        $this->assertTrue($professorMantido->ativo);
        $this->assertSame($cenario['matriculaMantida']->id, $professorMantido->professor_matricula_id);
        $this->assertSame('MAT-MANTER', $professorMantido->matricula);
        $this->assertSame('tarde', $professorMantido->turno);

        $this->assertSame($cenario['vinculo']->id, $vinculo->id);
        $this->assertNull($vinculo->professor_id);
        $this->assertFalse($vinculo->tem_professor);
        $this->assertSame($cenario['turma']->id, $vinculo->turma_id);
        $this->assertSame($cenario['componente']->id, $vinculo->componente_curricular_id);

        $this->assertSame(1, PessoaMatricula::query()->where('servidor_id', $cenario['servidor']->id)->count());
        $this->assertSame(2, Professor::query()->where('servidor_id', $cenario['servidor']->id)->count());
        $this->assertSame(1, Professor::query()
            ->where('servidor_id', $cenario['servidor']->id)
            ->where('ativo', true)
            ->count());

        $user = $cenario['servidor']->user->fresh();
        $this->assertTrue($user->hasRole('Professor'));
        $this->assertTrue($user->hasRole($roleIndependente));
        $this->assertSame([$cenario['escolaMantida']->id], $user->escolas()->pluck('escolas.id')->all());
        $this->assertSame($cenario['escolaMantida']->id, $user->id_escola);

        $this->assertDatabaseHas('servidor_funcao_administrativa', [
            'id' => $cenario['vinculoFuncionalRemovidoId'],
            'status' => 'inativo',
            'principal' => false,
        ]);
        $this->assertDatabaseHas('servidor_funcao_administrativa', [
            'id' => $cenario['vinculoFuncionalMantidoId'],
            'status' => 'ativo',
        ]);
        $this->assertDatabaseHas('servidor_funcao_turma', [
            'id' => $vinculoFuncaoTurmaId,
            'principal' => false,
            'status' => 'inativo',
            'data_fim' => now()->toDateString(),
        ]);
    }

    public function test_estado_hierarquico_vazio_nao_reutiliza_payload_legado(): void
    {
        $this->assertSame([], ServidorResource::extrairRegistrosProfessorDoForm([
            'matriculas_professor' => [],
            'registros_professor' => [[
                'matricula' => 'LEGADA',
                'turno' => 'manha',
                'id_escola' => 999,
            ]],
        ]));
    }

    public function test_saneia_estado_legado_com_tres_matriculas_ao_remover_a_omitida(): void
    {
        $cenario = $this->criarCenarioComDuasMatriculas();
        $escolaTerceira = $this->criarEscola(
            'Escola da terceira matrícula legada',
            Setor::query()->findOrFail($cenario['escolaMantida']->setor_id),
        );
        $matriculaTerceira = PessoaMatricula::query()->create([
            'servidor_id' => $cenario['servidor']->id,
            'matricula' => 'MAT-MANTER-MANHA',
            'turno' => 'manha',
        ]);
        $professorTerceiro = Professor::withoutEvents(fn (): Professor => Professor::query()->create([
            'servidor_id' => $cenario['servidor']->id,
            'professor_matricula_id' => $matriculaTerceira->id,
            'id_escola' => $escolaTerceira->id,
            'matricula' => $matriculaTerceira->matricula,
            'turno' => $matriculaTerceira->turno,
            'nome' => $cenario['servidor']->nome,
            'email' => $cenario['servidor']->email,
            'user_id' => $cenario['servidor']->user_id,
            'ativo' => true,
        ]));

        app(PessoaProfessorService::class)->atualizarPessoaProfessor($cenario['servidor'], [
            'nome' => $cenario['servidor']->nome,
            'email' => $cenario['servidor']->email,
            'status' => Servidor::STATUS_ATIVO,
        ], [
            [
                'id' => $cenario['matriculaMantida']->id,
                'matricula' => $cenario['matriculaMantida']->matricula,
                'turno' => $cenario['matriculaMantida']->turno,
                'escolas' => [[
                    'id' => $cenario['professorMantido']->id,
                    'id_escola' => $cenario['escolaMantida']->id,
                    'vinculos_turma_componente' => [],
                ]],
            ],
            [
                'id' => $matriculaTerceira->id,
                'matricula' => $matriculaTerceira->matricula,
                'turno' => $matriculaTerceira->turno,
                'escolas' => [[
                    'id' => $professorTerceiro->id,
                    'id_escola' => $escolaTerceira->id,
                    'vinculos_turma_componente' => [],
                ]],
            ],
        ]);

        $this->assertSame(
            ['MAT-MANTER', 'MAT-MANTER-MANHA'],
            $cenario['servidor']->matriculas()->pluck('matricula')->sort()->values()->all(),
        );
        $this->assertDatabaseMissing('professor_matriculas', ['id' => $cenario['matriculaRemovida']->id]);
        $this->assertDatabaseHas('professores', [
            'id' => $cenario['professorRemovido']->id,
            'professor_matricula_id' => null,
            'ativo' => false,
        ]);
        $this->assertDatabaseHas('turma_componente_professor', [
            'id' => $cenario['vinculo']->id,
            'professor_id' => null,
            'tem_professor' => false,
        ]);
    }

    public function test_bloqueia_remocao_da_ultima_matricula_sem_persistencia_parcial(): void
    {
        $this->seedCargoProfessor();
        $setor = $this->criarSetor('Setor da escola única');
        $escola = $this->criarEscola('Escola Única', $setor);
        $service = app(PessoaProfessorService::class);

        $servidor = $service->criarPessoaProfessor([
            'nome' => 'Professor de matrícula única',
            'email' => 'professor.unico@edu.umuarama.pr.gov.br',
            'status' => Servidor::STATUS_ATIVO,
        ], [[
            'matricula' => 'MAT-UNICA',
            'turno' => 'integral',
            'escolas' => [['id_escola' => $escola->id]],
        ]]);

        $matriculaId = $servidor->matriculas()->value('id');
        $professorId = $servidor->professores()->value('id');

        try {
            $service->atualizarPessoaProfessor($servidor, [
                'nome' => 'Nome que deve sofrer rollback',
                'email' => $servidor->email,
                'status' => Servidor::STATUS_ATIVO,
            ], []);

            $this->fail('A remoção da última matrícula deveria ser rejeitada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('matriculas_professor', $exception->errors());
        }

        $this->assertDatabaseHas('servidores', [
            'id' => $servidor->id,
            'nome' => 'Professor de matrícula única',
        ]);
        $this->assertDatabaseHas('professor_matriculas', [
            'id' => $matriculaId,
            'matricula' => 'MAT-UNICA',
        ]);
        $this->assertDatabaseHas('professores', [
            'id' => $professorId,
            'professor_matricula_id' => $matriculaId,
            'ativo' => true,
        ]);
    }

    public function test_recadastrar_matricula_reutiliza_lotacao_historica_sem_duplicar_professor(): void
    {
        $cenario = $this->criarCenarioComDuasMatriculas();

        $this->manterSomenteMatricula($cenario, $cenario['matriculaMantida'], $cenario['professorMantido']);

        app(PessoaProfessorService::class)->atualizarPessoaProfessor($cenario['servidor']->fresh(), [
            'nome' => $cenario['servidor']->nome,
            'email' => $cenario['servidor']->email,
            'status' => Servidor::STATUS_ATIVO,
        ], [
            [
                'id' => $cenario['matriculaMantida']->id,
                'matricula' => 'MAT-MANTER',
                'turno' => 'tarde',
                'escolas' => [[
                    'id' => $cenario['professorMantido']->id,
                    'id_escola' => $cenario['escolaMantida']->id,
                    'vinculos_turma_componente' => [],
                ]],
            ],
            [
                'matricula' => 'MAT-REMOVER',
                'turno' => 'manha',
                'escolas' => [[
                    'id_escola' => $cenario['escolaRemovida']->id,
                    'vinculos_turma_componente' => [[
                        'turma_id' => $cenario['turma']->id,
                        'componente_curricular_id' => $cenario['componente']->id,
                    ]],
                ]],
            ],
        ]);

        $professorReativado = $cenario['professorRemovido']->fresh();
        $matriculaRecriada = PessoaMatricula::query()
            ->where('servidor_id', $cenario['servidor']->id)
            ->where('matricula', 'MAT-REMOVER')
            ->sole();

        $this->assertTrue($professorReativado->ativo);
        $this->assertSame($matriculaRecriada->id, $professorReativado->professor_matricula_id);
        $this->assertNull($professorReativado->desativado_em);
        $this->assertNull($professorReativado->desativado_por_id);
        $this->assertNull($professorReativado->motivo_desativacao);
        $this->assertSame(1, Professor::query()
            ->where('servidor_id', $cenario['servidor']->id)
            ->where('id_escola', $cenario['escolaRemovida']->id)
            ->where('matricula', 'MAT-REMOVER')
            ->count());
        $this->assertSame(2, PessoaMatricula::query()->where('servidor_id', $cenario['servidor']->id)->count());
        $this->assertDatabaseHas('turma_componente_professor', [
            'id' => $cenario['vinculo']->id,
            'professor_id' => $professorReativado->id,
            'tem_professor' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function criarCenarioComDuasMatriculas(): array
    {
        $this->seedCargoProfessor();
        $setor = $this->criarSetor('Setor pedagógico');
        $escolaRemovida = $this->criarEscola('Escola da matrícula removida', $setor);
        $escolaMantida = $this->criarEscola('Escola da matrícula mantida', $setor);
        $serie = Serie::query()->create(['codigo' => 'SER-REM', 'nome' => 'Série Remoção']);
        $componente = ComponenteCurricular::query()->create(['codigo' => 'COMP-REM', 'nome' => 'Componente Remoção']);
        $serie->componentesCurriculares()->attach($componente->id);
        $turma = Turma::query()->create([
            'codigo' => 'TURMA-REM',
            'nome' => 'Turma Remoção',
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escolaRemovida->id,
        ]);

        $servidor = app(PessoaProfessorService::class)->criarPessoaProfessor([
            'nome' => 'Professor com duas matrículas',
            'email' => 'professor.remocao@edu.umuarama.pr.gov.br',
            'status' => Servidor::STATUS_ATIVO,
        ], [
            [
                'matricula' => 'MAT-REMOVER',
                'turno' => 'manha',
                'escolas' => [[
                    'id_escola' => $escolaRemovida->id,
                    'vinculos_turma_componente' => [[
                        'turma_id' => $turma->id,
                        'componente_curricular_id' => $componente->id,
                    ]],
                ]],
            ],
            [
                'matricula' => 'MAT-MANTER',
                'turno' => 'tarde',
                'escolas' => [['id_escola' => $escolaMantida->id]],
            ],
        ]);

        $matriculaRemovida = $servidor->matriculas()->where('matricula', 'MAT-REMOVER')->sole();
        $matriculaMantida = $servidor->matriculas()->where('matricula', 'MAT-MANTER')->sole();
        $professorRemovido = $servidor->professores()->where('professor_matricula_id', $matriculaRemovida->id)->sole();
        $professorMantido = $servidor->professores()->where('professor_matricula_id', $matriculaMantida->id)->sole();
        $vinculo = TurmaComponenteProfessor::query()
            ->where('professor_id', $professorRemovido->id)
            ->sole();
        $vinculoFuncionalRemovidoId = $professorRemovido->servidor_funcao_administrativa_id;
        $vinculoFuncionalMantidoId = $professorMantido->servidor_funcao_administrativa_id;

        return compact(
            'servidor',
            'escolaRemovida',
            'escolaMantida',
            'turma',
            'componente',
            'matriculaRemovida',
            'matriculaMantida',
            'professorRemovido',
            'professorMantido',
            'vinculo',
            'vinculoFuncionalRemovidoId',
            'vinculoFuncionalMantidoId',
        );
    }

    /** @param array<string, mixed> $cenario */
    private function manterSomenteMatricula(array $cenario, PessoaMatricula $matricula, Professor $professor): void
    {
        app(PessoaProfessorService::class)->atualizarPessoaProfessor($cenario['servidor'], [
            'nome' => $cenario['servidor']->nome,
            'email' => $cenario['servidor']->email,
            'status' => Servidor::STATUS_ATIVO,
        ], [[
            'id' => $matricula->id,
            'matricula' => $matricula->matricula,
            'turno' => $matricula->turno,
            'escolas' => [[
                'id' => $professor->id,
                'id_escola' => $professor->id_escola,
                'vinculos_turma_componente' => [],
            ]],
        ]]);
    }

    private function seedCargoProfessor(): void
    {
        $roleProfessor = Role::query()->firstOrCreate(['name' => 'Professor', 'guard_name' => 'web']);
        $funcao = FuncaoAdministrativa::professorPadrao();
        $funcao->update(['concede_acesso_sistema' => true, 'exige_professor' => true]);
        $funcao->rolesPadrao()->sync([$roleProfessor->id]);
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
