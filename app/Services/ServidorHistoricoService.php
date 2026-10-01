<?php

namespace App\Services;

use App\Models\PessoaMatricula;
use App\Models\Professor;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\ServidorMovimentacao;
use App\Models\TurmaComponenteProfessor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ServidorHistoricoService
{
    /** Snapshot compacto dos vínculos funcionais; rótulos ficam persistidos para sobreviver a renomes. */
    public function capturar(Servidor $servidor): array
    {
        $servidor->loadMissing(['escola', 'setor', 'lotacao.escola']);
        $funcoes = $servidor->servidorFuncoes()->with(['funcaoAdministrativa', 'escola', 'setor'])->orderBy('id')->get();
        $professores = $servidor->professores()->with('escola')->get();
        $professorIds = $professores->pluck('id');
        $matriculas = Schema::hasTable('professor_matriculas')
            ? PessoaMatricula::withTrashed()->where('servidor_id', $servidor->getKey())->orderBy('id')->get()
            : collect();

        $pedagogico = TurmaComponenteProfessor::query()
            ->with(['turma.serie', 'componente'])
            ->whereIn('professor_id', $professorIds)
            ->orderBy('id')
            ->get()
            ->map(fn (TurmaComponenteProfessor $v): array => [
                'professor' => $professores->firstWhere('id', $v->professor_id)?->nome,
                'escola' => $professores->firstWhere('id', $v->professor_id)?->escola?->nome,
                'serie' => $v->turma?->serie?->nome,
                'turma' => $v->turma?->nome,
                'componente' => $v->componente?->nome,
                'ativo' => (bool) $v->tem_professor,
            ])->values()->all();

        return [
            'cargo' => $this->ordenar($funcoes->map(fn (ServidorFuncaoAdministrativa $v): array => [
                'cargo' => $v->funcaoAdministrativa?->nome,
                'codigo' => $v->funcaoAdministrativa?->codigo,
                'escola' => $v->escola?->nome,
                'setor' => $v->setor?->nome,
                'matricula' => $v->matricula,
                'status' => $v->status,
                'inicio' => $v->data_inicio?->toDateString(),
                'fim' => $v->data_fim?->toDateString(),
            ])->values()->merge($professores->map(fn (Professor $p): array => [
                'cargo' => 'Professor',
                'escola' => $p->escola?->nome,
                'matricula' => $p->matricula,
                'turno' => $p->turnoLabel(),
                'status' => $p->ativo ? 'ativo' : 'inativo',
                'inicio' => $p->created_at?->toDateString(),
                'fim' => $p->desativado_em?->toDateString(),
            ])->values())->all()),
            'lotacao' => [
                'escola' => $servidor->escola ? ['id' => $servidor->escola->id, 'nome' => $servidor->escola->nome] : null,
                'setor' => $servidor->setor ? ['id' => $servidor->setor->id, 'nome' => $servidor->setor->nome] : null,
                'lotacao' => $servidor->lotacao ? [
                    'id' => $servidor->lotacao->id,
                    'nome' => $servidor->lotacao->nome,
                    'escola' => $servidor->lotacao->escola?->nome,
                ] : null,
            ],
            'matriculas' => $this->ordenar($matriculas->map(fn (PessoaMatricula $m): array => [
                'matricula' => $m->matricula,
                'turno' => $m->turnoLabel(),
                'jornada' => (bool) $m->jornada,
                'arquivada' => $m->trashed(),
            ])->values()->all()),
            'pedagogico' => $this->ordenar($pedagogico),
        ];
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function ordenar(array $rows): array
    {
        usort($rows, fn (array $a, array $b): int => strcmp(
            json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
            json_encode($b, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
        ));

        return $rows;
    }

    public function registrarSeAlterou(Servidor $servidor, array $antes, ?int $usuarioId = null): ?ServidorMovimentacao
    {
        $depois = $this->capturar($servidor->fresh());
        $alteracoes = [];

        foreach ($depois as $campo => $valor) {
            if ($antes[$campo] !== $valor) {
                $alteracoes[$campo] = ['antes' => $antes[$campo], 'depois' => $valor];
            }
        }

        if ($alteracoes === []) {
            return null;
        }

        return ServidorMovimentacao::query()->create([
            'servidor_id' => $servidor->getKey(),
            'usuario_id' => $usuarioId,
            'tipo' => 'alteracao_funcional',
            'alteracoes' => $alteracoes,
            'ocorrido_em' => now(),
        ]);
    }

    public function registrarAtualizacao(Servidor $servidor, callable $atualizar, ?int $usuarioId = null): Servidor
    {
        return DB::transaction(function () use ($servidor, $atualizar, $usuarioId): Servidor {
            $servidor = Servidor::query()->lockForUpdate()->findOrFail($servidor->getKey());
            $antes = $this->capturar($servidor);
            $resultado = $atualizar($servidor);
            $resultado = $resultado instanceof Servidor ? $resultado : $servidor->fresh();
            $this->registrarSeAlterou($resultado, $antes, $usuarioId);

            return $resultado;
        });
    }
}
