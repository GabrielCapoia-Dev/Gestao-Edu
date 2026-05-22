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

class AlunoImportacaoSpreadsheetServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_importa_aba_matriculados_criando_serie_turma_e_aluno(): void
    {
        Storage::fake('local');

        Escola::query()->create([
            'nome' => 'CMEI - Cecilia Meireles',
            'ativo' => true,
        ]);

        $caminho = $this->criarPlanilhaNoStorage('local', [
            ['Escola', 'Seriacao', 'Turma', 'Turno', 'Curso', 'CGM', 'Nome do Aluno', 'Data de Nasc'],
            ['CMEI - Cecilia Meireles', 'INFANTIL 4', 'A', 'Manha', 'EDUC INFANTIL', '1035708266', 'ALANA GRAZIELY DA SILVA SARAIVA', 44600],
        ]);

        $resultado = app(AlunoImportacaoSpreadsheetService::class)->importar($caminho, null, 'local');

        $this->assertSame(1, $resultado['total_importado']);
        $this->assertSame(1, $resultado['series_criadas']);
        $this->assertSame(1, $resultado['turmas_criadas']);

        $this->assertDatabaseHas('series', [
            'nome' => 'INFANTIL 4',
        ]);

        $serie = Serie::query()->where('nome', 'INFANTIL 4')->firstOrFail();
        $turma = Turma::query()->where('id_serie', $serie->id)->firstOrFail();

        $this->assertSame('A', $turma->nome);
        $this->assertSame('manha', $turma->turno);

        $this->assertDatabaseHas('alunos', [
            'cgm' => '1035708266',
            'nome' => 'ALANA GRAZIELY DA SILVA SARAIVA',
            'id_turma' => $turma->id,
            'status' => Aluno::STATUS_MATRICULADO,
        ]);

        $this->assertSame(
            '2022-02-08',
            Aluno::query()->where('cgm', '1035708266')->firstOrFail()->data_nascimento?->toDateString()
        );
    }

    public function test_importacao_ignora_repeticoes_de_cgm_no_arquivo(): void
    {
        Storage::fake('local');

        Escola::query()->create([
            'nome' => 'Escola Municipal Teste',
            'ativo' => true,
        ]);

        $caminho = $this->criarPlanilhaNoStorage('local', [
            ['Escola', 'Seriacao', 'Turma', 'Turno', 'CGM', 'Nome do Aluno', 'Data de Nascimento'],
            ['Escola Municipal Teste', '1 Ano', 'A', 'Tarde', '123', 'Aluno Um', '01/02/2018'],
            ['Escola Municipal Teste', '1 Ano', 'B', 'Tarde', '123', 'Aluno Dois', '02/02/2018'],
        ]);

        $resultado = app(AlunoImportacaoSpreadsheetService::class)->importar($caminho, null, 'local');

        $this->assertSame(1, $resultado['total_importado']);
        $this->assertSame(1, $resultado['duplicados_ignorados']);
        $this->assertDatabaseCount('alunos', 1);
        $this->assertDatabaseHas('alunos', [
            'cgm' => '123',
            'nome' => 'Aluno Um',
        ]);
    }

    private function criarPlanilhaNoStorage(string $disk, array $linhas): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Matriculados');

        foreach ($linhas as $indice => $linha) {
            $sheet->fromArray($linha, null, 'A'.($indice + 1));
        }

        $arquivoTemporario = tempnam(sys_get_temp_dir(), 'alunos_importacao_test_');

        (new Xlsx($spreadsheet))->save($arquivoTemporario);

        $caminho = 'imports/tests/'.basename($arquivoTemporario).'.xlsx';
        Storage::disk($disk)->put($caminho, file_get_contents($arquivoTemporario));

        @unlink($arquivoTemporario);

        return $caminho;
    }
}
