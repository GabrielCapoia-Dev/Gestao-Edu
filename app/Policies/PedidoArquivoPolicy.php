<?php

namespace App\Policies;

use App\Models\PedidoArquivo;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PedidoArquivoPolicy
{
    public function download(User $user, PedidoArquivo $arquivo): bool
    {
        return $user->hasPermissionTo('Exportar Arquivos Pedido');
    }
}
