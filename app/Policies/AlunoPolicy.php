<?php

namespace App\Policies;

use App\Models\Aluno;
use App\Models\User;

class AlunoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Listar Alunos');
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
        return $user->hasPermissionTo('Editar Alunos')
            && $aluno->estaMatriculado()
            && $this->pertenceAoEscopoDoUsuario($user, $aluno);
    }

    public function delete(User $user, Aluno $aluno): bool
    {
        return $user->hasPermissionTo('Excluir Alunos')
            && $aluno->estaMatriculado()
            && $this->pertenceAoEscopoDoUsuario($user, $aluno);
    }

    private function pertenceAoEscopoDoUsuario(User $user, Aluno $aluno): bool
    {
        $aluno->loadMissing('turma.componentes');

        if ($user->hasRole('Admin')) {
            return true;
        }

        if ($user->ehProfessor()) {
            $professoresIds = $user->professores()->pluck('id')->toArray();

            return $aluno->turma?->componentes
                ?->pluck('pivot.professor_id')
                ->intersect($professoresIds)
                ->isNotEmpty() ?? false;
        }

        if (filled($user->id_escola)) {
            return (int) $aluno->turma?->id_escola === (int) $user->id_escola;
        }

        return true;
    }
}
