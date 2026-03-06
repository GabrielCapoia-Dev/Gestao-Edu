<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setor;

class SetorSeeder extends Seeder
{
    public function run(): void
    {
        $setores = [
            ['nome' => 'Educação',         'status' => 'Ativo', 'ativo' => true],
            ['nome' => 'Obras',            'status' => 'Ativo', 'ativo' => true],
            ['nome' => 'Serviços Publicos','status' => 'Ativo', 'ativo' => true],
        ];

        foreach ($setores as $setor) {
            Setor::firstOrCreate(
                ['nome' => $setor['nome']],
                [
                    'status'        => $setor['status'],
                    'ativo'         => $setor['ativo'],
                    'alterado_por'  => 'Seeder',
                ]
            );
        }
    }
}