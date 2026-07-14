<?php

namespace App\Services;

use App\Models\Pessoa;
use App\Models\Professor;
use App\Models\Servidor;
use App\Models\Turma;
use App\Models\TurmaComponenteProfessor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PessoaEdicaoEscopadaService
{
    public function atualizar(Servidor $pessoa, User $user, array $data): Servidor
    {
        return DB::transaction(function () use ($pessoa, $user, $data): Servidor {
            /** @var Servidor $locked */
            $locked = Servidor::query()
                ->lockForUpdate()
                ->findOrFail($pessoa->getKey());

            if (! Gate::forUser($user)->allows('update', $locked)) {
                throw new \Illuminate\Auth\Access\AuthorizationException('Voce nao pode editar esta Pessoa.');
            }

            $editaveis = [
                'cpf',
                'email',
                'telefone',
                'matriculas_professor',
                'registros_professor',
            ];
            $somenteLeitura = [
                'nome',
                'status',
                'cargo',
                'observacoes',
                'id_escola',
                'cargos_gestores',
                'portaria',
                'turma_ids',
            ];
            $forjados = collect(array_keys($data))
                ->diff([...$editaveis, ...$somenteLeitura])
                ->reject(fn (string $key): bool => str_starts_with($key, 'permissions_'))
                ->values();

            if ($forjados->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'pessoa' => 'Payload contem campos estruturais nao permitidos: '.$forjados->implode(', '),
                ]);
            }

            $alterados = [];
            foreach (['nome', 'status', 'observacoes'] as $key) {
                if (array_key_exists($key, $data) && (string) ($data[$key] ?? '') !== (string) ($locked->{$key} ?? '')) {
                    $alterados[] = $key;
                }
            }

            if ($alterados !== []) {
                throw ValidationException::withMessages([
                    'pessoa' => 'Campos estruturais nao podem ser alterados neste perfil: '.implode(', ', $alterados),
                ]);
            }

            if (Gate::forUser($user)->allows('editBasicData', $locked)) {
                $locked->fill([
                    'cpf' => Pessoa::normalizarCpf($data['cpf'] ?? $locked->cpf),
                    'email' => filled($data['email'] ?? null)
                        ? Professor::normalizarEmail((string) $data['email'])
                        : null,
                    'telefone' => $data['telefone'] ?? null,
                ])->save();

                $this->propagarDadosBasicos($locked);
            }

            if (Gate::forUser($user)->allows('editTeachingAssignments', $locked)) {
                $this->sincronizarTurmasComponentes($locked, $user, $data['matriculas_professor'] ?? []);
            }

            return $locked->fresh([
                'professores.escola',
                'matriculas',
                'vinculosAtivos.funcaoAdministrativa',
            ]);
        });
    }

    private function propagarDadosBasicos(Servidor $pessoa): void
    {
        $professores = $pessoa->professores()->where('ativo', true)->get();

        foreach ($professores as $professor) {
            $professor->update([
                'email' => $pessoa->email,
                'telefone' => $pessoa->telefone,
            ]);
        }

        if ($pessoa->user) {
            $payload = [
                'email' => $pessoa->email ?? $pessoa->user->email,
            ];

            $pessoa->user->update($payload);
        }
    }

    private function sincronizarTurmasComponentes(Servidor $pessoa, User $user, array $matriculas): void
    {
        $escolaIds = app(PessoaScopeService::class)->escolaIdsDosVinculos($user);

        if ($escolaIds === []) {
            throw ValidationException::withMessages([
                'matriculas_professor' => 'Usuario sem escola autorizada para editar turmas e componentes.',
            ]);
        }

        $professoresPermitidos = $pessoa->professores()
            ->where('ativo', true)
            ->whereIn('id_escola', $escolaIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $enviados = collect($matriculas)
            ->filter(fn ($item): bool => is_array($item))
            ->flatMap(fn (array $matricula): array => is_array($matricula['escolas'] ?? null) ? $matricula['escolas'] : [])
            ->filter(fn ($item): bool => is_array($item) && filled($item['id'] ?? null))
            ->values();

        foreach ($enviados as $lotacao) {
            $professorId = (int) $lotacao['id'];
            /** @var Professor|null $professor */
            $professor = $professoresPermitidos->get($professorId);

            if (! $professor) {
                throw ValidationException::withMessages([
                    'matriculas_professor' => 'Lotacao informada nao pertence a Pessoa ou a escola autorizada.',
                ]);
            }

            $this->sincronizarProfessor($professor, $lotacao['vinculos_turma_componente'] ?? []);
        }
    }

    private function sincronizarProfessor(Professor $professor, array $vinculos): void
    {
        $normalizados = collect($vinculos)
            ->filter(fn ($item): bool => is_array($item))
            ->map(function (array $item): ?array {
                if (! filled($item['turma_id'] ?? null) || ! filled($item['componente_curricular_id'] ?? null)) {
                    return null;
                }

                return [
                    'turma_id' => (int) $item['turma_id'],
                    'componente_curricular_id' => (int) $item['componente_curricular_id'],
                ];
            })
            ->filter()
            ->unique(fn (array $item): string => $item['turma_id'].'-'.$item['componente_curricular_id'])
            ->values();

        $mantidos = collect();

        foreach ($normalizados as $vinculo) {
            $turma = Turma::query()
                ->with('serie.componentesCurriculares')
                ->find($vinculo['turma_id']);

            if (! $turma || (int) $turma->id_escola !== (int) $professor->id_escola) {
                throw ValidationException::withMessages([
                    'matriculas_professor' => 'Turma informada nao pertence a escola autorizada.',
                ]);
            }

            $componentesDaSerie = $turma->serie?->componentesCurriculares
                ?->pluck('id')
                ->map(fn ($id): int => (int) $id) ?? collect();

            if (! $componentesDaSerie->contains($vinculo['componente_curricular_id'])) {
                throw ValidationException::withMessages([
                    'matriculas_professor' => 'Componente informado nao pertence a serie da turma.',
                ]);
            }

            TurmaComponenteProfessor::query()->updateOrCreate(
                [
                    'turma_id' => $vinculo['turma_id'],
                    'componente_curricular_id' => $vinculo['componente_curricular_id'],
                ],
                [
                    'professor_id' => $professor->id,
                    'tem_professor' => true,
                ],
            );

            $mantidos->push($vinculo['turma_id'].'-'.$vinculo['componente_curricular_id']);
        }

        TurmaComponenteProfessor::query()
            ->where('professor_id', $professor->id)
            ->where('tem_professor', true)
            ->get()
            ->each(function (TurmaComponenteProfessor $row) use ($mantidos): void {
                $key = $row->turma_id.'-'.$row->componente_curricular_id;

                if ($mantidos->contains($key)) {
                    return;
                }

                $row->update([
                    'professor_id' => null,
                    'tem_professor' => false,
                ]);
            });

        app(ProfessorEscolaVinculoService::class)->sincronizarPorProfessores([$professor->id]);
    }
}
