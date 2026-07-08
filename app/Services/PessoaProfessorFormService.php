<?php

namespace App\Services;

use App\Filament\Admin\Resources\Servidores\ServidorResource;
use App\Models\Professor;
use App\Models\Servidor;
use App\Models\TurmaComponenteProfessor;

class PessoaProfessorFormService
{
    public function __construct(private readonly PessoaAcessoService $acessoService) {}

    /** @return array<string, mixed> */
    public function dadosParaFormulario(Servidor $servidor): array
    {
        $servidor->loadMissing(['professores.escola', 'user.roles']);

        $professores = $servidor->professores;

        $vinculosPorProfessor = TurmaComponenteProfessor::query()
            ->whereIn('professor_id', $professores->pluck('id'))
            ->where('tem_professor', true)
            ->whereNotNull('professor_id')
            ->get()
            ->groupBy('professor_id');

        $registros = $professores->map(fn (Professor $professor): array => [
            'id' => $professor->id,
            'matricula' => $professor->matricula,
            'turno' => $professor->turno,
            'id_escola' => $professor->id_escola,
            'vinculos_turma_componente' => ($vinculosPorProfessor->get($professor->id) ?? collect())
                ->map(fn (TurmaComponenteProfessor $vinculo): array => [
                    'turma_id' => $vinculo->turma_id,
                    'componente_curricular_id' => $vinculo->componente_curricular_id,
                ])
                ->values()
                ->all(),
        ])->values()->all();

        $dados = [
            'nome' => $servidor->nome,
            'cpf' => $servidor->cpf,
            'email' => $servidor->email,
            'telefone' => $servidor->telefone,
            'status' => $servidor->status,
            'observacoes' => $servidor->observacoes,
            'cargo' => ServidorResource::CARGO_PROFESSOR,
            'registros_professor' => $registros,
            'email_approved' => true,
            'usar_permissoes_extras' => false,
            'roles_adicionais' => [],
        ];

        if ($servidor->user) {
            $imutaveis = $this->acessoService->rolesImutaveisProfessor();
            $dados['roles_adicionais'] = $servidor->user->roles
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->diff($imutaveis)
                ->values()
                ->all();
            $dados['email_approved'] = (bool) $servidor->user->email_approved;
            $dados['usar_permissoes_extras'] = $servidor->user->getDirectPermissions()->isNotEmpty();

            foreach ($servidor->user->getDirectPermissions()->pluck('name') as $permission) {
                $grupo = explode(' ', (string) $permission)[0];
                $dados["permissions_{$grupo}"] ??= [];
                $dados["permissions_{$grupo}"][] = $permission;
            }
        }

        return $dados;
    }
}