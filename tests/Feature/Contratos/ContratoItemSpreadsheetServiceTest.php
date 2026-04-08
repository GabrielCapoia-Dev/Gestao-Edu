<?php

namespace Tests\Feature\Contratos;

use App\Models\Contrato;
use App\Models\EmpresaContratada;
use App\Models\Enums\TipoItem;
use App\Models\Enums\UnidadeMedida;
use App\Models\Item;
use App\Services\Contratos\ContratoItemSpreadsheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ContratoItemSpreadsheetServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_recebe_codigo_automaticamente_quando_nao_informado(): void
    {
        $item = Item::query()->create([
            'nome' => 'Arroz',
            'tipo_item' => TipoItem::CerealDerivado,
            'unidade_medida' => UnidadeMedida::Quilograma,
            'ativo' => true,
        ]);

        $this->assertSame('ITM-000001', $item->codigo);
    }

    public function test_importa_planilha_e_cria_itens_no_contrato(): void
    {
        Storage::fake('local');

        $contrato = $this->criarContrato();

        $item = Item::query()->create([
            'nome' => 'Feijao',
            'codigo' => 'FEI-001',
            'tipo_item' => TipoItem::Leguminosa,
            'unidade_medida' => UnidadeMedida::Quilograma,
            'ativo' => true,
        ]);

        $caminho = $this->criarPlanilhaNoStorage('local', [
            ['Codigo do Item', 'Quantidade', 'Preco unitario'],
            ['FEI-001', '12,500', '7,35'],
        ]);

        $resultado = app(ContratoItemSpreadsheetService::class)->importar($contrato, $caminho, 'local');

        $this->assertSame(1, $resultado['total_importado']);
        $this->assertDatabaseHas('contrato_item', [
            'contrato_id' => $contrato->id,
            'item_id' => $item->id,
            'quantidade_total' => '12.500',
            'preco_unitario' => '7.35',
        ]);
    }

    public function test_importacao_falha_quando_codigo_nao_existe(): void
    {
        Storage::fake('local');

        $contrato = $this->criarContrato();
        $caminho = $this->criarPlanilhaNoStorage('local', [
            ['Codigo do Item', 'Quantidade', 'Preco unitario'],
            ['NAO-EXISTE', '2', '4,50'],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('NAO-EXISTE');

        app(ContratoItemSpreadsheetService::class)->importar($contrato, $caminho, 'local');
    }

    public function test_importacao_falha_quando_arquivo_tem_codigos_duplicados(): void
    {
        Storage::fake('local');

        $contrato = $this->criarContrato();

        Item::query()->create([
            'nome' => 'Macarrao',
            'codigo' => 'MAC-001',
            'tipo_item' => TipoItem::Industrializado,
            'unidade_medida' => UnidadeMedida::Pacote,
            'ativo' => true,
        ]);

        $caminho = $this->criarPlanilhaNoStorage('local', [
            ['Codigo do Item', 'Quantidade', 'Preco unitario'],
            ['MAC-001', '1', '3,20'],
            ['MAC-001', '2', '3,20'],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('aparece mais de uma vez');

        app(ContratoItemSpreadsheetService::class)->importar($contrato, $caminho, 'local');
    }

    private function criarContrato(): Contrato
    {
        $empresa = EmpresaContratada::query()->create([
            'nome' => 'Fornecedor Teste ' . fake()->unique()->randomNumber(4, true),
            'cnpj' => (string) fake()->unique()->numerify('##############'),
            'ativo' => true,
        ]);

        return Contrato::query()->create([
            'id_empresa_contratada' => $empresa->id,
            'numero_contrato' => 'CTR-' . now()->timestamp . '-' . fake()->randomNumber(3, true),
            'data_inicio' => now()->toDateString(),
            'data_vencimento' => now()->addYear()->toDateString(),
            'ativo' => true,
        ]);
    }

    private function criarPlanilhaNoStorage(string $disk, array $linhas): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($linhas as $indice => $linha) {
            $sheet->fromArray($linha, null, 'A' . ($indice + 1));
        }

        $arquivoTemporario = tempnam(sys_get_temp_dir(), 'contrato_test_');

        (new Xlsx($spreadsheet))->save($arquivoTemporario);

        $caminho = 'imports/tests/' . basename($arquivoTemporario) . '.xlsx';
        Storage::disk($disk)->put($caminho, file_get_contents($arquivoTemporario));

        @unlink($arquivoTemporario);

        return $caminho;
    }
}
