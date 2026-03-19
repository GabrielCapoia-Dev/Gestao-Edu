<?php

namespace Database\Seeders;

use App\Models\Contrato;
use App\Models\ContratoItem;
use App\Models\EmpresaContratada;
use App\Models\Item;
use App\Models\Enums\TipoItemContrato;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ContratoSeeder extends Seeder
{
    public function run(): void
    {
        $empresas = EmpresaContratada::where('ativo', true)->get();
        $itens    = Item::where('ativo', true)->pluck('id')->toArray();

        if ($empresas->isEmpty()) {
            $this->command->error('Nenhuma empresa encontrada. Rode o EmpresaContratadaSeeder primeiro.');
            return;
        }

        if (empty($itens)) {
            $this->command->error('Nenhum item encontrado. Rode o ItensSeeder primeiro.');
            return;
        }

        $contador = 0;

        foreach ($empresas as $empresa) {
            for ($c = 1; $c <= 2; $c++) {

                $numeroContrato = sprintf(
                    'CT-%d/%d-%02d',
                    $empresa->id,
                    now()->year,
                    $c
                );

                $contrato = Contrato::firstOrCreate(
                    ['numero_contrato' => $numeroContrato],
                    [
                        'id_empresa_contratada' => $empresa->id,
                        'data_inicio'           => Carbon::now()->subMonths(rand(1, 6)),
                        'data_vencimento'       => Carbon::now()->addMonths(rand(6, 18)),
                        'observacoes'           => "Contrato de fornecimento de merenda - {$empresa->nome}",
                        'ativo'                 => true,
                        'alterado_por'          => 'Sistema',
                    ]
                );

                // Sorteia entre 5 e 20 itens únicos para este contrato
                $qtdItens      = rand(5, 20);
                $itensSorteados = collect($itens)->shuffle()->take($qtdItens);

                foreach ($itensSorteados as $itemId) {
                    $quantidadeTotal = round(rand(50, 500) + rand(0, 999) / 1000, 3);
                    $precoUnitario   = round(rand(2, 50) + rand(0, 99) / 100, 2);

                    ContratoItem::firstOrCreate(
                        [
                            'contrato_id' => $contrato->id,
                            'item_id'     => $itemId,
                            'tipo'        => TipoItemContrato::Compra,
                        ],
                        [
                            'quantidade_total'     => $quantidadeTotal,
                            'quantidade_utilizada' => 0,
                            'quantidade_reservada' => 0,
                            'preco_unitario'       => $precoUnitario,
                        ]
                    );
                }

                $contador++;
            }
        }

        $this->command->info("✔ {$contador} contratos criados ({$empresas->count()} empresas × 2).");
    }
}