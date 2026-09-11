<?php

namespace App\Policies;

use App\Models\Aluno;
use App\Models\User;
use App\Services\PessoaScopeService;

class AlunoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Listar Alunos');
    }

    public function export(User $user): bool
    {
        return ! $user->ehProfessor() && $this->viewAny($user);
    }

    public function view(User $user, Aluno $aluno): bool
    {
        return $user->hasPermissionTo('Listar Alunos')
            && $this->pertenceAoEscopoDoUsuario($user, $aluno);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Criar Alunos');
    }

    public function update(User $user, Aluno $aluno): bool
    {
        return $this->updateAny($user)
            && $aluno->estaMatriculado()
            && $this->pertenceAoEscopoDoUsuario($user, $aluno);
    }

    public function updateAny(User $user): bool
    {
        return $user->hasPermissionTo('Editar Alunos');
    }

    public function delete(User $user, Aluno $aluno): bool
    {
        return $user->hasPermissionTo('Excluir Alunos')
            && $aluno->estaMatriculado()
            && $this->pertenceAoEscopoDoUsuario($user, $aluno);
    }

    public function import(User $user): bool
    {
        return $user->hasPermissionTo('Importar Alunos por Planilha');
    }

    public function exportTemplate(User $user): bool
    {
        return $user->hasPermissionTo('Exportar Modelo de Importacao de Alunos');
    }

    public function deleteBulk(User $user): bool
    {
        return $user->hasPermissionTo('Excluir Alunos em Massa');
    }

    public function filterBySchool(User $user): bool
    {
        return $user->hasPermissionTo('Filtrar Alunos por Escola');
    }

    public function editSchool(User $user): bool
    {
        return $user->hasPermissionTo('Editar Escola do Aluno')
            || $user->hasPermissionTo('Editar Escola da Turma');
    }

    public function remanejar(User $user, Aluno $aluno): bool
    {
        return $user->hasPermissionLike('Realizar Remanejamento de Aluno')
            && $this->pertenceAoEscopoDoUsuario($user, $aluno);
    }

    public function voltarTurma(User $user, Aluno $aluno): bool
    {
        return $this->remanejar($user, $aluno);
    }

    public function contraTurno(User $user, Aluno $aluno): bool
    {
        return $this->updateAny($user)
            && $this->pertenceAoEscopoDoUsuario($user, $aluno);
    }

    public function encerrarContraTurno(User $user, Aluno $aluno): bool
    {
        return $this->updateAny($user)
            && $this->pertenceAoEscopoDoUsuario($user, $aluno);
    }

    public function parecerTransferencia(User $user, Aluno $aluno): bool
    {
        return ($user->hasPermissionLike('realizar transferencia de aluno')
            || $user->hasPermissionLike('realizar tranferencia de aluno')
            || $user->hasPermissionLike('gerar parecer de transferencia'))
            && $this->pertenceAoEscopoDoUsuario($user, $aluno);
    }

    private function pertenceAoEscopoDoUsuario(User $user, Aluno $aluno): bool
    {
        $aluno->loadMissing('turma.componentes');

        $scope = app(PessoaScopeService::class);

        if ($scope->hasGlobalAccess($user)) {
            return true;
        }

        if ($scope->ehEquipeGestora($user)) {
            return $scope->canAccessEscola($user, (int) $aluno->turma?->id_escola);
        }

        if ($user->ehProfessor()) {
            $professoresIds = $user->professores()->pluck('id')->toArray();

            return $aluno->turma?->componentes
                ?->pluck('pivot.professor_id')
                ->intersect($professoresIds)
                ->isNotEmpty() ?? false;
        }

        return $scope->canAccessEscola($user, (int) $aluno->turma?->id_escola);
    }
}
