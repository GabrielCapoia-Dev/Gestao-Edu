<?php

namespace Tests\Feature\Pessoas;

use App\Models\Escola;
use App\Models\FuncaoAdministrativa;
use App\Models\Professor;
use App\Models\Role;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\Setor;
use App\Services\PessoaProfessorService;
use App\Services\ServidorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PessoaProfessorCadastroTest extends TestCase
{
    use RefreshDatabase;

    public function test_cadastro_cria_registros_user_e_shadow_vinculo_com_setor_da_escola(): void
    {
        $roleProfessor = Role::query()->create(['name' => 'Professor', 'guard_name' => 'web']);
        $funcao = FuncaoAdministrativa::professorPadrao();
        $funcao->update(['concede_acesso_sistema' => true, 'exige_professor' => true]);
        $funcao->rolesPadrao()->sync([$roleProfessor->id]);

        $setor = $this->criarSetor('Pedagógico');
        $escolaA = $this->criarEscola('Escola A', $setor);
        $escolaB = $this->criarEscola('Escola B', $setor);

        $servidor = app(ServidorService::class)->criarServidorComFuncoes([
            'cargo' => 'professor',
            'nome' => 'Maria Professora',
            'email' => 'maria.prof@edu.umuarama.pr.gov.br',
            'status' => Servidor::STATUS_ATIVO,
        ], [
            'registros_professor' => [
                [
                    'matricula' => 'MAT-A',
                    'turno' => 'manha',
                    'id_escola' => $escolaA->id,
                ],
                [
                    'matricula' => 'MAT-B',
                    'turno' => 'tarde',
                    'id_escola' => $escolaB->id,
                ],
            ],
        ]);

        $servidor->refresh()->load(['user', 'professores']);

        $this->assertCount(2, $servidor->professores);
        $this->assertNotNull($servidor->user_id);
        $this->assertSame('maria.prof@edu.umuarama.pr.gov.br', $servidor->user->email);
        $this->assertTrue($servidor->user->hasRole('Professor'));

        $this->assertDatabaseHas('servidor_funcao_administrativa', [
            'servidor_id' => $servidor->id,
            'funcao_administrativa_id' => $funcao->id,
            'matricula' => 'MAT-A',
            'id_escola' => $escolaA->id,
            'setor_id' => $setor->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
        ]);

        foreach ($servidor->professores as $professor) {
            $this->assertSame('Maria Professora', $professor->nome);
            $this->assertSame('maria.prof@edu.umuarama.pr.gov.br', $professor->email);
            $this->assertSame($servidor->user_id, $professor->user_id);
        }
    }

    public function test_edicao_propaga_dados_do_servidor_para_professor_e_user(): void
    {
        $roleProfessor = Role::query()->create(['name' => 'Professor', 'guard_name' => 'web']);
        $funcao = FuncaoAdministrativa::professorPadrao();
        $funcao->rolesPadrao()->sync([$roleProfessor->id]);

        $setor = $this->criarSetor('Pedagógico');
        $escola = $this->criarEscola('Escola Sync', $setor);

        $servidor = app(PessoaProfessorService::class)->criarPessoaProfessor([
            'nome' => 'Nome Original',
            'email' => 'original@edu.umuarama.pr.gov.br',
            'status' => Servidor::STATUS_ATIVO,
        ], [[
            'matricula' => 'SYNC-001',
            'turno' => 'integral',
            'id_escola' => $escola->id,
        ]]);

        app(ServidorService::class)->atualizarServidorComFuncoes($servidor, [
            'cargo' => 'professor',
            'nome' => 'Nome Atualizado',
            'email' => 'atualizado@edu.umuarama.pr.gov.br',
            'status' => Servidor::STATUS_ATIVO,
        ], [
            'registros_professor' => [[
                'id' => $servidor->professores()->first()->id,
                'matricula' => 'SYNC-001',
                'turno' => 'integral',
                'id_escola' => $escola->id,
            ]],
        ]);

        $servidor->refresh()->load(['user', 'professores']);

        $this->assertSame('Nome Atualizado', $servidor->user->name);
        $this->assertSame('atualizado@edu.umuarama.pr.gov.br', $servidor->user->email);
        $this->assertSame('Nome Atualizado', $servidor->professores->first()->nome);
        $this->assertSame('atualizado@edu.umuarama.pr.gov.br', $servidor->professores->first()->email);
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