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

class AlunoImportacaoDataMatriculaTest extends TestCase
{
    use RefreshDatabase;

    public function test_importacao_reconhece_data_da_matricula_e_atualiza_aluno_existente(): void
    {
        Storage::fake('local');

        $escola = Escola::query()->create([
            'nome' => 'ESCOLA - Evangélica',
            'ativo' => true,
        ]);

        $serie = Serie::query()->create([
            'nome' => '4º Ano',
        ]);

        $turma = Turma::query()->create([
            'nome' => 'A',
            'turno' => 'integral',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);

        $aluno = Aluno::query()->create([
            'nome' => 'EMANUEL DE SOUZA SABINO',
            'cgm' => '1014238138',
            'data_nascimento' => '2016-05-12',
            'sexo' => 'M',
            'data_matricula' => null,
            'id_turma' => $turma->id,
            'tipo_vinculo' => Aluno::TIPO_VINCULO_PRINCIPAL,
            'status' => Aluno::STATUS_MATRICULADO,
        ]);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(AlunoImportacaoSpreadsheetService::SHEET_NAME);

        $sheet->fromArray([
            ['Escola', 'Seriação', 'Turma', 'Turno', 'CGM', 'Nome do aluno', 'Data de Nascimento', 'Sexo', 'Data da matrícula', 'Tipo de vínculo'],
            ['ESCOLA - Evangélica', '4º Ano', 'A', 'Integral', '1014238138', 'EMANUEL DE SOUZA SABINO', '12/05/2016', 'M', '08/06/2026', 'Principal'],
        ], null, 'A1');

        $arquivoTemporario = tempnam(sys_get_temp_dir(), 'aluno_data_matricula_');
        (new Xlsx($spreadsheet))->save($arquivoTemporario);
        $spreadsheet->disconnectWorksheets();

        $caminho = 'imports/tests/data-matricula.xlsx';
        Storage::disk('local')->put($caminho, file_get_contents($arquivoTemporario));
        @unlink($arquivoTemporario);

        $resultado = app(AlunoImportacaoSpreadsheetService::class)->importar(
            $caminho,
            null,
            'local',
        );

        $this->assertSame(1, $resultado['total_atualizado']);
        $this->assertSame('2026-06-08', $aluno->fresh()->data_matricula?->toDateString());
    }
}
