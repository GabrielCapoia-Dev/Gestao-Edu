<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setor;

class SetorSeeder extends Seeder
{
    public function run(): void
    {
        $setorBase = Setor::create([
            'nome' => 'Manutenção Predial',
            'status' => 'Ativo',
            'alterado_por' => 'Seeder',
            'ativo' => true,
        ]);

        // Versão atualizada (histórico)
        Setor::create([
            'nome' => 'Manutenção e Infraestrutura',
            'status' => 'Ativo',
            'alterado_por' => 'Seeder',
            'ativo' => true,
            'registro_anterior_id' => $setorBase->id,
        ]);

        Setor::create([
            'nome' => 'Tecnologia',
            'status' => 'Ativo',
            'alterado_por' => 'Seeder',
            'ativo' => true,
        ]);
    }
}