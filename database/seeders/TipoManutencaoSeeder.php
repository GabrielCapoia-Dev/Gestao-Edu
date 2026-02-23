<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TipoManutencao;

class TipoManutencaoSeeder extends Seeder
{
    public function run(): void
    {
        $base = TipoManutencao::create([
            'nome' => 'Elétrica',
            'descricao' => 'Problemas elétricos gerais',
            'ativo' => true,
            'alterado_por' => 'Seeder',
        ]);

        TipoManutencao::create([
            'nome' => 'Elétrica Predial',
            'descricao' => 'Instalações e rede elétrica',
            'ativo' => true,
            'registro_anterior_id' => $base->id,
            'alterado_por' => 'Seeder',
        ]);

        TipoManutencao::create([
            'nome' => 'Hidráulica',
            'descricao' => 'Vazamentos e encanamentos',
            'ativo' => true,
            'alterado_por' => 'Seeder',
        ]);
    }
}