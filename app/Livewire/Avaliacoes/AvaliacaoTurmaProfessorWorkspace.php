<?php

namespace App\Livewire\Avaliacoes;

use App\Models\Turma;
use Illuminate\Support\Collection;

class AvaliacaoTurmaProfessorWorkspace extends AvaliacaoTurmaWorkspace
{
    /**
     * Na tela "Minhas Avaliações", quando a navegação já informa uma turma,
     * mantém todo o workspace restrito a ela.
     *
     * O componente base também é usado pelo acompanhamento administrativo.
     * Por isso o ajuste fica isolado no workspace do professor, evitando que
     * uma interação Livewire (collapse, troca para "Por alunos", etc.) volte
     * a carregar todas as turmas da série e todo o conjunto de alunos/pautas.
     */
    public function getTurmasDisponiveisProperty(): Collection
    {
        if ($this->turmasDisponiveisCache instanceof Collection) {
            return $this->turmasDisponiveisCache;
        }

        $turmas = parent::getTurmasDisponiveisProperty();

        if (! $this->turma) {
            return $turmas;
        }

        return $this->turmasDisponiveisCache = $turmas
            ->filter(fn (Turma $turma): bool => (int) $turma->id === (int) $this->turma)
            ->values();
    }
}
