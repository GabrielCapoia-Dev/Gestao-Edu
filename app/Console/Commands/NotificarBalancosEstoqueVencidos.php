<?php

namespace App\Console\Commands;

use App\Models\BalancoEstoque;
use App\Models\Enums\BalancoEstoqueStatus;
use App\Models\User;
use App\Notifications\SistemaNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class NotificarBalancosEstoqueVencidos extends Command
{
    protected $signature = 'app:notificar-balancos-estoque-vencidos';

    protected $description = 'Notifica balanços de estoque agendados e ainda não iniciados.';

    public function handle(): int
    {
        $usuarios = User::permission('Visualizar Notificação: Balanço de Estoque')->get();

        if ($usuarios->isEmpty()) {
            return self::SUCCESS;
        }

        $balancos = BalancoEstoque::query()
            ->where('status', BalancoEstoqueStatus::Agendado)
            ->where('data_agendada', '<=', now())
            ->get();

        foreach ($balancos as $balanco) {
            $cacheKey = 'balanco_estoque_vencido_' . $balanco->getKey() . '_' . now()->format('Y-m-d');

            if (Cache::has($cacheKey)) {
                continue;
            }

            foreach ($usuarios as $usuario) {
                $usuario->notify(new SistemaNotification(
                    titulo: 'Balanço de Estoque Pendente',
                    mensagem: "O Balanço {$balanco->codigo} está agendado desde {$balanco->data_agendada?->format('d/m/Y H:i')}.",
                    url: route('filament.admin.resources.balancos-estoque.view', $balanco),
                ));
            }

            Cache::put($cacheKey, true, now()->endOfDay());
        }

        return self::SUCCESS;
    }
}
