<?php

namespace Tests\Feature\Exports;

use App\Models\Aluno;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\ExportRequest;
use App\Models\FuncaoAdministrativa;
use App\Models\Lotacao;
use App\Models\PessoaMatricula;
use App\Models\Professor;
use App\Models\Serie;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\Turma;
use App\Models\User;
use App\Services\Exports\Handlers\SelectedRecordsXlsxExportHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SelectedRecordsXlsxExportHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_gera_planilhas_com_apenas_os_registros_selecionados_e_colunas_esperadas(): void
    {
        Storage::fake('local');
        config()->set('exports.disk', 'local');

        Permission::findOrCreate('Listar Turmas');
        Permission::findOrCreate('Listar Pessoas');

        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Admin', 'web'));
        $user->givePermissionTo(['Listar Turmas', 'Listar Pessoas']);

        $escola = $this->criarEscola('Escola Exportação');
        $serie = Serie::query()->create(['codigo' => 'SER-EXP', 'nome' => '1º Ano']);
        $turmaSelecionada = $this->criarTurma($escola, $serie, 'TUR-EXP-1', 'A');
        $turmaNaoSelecionada = $this->criarTurma($escola, $serie, 'TUR-EXP-2', 'B');
        $turmaContraTurno = $this->criarTurma($escola, $serie, 'TUR-EXP-CT', 'Contraturno');
        $turmaContraTurno->update(['turno' => 'tarde']);
        $alunoSelecionado = Aluno::query()->create([
            'nome' => 'Aluno Selecionado',
            'cgm' => 'CGM-EXP-1',
            'data_nascimento' => '2018-01-02',
            'id_turma' => $turmaSelecionada->id,
            'sexo' => 'F',
        ]);
        Aluno::query()->create([
            'nome' => 'Aluno Selecionado',
            'cgm' => 'CGM-EXP-1',
            'data_nascimento' => '2018-01-02',
            'sexo' => 'F',
            'id_turma' => $turmaContraTurno->id,
            'tipo_vinculo' => Aluno::TIPO_VINCULO_CONTRA_TURNO,
            'status' => Aluno::STATUS_MATRICULADO,
            'aluno_origem_id' => $alunoSelecionado->id,
        ]);
        Aluno::query()->create([
            'nome' => 'Aluno Não Selecionado',
            'cgm' => 'CGM-EXP-2',
            'data_nascimento' => '2018-02-03',
            'id_turma' => $turmaNaoSelecionada->id,
        ]);
        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-EXP',
            'nome' => 'Matemática',
        ]);
        $professor = Professor::query()->create([
            'id_escola' => $escola->id,
            'matricula' => 'PROF-EXP',
            'turno' => 'manha',
            'nome' => 'Professor da Turma',
            'email' => 'professor.turma@edu.umuarama.pr.gov.br',
        ]);
        $turmaSelecionada->componentes()->attach($componente->id, [
            'professor_id' => $professor->id,
            'tem_professor' => true,
        ]);
        $lotacao = Lotacao::query()->create([
            'escola_id' => $escola->id,
            'codigo' => 'LOT-EXP',
            'nome' => 'Secretaria',
        ]);
        $servidorSelecionado = Servidor::query()->create([
            'id_escola' => $escola->id,
            'matricula' => 'MAT-001',
            'nome' => 'Servidor Selecionado',
            'user_id' => $user->id,
            'status' => Servidor::STATUS_ATIVO,
            'carga_horaria' => 20,
            'jornada' => false,
            'lotacao_id' => $lotacao->id,
        ]);
        PessoaMatricula::query()->create([
            'servidor_id' => $servidorSelecionado->id,
            'matricula' => 'MAT-001',
            'turno' => 'manha',
        ]);
        $funcao = FuncaoAdministrativa::query()->create([
            'codigo' => 'auxiliar-administrativo-exportacao',
            'nome' => 'Auxiliar Administrativo',
            'categoria' => FuncaoAdministrativa::CATEGORIA_ADMINISTRATIVO,
            'ativo' => true,
            'exige_professor' => false,
        ]);
        ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $servidorSelecionado->id,
            'funcao_administrativa_id' => $funcao->id,
            'matricula' => 'MAT-001',
            'id_escola' => $escola->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
        ]);
        Servidor::query()->create([
            'nome' => 'Servidor Não Selecionado',
            'email' => 'servidor.nao.selecionado@teste.local',
            'status' => Servidor::STATUS_ATIVO,
        ]);

        $casos = [
            ['turmas_selecionadas', $turmaSelecionada->id, 'Turmas', 'A', 'B'],
            ['turmas_selecionadas_detalhado', $turmaSelecionada->id, 'Turmas detalhadas', 'Professor da Turma', 'B'],
            ['alunos_selecionados', $alunoSelecionado->id, 'Alunos', 'Aluno Selecionado', 'Aluno Não Selecionado'],
            ['servidores_selecionados', $servidorSelecionado->id, 'Servidores', 'Servidor Selecionado', 'Servidor Não Selecionado'],
        ];

        foreach ($casos as [$type, $id, $sheetName, $expected, $unexpected]) {
            $request = $this->criarSolicitacao($user, $type, [$id]);
            $result = app(SelectedRecordsXlsxExportHandler::class)->handle($request);

            Storage::disk('local')->assertExists($result->path);
            $path = Storage::disk('local')->path($result->path);
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();

            $this->assertSame($sheetName, $sheet->getTitle());
            $this->assertStringContainsString($expected, json_encode($sheet->toArray(), JSON_UNESCAPED_UNICODE));
            $this->assertStringNotContainsString($unexpected, json_encode($sheet->toArray(), JSON_UNESCAPED_UNICODE));

            if ($type === 'turmas_selecionadas') {
                $this->assertSame(
                    ['Escola', 'Série', 'Turma', 'Turno', 'Número de alunos'],
                    $sheet->rangeToArray('A1:E1')[0],
                );
                $this->assertSame([
                    $escola->nome,
                    $serie->nome,
                    'A',
                    'Manhã',
                    '1',
                ], $sheet->rangeToArray('A2:E2')[0]);
                $this->assertNull($sheet->getCell('F1')->getValue());
            }

            if ($type === 'turmas_selecionadas_detalhado') {
                $this->assertSame(
                    ['Escola', 'Série', 'Turma', 'Turno', 'Número de alunos', 'Componente', 'Professor'],
                    $sheet->rangeToArray('A1:G1')[0],
                );
                $this->assertSame('Matemática', $sheet->getCell('F2')->getValue());
                $this->assertSame('Professor da Turma', $sheet->getCell('G2')->getValue());
                $this->assertNull($sheet->getCell('H1')->getValue());
            }

            if ($type === 'alunos_selecionados') {
                $this->assertSame([
                    'Escola', 'Série', 'Turma', 'Turno', 'CGM', 'Nome do aluno', 'Status', 'Sexo',
                    'Data de nascimento', 'Contraturno', 'Escola do contraturno', 'Série do contraturno',
                    'Turma do contraturno', 'Turno do contraturno',
                ], $sheet->rangeToArray('A1:N1')[0]);
                $this->assertSame('Sim', $sheet->getCell('J2')->getValue());
                $this->assertSame('Contraturno', $sheet->getCell('M2')->getValue());
                $this->assertSame('Tarde', $sheet->getCell('N2')->getValue());
                $this->assertNull($sheet->getCell('O1')->getValue());
            }

            if ($type === 'servidores_selecionados') {
                $this->assertSame(
                    [
                        'Escola', 'Matrícula', 'Nome', 'Turno', 'Carga horária', 'Jornada',
                        'Lotação', 'Escola da lotação', 'E-mail', 'Cargo', 'Status',
                    ],
                    $sheet->rangeToArray('A1:K1')[0],
                );
                $this->assertSame([
                    $escola->nome,
                    'MAT-001',
                    'Servidor Selecionado',
                    'Manhã',
                    '20 horas semanais',
                    'Não',
                    'LOT-EXP - Secretaria',
                    $escola->nome,
                    $user->email,
                    'Auxiliar Administrativo',
                    'Ativo',
                ], $sheet->rangeToArray('A2:K2')[0]);
                $this->assertNull($sheet->getCell('L1')->getValue());
            }

            $spreadsheet->disconnectWorksheets();
            unset($sheet, $spreadsheet);
            gc_collect_cycles();
        }
    }

    public function test_rejeita_solicitacao_quando_um_id_esta_fora_do_escopo_do_usuario(): void
    {
        Storage::fake('local');
        Permission::findOrCreate('Listar Turmas');

        $escolaPermitida = $this->criarEscola('Escola Permitida');
        $escolaBloqueada = $this->criarEscola('Escola Bloqueada');
        $serie = Serie::query()->create(['codigo' => 'SER-ESCOPO', 'nome' => '2º Ano']);
        $turmaPermitida = $this->criarTurma($escolaPermitida, $serie, 'TUR-PERMITIDA', 'A');
        $turmaBloqueada = $this->criarTurma($escolaBloqueada, $serie, 'TUR-BLOQUEADA', 'B');
        $user = User::factory()->create(['id_escola' => $escolaPermitida->id]);
        $user->givePermissionTo('Listar Turmas');
        $request = $this->criarSolicitacao($user, 'turmas_selecionadas', [
            $turmaPermitida->id,
            $turmaBloqueada->id,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('não estão mais disponíveis no seu escopo de acesso');

        app(SelectedRecordsXlsxExportHandler::class)->handle($request);
    }

    private function criarSolicitacao(User $user, string $type, array $ids): ExportRequest
    {
        return ExportRequest::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'format' => 'xlsx',
            'label' => 'Teste de exportação',
            'filters' => ['ids' => $ids],
            'metadata' => [],
            'fingerprint' => fake()->uuid(),
            'status' => ExportRequest::STATUS_RUNNING,
            'status_message' => 'Processando.',
            'progress_current' => 0,
            'progress_total' => 100,
        ]);
    }

    private function criarEscola(string $nome): Escola
    {
        return Escola::query()->create([
            'codigo' => strtoupper(substr(md5($nome), 0, 8)),
            'nome' => $nome,
            'email' => strtolower(str_replace(' ', '.', $nome)).'@teste.local',
            'telefone' => '(44) 99999-9999',
            'ativo' => true,
        ]);
    }

    private function criarTurma(Escola $escola, Serie $serie, string $codigo, string $nome): Turma
    {
        return Turma::query()->create([
            'codigo' => $codigo,
            'nome' => $nome,
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
    }
}
