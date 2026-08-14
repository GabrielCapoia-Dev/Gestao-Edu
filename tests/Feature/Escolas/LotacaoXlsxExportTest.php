<?php

namespace Tests\Feature\Escolas;

use App\Filament\Admin\Resources\Lotacoes\Pages\ManageLotacoes;
use App\Jobs\ProcessExportRequestJob;
use App\Models\Escola;
use App\Models\ExportRequest;
use App\Models\LocalTrabalho;
use App\Models\Professor;
use App\Models\Servidor;
use App\Models\Setor;
use App\Models\User;
use App\Services\Exports\Handlers\LotacaoXlsxExportHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LotacaoXlsxExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_acao_da_tela_envia_exportacao_de_lotacoes_para_a_fila(): void
    {
        Queue::fake();
        Permission::findOrCreate('Listar Escolas');

        $setor = Setor::query()->create(['nome' => 'Setor administrativo', 'ativo' => true]);
        $escola = $this->criarEscola($setor, 'Escola de acesso');
        $user = User::factory()->create([
            'setor_id' => $setor->id,
            'id_escola' => $escola->id,
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $user->givePermissionTo('Listar Escolas');
        $this->tornarUsuarioOperacional($user, $escola);

        Livewire::actingAs($user)
            ->test(ManageLotacoes::class)
            ->callAction('exportar_lotacoes_xlsx')
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('export_requests', [
            'user_id' => $user->id,
            'type' => 'lotacoes_xlsx',
            'format' => 'xlsx',
            'status' => ExportRequest::STATUS_QUEUED,
        ]);
        Queue::assertPushed(ProcessExportRequestJob::class, 1);
    }

    public function test_handler_exporta_todas_as_lotacoes_visiveis_com_local_tipo_e_setor(): void
    {
        Storage::fake('local');
        config()->set('exports.disk', 'local');
        Permission::findOrCreate('Listar Escolas');

        $setorPermitido = Setor::query()->create(['nome' => 'Setor permitido', 'ativo' => true]);
        $setorBloqueado = Setor::query()->create(['nome' => 'Setor bloqueado', 'ativo' => true]);
        $escola = $this->criarEscola($setorPermitido, 'Escola Alfa');
        $local = LocalTrabalho::query()->create([
            'setor_id' => $setorPermitido->id,
            'nome' => 'Secretaria Municipal',
            'email' => 'secretaria@teste.local',
            'ativo' => true,
            'nao_e_escola' => true,
        ]);
        $escolaBloqueada = $this->criarEscola($setorBloqueado, 'Escola Bloqueada');
        $escola->lotacoes()->create(['codigo' => 'LOT-001', 'nome' => 'Docentes']);
        $local->lotacoes()->create(['codigo' => 'LOT-002', 'nome' => 'Administrativo']);
        $escolaBloqueada->lotacoes()->create(['codigo' => 'LOT-003', 'nome' => 'Fora do escopo']);

        $user = User::factory()->create(['setor_id' => $setorPermitido->id]);
        $user->givePermissionTo('Listar Escolas');
        $request = ExportRequest::query()->create([
            'user_id' => $user->id,
            'type' => 'lotacoes_xlsx',
            'format' => 'xlsx',
            'label' => 'XLSX de lotações',
            'filters' => [],
            'metadata' => [],
            'fingerprint' => fake()->uuid(),
            'status' => ExportRequest::STATUS_RUNNING,
            'status_message' => 'Processando.',
            'progress_current' => 0,
            'progress_total' => 100,
        ]);

        $result = app(LotacaoXlsxExportHandler::class)->handle($request);

        Storage::disk('local')->assertExists($result->path);
        $spreadsheet = IOFactory::load(Storage::disk('local')->path($result->path));
        $sheet = $spreadsheet->getActiveSheet();

        $this->assertSame('Lotações', $sheet->getTitle());
        $this->assertSame([
            'Número da lotação',
            'Nome da lotação',
            'Local de trabalho',
            'Tipo do local',
            'Setor',
            'Criada em',
            'Atualizada em',
        ], $sheet->rangeToArray('A1:G1')[0]);
        $this->assertSame('LOT-001', $sheet->getCell('A2')->getValue());
        $this->assertSame('Escola Alfa', $sheet->getCell('C2')->getValue());
        $this->assertSame('Escola', $sheet->getCell('D2')->getValue());
        $this->assertSame('LOT-002', $sheet->getCell('A3')->getValue());
        $this->assertSame('Secretaria Municipal', $sheet->getCell('C3')->getValue());
        $this->assertSame('Local não escolar', $sheet->getCell('D3')->getValue());
        $this->assertNull($sheet->getCell('A4')->getValue());

        $spreadsheet->disconnectWorksheets();
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

    private function tornarUsuarioOperacional(User $user, Escola $escola): void
    {
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
            'matricula' => 'PROF-LOT-'.$user->id,
            'nome' => $user->name,
            'email' => $user->email,
            'ativo' => true,
        ]);
    }
}
