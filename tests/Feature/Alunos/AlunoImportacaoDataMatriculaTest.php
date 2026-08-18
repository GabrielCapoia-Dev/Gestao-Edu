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

    public function test_importacao_reconhece_data_da_matricula_e_preserva_data_especifica_de_cada_vinculo(): void
    {
        Storage::fake('local');

        $escola = Escola::query()->create([
            'codigo' => 'EVANG-DATA-MAT',
            'nome' => 'ESCOLA - Evangélica',
            'ativo' => true,
        ]);

        $seriePrincipal = Serie::query()->create([
            'codigo' => '4ANO-DATA-MAT',
            'nome' => '4º Ano',
        ]);

        $serieContraTurno = Serie::query()->create([
            'codigo' => '4ANO-INT-DATA-MAT',
            'nome' => '4º Ano - Integral',
        ]);

        $turmaPrincipal = Turma::query()->create([
            'codigo' => '4A-PRINC-DATA-MAT',
            'nome' => 'A',
            'turno' => 'integral',
            'id_serie' => $seriePrincipal->id,
            'id_escola' => $escola->id,
        ]);

        $turmaContraTurno = Turma::query()->create([
            'codigo' => '4A-CT-DATA-MAT',
            'nome' => 'A',
            'turno' => 'integral',
            'id_serie' => $serieContraTurno->id,
            'id_escola' => $escola->id,
        ]);

        $principal = Aluno::query()->create([
            'nome' => 'EMANUEL DE SOUZA SABINO',
            'cgm' => '1014238138',
            'data_nascimento' => '2016-05-12',
            'sexo' => 'M',
            'data_matricula' => null,
            'id_turma' => $turmaPrincipal->id,
            'tipo_vinculo' => Aluno::TIPO_VINCULO_PRINCIPAL,
            'status' => Aluno::STATUS_PENDENTE,
            'permite_contra_turno' => true,
        ]);

        $contraTurno = Aluno::query()->create([
            'nome' => 'EMANUEL DE SOUZA SABINO',
            'cgm' => '1014238138',
            'data_nascimento' => '2016-05-12',
            'sexo' => 'M',
            'data_matricula' => null,
            'id_turma' => $turmaContraTurno->id,
            'tipo_vinculo' => Aluno::TIPO_VINCULO_CONTRA_TURNO,
            'status' => Aluno::STATUS_PENDENTE,
            'aluno_origem_id' => $principal->id,
            'turma_origem_id' => $turmaPrincipal->id,
        ]);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(AlunoImportacaoSpreadsheetService::SHEET_NAME);

        $sheet->fromArray([
            ['Escola', 'Seriação', 'Turma', 'Turno', 'CGM', 'Nome do aluno', 'Data de Nascimento', 'Sexo', 'Data da matrícula', 'Tipo de vínculo'],
            ['ESCOLA - Evangélica', '4º Ano', 'A', 'Integral', '1014238138', 'EMANUEL DE SOUZA SABINO', '12/05/2016', 'M', '08/06/2026', 'Principal'],
            ['ESCOLA - Evangélica', '4º Ano - Integral', 'A', 'Integral', '1014238138', 'EMANUEL DE SOUZA SABINO', '12/05/2016', 'M', '06/08/2026', 'Contra Turno'],
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

        $principal->refresh();
        $contraTurno->refresh();

        $this->assertSame(2, $resultado['total_atualizado']);
        $this->assertSame(Aluno::STATUS_PENDENTE, $principal->status);
        $this->assertSame(Aluno::STATUS_PENDENTE, $contraTurno->status);
        $this->assertSame('2026-06-08', $principal->data_matricula?->toDateString());
        $this->assertSame('2026-08-06', $contraTurno->data_matricula?->toDateString());
    }
}
