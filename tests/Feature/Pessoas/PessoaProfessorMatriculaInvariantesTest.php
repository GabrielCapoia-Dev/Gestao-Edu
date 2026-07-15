<?php

namespace Tests\Feature\Pessoas;

use App\Models\Escola;
use App\Models\FuncaoAdministrativa;
use App\Models\Pessoa;
use App\Models\ProfessorMatricula;
use App\Models\Role;
use App\Models\Servidor;
use App\Models\Setor;
use App\Services\PessoaProfessorService;
use App\Services\ServidorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PessoaProfessorMatriculaInvariantesTest extends TestCase
{
    use RefreshDatabase;

    public function test_pessoa_model_e_centro_da_identidade_e_servidor_estende_pessoa(): void
    {
        $this->assertTrue(is_subclass_of(Servidor::class, Pessoa::class));

        $pessoa = Servidor::query()->create([
            'nome' => 'Identidade',
            'cpf' => '123.456.789-09',
            'email' => 'id@edu.umuarama.pr.gov.br',
            'status' => Pessoa::STATUS_ATIVO,
        ]);

        $this->assertInstanceOf(Pessoa::class, $pessoa);
        $this->assertSame('12345678909', $pessoa->cpf);
        $this->assertDatabaseHas('servidores', [
            'id' => $pessoa->id,
            'cpf' => '12345678909',
        ]);
    }

    public function test_cria_matricula_agregada_e_lotacoes_por_escola(): void
    {
        $this->seedCargoProfessor();
        $setor = $this->criarSetor('Pedagógico');
        $escolaA = $this->criarEscola('Escola A', $setor);
        $escolaB = $this->criarEscola('Escola B', $setor);

        $servidor = app(PessoaProfessorService::class)->criarPessoaProfessor([
            'nome' => 'Prof Multi Escola',
            'cpf' => '529.982.247-25',
            'email' => 'multi@edu.umuarama.pr.gov.br',
            'status' => Servidor::STATUS_ATIVO,
        ], [
            [
                'matricula' => 'MAT-1',
                'turno' => 'manha',
                'escolas' => [
                    ['id_escola' => $escolaA->id],
                    ['id_escola' => $escolaB->id],
                ],
            ],
        ]);

        $this->assertCount(1, $servidor->professorMatriculas);
        $this->assertCount(2, $servidor->professores);
        $this->assertSame('manha', $servidor->professorMatriculas->first()->turno);
        $this->assertTrue(
            $servidor->professores->every(fn ($p) => (int) $p->professor_matricula_id === (int) $servidor->professorMatriculas->first()->id)
        );
    }

    public function test_rejeita_mais_de_duas_matriculas(): void
    {
        $this->seedCargoProfessor();
        $setor = $this->criarSetor('Pedagógico');
        $escola = $this->criarEscola('Escola Limite', $setor);

        $this->expectException(ValidationException::class);

        app(PessoaProfessorService::class)->criarPessoaProfessor([
            'nome' => 'Prof Limite',
            'email' => 'limite@edu.umuarama.pr.gov.br',
            'status' => Servidor::STATUS_ATIVO,
        ], [
            ['matricula' => 'M1', 'turno' => 'manha', 'escolas' => [['id_escola' => $escola->id]]],
            ['matricula' => 'M2', 'turno' => 'tarde', 'escolas' => [['id_escola' => $escola->id]]],
            ['matricula' => 'M3', 'turno' => 'integral', 'escolas' => [['id_escola' => $escola->id]]],
        ]);
    }

    public function test_rejeita_mesma_matricula_com_turnos_diferentes_no_formato_flat(): void
    {
        $this->seedCargoProfessor();
        $setor = $this->criarSetor('Pedagógico');
        $escolaA = $this->criarEscola('Escola X', $setor);
        $escolaB = $this->criarEscola('Escola Y', $setor);

        $this->expectException(ValidationException::class);

        app(ServidorService::class)->criarServidorComFuncoes([
            'cargo' => 'professor',
            'nome' => 'Prof Conflito Turno',
            'email' => 'conflito@edu.umuarama.pr.gov.br',
            'status' => Servidor::STATUS_ATIVO,
        ], [
            'registros_professor' => [
                [
                    'matricula' => 'IGUAL',
                    'turno' => 'manha',
                    'id_escola' => $escolaA->id,
                ],
                [
                    'matricula' => 'IGUAL',
                    'turno' => 'tarde',
                    'id_escola' => $escolaB->id,
                ],
            ],
        ]);
    }

    public function test_duas_matriculas_na_mesma_escola(): void
    {
        $this->seedCargoProfessor();
        $setor = $this->criarSetor('Pedagógico');
        $escola = $this->criarEscola('Escola Dupla', $setor);

        $servidor = app(PessoaProfessorService::class)->criarPessoaProfessor([
            'nome' => 'Prof Dupla',
            'email' => 'dupla@edu.umuarama.pr.gov.br',
            'status' => Servidor::STATUS_ATIVO,
        ], [
            [
                'matricula' => 'MANHA-1',
                'turno' => 'manha',
                'escolas' => [['id_escola' => $escola->id]],
            ],
            [
                'matricula' => 'TARDE-1',
                'turno' => 'tarde',
                'escolas' => [['id_escola' => $escola->id]],
            ],
        ]);

        $this->assertCount(2, $servidor->professorMatriculas);
        $this->assertCount(2, $servidor->professores->where('id_escola', $escola->id));
        $this->assertSame(ProfessorMatricula::MAX_POR_PESSOA, $servidor->professorMatriculas->count());
    }

    public function test_substituicao_remove_matriculas_canonicas_e_preserva_historico_desvinculado(): void
    {
        $this->seedCargoProfessor();
        $setor = $this->criarSetor('Pedagógico');
        $escola = $this->criarEscola('Escola Histórico Matrículas', $setor);
        $service = app(PessoaProfessorService::class);
        $servidor = $service->criarPessoaProfessor([
            'nome' => 'Prof Histórico de Matrículas',
            'email' => 'historico.matriculas@edu.umuarama.pr.gov.br',
            'status' => Servidor::STATUS_ATIVO,
        ], [
            ['matricula' => 'ANTIGA-M', 'turno' => 'manha', 'escolas' => [['id_escola' => $escola->id]]],
            ['matricula' => 'ANTIGA-T', 'turno' => 'tarde', 'escolas' => [['id_escola' => $escola->id]]],
        ]);
        $matriculasAntigasIds = $servidor->matriculas->pluck('id')->all();
        $professoresAntigosIds = $servidor->professores->pluck('id')->all();
        $servidor->professores()->update(['ativo' => false]);

        $atualizado = $service->atualizarPessoaProfessor($servidor, [
            'nome' => $servidor->nome,
            'email' => $servidor->email,
            'status' => Servidor::STATUS_ATIVO,
        ], [
            ['matricula' => 'NOVA-M', 'turno' => 'manha', 'escolas' => [['id_escola' => $escola->id]]],
            ['matricula' => 'NOVA-T', 'turno' => 'tarde', 'escolas' => [['id_escola' => $escola->id]]],
        ]);

        $this->assertSame(['NOVA-M', 'NOVA-T'], $atualizado->matriculas->pluck('matricula')->sort()->values()->all());
        $this->assertSame(0, ProfessorMatricula::query()->whereIn('id', $matriculasAntigasIds)->count());
        $this->assertSame(2, $servidor->professores()
            ->whereIn('id', $professoresAntigosIds)
            ->where('ativo', false)
            ->whereNull('professor_matricula_id')
            ->count());
        $this->assertSame(
            ['ANTIGA-M', 'ANTIGA-T'],
            $servidor->professores()
                ->whereIn('id', $professoresAntigosIds)
                ->pluck('matricula')
                ->sort()
                ->values()
                ->all(),
        );
    }

    public function test_substituicao_considera_estado_final_apos_remover_lotacoes_ativas(): void
    {
        $this->seedCargoProfessor();
        $setor = $this->criarSetor('Pedagógico');
        $escola = $this->criarEscola('Escola Substituição Ativa', $setor);
        $service = app(PessoaProfessorService::class);
        $servidor = $service->criarPessoaProfessor([
            'nome' => 'Prof Substituição Ativa',
            'email' => 'substituicao.ativa@edu.umuarama.pr.gov.br',
            'status' => Servidor::STATUS_ATIVO,
        ], [
            ['matricula' => 'ATIVA-M', 'turno' => 'manha', 'escolas' => [['id_escola' => $escola->id]]],
            ['matricula' => 'ATIVA-T', 'turno' => 'tarde', 'escolas' => [['id_escola' => $escola->id]]],
        ]);

        $atualizado = $service->atualizarPessoaProfessor($servidor, [
            'nome' => $servidor->nome,
            'email' => $servidor->email,
            'status' => Servidor::STATUS_ATIVO,
        ], [
            ['matricula' => 'FINAL-M', 'turno' => 'manha', 'escolas' => [['id_escola' => $escola->id]]],
            ['matricula' => 'FINAL-T', 'turno' => 'tarde', 'escolas' => [['id_escola' => $escola->id]]],
        ]);

        $this->assertSame(['FINAL-M', 'FINAL-T'], $atualizado->matriculas->pluck('matricula')->sort()->values()->all());
    }

    public function test_rejeita_matriculas_no_mesmo_turno(): void
    {
        $this->seedCargoProfessor();
        $setor = $this->criarSetor('Pedagógico');
        $escola = $this->criarEscola('Escola Turno Duplicado', $setor);

        try {
            app(PessoaProfessorService::class)->criarPessoaProfessor([
                'nome' => 'Prof Mesmo Turno',
                'email' => 'mesmo.turno@edu.umuarama.pr.gov.br',
                'status' => Servidor::STATUS_ATIVO,
            ], [
                ['matricula' => 'MANHA-A', 'turno' => 'manha', 'escolas' => [['id_escola' => $escola->id]]],
                ['matricula' => 'MANHA-B', 'turno' => 'manha', 'escolas' => [['id_escola' => $escola->id]]],
            ]);
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Não é permitido duas matrículas no mesmo turno.', $this->mensagensValidacao($exception));

            return;
        }

        $this->fail('Matrículas no mesmo turno deveriam ser rejeitadas.');
    }

    public function test_rejeita_integral_com_outra_matricula(): void
    {
        $this->seedCargoProfessor();
        $setor = $this->criarSetor('Pedagógico');
        $escola = $this->criarEscola('Escola Integral', $setor);

        try {
            app(PessoaProfessorService::class)->criarPessoaProfessor([
                'nome' => 'Prof Integral Duplicado',
                'email' => 'integral.duplicado@edu.umuarama.pr.gov.br',
                'status' => Servidor::STATUS_ATIVO,
            ], [
                ['matricula' => 'INT-1', 'turno' => 'integral', 'escolas' => [['id_escola' => $escola->id]]],
                ['matricula' => 'TARDE-2', 'turno' => 'tarde', 'escolas' => [['id_escola' => $escola->id]]],
            ]);
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Matrícula integral já cobre manhã e tarde.', $this->mensagensValidacao($exception));

            return;
        }

        $this->fail('Matrícula integral com outra matrícula deveria ser rejeitada.');
    }

    public function test_rejeita_turno_invalido(): void
    {
        $this->seedCargoProfessor();
        $setor = $this->criarSetor('Pedagógico');
        $escola = $this->criarEscola('Escola Turno Inválido', $setor);

        try {
            app(PessoaProfessorService::class)->criarPessoaProfessor([
                'nome' => 'Prof Turno Inválido',
                'email' => 'turno.invalido@edu.umuarama.pr.gov.br',
                'status' => Servidor::STATUS_ATIVO,
            ], [
                ['matricula' => 'NOITE-1', 'turno' => 'noite', 'escolas' => [['id_escola' => $escola->id]]],
            ]);
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Turno informado é inválido.', $this->mensagensValidacao($exception));

            return;
        }

        $this->fail('Turno inválido deveria ser rejeitado.');
    }

    public function test_helpers_de_turno_para_nova_matricula(): void
    {
        $this->assertTrue(ProfessorMatricula::podeAdicionarMatricula([
            ['matricula' => 'M1', 'turno' => 'manha'],
        ]));
        $this->assertFalse(ProfessorMatricula::podeAdicionarMatricula([
            ['matricula' => 'M1', 'turno' => 'integral'],
        ]));
        $this->assertFalse(ProfessorMatricula::podeAdicionarMatricula([
            ['matricula' => 'M1', 'turno' => 'manha'],
            ['matricula' => 'M2', 'turno' => 'tarde'],
        ]));

        $this->assertSame(
            ['tarde' => 'Tarde'],
            ProfessorMatricula::turnosDisponiveisParaItem(['manha']),
        );
        $this->assertSame(
            ['manha' => 'Manhã'],
            ProfessorMatricula::turnosDisponiveisParaItem(['tarde']),
        );
    }

    public function test_user_name_email_sincronizam_com_pessoa(): void
    {
        $this->seedCargoProfessor();
        $setor = $this->criarSetor('Pedagógico');
        $escola = $this->criarEscola('Escola Sync', $setor);

        $servidor = app(PessoaProfessorService::class)->criarPessoaProfessor([
            'nome' => 'Nome Original',
            'email' => 'original@edu.umuarama.pr.gov.br',
            'status' => Servidor::STATUS_ATIVO,
        ], [
            [
                'matricula' => 'SYNC-M',
                'turno' => 'integral',
                'escolas' => [['id_escola' => $escola->id]],
            ],
        ]);

        app(PessoaProfessorService::class)->atualizarPessoaProfessor($servidor, [
            'nome' => 'Nome Novo',
            'email' => 'novo@edu.umuarama.pr.gov.br',
            'status' => Servidor::STATUS_ATIVO,
        ], [
            [
                'id' => $servidor->professorMatriculas->first()->id,
                'matricula' => 'SYNC-M',
                'turno' => 'integral',
                'escolas' => [[
                    'id' => $servidor->professores->first()->id,
                    'id_escola' => $escola->id,
                ]],
            ],
        ]);

        $servidor->refresh()->load(['user', 'professores']);

        $this->assertSame('Nome Novo', $servidor->user->name);
        $this->assertSame('novo@edu.umuarama.pr.gov.br', $servidor->user->email);
        $this->assertSame('Nome Novo', $servidor->professores->first()->nome);
    }

    private function seedCargoProfessor(): void
    {
        $roleProfessor = Role::query()->firstOrCreate(['name' => 'Professor', 'guard_name' => 'web']);
        $funcao = FuncaoAdministrativa::professorPadrao();
        $funcao->update(['concede_acesso_sistema' => true, 'exige_professor' => true]);
        $funcao->rolesPadrao()->sync([$roleProfessor->id]);
    }

    private function mensagensValidacao(ValidationException $exception): string
    {
        return collect($exception->errors())->flatten()->implode(' ');
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
