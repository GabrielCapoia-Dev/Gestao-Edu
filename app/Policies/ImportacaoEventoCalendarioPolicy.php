<?php

namespace App\Policies;

use App\Models\ImportacaoEventoCalendario;
use App\Models\Enums\ListaPermissoes;
use App\Models\User;
use App\Services\PessoaScopeService;

class ImportacaoEventoCalendarioPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(ListaPermissoes::ImportarEventosPorPlanilha->label());
    }

    public function view(User $user, ImportacaoEventoCalendario $importacao): bool
    {
        return $this->viewAny($user)
            && ($this->global($user) || (int) $importacao->usuario_id === (int) $user->getKey());
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function confirm(User $user, ImportacaoEventoCalendario $importacao): bool
    {
        return $this->view($user, $importacao);
    }

    public function cancel(User $user, ImportacaoEventoCalendario $importacao): bool
    {
        return $this->view($user, $importacao);
    }

    public function exportTemplate(User $user): bool
    {
        return $user->hasPermissionTo(ListaPermissoes::ExportarModeloDeImportacaoDeEventos->label());
    }

    private function global(User $user): bool
    {
        return app(PessoaScopeService::class)->hasGlobalAccess($user);
    }
}
