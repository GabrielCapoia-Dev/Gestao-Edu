<?php

namespace Tests\Feature\Alunos;

use App\Models\Aluno;
use App\Models\Escola;
use App\Models\Serie;
use App\Models\Turma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlunoFichaModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_ficha_exibe_registro_atual_e_datas_dos_outros_vinculos_do_mesmo_cgm(): void
    {
        $serie = Serie::query()->create([
            'codigo' => '4-ANO-FICHA',
            'nome' => '4º Ano',
        ]);

        $escolaOrigem = Escola::query()->create([
            'codigo' => 'ORIGEM-FICHA',
            'nome' => 'ESCOLA - Dr. Ângelo Moreira da Fonseca',
            'ativo' => true,
        ]);

        $escolaDestino = Escola::query()->create([
            'codigo' => 'DESTINO-FICHA',
            'nome' => 'ESCOLA - Evangélica',
            'ativo' => true,
        ]);

        $turmaOrigem = Turma::query()->create([
            'codigo' => '4D-TARDE',
            'nome' => 'D',
            'turno' => 'tarde',
            'id_serie' => $serie->id,
            'id_escola' => $escolaOrigem->id,
        ]);

        $turmaDestino = Turma::query()->create([
            'codigo' => '4A-INTEGRAL',
            'nome' => 'A',
            'turno' => 'integral',
            'id_serie' => $serie->id,
            'id_escola' => $escolaDestino->id,
        ]);

        $origem = Aluno::query()->create([
            'nome' => 'EMANUEL DE SOUZA SABINO',
            'cgm' => '1014238138',
            'data_nascimento' => '2016-05-12',
            'sexo' => 'M',
            'data_matricula' => null,
            'id_turma' => $turmaOrigem->id,
            'tipo_vinculo' => Aluno::TIPO_VINCULO_PRINCIPAL,
            'status' => Aluno::STATUS_MATRICULADO,
        ]);

        $destino = Aluno::query()->create([
            'nome' => 'EMANUEL DE SOUZA SABINO',
            'cgm' => '1014238138',
            'data_nascimento' => '2016-05-12',
            'sexo' => 'M',
            'data_matricula' => '2026-06-08',
            'id_turma' => $turmaDestino->id,
            'tipo_vinculo' => Aluno::TIPO_VINCULO_PRINCIPAL,
            'status' => Aluno::STATUS_PENDENTE,
            'pendencia_origem_aluno_id' => $origem->id,
        ]);

        $origem->loadMissing([
            'turma.escola',
            'turma.serie',
            'statusAlteradoPor',
            'alunoOrigem.turma.escola',
            'alunoOrigem.turma.serie',
            'pendenciaOrigem.turma.escola',
            'pendenciaOrigem.turma.serie',
            'turmaOrigem.escola',
            'turmaOrigem.serie',
        ]);

        $vinculos = Aluno::query()
            ->with(['turma.escola', 'turma.serie'])
            ->where('cgm', '1014238138')
            ->get();

        $html = view('components.alunos.ficha-aluno-modal', [
            'aluno' => $origem,
            'vinculos' => $vinculos,
            'podeListarEscolas' => true,
        ])->render();

        $this->assertStringContainsString('ESCOLA - Dr. Ângelo Moreira da Fonseca', $html);
        $this->assertStringContainsString('ESCOLA - Evangélica', $html);
        $this->assertStringContainsString('08/06/2026', $html);
        $this->assertStringContainsString('REGISTRO ABERTO', $html);
        $this->assertStringContainsString('matrícula Principal pendente', $html);
        $this->assertSame('2026-06-08', $destino->fresh()->data_matricula?->toDateString());
    }
}
