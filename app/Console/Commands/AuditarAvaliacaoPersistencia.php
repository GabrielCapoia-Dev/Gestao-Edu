<?php

namespace App\Console\Commands;

use App\Models\AvaliacaoAlunoDocumento;
use App\Models\AvaliacaoInformacaoOperacional;
use App\Models\AvaliacaoRespostaOperacional;
use App\Models\AvaliacaoSnapshotEvento;
use App\Models\AvaliacaoTurmaCiclo;
use App\Services\Avaliacoes\AvaliacaoSnapshotService;
use Illuminate\Console\Command;
use Throwable;

class AuditarAvaliacaoPersistencia extends Command
{
    protected $signature = 'avaliacoes:auditar-persistencia
        {--avaliacao= : Restringe a uma avaliação}
        {--paridade-legado : Compara documentos JSON com as linhas operacionais}';

    protected $description = 'Verifica estados canônicos, tokens, hashes e paridade da persistência de avaliações';

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
            }
        }

        if ($this->option('paridade-legado')) {
            $documentos = AvaliacaoAlunoDocumento::query()
                ->when($avaliacaoId > 0, fn ($query) => $query->where('avaliacao_id', $avaliacaoId))
                ->get();
            foreach ($documentos as $documento) {
                $relacional = AvaliacaoRespostaOperacional::query()
                    ->where('avaliacao_id', (int) $documento->avaliacao_id)
                    ->where('aluno_id', (int) $documento->aluno_id)
                    ->get()
                    ->mapWithKeys(fn ($item): array => [(string) $item->pauta_id => [
                        'alternativa_id' => $item->alternativa_id ? (int) $item->alternativa_id : null,
                        'observacao' => $this->texto($item->observacao),
                        'professor_id' => $item->professor_id ? (int) $item->professor_id : null,
                        'componente_curricular_id' => $item->componente_curricular_id ? (int) $item->componente_curricular_id : null,
                    ]])->all();
                $legado = collect($documento->pautasPayload())->map(fn (array $item): array => [
                    'alternativa_id' => ! empty($item['alternativa_id']) ? (int) $item['alternativa_id'] : null,
                    'observacao' => $this->texto($item['observacao'] ?? null),
                    'professor_id' => ! empty($item['professor_id']) ? (int) $item['professor_id'] : null,
                    'componente_curricular_id' => ! empty($item['componente_curricular_id']) ? (int) $item['componente_curricular_id'] : null,
                ])->all();
                ksort($relacional);
                ksort($legado);
                if ($relacional !== $legado) {
                    $erros[] = "Documento {$documento->id}: divergência nas respostas relacionais.";
                }

                $infosRelacionais = AvaliacaoInformacaoOperacional::query()
                    ->where('avaliacao_id', (int) $documento->avaliacao_id)
                    ->where('aluno_id', (int) $documento->aluno_id)
                    ->pluck('texto', 'componente_chave')
                    ->map(fn ($texto): ?string => $this->texto($texto))
                    ->all();
                $infosLegado = collect($documento->informacoesComplementaresPayload())
                    ->map(fn (array $item): ?string => $this->texto($item['texto'] ?? null))
                    ->all();
                ksort($infosRelacionais);
                ksort($infosLegado);
                if ($infosRelacionais !== $infosLegado) {
                    $erros[] = "Documento {$documento->id}: divergência nas informações complementares.";
                }
            }
        }

        if ($erros !== []) {
            foreach ($erros as $erro) {
                $this->error($erro);
            }
            $this->error(count($erros).' inconsistência(s) encontrada(s).');

            return self::FAILURE;
        }

        $this->info("Auditoria concluída: {$ciclos->count()} ciclo(s) válido(s).");

        return self::SUCCESS;
    }

    private function texto(mixed $valor): ?string
    {
        $valor = trim((string) ($valor ?? ''));

        return $valor !== '' ? $valor : null;
    }
}
