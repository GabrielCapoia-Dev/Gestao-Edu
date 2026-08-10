<?php

namespace Tests\Feature\Pessoas;

use App\Models\Escola;
use App\Models\FuncaoAdministrativa;
use App\Models\Professor;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\Setor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackfillPessoaMatriculasTest extends TestCase
{
    use RefreshDatabase;

    public function test_nao_consolida_pessoas_duplicadas_por_cpf(): void
    {
        $setor = $this->criarSetor('Pedagógico');

        // Insere formatos legados distintos no banco (sem mutator de normalização de CPF)
        // para validar consolidação por dígitos.
        $principalId = DB::table('servidores')->insertGetId([
            'cpf' => '123.456.789-00',
            'nome' => 'Maria Duplicada',
            'status' => Servidor::STATUS_ATIVO,
            'setor_id' => $setor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $duplicataId = DB::table('servidores')->insertGetId([
            'cpf' => '12345678900',
            'nome' => 'Maria Duplicada Cópia',
            'matricula' => 'MAT-LEGADA',
            'status' => Servidor::STATUS_ATIVO,
            'setor_id' => $setor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $duplicataId,
            'funcao_administrativa_id' => $this->funcaoAuxiliar()->id,
            'matricula' => 'MAT-VINC',
            'setor_id' => $setor->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
        ]);

        Artisan::call('pessoas:backfill-matriculas');

        $this->assertSame(2, Servidor::query()->where('cpf', 'like', '%123%')->count());
        $this->assertDatabaseHas('servidores', ['id' => $principalId]);
        $this->assertDatabaseHas('servidores', ['id' => $duplicataId]);
        $this->assertDatabaseHas('servidor_funcao_administrativa', [
            'servidor_id' => $duplicataId,
            'matricula' => 'MAT-VINC',
        ]);
    }

    public function test_backfill_propaga_matricula_legada_para_vinculos(): void
    {
        $setor = $this->criarSetor('Administrativo');
        $funcao = $this->funcaoAuxiliar();

        $servidor = Servidor::query()->create([
            'nome' => 'Servidor Legado',
            'matricula' => 'LEG-001',
            'setor_id' => $setor->id,
            'status' => Servidor::STATUS_ATIVO,
        ]);

        ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $servidor->id,
            'funcao_administrativa_id' => $funcao->id,
            'setor_id' => $setor->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
        ]);

        Artisan::call('pessoas:backfill-matriculas');

        $this->assertDatabaseHas('servidor_funcao_administrativa', [
            'servidor_id' => $servidor->id,
            'matricula' => 'LEG-001',
        ]);
    }

    public function test_backfill_liga_professor_ao_vinculo_funcional(): void
    {
        $escola = $this->criarEscola('Escola Backfill');
        $funcaoProfessor = FuncaoAdministrativa::professorPadrao();

        $servidor = Servidor::query()->create([
            'nome' => 'Professor Vinculado',
            'matricula' => 'PROF-001',
            'id_escola' => $escola->id,
            'setor_id' => $escola->setor_id,
            'status' => Servidor::STATUS_ATIVO,
        ]);

        $vinculo = ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $servidor->id,
            'funcao_administrativa_id' => $funcaoProfessor->id,
            'matricula' => 'PROF-001',
            'id_escola' => $escola->id,
            'setor_id' => $escola->setor_id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
        ]);

        // Sem observer: evita shadow sync criar outro vínculo e mascarar o backfill.
        $professor = Professor::withoutEvents(fn () => Professor::query()->create([
            'servidor_id' => $servidor->id,
            'id_escola' => $escola->id,
            'matricula' => 'PROF-001',
            'nome' => 'Professor Vinculado',
            'email' => 'professor.vinculado@edu.umuarama.pr.gov.br',
        ]));

        Artisan::call('pessoas:backfill-matriculas');

        $this->assertDatabaseHas('professores', [
            'id' => $professor->id,
            'servidor_funcao_administrativa_id' => $vinculo->id,
        ]);
    }

    public function test_backfill_e_idempotente(): void
    {
        $setor = $this->criarSetor('Operacional');

        Servidor::query()->create([
            'cpf' => '987.654.321-00',
            'nome' => 'Servidor Idempotente',
            'matricula' => 'IDEM-001',
            'setor_id' => $setor->id,
            'status' => Servidor::STATUS_ATIVO,
        ]);

        Artisan::call('pessoas:backfill-matriculas');
        $totalAposPrimeira = Servidor::query()->count();

        Artisan::call('pessoas:backfill-matriculas');

        $this->assertSame($totalAposPrimeira, Servidor::query()->count());
    }

    private function funcaoAuxiliar(): FuncaoAdministrativa
    {
        return FuncaoAdministrativa::query()->create([
            'nome' => 'Auxiliar Administrativo',
            'categoria' => FuncaoAdministrativa::CATEGORIA_OPERACIONAL,
            'ativo' => true,
            'exige_professor' => false,
            'tem_relacao_turma' => false,
        ]);
    }

    private function criarSetor(string $nome): Setor
    {
        return Setor::query()->create([
            'nome' => $nome,
            'ativo' => true,
            'status' => 'Ativo',
            'is_default_root' => true,
            'contexto' => 'central',
        ]);
    }

    private function criarEscola(string $nome): Escola
    {
        $setor = $this->criarSetor('Setor '.$nome);

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
