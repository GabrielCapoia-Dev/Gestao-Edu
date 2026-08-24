<?php

namespace Tests\Feature\Pessoas;

use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\FuncaoAdministrativa;
use App\Models\PessoaExclusaoDefinitiva;
use App\Models\PessoaMatricula;
use App\Models\Professor;
use App\Models\Role;
use App\Models\Serie;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\Setor;
use App\Models\Turma;
use App\Models\TurmaComponenteProfessor;
use App\Models\User;
use App\Services\PessoaExclusaoDefinitivaService;
use App\Services\ServidorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

class PessoaExclusaoDefinitivaTest extends TestCase
{
    use RefreshDatabase;

    private User $operador;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('permissoes:criar');
        $this->operador = User::factory()->create(['email_approved' => true]);
        $this->operador->assignRole(Role::query()->where('name', 'Admin')->firstOrFail());
        $pessoaOperador = Servidor::query()->create([
            'user_id' => $this->operador->id,
            'nome' => $this->operador->name,
            'email' => $this->operador->email,
            'status' => Servidor::STATUS_ATIVO,
        ]);
        ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $pessoaOperador->id,
            'funcao_administrativa_id' => FuncaoAdministrativa::professorPadrao()->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'origem' => 'teste',
        ]);
        $this->actingAs($this->operador);
    }

    public function test_exclui_pessoa_arquivada_anonimiza_historico_e_libera_identificadores(): void
    {
        [$pessoa, $user, $professor, $matricula, $vinculo, $pivot] = $this->criarPessoaCompleta();

        DB::table('export_requests')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'user_id_legado' => $user->id,
            'user_nome_snapshot' => $user->name,
            'user_email_snapshot' => $user->email,
            'type' => 'teste',
            'format' => 'csv',
            'fingerprint' => str_repeat('a', 64),
            'status' => 'completed',
            'progress_current' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(ServidorService::class)->arquivarPessoa($pessoa);
        $arquivada = Servidor::withTrashed()->findOrFail($pessoa->id);

        $this->assertTrue(Gate::forUser($this->operador)->allows('forceDelete', $arquivada));
        app(PessoaExclusaoDefinitivaService::class)->excluir($arquivada, $this->operador);

        $this->assertNull(Servidor::withTrashed()->find($pessoa->id));
        $this->assertDatabaseMissing('professor_matriculas', ['id' => $matricula->id]);
        $this->assertDatabaseHas('professores', [
            'id' => $professor->id,
            'servidor_id' => null,
            'professor_matricula_id' => null,
            'email' => null,
            'telefone' => null,
            'ativo' => false,
        ]);
        $this->assertStringStartsWith('EXCLUIDO-PROF-', Professor::query()->findOrFail($professor->id)->matricula);
        $this->assertDatabaseHas('servidor_funcao_administrativa', [
            'id' => $vinculo->id,
            'servidor_id' => null,
            'matricula' => null,
            'status' => ServidorFuncaoAdministrativa::STATUS_INATIVO,
        ]);
        $this->assertDatabaseHas('turma_componente_professor', [
            'id' => $pivot->id,
            'professor_id' => null,
            'tem_professor' => false,
        ]);

        $anonimo = User::withTrashed()->findOrFail($user->id);
        $this->assertTrue($anonimo->trashed());
        $this->assertNull($anonimo->email);
        $this->assertStringStartsWith('Usuário excluído #', $anonimo->name);
        $this->assertFalse((bool) $anonimo->ativo);
        $this->assertCount(0, $anonimo->roles);
        $this->assertCount(0, $anonimo->permissions);
        $this->assertDatabaseMissing('escola_user', ['user_id' => $user->id]);
        $this->assertDatabaseHas('export_requests', [
            'user_id' => $user->id,
            'user_nome_snapshot' => 'Usuário excluído',
            'user_email_snapshot' => null,
        ]);

        $auditoria = PessoaExclusaoDefinitiva::query()->sole();
        $this->assertSame($pessoa->id, $auditoria->servidor_id_legado);
        $this->assertSame($this->operador->id, $auditoria->executado_por_user_id);
        $this->assertSame(1, $auditoria->resumo['usuario_anonimizado']);

        $novaPessoa = Servidor::query()->create([
            'nome' => 'Nova Pessoa',
            'cpf' => '12345678901',
            'email' => 'pessoa.excluir@edu.umuarama.pr.gov.br',
            'status' => Servidor::STATUS_INATIVO,
        ]);
        PessoaMatricula::query()->create([
            'servidor_id' => $novaPessoa->id,
            'matricula' => 'MAT-EXCLUIR',
            'turno' => 'manha',
        ]);
        $novaConta = User::factory()->create(['email' => 'pessoa.excluir@edu.umuarama.pr.gov.br']);

        $this->assertNotSame($user->id, $novaConta->id);
        $this->assertSame('12345678901', $novaPessoa->cpf);
    }

    public function test_bloqueia_registro_ativo_e_administrador_protegido(): void
    {
        $user = User::factory()->create(['email' => 'compartilhada@edu.umuarama.pr.gov.br']);
        $pessoa = Servidor::query()->create([
            'user_id' => $user->id,
            'nome' => 'Pessoa Ativa',
            'email' => $user->email,
            'status' => Servidor::STATUS_INATIVO,
        ]);

        $this->assertFalse(Gate::forUser($this->operador)->allows('forceDelete', $pessoa));

        $adminAlvo = User::factory()->create();
        $adminAlvo->assignRole(Role::query()->where('name', 'Admin')->firstOrFail());
        $pessoaAdmin = Servidor::query()->create([
            'user_id' => $adminAlvo->id,
            'nome' => 'Administrador Protegido',
            'email' => $adminAlvo->email,
            'status' => Servidor::STATUS_INATIVO,
        ]);
        $pessoaAdmin->delete();

        $this->assertFalse(Gate::forUser($this->operador)->allows(
            'forceDelete',
            Servidor::withTrashed()->findOrFail($pessoaAdmin->id),
        ));
    }

    /** @return array{Servidor,User,Professor,PessoaMatricula,ServidorFuncaoAdministrativa,TurmaComponenteProfessor} */
    private function criarPessoaCompleta(): array
    {
        $setor = Setor::query()->create([
            'nome' => 'Setor Exclusão',
            'ativo' => true,
            'status' => 'Ativo',
            'is_default_root' => true,
            'contexto' => 'escolar',
            'exige_vinculo_escola' => true,
        ]);
        $escola = Escola::query()->create([
            'codigo' => 'ESC-EXC',
            'nome' => 'Escola Exclusão',
            'setor_id' => $setor->id,
            'email' => 'escola.exclusao@teste.local',
            'telefone' => '(44) 99999-9999',
            'ativo' => true,
        ]);
        $user = User::factory()->create([
            'name' => 'Pessoa para Excluir',
            'email' => 'pessoa.excluir@edu.umuarama.pr.gov.br',
            'email_approved' => true,
        ]);
        $user->assignRole(Role::query()->firstOrCreate(['name' => 'Professor', 'guard_name' => 'web']));
        $user->escolas()->attach($escola->id);

        $pessoa = Servidor::query()->create([
            'user_id' => $user->id,
            'nome' => $user->name,
            'cpf' => '12345678901',
            'email' => $user->email,
            'telefone' => '(44) 98888-7777',
            'matricula' => 'MAT-EXCLUIR',
            'status' => Servidor::STATUS_ATIVO,
            'carga_horaria' => 20,
            'jornada' => false,
        ]);
        $matricula = PessoaMatricula::query()->create([
            'servidor_id' => $pessoa->id,
            'matricula' => 'MAT-EXCLUIR',
            'turno' => 'manha',
        ]);
        $funcao = FuncaoAdministrativa::professorPadrao();
        $vinculo = ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $pessoa->id,
            'funcao_administrativa_id' => $funcao->id,
            'matricula' => 'MAT-EXCLUIR',
            'id_escola' => $escola->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'origem' => 'teste',
            'data_inicio' => now()->toDateString(),
        ]);
        $professor = Professor::query()->create([
            'user_id' => $user->id,
            'servidor_id' => $pessoa->id,
            'professor_matricula_id' => $matricula->id,
            'servidor_funcao_administrativa_id' => $vinculo->id,
            'id_escola' => $escola->id,
            'matricula' => 'MAT-EXCLUIR',
            'turno' => 'manha',
            'nome' => $pessoa->nome,
            'email' => $pessoa->email,
            'telefone' => $pessoa->telefone,
            'ativo' => true,
        ]);
        $serie = Serie::query()->create(['codigo' => 'SER-EXC', 'nome' => '1º Ano']);
        $componente = ComponenteCurricular::query()->create(['codigo' => 'COMP-EXC', 'nome' => 'Matemática']);
        $serie->componentesCurriculares()->attach($componente->id);
        $turma = Turma::query()->create([
            'codigo' => 'TUR-EXC',
            'nome' => 'Turma Exclusão',
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
        $pivot = TurmaComponenteProfessor::query()->create([
            'turma_id' => $turma->id,
            'componente_curricular_id' => $componente->id,
            'professor_id' => $professor->id,
            'tem_professor' => true,
        ]);

        return [$pessoa, $user, $professor, $matricula, $vinculo, $pivot];
    }
}
