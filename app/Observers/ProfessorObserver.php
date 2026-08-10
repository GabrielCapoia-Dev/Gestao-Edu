<?php

namespace App\Observers;

use App\Models\Professor;
use App\Models\Servidor;
use App\Services\ProfessorEscolaVinculoService;
use App\Services\ServidorService;

class ProfessorObserver
{
    public function saved(Professor $professor): void
    {
        $this->sincronizarUsuariosRelacionados($professor);

        if (filled($professor->servidor_id)
            && Servidor::withTrashed()->whereKey($professor->servidor_id)->onlyTrashed()->exists()) {
            return;
        }

        app(ServidorService::class)->sincronizarProfessor($professor);
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
