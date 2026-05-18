<?php

namespace Database\Seeders;

use App\Models\Setor;
use Illuminate\Database\Seeder;

class SetorSeeder extends Seeder
{
    public function run(): void
    {
        $rootName = (string) config('app.default_setor_root_name', env('SETOR_DEFAULT_ROOT_NAME', 'Geral'));

        Setor::query()
            ->where('is_default_root', true)
            ->where('nome', '!=', $rootName)
            ->update(['is_default_root' => false]);

        Setor::updateOrCreate(
            ['nome' => $rootName],
            [
                'parent_id' => null,
                'status' => 'Ativo',
                'ativo' => true,
                'is_default_root' => true,
                'recebe_pedidos_iniciais' => true,
                'encaminha_pedido_para_setor_ids' => [],
                'alterado_por' => 'Seeder',
            ]
        );
    }
}
