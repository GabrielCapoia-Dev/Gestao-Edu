<?php

namespace App\Policies;

use App\Models\Avaliacao;
use App\Models\User;

class AvaliacaoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('Listar Avaliações');
    }

    public function view(User $user, Avaliacao $model): bool
    {
        return $user->hasPermissionTo('Listar Avaliações');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('Criar Avaliações');
    }

    public function update(User $user, Avaliacao $model): bool
    {
        return $user->hasPermissionTo('Editar Avaliações');
    }

    public function delete(User $user, Avaliacao $model): bool
    {
        return $user->hasPermissionTo('Excluir Avaliações');
    }

    public function follow(User $user): bool
    {
        return $user->hasPermissionTo('Acompanhar Avaliações');
    }

    public function export(User $user): bool
    {
        return $user->hasPermissionLike('exportar avaliacoes');
    }

    public function viewProgressBySchool(User $user): bool
    {
        return $user->hasPermissionTo('Visualizar Progresso por Escola');
    }

    public function accessProfessorPage(User $user): bool
    {
        return $user->hasPermissionLike('listar avaliacoes')
            || $user->hasPermissionLike('responder avaliacoes');
    }

    public function respond(User $user): bool
    {
        return $user->hasPermissionLike('responder avaliacoes');
    }

    public function viewReportsDashboard(User $user): bool
    {
        return $user->hasPermissionTo('Listar Relatórios: Dashboard');
    }

    public function viewProfessorComponentTurmaReport(User $user): bool
    {
        return $user->hasPermissionTo('Listar Relatórios: Professor por Componente e Turma');
    }

    public function viewMissingTeachersReport(User $user): bool
    {
        return $user->hasPermissionTo('Listar Relatórios: Componentes com Professores Faltando');
    }

    public function fillBulk(User $user): bool
    {
        return $user->hasPermissionTo('Preencher Avaliações em Massa');
    }
}
