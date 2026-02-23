<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TipoStatus;

class TipoStatusSeeder extends Seeder
{
    public function run(): void
    {
        $emAberto = TipoStatus::create([
            'nome' => 'Em Aberto',
            'cor' => '#3b82f6',
            'finaliza_pedido' => false,
            'cancela_pedido' => false,
            'ativo' => true,
        ]);

        TipoStatus::create([
            'nome' => 'Em Análise',
            'cor' => '#f59e0b',
            'finaliza_pedido' => false,
            'cancela_pedido' => false,
            'ativo' => true,
        ]);

        TipoStatus::create([
            'nome' => 'Aguardando Peças',
            'cor' => '#8b5cf6',
            'finaliza_pedido' => false,
            'cancela_pedido' => false,
            'ativo' => true,
        ]);

        TipoStatus::create([
            'nome' => 'Concluído',
            'cor' => '#10b981',
            'finaliza_pedido' => true,
            'cancela_pedido' => false,
            'ativo' => true,
        ]);

        TipoStatus::create([
            'nome' => 'Cancelado',
            'cor' => '#ef4444',
            'finaliza_pedido' => false,
            'cancela_pedido' => true,
            'ativo' => true,
        ]);

        // Histórico de exemplo (versão antiga de Em Aberto)
        TipoStatus::create([
            'nome' => 'Aberto',
            'cor' => '#2563eb',
            'finaliza_pedido' => false,
            'cancela_pedido' => false,
            'ativo' => false,
            'registro_anterior_id' => $emAberto->id,
        ]);
    }
}