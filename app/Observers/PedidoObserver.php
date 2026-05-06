<?php

namespace App\Observers;

use App\Models\Enums\NivelEmergenciaPedido;
use App\Models\Pedido;
use App\Models\TipoStatus;
use App\Models\User;
use App\Notifications\SistemaNotification;
use Illuminate\Support\Collection;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class PedidoObserver
{
    public function created(Pedido $pedido): void
    {
        //
    }

    public function updated(Pedido $pedido): void
    {
        if (
            $pedido->wasChanged('nivel_prioridade') &&
            $pedido->nivel_prioridade === NivelEmergenciaPedido::EMERGENCIAL
        ) {
            $usuarios = $this->usuariosComPermissao('Visualizar Notificação: Pedidos Emergenciais');

            foreach ($usuarios as $user) {
                $user->notify(
                    new SistemaNotification(
                        titulo: 'Pedido Emergencial',
                        mensagem: "O pedido {$pedido->numero_protocolo} foi marcado como Emergencial.",
                        url: route('filament.admin.resources.pedidos.edit', $pedido),
                    )
                );
            }
        }

        if (! $pedido->wasChanged('tipo_status_id')) {
            return;
        }

        $statusAtual = $pedido->tipoStatus;
        $statusReaberto = $this->statusPorNome('Reaberto');
        $solicitante = $pedido->solicitante;

        if ($statusReaberto && (int) $pedido->tipo_status_id === (int) $statusReaberto->id) {
            if ($solicitante) {
                $solicitante->notify(
                    new SistemaNotification(
                        titulo: 'Pedido Reaberto',
                        mensagem: "Seu pedido {$pedido->numero_protocolo} foi reaberto e encaminhado ao setor responsável.",
                        url: route('filament.admin.resources.pedidos.edit', $pedido),
                    )
                );
            }

            foreach ($this->usuariosComPermissao('Visualizar Notificação: Pedido Reaberto') as $user) {
                if ($solicitante && (int) $user->id === (int) $solicitante->id) {
                    continue;
                }

                $user->notify(
                    new SistemaNotification(
                        titulo: 'Atualização no Pedido',
                        mensagem: "O status do pedido {$pedido->numero_protocolo} foi atualizado para \"{$statusAtual?->nome}\".",
                        url: route('filament.admin.resources.pedidos.edit', $pedido),
                    )
                );
            }

            return;
        }

        if ($solicitante && $statusAtual) {
            $solicitante->notify(
                new SistemaNotification(
                    titulo: 'Atualização no Pedido',
                    mensagem: "O status do seu pedido {$pedido->numero_protocolo} foi atualizado para \"{$statusAtual->nome}\".",
                    url: route('filament.admin.resources.pedidos.index', $pedido),
                )
            );
        }
    }

    public function deleted(Pedido $pedido): void
    {
        //
    }

    public function restored(Pedido $pedido): void
    {
        //
    }

    public function forceDeleted(Pedido $pedido): void
    {
        //
    }

    private function usuariosComPermissao(string $permission): Collection
    {
        foreach ($this->aliasesTexto($permission) as $alias) {
            try {
                return User::permission($alias)->get();
            } catch (PermissionDoesNotExist) {
                continue;
            }
        }

        return collect();
    }

    private function statusPorNome(string $nome): ?TipoStatus
    {
        return TipoStatus::query()
            ->whereIn('nome', $this->aliasesTexto($nome))
            ->first();
    }

    private function aliasesTexto(string $texto): array
    {
        $aliases = [$texto];

        if (function_exists('mb_convert_encoding')) {
            $aliases[] = mb_convert_encoding($texto, 'UTF-8', 'ISO-8859-1');

            if (str_contains($texto, 'Ã') || str_contains($texto, 'Â')) {
                $aliases[] = mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
            }
        }

        return array_values(array_unique(array_filter($aliases)));
    }
}
