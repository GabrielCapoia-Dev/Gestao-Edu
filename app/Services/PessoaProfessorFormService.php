<?php

namespace App\Services;

use App\Filament\Admin\Resources\Servidores\ServidorResource;
use App\Models\Pessoa;
use App\Models\PessoaMatricula;
use App\Models\Professor;
use App\Models\Servidor;
use App\Models\TurmaComponenteProfessor;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class PessoaProfessorFormService
{
    /** @return array<string, mixed> */
    public function dadosParaFormulario(Pessoa|Servidor $pessoa): array
    {
        $pessoa->loadMissing([
            'professores.escola',
            'matriculas',
            'vinculosAtivos.funcaoAdministrativa',
            'vinculosAtivos.escolasAssessoradas',
            'vinculosAtivos.vinculosTurmaAtivos',
        ]);

        $professores = $pessoa->professores
            ->where('ativo', true)
            ->values();

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
        ];

        $vinculoManutencao = $pessoa->vinculosAtivos
            ->first(fn ($vinculo): bool => (bool) $vinculo->funcaoAdministrativa?->ehManutencao());

        $vinculoObras = $pessoa->vinculosAtivos
            ->first(fn ($vinculo): bool => (bool) $vinculo->funcaoAdministrativa?->ehObras());

        $vinculoMotorista = $pessoa->vinculosAtivos
            ->first(fn ($vinculo): bool => (bool) $vinculo->funcaoAdministrativa?->ehMotorista());

        $vinculoTransporte = $pessoa->vinculosAtivos
            ->first(fn ($vinculo): bool => (bool) $vinculo->funcaoAdministrativa?->ehTransporte());

        $vinculoAssessoriaPedagogica = $pessoa->vinculosAtivos
            ->first(fn ($vinculo): bool => (bool) $vinculo->funcaoAdministrativa?->ehAssessoriaPedagogica());

        if ($vinculoMotorista) {
            return array_merge($dados, [
                'cargo' => ServidorResource::CARGO_MOTORISTA,
                'matricula_motorista' => $vinculoMotorista->matricula ?? $pessoa->matricula,
            ]);
        }

        if ($vinculoTransporte) {
            return array_merge($dados, [
                'cargo' => ServidorResource::CARGO_TRANSPORTE,
                'matricula_operacional' => $vinculoTransporte->matricula ?? $pessoa->matricula,
            ]);
        }

        if ($vinculoAssessoriaPedagogica) {
            return array_merge($dados, [
                'cargo' => ServidorResource::CARGO_ASSESSORIA_PEDAGOGICA,
                'matricula_operacional' => $vinculoAssessoriaPedagogica->matricula ?? $pessoa->matricula,
                'escola_ids_assessoria' => $vinculoAssessoriaPedagogica->escolasAssessoradas
                    ->pluck('id')
                    ->map(fn (mixed $id): int => (int) $id)
                    ->values()
                    ->all(),
            ]);
        }

        if ($vinculoObras) {
            return array_merge($dados, [
                'cargo' => ServidorResource::CARGO_OBRAS,
                'setor_id' => $vinculoObras->setor_id,
            ]);
        }

        if ($vinculoManutencao) {
            return array_merge($dados, [
                'cargo' => ServidorResource::CARGO_MANUTENCAO,
                'setor_id' => $vinculoManutencao->setor_id,
            ]);
        }

        $vinculosGestores = $pessoa->vinculosAtivos
            ->filter(fn ($vinculo): bool => (bool) (
                $vinculo->funcaoAdministrativa?->direcao_escolar
                || $vinculo->funcaoAdministrativa?->coordenacao_pedagogica
                || $vinculo->funcaoAdministrativa?->secretaria_escolar
            ))
            ->values();

        if ($vinculosGestores->isEmpty()) {
            return $dados;
        }

        $diretor = $vinculosGestores->first(
            fn ($vinculo): bool => (bool) $vinculo->funcaoAdministrativa?->direcao_escolar,
        );
        $coordenador = $vinculosGestores->first(
            fn ($vinculo): bool => (bool) $vinculo->funcaoAdministrativa?->coordenacao_pedagogica,
        );
        $secretario = $vinculosGestores->first(
            fn ($vinculo): bool => (bool) $vinculo->funcaoAdministrativa?->secretaria_escolar,
        );
        $escolas = $vinculosGestores->pluck('id_escola')->filter()->map(fn ($id): int => (int) $id)->unique()->values();
        $cargos = collect([
            $diretor ? 'diretor' : null,
            $coordenador ? 'coordenador' : null,
            $secretario ? 'secretario' : null,
        ])->filter()->values()->all();

        return array_merge($dados, [
            'cargo' => ServidorResource::CARGO_EQUIPE_GESTORA,
            'id_escola' => $escolas->count() === 1 ? $escolas->first() : null,
            'cargos_gestores' => $cargos,
            'portaria' => $vinculosGestores->pluck('portaria')->filter()->first(),
            'turma_ids' => $coordenador?->vinculosTurmaAtivos
                ?->pluck('turma_id')->map(fn ($id): int => (int) $id)->values()->all() ?? [],
        ]);
    }

    /** @return array<string, mixed> */
    public function dadosEscopadosParaFormulario(Pessoa|Servidor $pessoa, User $user): array
    {
        $escolaIds = app(PessoaScopeService::class)->escolaIdsDosVinculos($user);

        $pessoa->loadMissing([
            'professores.escola',
            'matriculas',
            'vinculosAtivos.funcaoAdministrativa',
            'vinculosAtivos.escolasAssessoradas',
        ]);

        $professores = $pessoa->professores
            ->where('ativo', true)
            ->filter(fn (Professor $professor): bool => in_array((int) $professor->id_escola, $escolaIds, true))
            ->values();

        $vinculosPorProfessor = TurmaComponenteProfessor::query()
            ->whereIn('professor_id', $professores->pluck('id'))
            ->where('tem_professor', true)
            ->whereNotNull('professor_id')
            ->get()
            ->groupBy('professor_id');

        $matriculasProfessor = collect(
            $this->montarMatriculasHierarquicas($pessoa, $professores, $vinculosPorProfessor),
        )
            ->filter(fn (array $matricula): bool => ! empty($matricula['escolas']))
            ->values()
            ->all();

        $ehEquipeGestoraNoEscopo = $pessoa->vinculosAtivos
            ->contains(fn ($vinculo): bool => filled($vinculo->id_escola)
                && in_array((int) $vinculo->id_escola, $escolaIds, true)
                && (bool) (
                    $vinculo->funcaoAdministrativa?->direcao_escolar
                    || $vinculo->funcaoAdministrativa?->coordenacao_pedagogica
                    || $vinculo->funcaoAdministrativa?->secretaria_escolar
                ));

        $ehManutencaoNoEscopo = $pessoa->vinculosAtivos
            ->contains(fn ($vinculo): bool => (bool) $vinculo->funcaoAdministrativa?->ehManutencao());
        $ehObrasNoEscopo = $pessoa->vinculosAtivos
            ->contains(fn ($vinculo): bool => (bool) $vinculo->funcaoAdministrativa?->ehObras());
        $ehMotoristaNoEscopo = $pessoa->vinculosAtivos
            ->contains(fn ($vinculo): bool => (bool) $vinculo->funcaoAdministrativa?->ehMotorista());
        $ehTransporteNoEscopo = $pessoa->vinculosAtivos
            ->contains(fn ($vinculo): bool => (bool) $vinculo->funcaoAdministrativa?->ehTransporte());
        $ehAssessoriaPedagogicaNoEscopo = $pessoa->vinculosAtivos
            ->contains(fn ($vinculo): bool => (bool) $vinculo->funcaoAdministrativa?->ehAssessoriaPedagogica());

        return [
            'nome' => $pessoa->nome,
            'cpf' => Pessoa::formatarCpf($pessoa->cpf),
            'email' => $pessoa->email,
            'telefone' => $pessoa->telefone,
            'status' => $pessoa->status,
            'cargo' => match (true) {
                $ehMotoristaNoEscopo => ServidorResource::CARGO_MOTORISTA,
                $ehTransporteNoEscopo => ServidorResource::CARGO_TRANSPORTE,
                $ehAssessoriaPedagogicaNoEscopo => ServidorResource::CARGO_ASSESSORIA_PEDAGOGICA,
                $ehObrasNoEscopo => ServidorResource::CARGO_OBRAS,
                $ehManutencaoNoEscopo => ServidorResource::CARGO_MANUTENCAO,
                $ehEquipeGestoraNoEscopo => ServidorResource::CARGO_EQUIPE_GESTORA,
                default => ServidorResource::CARGO_PROFESSOR,
            },
            'setor_id' => ($ehManutencaoNoEscopo || $ehObrasNoEscopo)
                ? $pessoa->vinculosAtivos
                    ->first(fn ($vinculo): bool => (bool) (
                        $vinculo->funcaoAdministrativa?->ehManutencao()
                        || $vinculo->funcaoAdministrativa?->ehObras()
                    ))?->setor_id
                : null,
            'matriculas_professor' => $matriculasProfessor,
            'matricula_motorista' => $ehMotoristaNoEscopo
                ? ($pessoa->vinculosAtivos
                    ->first(fn ($vinculo): bool => (bool) $vinculo->funcaoAdministrativa?->ehMotorista())
                    ?->matricula ?? $pessoa->matricula)
                : null,
            'matricula_operacional' => ($ehTransporteNoEscopo || $ehAssessoriaPedagogicaNoEscopo)
                ? ($pessoa->vinculosAtivos
                    ->first(fn ($vinculo): bool => (bool) (
                        $vinculo->funcaoAdministrativa?->ehTransporte()
                        || $vinculo->funcaoAdministrativa?->ehAssessoriaPedagogica()
                    ))
                    ?->matricula ?? $pessoa->matricula)
                : null,
            'escola_ids_assessoria' => $ehAssessoriaPedagogicaNoEscopo
                ? $pessoa->vinculosAtivos
                    ->first(fn ($vinculo): bool => (bool) $vinculo->funcaoAdministrativa?->ehAssessoriaPedagogica())
                    ?->escolasAssessoradas
                    ->pluck('id')
                    ->map(fn (mixed $id): int => (int) $id)
                    ->intersect($escolaIds)
                    ->values()
                    ->all() ?? []
                : [],
            'registros_professor' => [],
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Professor>  $professores
     * @param  \Illuminate\Support\Collection<int|string, \Illuminate\Support\Collection<int, TurmaComponenteProfessor>>  $vinculosPorProfessor
     * @return list<array<string, mixed>>
     */
    private function montarMatriculasHierarquicas(Pessoa|Servidor $pessoa, $professores, $vinculosPorProfessor): array
    {
        if (Schema::hasTable('professor_matriculas') && $pessoa->matriculas->isNotEmpty()) {
            return $pessoa->matriculas
                ->map(function (PessoaMatricula $matricula) use ($professores, $vinculosPorProfessor): array {
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
