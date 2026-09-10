<?php

namespace App\Livewire\Avaliacoes;

use App\Models\Pauta;
use App\Models\Turma;
use Illuminate\Support\Collection;

class AvaliacaoTurmaProfessorWorkspace extends AvaliacaoTurmaWorkspace
{
    public ?int $componenteModalId = null;

    public ?int $pautaModalId = null;

    protected ?Collection $turmasDaAvaliacaoProfessorCache = null;

    public function interfaceProfessorEmLista(): bool
    {
        return true;
    }

    public function getTurmasDaAvaliacaoProfessorProperty(): Collection
    {
        if ($this->turmasDaAvaliacaoProfessorCache instanceof Collection) {
            return $this->turmasDaAvaliacaoProfessorCache;
        }

        if (! $this->avaliacaoAtual) {
            return $this->turmasDaAvaliacaoProfessorCache = collect();
        }

        return $this->turmasDaAvaliacaoProfessorCache = $this->filtrarTurmasDaAvaliacao($this->avaliacaoAtual);
    }

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

        $this->visualizacao = $visualizacao;
        $this->pautaModalId = null;
        $this->alunoEmFoco = null;
    }

    public function abrirTurma(int $turmaId): void
    {
        abort_unless($this->turmasDaAvaliacaoProfessor->contains('id', $turmaId), 403);

        if ($this->turma === $turmaId && $this->turmaEstaExpandida($turmaId)) {
            $this->turmasExpandidas = [];
            $this->fecharComponente();

            return;
        }

        $this->turma = $turmaId;
        $this->updatedTurma();
        $this->turmasExpandidas = [$turmaId];
        $this->fecharComponente();
    }

    public function abrirComponente(int $turmaId, int $componenteId): void
    {
        abort_unless($this->turma === $turmaId, 403);
        abort_unless(
            $this->gruposPorComponenteDaTurma($turmaId)
                ->contains(fn (array $grupo): bool => (int) $grupo['componente_id'] === $componenteId),
            403,
        );

        $this->componenteModalId = $componenteId;
        $this->pautaModalId = null;
        $this->alunoEmFoco = null;
    }

    public function fecharComponente(): void
    {
        $this->componenteModalId = null;
        $this->pautaModalId = null;
        $this->alunoEmFoco = null;
    }

    public function selecionarPautaModal(int $pautaId): void
    {
        abort_unless(
            $this->pautasDoComponenteModal->contains('id', $pautaId),
            403,
        );

        $this->pautaModalId = $pautaId;
    }

    public function selecionarAlunoModal(int $alunoId): void
    {
        abort_unless(
            $this->turma && $this->alunosDaTurma((int) $this->turma)->contains('id', $alunoId),
            403,
        );

        $this->alunoEmFoco = $alunoId;
    }

    public function getGrupoComponenteModalProperty(): ?array
    {
        if (! $this->turma || $this->componenteModalId === null) {
            return null;
        }

        return $this->gruposPorComponenteDaTurma((int) $this->turma)
            ->first(fn (array $grupo): bool => (int) $grupo['componente_id'] === $this->componenteModalId);
    }

    public function getPautasDoComponenteModalProperty(): Collection
    {
        return collect($this->grupoComponenteModal['pautas'] ?? [])->values();
    }

    public function getTurmaModalProperty(): ?Turma
    {
        if (! $this->turma) {
            return null;
        }

        return $this->turmasDaAvaliacaoProfessor->firstWhere('id', (int) $this->turma);
    }

    public function getPautaModalProperty(): ?Pauta
    {
        if ($this->pautaModalId === null) {
            return null;
        }

        return $this->pautasDoComponenteModal->firstWhere('id', $this->pautaModalId);
    }
}
