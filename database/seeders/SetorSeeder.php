<?php

namespace Database\Seeders;

use App\Models\Setor;
use Illuminate\Database\Seeder;

class SetorSeeder extends Seeder
{
    public function run(): void
    {
        $setores = [
            ['nome' => 'Educação', 'status' => 'Ativo', 'ativo' => true, 'recebe_pedidos_iniciais' => true],
            ['nome' => 'Obras', 'status' => 'Ativo', 'ativo' => true, 'recebe_pedidos_iniciais' => false],
            ['nome' => 'Serviços Publicos', 'status' => 'Ativo', 'ativo' => true, 'recebe_pedidos_iniciais' => false],
        ];

        $setorGeral = null;

        foreach ($setores as $setor) {
            $registro = Setor::firstOrCreate(
                ['nome' => $setor['nome']],
                [
                    'status' => $setor['status'],
                    'ativo' => $setor['ativo'],
                    'recebe_pedidos_iniciais' => $setor['recebe_pedidos_iniciais'],
                    'alterado_por' => 'Seeder',
                ]
            );

            if ($setor['recebe_pedidos_iniciais']) {
                $setorGeral = $registro;
            }
        }

        if ($setorGeral) {
            Setor::query()
                ->where('id', '!=', $setorGeral->id)
                ->whereNull('encaminha_pedido_para_setor_ids')
                ->update(['encaminha_pedido_para_setor_ids' => [$setorGeral->id]]);
        }
    }
}
