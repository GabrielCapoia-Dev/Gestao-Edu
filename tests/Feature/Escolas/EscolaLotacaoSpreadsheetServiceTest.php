<?php

namespace Tests\Feature\Escolas;

use App\Models\Escola;
use App\Models\Setor;
use App\Models\User;
use App\Services\Escolas\EscolaLotacaoSpreadsheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EscolaLotacaoSpreadsheetServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_importa_lotacoes_de_varias_escolas_e_atualiza_as_ja_existentes(): void
    {
        Storage::fake('local');
        $setor = $this->criarSetor('Rede municipal');
        $escolaA = $this->criarEscola($setor, 'Escola A');
        $escolaB = $this->criarEscola($setor, 'Escola B');
        $escolaA->lotacoes()->create(['codigo' => '100', 'nome' => 'Nome anterior']);
        $usuario = $this->usuarioAdmin();
        $arquivo = $this->criarPlanilha([
            ['Escola', 'Número da lotação', 'Nome da lotação'],
            ['Escola A', '100', 'Docentes'],
            ['Escola A', '101', 'Administrativo'],
            ['Escola B', '200', 'Apoio escolar'],
        ]);

        $resultado = app(EscolaLotacaoSpreadsheetService::class)->importar($arquivo, $usuario);

        $this->assertSame(['total_importado' => 3, 'criadas' => 2, 'atualizadas' => 1], $resultado);
        $this->assertDatabaseHas('lotacoes', [
            'escola_id' => $escolaA->id,
            'codigo' => '100',
            'nome' => 'Docentes',
        ]);
        $this->assertDatabaseHas('lotacoes', [
            'escola_id' => $escolaA->id,
            'codigo' => '101',
            'nome' => 'Administrativo',
        ]);
        $this->assertDatabaseHas('lotacoes', [
            'escola_id' => $escolaB->id,
            'codigo' => '200',
            'nome' => 'Apoio escolar',
        ]);
        Storage::disk('local')->assertMissing($arquivo);
    }

    public function test_rejeita_numero_de_lotacao_repetido_e_nao_grava_parcialmente(): void
    {
        Storage::fake('local');
        $setor = $this->criarSetor('Rede municipal');
        $this->criarEscola($setor, 'Escola A');
        $this->criarEscola($setor, 'Escola B');
        $arquivo = $this->criarPlanilha([
            ['Escola', 'Número da lotação', 'Nome da lotação'],
            ['Escola A', '300', 'Docentes'],
            ['Escola B', '300', 'Administrativo'],
        ]);

        try {
            app(EscolaLotacaoSpreadsheetService::class)->importar($arquivo, $this->usuarioAdmin());
            $this->fail('A importação deveria rejeitar a lotação repetida.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('aparece mais de uma vez na planilha', $exception->getMessage());
        }

        $this->assertDatabaseCount('lotacoes', 0);
        Storage::disk('local')->assertMissing($arquivo);
    }

    public function test_rejeita_lotacao_que_ja_pertence_a_outra_escola(): void
    {
        Storage::fake('local');
        $setor = $this->criarSetor('Rede municipal');
        $escolaA = $this->criarEscola($setor, 'Escola A');
        $this->criarEscola($setor, 'Escola B');
        $escolaA->lotacoes()->create(['codigo' => '400', 'nome' => 'Docentes']);
        $arquivo = $this->criarPlanilha([
            ['Escola', 'Número da lotação', 'Nome da lotação'],
            ['Escola B', '400', 'Administrativo'],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('já pertence a outra escola');

        app(EscolaLotacaoSpreadsheetService::class)->importar($arquivo, $this->usuarioAdmin());
    }

    public function test_rejeita_escola_fora_do_escopo_do_usuario(): void
    {
        Storage::fake('local');
        $setorPermitido = $this->criarSetor('Setor permitido');
        $setorBloqueado = $this->criarSetor('Setor bloqueado');
        $this->criarEscola($setorPermitido, 'Escola Permitida');
        $this->criarEscola($setorBloqueado, 'Escola Bloqueada');
        $usuario = User::factory()->create(['setor_id' => $setorPermitido->id]);
        $arquivo = $this->criarPlanilha([
            ['Escola', 'Número da lotação', 'Nome da lotação'],
            ['Escola Bloqueada', '500', 'Administrativo'],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('não foi encontrada ou está fora do seu escopo');

        app(EscolaLotacaoSpreadsheetService::class)->importar($arquivo, $usuario);
    }

    private function criarSetor(string $nome): Setor
    {
        return Setor::query()->create(['nome' => $nome, 'ativo' => true]);
    }

    private function criarEscola(Setor $setor, string $nome): Escola
    {
        return Escola::query()->create([
            'setor_id' => $setor->id,
            'nome' => $nome,
            'email' => strtolower(str_replace(' ', '.', $nome)).'@teste.local',
            'ativo' => true,
        ]);
    }

    private function usuarioAdmin(): User
    {
        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->assignRole(Role::findOrCreate('Admin', 'web'));

        return $usuario;
    }

    /** @param list<list<string>> $linhas */
    private function criarPlanilha(array $linhas): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($linhas as $index => $linha) {
            $sheet->fromArray($linha, null, 'A'.($index + 1));
        }

        $temporario = tempnam(sys_get_temp_dir(), 'lotacoes_');
        (new Xlsx($spreadsheet))->save($temporario);
        $spreadsheet->disconnectWorksheets();

        $caminho = 'imports/tests/'.basename($temporario).'.xlsx';
        Storage::disk('local')->put($caminho, file_get_contents($temporario));
        @unlink($temporario);

        return $caminho;
    }
}
