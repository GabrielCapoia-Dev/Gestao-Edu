<?php

namespace Tests\Feature\Pessoas;

use App\Http\Controllers\ServidorDocumentoController;
use App\Models\Servidor;
use App\Services\SaldoEleitoralService;
use App\Services\ServidorHistoricoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ServidorFichaPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_da_ficha_apresenta_historico_funcional_sem_json_bruto(): void
    {
        $template = file_get_contents(resource_path('views/relatorios/servidores/ficha.blade.php'));

        $this->assertIsString($template);
        $this->assertStringNotContainsString('json_encode($alteracao', $template);
        $this->assertStringContainsString('$formatarHistoricoFicha', $template);
        $this->assertStringContainsString("['id', 'codigo', 'codigo_escola']", $template);
    }

    public function test_ficha_funcional_exporta_pdf_completo_e_autorizado(): void
    {
        $servidor = Servidor::query()->create([
            'nome' => 'Pessoa para ficha PDF',
            'cpf' => '12345678909',
            'email' => 'ficha-pdf@example.test',
            'telefone' => '(44) 3333-2222',
            'status' => Servidor::STATUS_INATIVO,
            'observacoes' => 'Observação de teste.',
        ]);
        Gate::shouldReceive('authorize')->once()->with('view', $servidor);

        $response = app(ServidorDocumentoController::class)->ficha(
            $servidor,
            app(ServidorHistoricoService::class),
            app(SaldoEleitoralService::class),
        );

        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }
}
