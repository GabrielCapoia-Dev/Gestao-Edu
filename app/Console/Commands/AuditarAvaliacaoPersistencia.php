<?php

namespace App\Console\Commands;

use App\Models\AvaliacaoSnapshotEvento;
use App\Models\AvaliacaoTurmaCiclo;
use App\Services\Avaliacoes\AvaliacaoSnapshotService;
use Illuminate\Console\Command;
use Throwable;

class AuditarAvaliacaoPersistencia extends Command
{
    protected $signature = 'avaliacoes:auditar-persistencia
        {--avaliacao= : Restringe a uma avaliação}';

    protected $description = 'Verifica ciclos operacionais e integridade dos snapshots concluídos';

    public function handle(AvaliacaoSnapshotService $snapshots): int
    {
        $avaliacaoId = (int) ($this->option('avaliacao') ?: 0);
        $erros = [];
        $ciclos = AvaliacaoTurmaCiclo::query()
            ->when($avaliacaoId > 0, fn ($query) => $query->where('avaliacao_id', $avaliacaoId))
            ->withCount(['respostas', 'informacoes'])
            ->with('tokenEscrita')
            ->get();

        foreach ($ciclos as $ciclo) {
            if ($ciclo->aceitaEscrita() && ! $ciclo->tokenEscrita) {
                $erros[] = "Ciclo {$ciclo->id}: aberto sem token de escrita.";
            }

            if ($ciclo->status === AvaliacaoTurmaCiclo::STATUS_CONCLUIDA) {
                if ($ciclo->tokenEscrita || $ciclo->respostas_count > 0 || $ciclo->informacoes_count > 0) {
                    $erros[] = "Ciclo {$ciclo->id}: concluído ainda possui estado operacional.";
                }

                $evento = AvaliacaoSnapshotEvento::query()
                    ->whereKey($ciclo->snapshot_evento_atual_id)
                    ->whereNotNull('publicado_em')
                    ->with('snapshots')
                    ->first();

                if (! $evento) {
                    $erros[] = "Ciclo {$ciclo->id}: concluído sem evento final publicado.";
                } else {
                    try {
                        $snapshots->validarEvento($evento);
                    } catch (Throwable $exception) {
                        $erros[] = "Ciclo {$ciclo->id}: {$exception->getMessage()}";
                    }
                }

                continue;
            }

            // Um ciclo aberto ainda não inicializado é válido: significa que a
            // turma ainda não foi acessada desde o corte para o modelo relacional.
            if ($ciclo->operacional_inicializado_em === null) {
                continue;
            }

            if ((int) $ciclo->legado_documentos_migrados > 0 && ! $ciclo->legado_migracao_hash) {
                $erros[] = "Ciclo {$ciclo->id}: migração lazy registrada sem hash do legado.";
            }
        }

        if ($erros !== []) {
            foreach ($erros as $erro) {
                $this->error($erro);
            }
            $this->error(count($erros).' inconsistência(s) encontrada(s).');

            return self::FAILURE;
        }

        $naoInicializados = $ciclos
            ->filter(fn (AvaliacaoTurmaCiclo $ciclo): bool => $ciclo->aceitaEscrita() && $ciclo->operacional_inicializado_em === null)
            ->count();

        $this->info(
            "Auditoria concluída: {$ciclos->count()} ciclo(s) válido(s); {$naoInicializados} ainda aguardando primeiro acesso.",
        );

        return self::SUCCESS;
    }
}
