<?php

namespace App\Livewire\Avaliacoes;

use App\Filament\Admin\Pages\AvaliacoesProfessor;
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
     * uma interação Livewire volte a carregar todas as turmas da série e todo
     * o conjunto de alunos/pautas.
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

    /**
     * Trocar entre "Por pautas" e "Por alunos" pode substituir uma árvore DOM
     * muito grande. Na tela do professor fazemos uma navegação limpa para
     * remontar o workspace no novo modo, evitando erros de morph/hidratação e
     * estados residuais do Livewire durante a troca de visualização.
     */
    public function definirVisualizacao(string $visualizacao): void
    {
        if (! in_array($visualizacao, ['pautas', 'alunos'], true)) {
            return;
        }

        if ($this->visualizacao === $visualizacao) {
            return;
        }

        $url = AvaliacoesProfessor::getUrl(array_filter([
            'avaliacao' => $this->avaliacao,
            'escola' => $this->escola,
            'serie' => $this->serie,
            'turma' => $this->turma,
            'visualizacao' => $visualizacao,
        ], static fn ($valor): bool => $valor !== null && $valor !== ''));

        // Redirecionamento completo intencional: evita reaproveitar a árvore
        // Livewire pesada da visualização anterior.
        $this->redirect($url, navigate: false);
    }
}
