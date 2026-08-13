<?php

namespace Tests\Feature\Escolas;

use App\Filament\Admin\Resources\Escolas\Pages\ManageEscolas;
use App\Jobs\ProcessExportRequestJob;
use App\Models\Escola;
use App\Models\ExportRequest;
use App\Models\Professor;
use App\Models\Setor;
use App\Models\Servidor;
use App\Models\User;
use App\Services\Exports\Handlers\EscolaXlsxExportHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EscolaXlsxExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_acao_da_tela_envia_exportacao_de_escolas_para_a_fila(): void
    {
        Queue::fake();
        Permission::findOrCreate('Listar Escolas');

        $setor = Setor::query()->create(['nome' => 'Setor administrativo', 'ativo' => true]);
        $escola = $this->criarEscola($setor, 'ESC-ADMIN', 'Escola Administrativa');
        $user = User::factory()->create([
            'id_escola' => $escola->id,
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole(Role::findOrCreate('Admin', 'web'));
        $user->givePermissionTo('Listar Escolas');
        $servidor = Servidor::query()->create([
            'user_id' => $user->id,
            'nome' => $user->name,
            'email' => $user->email,
            'status' => Servidor::STATUS_ATIVO,
        ]);
        Professor::query()->create([
            'user_id' => $user->id,
            'servidor_id' => $servidor->id,
            'id_escola' => $escola->id,
            'matricula' => 'PROF-EXP-001',
            'nome' => $user->name,
            'email' => $user->email,
            'ativo' => true,
        ]);

        Livewire::actingAs($user)
            ->test(ManageEscolas::class)
            ->callAction('exportar_escolas_xlsx')
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('export_requests', [
            'user_id' => $user->id,
            'type' => 'escolas_xlsx',
            'format' => 'xlsx',
            'status' => ExportRequest::STATUS_QUEUED,
        ]);
        Queue::assertPushed(ProcessExportRequestJob::class, 1);
    }

    public function test_handler_gera_xlsx_com_dados_das_escolas_do_escopo_do_usuario(): void
    {
        Storage::fake('local');
        config()->set('exports.disk', 'local');
        Permission::findOrCreate('Listar Escolas');

        $setorPermitido = Setor::query()->create(['nome' => 'Setor permitido', 'ativo' => true]);
        $setorBloqueado = Setor::query()->create(['nome' => 'Setor bloqueado', 'ativo' => true]);
        $escolaPermitida = $this->criarEscola($setorPermitido, 'ESC-001', 'Escola Permitida');
        $escolaPermitida->lotacoes()->create(['codigo' => 'LOT-001', 'nome' => 'Docentes']);
        $this->criarEscola($setorBloqueado, 'ESC-002', 'Escola Bloqueada');
        $this->criarEscola($setorPermitido, 'ESC-003', 'Escola Inativa', false);

        $user = User::factory()->create(['setor_id' => $setorPermitido->id]);
        $user->givePermissionTo('Listar Escolas');
        $request = ExportRequest::query()->create([
            'user_id' => $user->id,
            'type' => 'escolas_xlsx',
            'format' => 'xlsx',
            'label' => 'XLSX de escolas',
            'filters' => [],
            'metadata' => [],
            'fingerprint' => fake()->uuid(),
            'status' => ExportRequest::STATUS_RUNNING,
            'status_message' => 'Processando.',
            'progress_current' => 0,
            'progress_total' => 100,
        ]);

        $result = app(EscolaXlsxExportHandler::class)->handle($request);

        Storage::disk('local')->assertExists($result->path);
        $spreadsheet = IOFactory::load(Storage::disk('local')->path($result->path));
        $sheet = $spreadsheet->getActiveSheet();

        $this->assertSame('Escolas', $sheet->getTitle());
        $this->assertSame([
            'Código', 'Nome', 'E-mail', 'Telefone', 'Setor', 'Logradouro', 'Número',
            'Bairro', 'CEP', 'Cidade', 'UF', 'Complemento', 'Lotações', 'Status',
            'Criado em', 'Atualizado em',
        ], $sheet->rangeToArray('A1:P1')[0]);
        $this->assertSame('ESC-001', $sheet->getCell('A2')->getValue());
        $this->assertSame('Escola Permitida', $sheet->getCell('B2')->getValue());
        $this->assertSame('LOT-001 - Docentes', $sheet->getCell('M2')->getValue());
        $this->assertSame('Ativa', $sheet->getCell('N2')->getValue());
        $this->assertNull($sheet->getCell('A3')->getValue());

        $spreadsheet->disconnectWorksheets();
    }

    private function criarEscola(Setor $setor, string $codigo, string $nome, bool $ativa = true): Escola
    {
        return Escola::query()->create([
            'codigo' => $codigo,
            'setor_id' => $setor->id,
            'nome' => $nome,
            'email' => strtolower(str_replace(' ', '.', $nome)) . '@teste.local',
            'telefone' => '(44)99999-9999',
            'logradouro' => 'Rua da Escola',
            'numero' => '100',
            'bairro' => 'Centro',
            'cep' => '87500-000',
            'cidade' => 'Umuarama',
            'estado' => 'PR',
            'complemento' => 'Próxima à praça',
            'ativo' => $ativa,
        ]);
    }
}
