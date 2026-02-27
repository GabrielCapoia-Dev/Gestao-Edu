<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TipoManutencao;

class TipoManutencaoSeeder extends Seeder
{
    public function run(): void
    {
        $base = TipoManutencao::create([
            'nome' => 'Elétrica Interna',
            'descricao' => 'Problemas elétricos gerais',
            'ativo' => true,
            'alterado_por' => 'Seeder',
        ]);

        TipoManutencao::create([
            'nome' => 'Elétrica Externa',
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

        TipoManutencao::create([
            'nome' => 'Pintura',
            'descricao' => 'Vazamentos e encanamentos',
            'ativo' => true,
            'alterado_por' => 'Seeder',
        ]);

        TipoManutencao::create([
            'nome' => 'Telhado',
            'descricao' => 'Vazamentos e encanamentos',
            'ativo' => true,
            'alterado_por' => 'Seeder',
        ]);

        TipoManutencao::create([
            'nome' => 'Ar-condicionado',
            'descricao' => 'Vazamentos e encanamentos',
            'ativo' => true,
            'alterado_por' => 'Seeder',
        ]);

        TipoManutencao::create([
            'nome' => 'Infileração',
            'descricao' => 'Vazamentos e encanamentos',
            'ativo' => true,
            'alterado_por' => 'Seeder',
        ]);

        TipoManutencao::create([
            'nome' => 'Reforma',
            'descricao' => 'Vazamentos e encanamentos',
            'ativo' => true,
            'alterado_por' => 'Seeder',
        ]);

        TipoManutencao::create([
            'nome' => 'Limpeza de Calhas',
            'descricao' => 'Vazamentos e encanamentos',
            'ativo' => true,
            'alterado_por' => 'Seeder',
        ]);
    }
}