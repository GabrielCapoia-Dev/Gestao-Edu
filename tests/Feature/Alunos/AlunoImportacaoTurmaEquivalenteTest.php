<?php

namespace Tests\Feature\Alunos;

use App\Models\Aluno;
use App\Models\Escola;
use App\Models\Serie;
use App\Models\Turma;
use App\Services\Alunos\AlunoImportacaoSpreadsheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class AlunoImportacaoTurmaEquivalenteTest extends TestCase
{
    use RefreshDatabase;

    public function test_nao_remaneja_principal_quando_ids_de_turma_diferem_mas_dados_da_turma_sao_iguais(): void
    {
        Storage::fake('local');

        $escola = Escola::query()->create([
            'nome' => 'ESCOLA - Evangélica',
            'ativo' => true,
        ]);

        // Simula uma base legada com séries duplicadas visualmente iguais.
        $serieOriginal = Serie::query()->create(['nome' => '4º Ano']);
        $serieDuplicada = Serie::query()->create(['nome' => '4º Ano']);

        $turmaOriginal = Turma::query()->create([
            'nome' => 'B',
            'turno' => 'integral',
            'id_serie' => $serieOriginal->id,
            'id_escola' => $escola->id,
        ]);

        Turma::query()->create([
            'nome' => 'B',
            'turno' => 'integral',
            'id_serie' => $serieDuplicada->id,
            'id_escola' => $escola->id,
        ]);

        $aluno = Aluno::query()->create([
            'nome' => 'ANA BEATRIZ DO NASCIMENTO DE ALMEIDA',
            'cgm' => '1018502700',
            'data_nascimento' => '2016-05-10',
            'sexo' => 'F',
            'data_matricula' => '2026-02-18',
            'id_turma' => $turmaOriginal->id,
            'tipo_vinculo' => Aluno::TIPO_VINCULO_PRINCIPAL,
            'status' => Aluno::STATUS_MATRICULADO,
        ]);

        $caminho = $this->criarPlanilhaNoStorage([
            ['Escola', 'Seriacao', 'Turma', 'Turno', 'CGM', 'Nome do Aluno', 'Data de Nascimento', 'Sexo', 'Data Matricula', 'Tipo de vinculo'],
            [$escola->nome, '4º Ano', 'B', 'Integral', '1018502700', 'ANA BEATRIZ DO NASCIMENTO DE ALMEIDA', '10/05/2016', 'F', '18/02/2026', 'Principal'],
        ]);

        $resultado = app(AlunoImportacaoSpreadsheetService::class)
            ->importar($caminho, null, 'local');

        $this->assertSame(0, $resultado['total_remanejado']);
        $this->assertSame(0, $resultado['total_importado']);
        $this->assertSame(0, $resultado['total_atualizado']);
        $this->assertSame(1, $resultado['total_sem_alteracao']);

        $this->assertDatabaseCount('alunos', 1);
        $this->assertSame(Aluno::STATUS_MATRICULADO, $aluno->fresh()->status);
        $this->assertSame($turmaOriginal->id, $aluno->fresh()->id_turma);
    }

    private function criarPlanilhaNoStorage(array $linhas): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Matriculados');

        foreach ($linhas as $indice => $linha) {
            $sheet->fromArray($linha, null, 'A'.($indice + 1));
        }

        $arquivoTemporario = tempnam(sys_get_temp_dir(), 'alunos_turma_equivalente_test_');
        (new Xlsx($spreadsheet))->save($arquivoTemporario);
        $spreadsheet->disconnectWorksheets();

        $caminho = 'imports/tests/'.basename($arquivoTemporario).'.xlsx';
        Storage::disk('local')->put($caminho, file_get_contents($arquivoTemporario));

        @unlink($arquivoTemporario);
        gc_collect_cycles();

        return $caminho;
    }
}
