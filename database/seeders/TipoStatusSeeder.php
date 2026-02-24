<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TipoStatus;

class TipoStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [

            [
                'nome' => 'Em Aberto',
                'cor' => '#3b82f6',
                'finaliza_pedido' => false,
                'cancela_pedido' => false,
                'ativo' => true,
            ],

            [
                'nome' => 'Em Análise',
                'cor' => '#f59e0b',
                'finaliza_pedido' => false,
                'cancela_pedido' => false,
                'ativo' => true,
            ],

            [
                'nome' => 'Encaminhado ao Setor',
                'cor' => '#8b5cf6',
                'finaliza_pedido' => false,
                'cancela_pedido' => false,
                'ativo' => true,
            ],

            [
                'nome' => 'Aguardando Resposta',
                'cor' => '#0ea5e9',
                'finaliza_pedido' => false,
                'cancela_pedido' => false,
                'ativo' => true,
            ],

            [
                'nome' => 'Enviado para Empresa',
                'cor' => '#6366f1',
                'finaliza_pedido' => false,
                'cancela_pedido' => false,
                'ativo' => true,
            ],

            [
                'nome' => 'Em Manutenção',
                'cor' => '#f97316',
                'finaliza_pedido' => false,
                'cancela_pedido' => false,
                'ativo' => true,
            ],

            [
                'nome' => 'Concluído',
                'cor' => '#10b981',
                'finaliza_pedido' => true,
                'cancela_pedido' => false,
                'ativo' => true,
            ],

            [
                'nome' => 'Cancelado',
                'cor' => '#ef4444',
                'finaliza_pedido' => false,
                'cancela_pedido' => true,
                'ativo' => true,
            ],
        ];

        foreach ($statuses as $status) {
            TipoStatus::updateOrCreate(
                ['nome' => $status['nome']], // chave única
                $status
            );
        }

        // Versão antiga (inativa)
        $emAberto = TipoStatus::where('nome', 'Em Aberto')->first();

        TipoStatus::updateOrCreate(
            ['nome' => 'Aberto'],
            [
                'cor' => '#2563eb',
                'finaliza_pedido' => false,
                'cancela_pedido' => false,
                'ativo' => false,
                'registro_anterior_id' => $emAberto?->id,
            ]
        );
    }
}