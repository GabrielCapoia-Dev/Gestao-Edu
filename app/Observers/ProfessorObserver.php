<?php

namespace App\Observers;

use App\Models\Professor;
use App\Services\ProfessorEscolaVinculoService;

class ProfessorObserver
{
    public function saved(Professor $professor): void
    {
        $this->sincronizarUsuariosRelacionados($professor);
    }

    public function deleted(Professor $professor): void
    {
        $this->sincronizarUsuariosRelacionados($professor);
    }

    private function sincronizarUsuariosRelacionados(Professor $professor): void
    {
        $userIds = collect([
            $professor->user_id,
            $professor->getOriginal('user_id'),
        ])->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();

        if ($userIds === []) {
            return;
        }

        app(ProfessorEscolaVinculoService::class)->sincronizarPorUsuarios($userIds);
    }
}
