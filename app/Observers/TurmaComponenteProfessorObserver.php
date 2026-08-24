<?php

namespace App\Observers;

use App\Models\TurmaComponenteProfessor;
use App\Services\Avaliacoes\AvaliacaoDashboardMetricsService;
use App\Services\ProfessorEscolaVinculoService;
use Illuminate\Support\Facades\DB;

class TurmaComponenteProfessorObserver
{
    public function saved(TurmaComponenteProfessor $vinculo): void
    {
        $this->sincronizarUsuariosRelacionados($vinculo);
    }

    public function deleted(TurmaComponenteProfessor $vinculo): void
    {
        $this->sincronizarUsuariosRelacionados($vinculo);
    }

    private function sincronizarUsuariosRelacionados(TurmaComponenteProfessor $vinculo): void
    {
        $professorIds = collect([
            $vinculo->professor_id,
            $vinculo->getOriginal('professor_id'),
        ])->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();

        if ($professorIds === []) {
            return;
        }

        app(ProfessorEscolaVinculoService::class)->sincronizarPorProfessores($professorIds);

        DB::table('avaliacao_turma')
            ->where('turma_id', (int) $vinculo->turma_id)
            ->pluck('avaliacao_id')
            ->unique()
            ->each(fn ($avaliacaoId) => app(AvaliacaoDashboardMetricsService::class)
                ->forgetForAvaliacao((int) $avaliacaoId));
    }
}
