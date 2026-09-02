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
     * Mantém o componente do professor sempre restrito à turma selecionada
     * também nas coleções usadas diretamente pela view. O componente base
     * expõe "turmasDaSerieDisponiveis" para a visão geral; na tela do professor
     * isso fazia cada interação Livewire remontar todas as turmas da série,
     * mesmo quando o usuário estava trabalhando em uma única turma.
     */
    public function getTurmasDaSerieDisponiveisProperty(): Collection
    {
        $turmas = parent::getTurmasDaSerieDisponiveisProperty();

        if (! $this->turma) {
            return $turmas;
        }

        return $turmas
            ->filter(fn (Turma $turma): bool => (int) $turma->id === (int) $this->turma)
            ->values();
    }

    /**
     * A troca de modo altera uma árvore DOM grande. Fazemos um carregamento
     * completo da rota para impedir morph/hidratação residual do Livewire.
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

        $this->redirect($url, navigate: false);
    }
}
