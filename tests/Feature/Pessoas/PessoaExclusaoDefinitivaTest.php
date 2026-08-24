<?php

namespace Tests\Feature\Pessoas;

use App\Filament\Admin\Resources\Servidores\Pages\ManageServidores;
use App\Models\Aluno;
use App\Models\Avaliacao;
use App\Models\AvaliacaoAlunoDocumento;
use App\Models\AvaliacaoAlunoDocumentoHistorico;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\FuncaoAdministrativa;
use App\Models\PeriodoAvaliacao;
use App\Models\PessoaExclusaoDefinitiva;
use App\Models\PessoaMatricula;
use App\Models\Professor;
use App\Models\Role;
use App\Models\Serie;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\Setor;
use App\Models\TipoAvaliacao;
use App\Models\Turma;
use App\Models\TurmaComponenteProfessor;
use App\Models\User;
use App\Services\PessoaExclusaoDefinitivaService;
use App\Services\ServidorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
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

    public function test_exclui_fisicamente_pessoa_e_professor_sem_apagar_aluno_ou_autoria_da_avaliacao(): void
    {
        [$pessoa, $user, $professor, $matricula, $vinculo, $pivot, $turma] = $this->criarPessoaCompleta();
        [$aluno, $documento, $historico] = $this->criarHistoricoAvaliativo($turma, $professor);

        if (! Schema::hasColumn('alunos', 'id_professor')) {
            Schema::table('alunos', function ($table): void {
                $table->unsignedBigInteger('id_professor')->nullable();
            });
        }
        DB::table('alunos')->where('id', $aluno->id)->update(['id_professor' => $professor->id]);

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
        Livewire::actingAs($this->operador)
            ->test(ManageServidores::class)
            ->filterTable('trashed', false)
            ->assertCanSeeTableRecords([$arquivada])
            ->assertTableActionVisible('excluir_definitivamente', $arquivada)
            ->assertTableActionHasLabel('excluir_definitivamente', 'Excluir definitivamente', $arquivada)
            ->assertTableActionHasColor('excluir_definitivamente', 'danger', $arquivada);

        app(PessoaExclusaoDefinitivaService::class)->excluir($arquivada, $this->operador);

        $this->assertNull(Servidor::withTrashed()->find($pessoa->id));
        $this->assertDatabaseMissing('professor_matriculas', ['id' => $matricula->id]);
        $this->assertDatabaseMissing('professores', ['id' => $professor->id]);
        $this->assertDatabaseMissing('servidor_funcao_administrativa', ['id' => $vinculo->id]);
        $this->assertDatabaseHas('turma_componente_professor', [
            'id' => $pivot->id,
            'professor_id' => null,
            'tem_professor' => false,
        ]);
        $this->assertDatabaseHas('alunos', ['id' => $aluno->id, 'id_professor' => null]);

        $this->assertNull(User::withTrashed()->find($user->id));
        $this->assertDatabaseMissing('escola_user', ['user_id' => $user->id]);
        $this->assertDatabaseHas('export_requests', [
            'user_id' => null,
            'user_id_legado' => $user->id,
            'user_nome_snapshot' => 'Pessoa para Excluir',
            'user_email_snapshot' => 'pessoa.excluir@edu.umuarama.pr.gov.br',
        ]);

        $this->assertSame(
            'PESSOA PARA EXCLUIR',
            $documento->fresh()->payload['pautas']['1']['professor_nome'],
        );
        $this->assertSame(
            'PESSOA PARA EXCLUIR',
            $historico->fresh()->payload['pautas']['1']['professor_nome'],
        );

        $auditoria = PessoaExclusaoDefinitiva::query()->sole();
        $this->assertSame($pessoa->id, $auditoria->servidor_id_legado);
        $this->assertSame($this->operador->id, $auditoria->executado_por_user_id);
        $this->assertSame(1, $auditoria->resumo['professores_excluidos']);
        $this->assertSame(1, $auditoria->resumo['usuario_excluido']);
        $this->assertSame(2, $auditoria->resumo['documentos_avaliativos_com_autoria_preservada']);

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

    /** @return array{Servidor,User,Professor,PessoaMatricula,ServidorFuncaoAdministrativa,TurmaComponenteProfessor,Turma} */
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

        return [$pessoa, $user, $professor, $matricula, $vinculo, $pivot, $turma];
    }

    /** @return array{Aluno,AvaliacaoAlunoDocumento,AvaliacaoAlunoDocumentoHistorico} */
    private function criarHistoricoAvaliativo(Turma $turma, Professor $professor): array
    {
        $tipo = TipoAvaliacao::query()->create(['nome' => 'Tipo Exclusão', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Período Exclusão', 'status' => true]);
        $avaliacao = Avaliacao::query()->create([
            'nome' => 'Avaliação Exclusão',
            'tipo_avaliacao_id' => $tipo->id,
            'periodo_avaliacao_id' => $periodo->id,
            'data_inicio' => '2026-01-01',
            'data_fim' => '2026-12-31',
            'status' => Avaliacao::STATUS_ATIVA,
        ]);
        $aluno = Aluno::query()->create([
            'nome' => 'Aluno Preservado',
            'cgm' => 'CGM-EXCLUSAO-PROFESSOR',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turma->id,
        ]);
        $payload = [
            'v' => 1,
            'pautas' => [
                '1' => [
                    'pauta_id' => 1,
                    'alternativa_id' => 1,
                    'professor_id' => $professor->id,
                    'respondido_em' => now()->toIso8601String(),
                ],
            ],
            'informacoes_complementares' => [],
        ];
        $documento = AvaliacaoAlunoDocumento::query()->create([
            'avaliacao_id' => $avaliacao->id,
            'aluno_id' => $aluno->id,
            'cgm' => $aluno->cgm,
            'turma_id' => $turma->id,
            'escola_id' => $turma->id_escola,
            'serie_id' => $turma->id_serie,
            'payload' => $payload,
            'professor_ids' => [$professor->id],
        ]);
        $historico = AvaliacaoAlunoDocumentoHistorico::query()->create([
            'avaliacao_id' => $avaliacao->id,
            'documento_id' => $documento->id,
            'aluno_origem_id' => $aluno->id,
            'cgm' => $aluno->cgm,
            'turma_id' => $turma->id,
            'escola_id' => $turma->id_escola,
            'serie_id' => $turma->id_serie,
            'movimentacao_tipo' => AvaliacaoAlunoDocumentoHistorico::MOVIMENTACAO_TRANSFERENCIA,
            'payload' => $payload,
            'movimentado_em' => now(),
        ]);

        return [$aluno, $documento, $historico];
    }
}
