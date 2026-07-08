<?php

namespace App\Policies;

use App\Models\Pedido;
use App\Models\User;
use App\Services\PedidoService;

class PedidoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Listar Pedidos');
    }

    public function view(User $user, Pedido $model): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return app(PedidoService::class)->registroVisivelNoPerfil($model, $user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Criar Pedidos');
    }

    public function update(User $user, Pedido $model): bool
    {
        return $this->manage($user, $model);
    }

    public function updateAny(User $user): bool
    {
        return $user->hasPermissionTo('Editar Pedidos');
    }

    public function delete(User $user, Pedido $model): bool
    {
        return $user->hasPermissionTo('Excluir Pedidos')
            && $this->manage($user, $model);
    }

    public function viewHistory(User $user): bool
    {
        return $user->hasPermissionTo('Visualizar Histórico de Pedidos');
    }

    public function viewFeedback(User $user): bool
    {
        return $user->hasPermissionTo('Visualizar Feedback de Pedidos');
    }

    public function viewFiles(User $user): bool
    {
        return $user->hasPermissionTo('Visualizar Arquivos de Pedidos');
    }

    public function viewByStatus(User $user): bool
    {
        return $user->hasPermissionTo('Visualizar Pedidos por Status');
    }

    public function viewAll(User $user): bool
    {
        return $user->hasRole('Admin') || $user->hasPermissionTo('Listar Todos os Pedidos');
    }

    public function manage(User $user, Pedido $pedido): bool
    {
        if (! $user->hasPermissionTo('Editar Pedidos')) {
            return false;
        }

        return app(PedidoService::class)->setorPodeEditarRegistro($pedido, $user);
    }

    public function cancel(User $user, Pedido $pedido): bool
    {
        if (! $user->hasPermissionTo('Editar Pedidos')) {
            return false;
        }

        return app(PedidoService::class)->setorPodeCancelarRegistro($pedido, $user);
    }

    public function forward(User $user, Pedido $pedido, ?int $setorDestinoId = null): bool
    {
        if (! $user->hasPermissionTo('Encaminhar Pedidos para Setor')) {
            return false;
        }

        return app(PedidoService::class)->podeEncaminharNoEscopo($pedido, $user, $setorDestinoId);
    }

    public function sendToCompany(User $user): bool
    {
        return $user->hasPermissionTo('Enviar Pedidos para Empresa');
    }

    public function linkAdditionalsAny(User $user): bool
    {
        return $user->hasPermissionTo('Vincular Pedidos Adicionais');
    }

    public function linkAdditionals(User $user, Pedido $pedido): bool
    {
        if (! $this->linkAdditionalsAny($user)) {
            return false;
        }

        return app(PedidoService::class)->executarAcaoDaEscolaOuSetor($pedido, $user);
    }

    public function comment(User $user, Pedido $pedido): bool
    {
        if (! $user->hasPermissionTo(PedidoService::PERMISSAO_COMENTAR_PEDIDOS)) {
            return false;
        }

        return $this->view($user, $pedido);
    }

    public function evaluate(User $user, Pedido $pedido): bool
    {
        if (! $user->hasPermissionTo('Avaliar Pedidos')) {
            return false;
        }

        return app(PedidoService::class)->executarAcaoDaEscolaOuSetor($pedido, $user);
    }

    public function cancelAdditional(User $user, Pedido $adicional): bool
    {
        $service = app(PedidoService::class);

        if (! $service->adicionalElegivelParaAcao($adicional)) {
            return false;
        }

        $principal = $adicional->pedidoPrincipal;

        return $principal instanceof Pedido && $this->cancel($user, $principal);
    }

    public function promoteAdditional(User $user, Pedido $adicional): bool
    {
        $service = app(PedidoService::class);

        if (! $service->adicionalElegivelParaAcao($adicional)) {
            return false;
        }

        $principal = $adicional->pedidoPrincipal;

        return $principal instanceof Pedido && $this->manage($user, $principal);
    }

    public function exportReports(User $user): bool
    {
        return $user->hasPermissionLike('exportar relatorios');
    }
}