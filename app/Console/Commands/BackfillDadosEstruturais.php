<?php

namespace App\Console\Commands;

use App\Services\PedidoEstruturalBackfillService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BackfillDadosEstruturais extends Command
{
    protected $signature = 'dados:backfill-estrutural
                            {--apply : Aplica as correções; sem esta opção o comando executa em dry-run}
                            {--report= : Caminho relativo para salvar o relatório JSON no disco local}
                            {--batch=500 : Quantidade de registros processados por lote}';

    protected $description = 'Preserva snapshots e reconcilia referências históricas antes das constraints estruturais.';

    public function handle(PedidoEstruturalBackfillService $service): int
    {
        $apply = (bool) $this->option('apply');
        $batchSize = filter_var($this->option('batch'), FILTER_VALIDATE_INT);

        if (! $batchSize || $batchSize < 1 || $batchSize > 5000) {
            $this->error('O tamanho do lote deve ser um número entre 1 e 5000.');

            return self::FAILURE;
        }

        $this->warn($apply
            ? 'Modo apply: as correções serão gravadas em lotes.'
            : 'Modo dry-run: nenhuma alteração será gravada. Use --apply para aplicar.');

        try {
            $report = $service->run($apply, $batchSize);
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Conjunto', 'Lidos', 'Com mudanças', 'Atualizados', 'Órfãos de usuário', 'Escolas pendentes'],
            [
                $this->summaryRow('pedidos', $report['pedidos']),
                $this->summaryRow('pedido_historicos', $report['pedido_historicos']),
                $this->summaryRow('pedido_arquivos', $report['pedido_arquivos']),
                $this->summaryRow('export_requests', $report['export_requests']),
            ],
        );

        $hardConstraintsReady = (bool) $report['readiness']['hard_constraints_ready'];
        $outputMethod = $hardConstraintsReady ? 'info' : 'warn';
        $this->{$outputMethod}($hardConstraintsReady
            ? 'Banco pronto para aplicar as constraints estruturais.'
            : ($apply
                ? 'O apply terminou, mas ainda há pendências; não aplique as constraints estruturais.'
                : 'O dry-run encontrou alterações ou pendências; revise o relatório antes do apply.'));

        if (filled($this->option('report'))) {
            $path = trim(str_replace('\\', '/', (string) $this->option('report')), '/');

            if ($path === '' || str_contains($path, '..') || ! str_ends_with($path, '.json')) {
                $this->error('Informe em --report um caminho relativo seguro terminado em .json.');

                return self::FAILURE;
            }

            Storage::disk('local')->put(
                $path,
                json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            );
            $this->line('Relatório salvo em: '.Storage::disk('local')->path($path));
        }

        return $apply && ! $hardConstraintsReady
            ? self::FAILURE
            : self::SUCCESS;
    }

    /**
     * @param array<string, int> $stats
     * @return array<int, int|string>
     */
    private function summaryRow(string $name, array $stats): array
    {
        return [
            $name,
            $stats['scanned'],
            $stats['rows_with_changes'],
            $stats['rows_updated'],
            $stats['orphan_actor_ids_found'],
            $stats['schools_unresolved'] ?? '-',
        ];
    }
}
