<?php

namespace App\Services;

use App\Models\Aluno;
use App\Models\Professor;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use App\Services\UserService;

class AlunoService
{
    public function __construct(
        protected UserService $userService,
        protected EscolaService $escolaService
    ) {}

    /*
    |--------------------------------------------------------------------------
    | QUERIES / FILTROS
    |--------------------------------------------------------------------------
    */

    public function aplicarFiltroPorEscolaDoUsuario(Builder $query, ?User $user): Builder
    {
        return $this->userService->aplicarFiltroPorEscolaDoUsuario($query, $user);
    }

    /*
    |--------------------------------------------------------------------------
    | OPÇÕES DE SELECT
    |--------------------------------------------------------------------------
    */

    public function opcoesDeTurmasParaEscola(?int $idEscola): array
    {
        if (! $idEscola) return [];

        return Turma::with('serie')
            ->where('id_escola', $idEscola)
            ->get()
            ->filter(fn($t) => $t->serie)
            ->mapWithKeys(fn($turma) => [
                $turma->id => "{$turma->serie->nome} - {$turma->turma}",
            ])
            ->toArray();
    }

    public function opcoesDeProfissionaisApoioParaEscola(?int $idEscola, ?string $turno = null): array
    {
        if (! $idEscola) return [];

        return Professor::query()
            ->where('id_escola', $idEscola)
            ->where('profissional_apoio', true)
            ->when($turno, fn($q) => $q->where('turno', $turno))
            ->orderBy('nome')
            ->limit(500)
            ->pluck('nome', 'id')
            ->all();
    }

    public function opcoesDeProfissionaisParaEscola(?int $idEscola): array
    {
        $query = Professor::query();

        if ($idEscola) {
            $query->where('id_escola', $idEscola);
        }

        return $query->orderBy('nome')->pluck('nome', 'id')->toArray();
    }

    /*
    |--------------------------------------------------------------------------
    | REGRAS DE FORMULÁRIO (lógica de negócio, não UI)
    |--------------------------------------------------------------------------
    */

    public function escolaInicialParaForm(?Aluno $record, ?User $user): ?int
    {
        return $record?->turma?->id_escola ?? ($user?->id_escola ?? null);
    }

    public function desabilitarSelectTurma(?int $idEscola): bool
    {
        return blank($idEscola);
    }

    public function deveTravarCampoEscola(?User $currentUser, string $context): bool
    {
        if ($this->userService->ehAdmin($currentUser)) return false;
        if ($context === 'create' && filled($currentUser?->id_escola)) return true;
        if ($context === 'edit') return true;
        return false;
    }
}