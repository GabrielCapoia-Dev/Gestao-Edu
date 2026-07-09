<?php

namespace Tests\Feature\Pessoas;

use App\Models\Escola;
use App\Models\FuncaoAdministrativa;
use App\Models\Role;
use App\Models\Servidor;
use App\Models\Setor;
use App\Models\User;
use App\Services\PessoaProfessorService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PessoaProfessorRolesImutaveisTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_service_mantem_role_professor_ao_sincronizar_acessos(): void
    {
        $roleProfessor = Role::query()->firstOrCreate(['name' => 'Professor', 'guard_name' => 'web']);
        $roleExtra = Role::query()->create(['name' => 'Coordenador', 'guard_name' => 'web']);

        $funcao = FuncaoAdministrativa::professorPadrao();
        $funcao->rolesPadrao()->sync([$roleProfessor->id]);

        $setor = $this->criarSetor('Pedagógico');
        $escola = $this->criarEscola('Escola Roles', $setor);

        $servidor = app(PessoaProfessorService::class)->criarPessoaProfessor([
            'nome' => 'Professor Roles',
            'email' => 'roles@edu.umuarama.pr.gov.br',
            'status' => Servidor::STATUS_ATIVO,
        ], [[
            'matricula' => 'ROLE-001',
            'turno' => 'manha',
            'id_escola' => $escola->id,
        ]]);

        $user = $servidor->fresh()->user;
        $this->assertTrue($user->hasRole('Professor'));

        app(UserService::class)->sincronizarAcessosDoUsuario($user, [
            'roles' => [$roleExtra->id],
        ]);

        $user->refresh();

        $this->assertTrue($user->hasRole('Professor'));
        $this->assertTrue($user->hasRole('Coordenador'));
    }

    public function test_usuario_eh_professor_quando_servidor_possui_registros_ativos(): void
    {
        $setor = $this->criarSetor('Pedagógico');
        $escola = $this->criarEscola('Escola Flag', $setor);

        $user = User::factory()->create();
        $servidor = Servidor::query()->create([
            'user_id' => $user->id,
            'nome' => $user->name,
            'email' => $user->email,
            'status' => Servidor::STATUS_ATIVO,
        ]);

        app(PessoaProfessorService::class)->sincronizarRegistros($servidor, [[
            'matricula' => 'FLAG-001',
            'turno' => 'tarde',
            'id_escola' => $escola->id,
        ]]);

        $this->assertTrue(app(\App\Services\PessoaAcessoService::class)->usuarioEhProfessor($user));
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