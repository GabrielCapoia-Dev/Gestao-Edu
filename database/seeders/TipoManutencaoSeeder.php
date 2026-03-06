<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TipoManutencao;

class TipoManutencaoSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            ['nome' => 'Elétrica Interna', 'descricao' => 'Problemas elétricos gerais'],
            ['nome' => 'Elétrica Externa',  'descricao' => 'Instalações e rede elétrica'],
            ['nome' => 'Hidráulica',        'descricao' => 'Vazamentos e encanamentos'],
            ['nome' => 'Pintura',           'descricao' => 'Pintura em geral'],
            ['nome' => 'Telhado',           'descricao' => 'Reparos em telhado'],
            ['nome' => 'Ar-condicionado',   'descricao' => 'Manutenção de ar-condicionado'],
            ['nome' => 'Infileração',       'descricao' => 'Infiltrações e umidade'],
            ['nome' => 'Reforma',           'descricao' => 'Reformas em geral'],
            ['nome' => 'Limpeza de Calhas', 'descricao' => 'Limpeza e desobstrução de calhas'],
        ];

        foreach ($tipos as $tipo) {
            TipoManutencao::firstOrCreate(
                ['nome' => $tipo['nome']],
                [
                    'descricao'    => $tipo['descricao'],
                    'ativo'        => true,
                    'alterado_por' => 'Seeder',
                ]
            );
        }
    }
}