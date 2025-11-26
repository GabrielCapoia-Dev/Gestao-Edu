<?php

namespace App\Policies;

use App\Models\AlunoLaudo;
use App\Models\User;

class AlunoLaudoPolicy
{
    public function view(User $user, AlunoLaudo $alunoLaudo): bool
    {
        if (! $user->hasPermissionTo('Visualizar Laudos de Aluno')) {
            return false;
        }

        // regra extra opcional: só pode ver laudos de alunos da mesma escola
        if (! empty($user->id_escola)) {
            return $user->id_escola === $alunoLaudo->aluno->turma->id_escola;
        }

        return true;
    }

    public function download(User $user, AlunoLaudo $alunoLaudo): bool
    {
        if (! $user->hasPermissionTo('Baixar Laudos de Aluno')) {
            return false;
        }

        // reaproveita a regra de view
        return $this->view($user, $alunoLaudo);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Anexar Laudos de Aluno');
    }

    public function delete(User $user, AlunoLaudo $alunoLaudo): bool
    {
        return $user->hasPermissionTo('Excluir Laudos de Aluno');
    }
}
