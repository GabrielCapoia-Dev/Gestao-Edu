<?php

namespace Tests\Feature\Pessoas;

use App\Filament\Admin\Resources\FuncaoAdministrativas\FuncaoAdministrativaResource;
use App\Models\Escola;
use App\Models\FuncaoAdministrativa;
use App\Models\Role;
use App\Models\Servidor;
use App\Models\Setor;
use App\Services\PessoaVinculoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PessoaCargoAcessoTest extends TestCase
{
    use RefreshDatabase;

    public function test_funcao_administrativa_nao_aparece_no_menu(): void
    {
        $this->assertFalse(FuncaoAdministrativaResource::shouldRegisterNavigation());
    }

    public function test_cargo_professor_provisiona_user_com_role_padrao(): void
    {
        $roleProfessor = Role::query()->firstOrCreate(['name' => 'Professor', 'guard_name' => 'web']);
        Permission::findOrCreate('Listar Turmas');

        $setor = Setor::query()->create([
            'nome' => 'Pedagógico',
            'ativo' => true,
            'status' => 'Ativo',
            'is_default_root' => true,
            'contexto' => 'escolar',
            'exige_vinculo_escola' => true,
        ]);

        $escola = Escola::query()->create([
            'codigo' => 'ESC-PROF',
            'nome' => 'Escola Professor',
            'setor_id' => $setor->id,
            'email' => 'escola.prof@teste.local',
            'telefone' => '(44) 99999-9999',
            'ativo' => true,
        ]);

        $funcao = FuncaoAdministrativa::query()->create([
            'nome' => 'Professor',
            'categoria' => FuncaoAdministrativa::CATEGORIA_PEDAGOGICO,
            'ativo' => true,
            'exige_professor' => true,
            'concede_acesso_sistema' => true,
        ]);

        $funcao->rolesPadrao()->sync([$roleProfessor->id]);

        $servidor = app(PessoaVinculoService::class)->criarPessoaComVinculos([
            'nome' => 'Professor Automático',
            'email' => 'professor.auto@edu.umuarama.pr.gov.br',
            'status' => Servidor::STATUS_ATIVO,
            'id_escola' => $escola->id,
            'setor_id' => $setor->id,
        ], [[
            'funcao_administrativa_id' => $funcao->id,
            'matricula' => 'PROF-AUTO-001',
            'setor_id' => $setor->id,
            'id_escola' => $escola->id,
        ]]);

        $servidor->refresh();

        $this->assertNotNull($servidor->user_id);
        $this->assertDatabaseHas('users', [
            'id' => $servidor->user_id,
            'email' => 'professor.auto@edu.umuarama.pr.gov.br',
        ]);
        $this->assertTrue($servidor->user->hasRole('Professor'));
        $this->assertDatabaseHas('professores', [
            'servidor_id' => $servidor->id,
            'id_escola' => $escola->id,
        ]);
    }
}