<?php

namespace Tests\Feature\Codigos;

use App\Models\BalancoEstoque;
use App\Models\BalancoInventario;
use App\Models\ComponenteCurricular;
use App\Models\Enums\BalancoEstoqueStatus;
use App\Models\Enums\BalancoInventarioStatus;
use App\Models\Enums\TipoItem;
use App\Models\Enums\UnidadeMedida;
use App\Models\Escola;
use App\Models\Inventario;
use App\Models\InventarioRomaneio;
use App\Models\Item;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodigoUuidAutomaticoTest extends TestCase
{
    use RefreshDatabase;

    private const UUID_REGEX = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function test_models_com_codigo_recebem_uuid_quando_codigo_nao_e_informado(): void
    {
        $escola = Escola::query()->create([
            'nome' => 'Escola UUID',
            'ativo' => true,
        ]);

        $serie = Serie::query()->create([
            'nome' => 'Serie UUID',
        ]);

        $componente = ComponenteCurricular::query()->create([
            'nome' => 'Componente UUID',
        ]);

        $turma = Turma::query()->create([
            'nome' => 'A',
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);

        $item = Item::query()->create([
            'nome' => 'Arroz UUID',
            'tipo_item' => TipoItem::CerealDerivado,
            'unidade_medida' => UnidadeMedida::Quilograma,
            'ativo' => true,
        ]);

        $usuario = User::factory()->create();

        $romaneio = InventarioRomaneio::query()->create();

        $balancoEstoque = BalancoEstoque::query()->create([
            'status' => BalancoEstoqueStatus::Agendado,
            'data_agendada' => now(),
        ]);

        $inventario = Inventario::query()->create([
            'escola_id' => $escola->id,
            'ativo' => true,
        ]);

        $balancoInventario = BalancoInventario::query()->create([
            'inventario_id' => $inventario->id,
            'status' => BalancoInventarioStatus::Agendado,
            'data_agendada' => now(),
        ]);

        foreach ([
            $escola,
            $serie,
            $componente,
            $turma,
            $item,
            $usuario,
            $romaneio,
            $balancoEstoque,
            $balancoInventario,
        ] as $registro) {
            $this->assertMatchesRegularExpression(self::UUID_REGEX, $registro->codigo);
        }
    }

    public function test_codigo_informado_explicitamente_e_preservado(): void
    {
        $item = Item::query()->create([
            'codigo' => 'CODIGO-LEGADO',
            'nome' => 'Item legado',
            'tipo_item' => TipoItem::Industrializado,
            'unidade_medida' => UnidadeMedida::Pacote,
            'ativo' => true,
        ]);

        $this->assertSame('CODIGO-LEGADO', $item->codigo);
    }
}
