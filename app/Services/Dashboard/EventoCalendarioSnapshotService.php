<?php

namespace App\Services\Dashboard;

use App\Models\Aluno;
use App\Models\Enums\EventoCalendarioTransporteEscopo;
use App\Models\EventoCalendario;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class EventoCalendarioSnapshotService
{
    public function __construct(private readonly EventoCalendarioPublicoService $publico) {}

    /** @param list<int|string> $alunosExcluidos */
    public function sincronizar(EventoCalendario $evento, array $alunosExcluidos = []): void
    {
        $evento->participantesSnapshot()->delete();
        $evento->alunosSnapshot()->delete();

        $agora = now();
        $participantes = $this->publico->destinatarios($evento);
        $turnos = $evento->publicoRegras()
            ->get()
            ->pluck('filtros')
            ->flatMap(fn ($filtros): array => is_array($filtros) ? ($filtros['turnos'] ?? []) : [])
            ->filter()
            ->unique()
            ->map(fn (string $turno): string => match ($turno) {
                'manha' => 'Manhã', 'tarde' => 'Tarde', 'noite' => 'Noite', 'integral' => 'Integral', default => $turno,
            })
            ->values()
            ->join(', ');
        $linhasParticipantes = $participantes->map(function (User $usuario) use ($evento, $agora, $turnos): array {
            $usuario->loadMissing([
                'escola:id,nome', 'escolas:id,nome', 'servidores.funcoesAtivas:id,nome',
                'servidores.vinculosAtivos.escola:id,nome', 'servidores.professores.escola:id,nome',
            ]);
            $escolas = collect([$usuario->escola])
                ->merge($usuario->escolas)
                ->merge($usuario->servidores->flatMap->vinculosAtivos->pluck('escola'))
                ->merge($usuario->servidores->flatMap->professores->pluck('escola'))
                ->filter()->unique('id')->sortBy('nome')->values();
            $cargos = $usuario->servidores->flatMap->funcoesAtivas->pluck('nome')->filter()->unique()->sort()->values();

            return [
                'evento_calendario_id' => $evento->getKey(),
                'user_id' => $usuario->getKey(),
                'escola_id' => $escolas->count() === 1 ? $escolas->first()->getKey() : null,
                'escola_ids' => json_encode(
                    $escolas->pluck('id')->map(static fn ($id): int => (int) $id)->values()->all(),
                    JSON_THROW_ON_ERROR,
                ),
                'nome' => $usuario->name,
                'email' => $usuario->email,
                'escola_nome' => $escolas->pluck('nome')->join(', ') ?: null,
                'cargo_nome' => $cargos->join(', ') ?: null,
                'turno' => $turnos ?: null,
                'created_at' => $agora,
                'updated_at' => $agora,
            ];
        })->values();

        foreach ($linhasParticipantes->chunk(500) as $lote) {
            DB::table('evento_calendario_participantes_snapshot')->insert($lote->all());
        }

        $evento->load(['escolasAgendadas.series:id', 'escolasAgendadas.turmas:id']);
        $transportes = $evento->escolasAgendadas->where('precisa_transporte', true)->values();
        $excluidos = collect($alunosExcluidos)->filter(fn ($id): bool => is_numeric($id))->map(fn ($id): int => (int) $id)->unique()->all();
        $linhasAlunos = collect();

        if ($transportes->isNotEmpty()) {
            $agendamentosPorEscola = $transportes->keyBy('escola_id');
            $alunos = $this->queryAlunos($evento, $excluidos)
                ->get([
                    'alunos.id', 'alunos.nome', 'alunos.cgm', 'turmas.id as turma_id',
                    'turmas.nome as turma_nome', 'turmas.turno', 'turmas.id_escola',
                    'turmas.id_serie', 'escolas.nome as escola_nome', 'series.nome as serie_nome',
                ]);

            $linhasAlunos = $alunos->map(function ($aluno) use ($evento, $agendamentosPorEscola, $agora): array {
                return [
                    'evento_calendario_id' => $evento->getKey(),
                    'evento_calendario_escola_id' => $agendamentosPorEscola->get($aluno->id_escola)->getKey(),
                    'aluno_id' => $aluno->id,
                    'escola_id' => $aluno->id_escola,
                    'serie_id' => $aluno->id_serie,
                    'turma_id' => $aluno->turma_id,
                    'aluno_nome' => $aluno->nome,
                    'cgm' => $aluno->cgm,
                    'escola_nome' => $aluno->escola_nome,
                    'serie_nome' => $aluno->serie_nome,
                    'turma_nome' => $aluno->turma_nome,
                    'turno' => $aluno->turno,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ];
            });

            foreach ($linhasAlunos->chunk(500) as $lote) {
                DB::table('evento_calendario_alunos_snapshot')->insert($lote->all());
            }
        }

        $evento->forceFill([
            'participantes_snapshot_em' => $agora,
            'alunos_snapshot_em' => $agora,
        ])->saveQuietly();
    }

    /** @return list<int> */
    public function excecoesParaFormulario(EventoCalendario $evento): array
    {
        if (! $evento->alunos_snapshot_em) {
            return [];
        }

        $incluidos = $evento->alunosSnapshot()->whereNotNull('aluno_id')->pluck('aluno_id')->map(fn ($id): int => (int) $id);

        return $this->queryAlunos($evento)
            ->pluck('alunos.id')
            ->map(fn ($id): int => (int) $id)
            ->diff($incluidos)
            ->values()
            ->all();
    }

    /** @param list<int> $excluidos */
    private function queryAlunos(EventoCalendario $evento, array $excluidos = []): Builder
    {
        $evento->loadMissing(['escolasAgendadas.series:id', 'escolasAgendadas.turmas:id']);
        $transportes = $evento->escolasAgendadas->where('precisa_transporte', true)->values();
        $query = Aluno::query()
            ->join('turmas', 'turmas.id', '=', 'alunos.id_turma')
            ->join('escolas', 'escolas.id', '=', 'turmas.id_escola')
            ->leftJoin('series', 'series.id', '=', 'turmas.id_serie')
            ->where('alunos.tipo_vinculo', Aluno::TIPO_VINCULO_PRINCIPAL)
            ->where('alunos.status', Aluno::STATUS_MATRICULADO)
            ->when($excluidos !== [], fn (Builder $q): Builder => $q->whereNotIn('alunos.id', $excluidos));

        if ($transportes->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $selecao) use ($transportes): void {
            foreach ($transportes as $agendamento) {
                $selecao->orWhere(function (Builder $escola) use ($agendamento): void {
                    $escola->where('turmas.id_escola', $agendamento->escola_id);

                    if ($agendamento->escopo_transporte === EventoCalendarioTransporteEscopo::SERIES) {
                        $escola->whereIn('turmas.id_serie', $agendamento->series->modelKeys());
                    } elseif ($agendamento->escopo_transporte === EventoCalendarioTransporteEscopo::TURMAS) {
                        $escola->whereIn('turmas.id', $agendamento->turmas->modelKeys());
                    }
                });
            }
        });
    }
}
