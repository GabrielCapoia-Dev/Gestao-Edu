<?php

namespace App\Livewire\Avaliacoes;

use App\Filament\Admin\Pages\AvaliacoesProfessor;
use App\Models\Turma;
use Illuminate\Support\Collection;

class AvaliacaoTurmaProfessorWorkspace extends AvaliacaoTurmaWorkspace
{
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

    public function definirVisualizacao(string $visualizacao): void
    {
        if (! in_array($visualizacao, ['pautas', 'alunos'], true) || $this->visualizacao === $visualizacao) {
            return;
        }

        $url = AvaliacoesProfessor::getUrl(array_filter([
            'avaliacao' => $this->avaliacao,
            'escola' => $this->escola,
            'serie' => $this->serie,
            'turma' => $this->turma,
            'visualizacao' => $visualizacao,
        ], static fn ($valor): bool => $valor !== null && $valor !== ''));

        $this->redirect($url, navigate: false);
    }
}
