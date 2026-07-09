<?php

namespace Tests\Feature\Pessoas;

use App\Models\Escola;
use App\Models\FuncaoAdministrativa;
use App\Models\Role;
use App\Models\Servidor;
use App\Models\Setor;
use App\Services\PessoaProfessorService;
use App\Services\PessoaScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PessoaProfessorEscopoTest extends TestCase
{
    use RefreshDatabase;

    public function test_shadow_sync_mantem_escolas_no_escopo_agregado(): void
    {
        $roleProfessor = Role::query()->firstOrCreate(['name' => 'Professor', 'guard_name' => 'web']);
        $funcao = FuncaoAdministrativa::professorPadrao();
        $funcao->rolesPadrao()->sync([$roleProfessor->id]);

        $setor = $this->criarSetor('Pedagógico');
        $escolaA = $this->criarEscola('Escola Escopo A', $setor);
        $escolaB = $this->criarEscola('Escola Escopo B', $setor);

        $servidor = app(PessoaProfessorService::class)->criarPessoaProfessor([
            'nome' => 'Professor Escopo',
            'email' => 'escopo@edu.umuarama.pr.gov.br',
            'status' => Servidor::STATUS_ATIVO,
        ], [
            [
                'matricula' => 'ESC-A',
                'turno' => 'manha',
                'id_escola' => $escolaA->id,
            ],
            [
                'matricula' => 'ESC-B',
                'turno' => 'tarde',
                'id_escola' => $escolaB->id,
            ],
        ]);

        $user = $servidor->fresh()->user;
        $scope = app(PessoaScopeService::class);

        $escolaIds = $scope->escolaIdsDosVinculos($user);

        $this->assertContains($escolaA->id, $escolaIds);
        $this->assertContains($escolaB->id, $escolaIds);
        $this->assertContains($setor->id, $scope->setorIdsDosVinculos($user));
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