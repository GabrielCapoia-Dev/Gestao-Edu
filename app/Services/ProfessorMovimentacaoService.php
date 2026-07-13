<?php

namespace App\Services;

use App\Models\Aluno;
use App\Models\Avaliacao;
use App\Models\Escola;
use App\Models\Professor;
use App\Models\TurmaComponenteProfessor;
use App\Models\User;
use DomainException;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class ProfessorMovimentacaoService
{
    public function transferir(Professor $professor, Escola|int $escolaDestino, ?User $usuario = null): Professor
    {
        $escolaDestino = $escolaDestino instanceof Escola
            ? $escolaDestino
            : Escola::query()->findOrFail($escolaDestino);

        if (! $escolaDestino->ativo) {
            throw new DomainException('A escola de destino precisa estar ativa.');
        }

        $this->bloquearSeHouverPendencias($professor, 'transferido');

        DB::transaction(function () use ($professor, $escolaDestino): void {
            $this->removerVinculosAtuais($professor);

            $professor->forceFill([
                'id_escola' => $escolaDestino->id,
                'ativo' => true,
                'desativado_em' => null,
                'desativado_por_id' => null,
                'motivo_desativacao' => null,
            ])->save();
        });

        $this->sincronizarAcessoProfessor($professor);

        return $professor->fresh();
    }

    public function desativar(Professor $professor, ?User $usuario = null, ?string $motivo = null): Professor
    {
        $this->bloquearSeHouverPendencias($professor, 'desativado');

        DB::transaction(function () use ($professor, $usuario, $motivo): void {
            $this->removerVinculosAtuais($professor);

            $professor->forceFill([
                'ativo' => false,
                'desativado_em' => now(),
                'desativado_por_id' => $usuario?->id,
                'motivo_desativacao' => filled($motivo) ? trim((string) $motivo) : null,
            ])->save();
        });

        $this->sincronizarAcessoProfessor($professor);

        return $professor->fresh();
    }

    public function pendenciasAvaliativas(Professor $professor): array
    {
        $esperados = $this->contarPreenchimentosEsperados($professor);
        $pendentes = $this->contarPreenchimentosPendentes($professor);

        return [
            'preenchimentos_esperados' => $esperados,
            'preenchimentos_pendentes' => $pendentes,
        ];
    }

    private function bloquearSeHouverPendencias(Professor $professor, string $acao): void
    {
        $pendencias = $this->pendenciasAvaliativas($professor);
        $totalPendentes = (int) $pendencias['preenchimentos_pendentes'];

        if ($totalPendentes <= 0) {
            return;
        }

        throw new DomainException(sprintf(
            'O professor precisa terminar de preencher as avaliações pendentes antes de ser %s. Pendências: %d.',
            $acao,
            $totalPendentes,
        ));
    }

    private function removerVinculosAtuais(Professor $professor): void
    {
        TurmaComponenteProfessor::query()
            ->where('professor_id', $professor->id)
            ->update([
                'professor_id' => null,
                'tem_professor' => false,
                'updated_at' => now(),
            ]);
    }

    private function sincronizarAcessoProfessor(Professor $professor): void
    {
        $userId = (int) ($professor->user_id ?? 0);

        if ($userId <= 0) {
            return;
        }

        app(ProfessorEscolaVinculoService::class)->sincronizarPorUsuario($userId);
    }

    private function contarPreenchimentosEsperados(Professor $professor): int
    {
        return (int) DB::query()
            ->fromSub($this->preenchimentosEsperadosQuery($professor), 'esperados')
            ->count();
    }

    private function contarPreenchimentosPendentes(Professor $professor): int
    {
        return (int) DB::query()
            ->fromSub($this->preenchimentosEsperadosQuery($professor), 'esperados')
            ->leftJoin('avaliacao_resposta_fatos as ar', function ($join): void {
                $join->on('ar.avaliacao_id', '=', 'esperados.avaliacao_id')
                    ->on('ar.turma_id', '=', 'esperados.turma_id')
                    ->on('ar.pauta_id', '=', 'esperados.pauta_id')
                    ->on('ar.aluno_id', '=', 'esperados.aluno_id');
            })
            ->leftJoin('alternativas as alt', 'alt.id', '=', 'ar.alternativa_id')
            ->where(function (QueryBuilder $query): void {
                $query
                    ->whereNull('ar.id')
                    ->orWhereNull('ar.alternativa_id')
                    ->orWhereNull('alt.id')
                    ->orWhere('alt.status', false)
                    ->orWhere(function (QueryBuilder $observacoes): void {
                        $observacoes
                            ->where('alt.tem_observacao', true)
                            ->whereRaw("TRIM(COALESCE(ar.observacao, '')) = ''");
                    });
            })
            ->count();
    }

    private function preenchimentosEsperadosQuery(Professor $professor): QueryBuilder
    {
        return DB::table('turma_componente_professor as tcp')
            ->join('turmas as t', 't.id', '=', 'tcp.turma_id')
            ->join('avaliacao_turma as at', 'at.turma_id', '=', 't.id')
            ->join('avaliacoes as av', 'av.id', '=', 'at.avaliacao_id')
            ->join('avaliacao_pauta as ap', 'ap.avaliacao_id', '=', 'av.id')
            ->join('pautas as p', 'p.id', '=', 'ap.pauta_id')
            ->join('alunos as aln', 'aln.id_turma', '=', 't.id')
            ->where('tcp.professor_id', $professor->id)
            ->where('tcp.tem_professor', true)
            ->where('av.status', Avaliacao::STATUS_ATIVA)
            ->whereDate('av.data_inicio', '<=', now()->toDateString())
            ->whereDate('av.data_fim', '>=', now()->toDateString())
            ->where('p.status', true)
            ->whereIn('aln.status', [Aluno::STATUS_MATRICULADO, Aluno::STATUS_PENDENTE])
            ->where(function (QueryBuilder $alunos): void {
                $alunos
                    ->where('aln.status', '!=', Aluno::STATUS_PENDENTE)
                    ->orWhereNull('aln.pendencia_origem_aluno_id')
                    ->orWhere('aln.pendencia_origem_aluno_id', '<=', 0);
            })
            ->where(function (QueryBuilder $series): void {
                $series
                    ->whereNull('p.serie_id')
                    ->orWhereColumn('p.serie_id', 't.id_serie');
            })
            ->where(function (QueryBuilder $componentes): void {
                $componentes
                    ->whereNull('p.componente_curricular_id')
                    ->orWhereColumn('p.componente_curricular_id', 'tcp.componente_curricular_id');
            })
            ->distinct()
            ->select([
                'av.id as avaliacao_id',
                't.id as turma_id',
                'p.id as pauta_id',
                'aln.id as aluno_id',
            ]);
    }
}
