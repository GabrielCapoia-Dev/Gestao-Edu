<?php

namespace Database\Seeders;

use App\Models\Setor;
use Illuminate\Database\Seeder;

class SetorSeeder extends Seeder
{
    public function run(): void
    {
        $setorGeral = Setor::updateOrCreate(
            ['nome' => 'Educação'],
            [
                'status' => 'Ativo',
                'ativo' => true,
                'recebe_pedidos_iniciais' => true,
                'encaminha_pedido_para_setor_ids' => [],
                'alterado_por' => 'Seeder',
            ]
        );

        $setoresDependentes = [
            'Obras',
            'Serviços Publicos',
        ];

        foreach ($setoresDependentes as $nome) {
            Setor::updateOrCreate(
                ['nome' => $nome],
                [
                    'status' => 'Ativo',
                    'ativo' => true,
                    'recebe_pedidos_iniciais' => false,
                    'encaminha_pedido_para_setor_ids' => [$setorGeral->id],
                    'alterado_por' => 'Seeder',
                ]
            );
        }
    }
}
