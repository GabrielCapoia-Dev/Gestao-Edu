<?php

namespace App\Services;

use App\Filament\Admin\Resources\Servidores\ServidorResource;
use App\Models\Pessoa;
use App\Models\Professor;
use App\Models\ProfessorMatricula;
use App\Models\Servidor;
use App\Models\TurmaComponenteProfessor;
use Illuminate\Support\Facades\Schema;

class PessoaProfessorFormService
{
    public function __construct(private readonly PessoaAcessoService $acessoService) {}

    /** @return array<string, mixed> */
    public function dadosParaFormulario(Pessoa|Servidor $pessoa): array
    {
        $pessoa->loadMissing(['professores.escola', 'user.roles', 'professorMatriculas']);

        $professores = $pessoa->professores;

        $vinculosPorProfessor = TurmaComponenteProfessor::query()
            ->whereIn('professor_id', $professores->pluck('id'))
            ->where('tem_professor', true)
            ->whereNotNull('professor_id')
            ->get()
            ->groupBy('professor_id');

        $matriculasProfessor = $this->montarMatriculasHierarquicas($pessoa, $professores, $vinculosPorProfessor);

        // Flat legado (compat edit path e testes antigos de form)
        $registrosFlat = $professores->map(fn (Professor $professor): array => [
            'id' => $professor->id,
            'matricula' => $professor->matricula,
            'turno' => $professor->turnoEfetivo() ?? $professor->turno,
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
            'nome' => $pessoa->nome,
            'cpf' => Pessoa::formatarCpf($pessoa->cpf),
            'email' => $pessoa->email,
            'telefone' => $pessoa->telefone,
            'status' => $pessoa->status,
            'observacoes' => $pessoa->observacoes,
            'cargo' => ServidorResource::CARGO_PROFESSOR,
            'matriculas_professor' => $matriculasProfessor,
            'registros_professor' => $registrosFlat,
            'email_approved' => true,
            'usar_permissoes_extras' => false,
            'roles_adicionais' => [],
        ];

        if ($pessoa->user) {
            $imutaveis = $this->acessoService->rolesImutaveisProfessor();
            $dados['roles_adicionais'] = $pessoa->user->roles
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->diff($imutaveis)
                ->values()
                ->all();
            $dados['email_approved'] = (bool) $pessoa->user->email_approved;
            $dados['usar_permissoes_extras'] = $pessoa->user->getDirectPermissions()->isNotEmpty();

            foreach ($pessoa->user->getDirectPermissions()->pluck('name') as $permission) {
                $grupo = explode(' ', (string) $permission)[0];
                $dados["permissions_{$grupo}"] ??= [];
                $dados["permissions_{$grupo}"][] = $permission;
            }
        }

        return $dados;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Professor>  $professores
     * @param  \Illuminate\Support\Collection<int|string, \Illuminate\Support\Collection<int, TurmaComponenteProfessor>>  $vinculosPorProfessor
     * @return list<array<string, mixed>>
     */
    private function montarMatriculasHierarquicas(Pessoa|Servidor $pessoa, $professores, $vinculosPorProfessor): array
    {
        if (Schema::hasTable('professor_matriculas') && $pessoa->professorMatriculas->isNotEmpty()) {
            return $pessoa->professorMatriculas
                ->map(function (ProfessorMatricula $matricula) use ($professores, $vinculosPorProfessor): array {
                    $lotacoes = $professores
                        ->filter(fn (Professor $p): bool => (int) ($p->professor_matricula_id ?? 0) === (int) $matricula->id
                            || ((string) $p->matricula === (string) $matricula->matricula))
                        ->values();

                    return [
                        'id' => $matricula->id,
                        'matricula' => $matricula->matricula,
                        'turno' => $matricula->turno,
                        'escolas' => $lotacoes->map(fn (Professor $professor): array => [
                            'id' => $professor->id,
                            'id_escola' => $professor->id_escola,
                            'vinculos_turma_componente' => ($vinculosPorProfessor->get($professor->id) ?? collect())
                                ->map(fn (TurmaComponenteProfessor $vinculo): array => [
                                    'turma_id' => $vinculo->turma_id,
                                    'componente_curricular_id' => $vinculo->componente_curricular_id,
                                ])
                                ->values()
                                ->all(),
                        ])->all(),
                    ];
                })
                ->values()
                ->all();
        }

        return $professores
            ->groupBy(fn (Professor $p): string => (string) $p->matricula)
            ->map(function ($grupo, string $matricula) use ($vinculosPorProfessor): array {
                /** @var \Illuminate\Support\Collection<int, Professor> $grupo */
                $primeiro = $grupo->first();

                return [
                    'id' => null,
                    'matricula' => $matricula,
                    'turno' => $primeiro?->turnoEfetivo() ?? $primeiro?->turno,
                    'escolas' => $grupo->map(fn (Professor $professor): array => [
                        'id' => $professor->id,
                        'id_escola' => $professor->id_escola,
                        'vinculos_turma_componente' => ($vinculosPorProfessor->get($professor->id) ?? collect())
                            ->map(fn (TurmaComponenteProfessor $vinculo): array => [
                                'turma_id' => $vinculo->turma_id,
                                'componente_curricular_id' => $vinculo->componente_curricular_id,
                            ])
                            ->values()
                            ->all(),
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }
}
