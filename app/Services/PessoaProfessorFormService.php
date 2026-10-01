<?php

namespace App\Services;

use App\Filament\Admin\Resources\Servidores\ServidorResource;
use App\Models\Pessoa;
use App\Models\PessoaMatricula;
use App\Models\Professor;
use App\Models\Servidor;
use App\Models\TurmaComponenteProfessor;
use App\Models\User;
use Illuminate\Support\Collection;
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
            'carga_horaria' => $pessoa->carga_horaria,
            'jornada' => $pessoa->jornada,
            'lotacao_id' => $pessoa->lotacao_id,
            'cargo' => ServidorResource::CARGO_PROFESSOR,
            'matriculas_professor' => $matriculasProfessor,
            'jornadas_arquivadas' => $this->jornadasArquivadas($pessoa),
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
        $vinculoRh = $pessoa->vinculosAtivos
            ->first(fn ($vinculo): bool => (bool) $vinculo->funcaoAdministrativa?->ehRh());

        if ($vinculoMotorista) {
            return array_merge($dados, [
                'cargo' => ServidorResource::CARGO_MOTORISTA,
                'matricula_motorista' => $vinculoMotorista->matricula ?? $pessoa->matricula,
            ]);
        }

        if ($vinculoRh) {
            return array_merge($dados, [
                'cargo' => ServidorResource::CARGO_RH,
                'matricula_operacional' => $vinculoRh->matricula ?? $pessoa->matricula,
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
                'turno_operacional' => $pessoa->matriculas
                    ->firstWhere('matricula', $vinculoAssessoriaPedagogica->matricula ?? $pessoa->matricula)
                    ?->turno,
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
        $ehRhNoEscopo = $pessoa->vinculosAtivos
            ->contains(fn ($vinculo): bool => (bool) $vinculo->funcaoAdministrativa?->ehRh());

        return [
            'nome' => $pessoa->nome,
            'cpf' => Pessoa::formatarCpf($pessoa->cpf),
            'email' => $pessoa->email,
            'telefone' => $pessoa->telefone,
            'status' => $pessoa->status,
            'carga_horaria' => $pessoa->carga_horaria,
            'jornada' => $pessoa->jornada,
            'lotacao_id' => $pessoa->lotacao_id,
            'cargo' => match (true) {
                $ehMotoristaNoEscopo => ServidorResource::CARGO_MOTORISTA,
                $ehTransporteNoEscopo => ServidorResource::CARGO_TRANSPORTE,
                $ehAssessoriaPedagogicaNoEscopo => ServidorResource::CARGO_ASSESSORIA_PEDAGOGICA,
                $ehRhNoEscopo => ServidorResource::CARGO_RH,
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
            'jornadas_arquivadas' => $this->jornadasArquivadas($pessoa),
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
            'turno_operacional' => $ehAssessoriaPedagogicaNoEscopo
                ? $pessoa->matriculas
                    ->firstWhere('matricula', $pessoa->vinculosAtivos
                        ->first(fn ($vinculo): bool => (bool) $vinculo->funcaoAdministrativa?->ehAssessoriaPedagogica())
                        ?->matricula ?? $pessoa->matricula)
                    ?->turno
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
     * @param  Collection<int, Professor>  $professores
     * @param  Collection<int|string, Collection<int, TurmaComponenteProfessor>>  $vinculosPorProfessor
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
                        'carga_horaria' => $matricula->carga_horaria,
                        'jornada' => (bool) $matricula->jornada,
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
                /** @var Collection<int, Professor> $grupo */
                $primeiro = $grupo->first();

                return [
                    'id' => null,
                    'matricula' => $matricula,
                    'turno' => $primeiro?->turnoEfetivo() ?? $primeiro?->turno,
                    'carga_horaria' => ($primeiro?->turnoEfetivo() ?? $primeiro?->turno) === 'integral' ? 40 : 20,
                    'jornada' => false,
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

    /** @return list<array{id:int,matricula:string,turno:string,carga_horaria:int}> */
    private function jornadasArquivadas(Pessoa|Servidor $pessoa): array
    {
        if (! Schema::hasColumn('professor_matriculas', 'deleted_at')) {
            return [];
        }

        return $pessoa->matriculas()
            ->onlyTrashed()
            ->where('jornada', true)
            ->orderByDesc('deleted_at')
            ->get()
            ->map(fn (PessoaMatricula $matricula): array => [
                'id' => (int) $matricula->id,
                'matricula' => (string) $matricula->matricula,
                'turno' => (string) $matricula->turno,
                'carga_horaria' => (int) $matricula->carga_horaria,
            ])
            ->all();
    }
}
