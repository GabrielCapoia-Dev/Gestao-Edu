<?php

namespace App\Policies;

use App\Models\ExportRequest;
use App\Models\User;
use App\Services\Exports\ExportSessionService;

class ExportRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Admin')
            || $user->hasPermissionLike('exportar')
            || $user->hasPermissionLike('relatorios')
            || $user->hasPermissionLike('importar alunos')
            || $user->hasPermissionLike('excluir alunos');
    }

    public function view(User $user, ExportRequest $exportRequest): bool
    {
        return $this->owns($user, $exportRequest)
            && app(ExportSessionService::class)->belongsToCurrentSession($exportRequest);
    }

    public function download(User $user, ExportRequest $exportRequest): bool
    {
        return $exportRequest->isFinished()
            && filled($exportRequest->file_path)
            && $this->view($user, $exportRequest);
    }

    public function cancel(User $user, ExportRequest $exportRequest): bool
    {
        return $exportRequest->isActive() && $this->view($user, $exportRequest);
    }

    private function owns(User $user, ExportRequest $exportRequest): bool
    {
        return (int) $exportRequest->user_id === (int) $user->id;
    }
}
