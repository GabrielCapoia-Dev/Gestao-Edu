<?php

namespace App\Console\Commands;

use App\Models\Pessoa;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class AuditarEmailsPessoas extends Command
{
    protected $signature = 'pessoas:auditar-emails
                            {--report= : Caminho relativo para salvar o relatório JSON no disco local}';

    protected $description = 'Audita conflitos de e-mail normalizado sem mesclar ou alterar Pessoas.';

    public function handle(): int
    {
        if (! Schema::hasColumn('servidores', 'email_normalizado')) {
            $this->error('Execute a migration de e-mail normalizado antes desta auditoria.');

            return self::FAILURE;
        }

        $groups = Pessoa::withTrashed()
            ->select('email_normalizado')
            ->whereNotNull('email_normalizado')
            ->groupBy('email_normalizado')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('email_normalizado')
            ->get()
            ->map(function (Pessoa $group): array {
                $ids = Pessoa::withTrashed()
                    ->where('email_normalizado', $group->email_normalizado)
                    ->orderBy('id')
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->all();

                return [
                    'email_normalizado' => $group->email_normalizado,
                    'pessoa_ids' => $ids,
                    'quantidade' => count($ids),
                ];
            })
            ->values();

        $this->table(
            ['E-mail normalizado', 'Pessoas', 'Quantidade'],
            $groups->map(fn (array $group): array => [
                $group['email_normalizado'],
                implode(', ', $group['pessoa_ids']),
                $group['quantidade'],
            ])->all(),
        );

        $reportPath = $this->safeReportPath($this->option('report'));

        if ($reportPath === false) {
            return self::FAILURE;
        }

        if (is_string($reportPath)) {
            Storage::disk('local')->put($reportPath, json_encode([
                'generated_at' => now()->toIso8601String(),
                'duplicate_groups' => $groups->count(),
                'people_in_conflict' => $groups->sum('quantidade'),
                'groups' => $groups->all(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            $this->line('Relatório salvo em: '.Storage::disk('local')->path($reportPath));
        }

        if ($groups->isNotEmpty()) {
            $this->warn("Foram encontrados {$groups->count()} grupo(s) de e-mail duplicado. A restrição UNIQUE não está pronta.");

            return self::FAILURE;
        }

        $this->info('Nenhum e-mail duplicado encontrado. A fase de unicidade definitiva está pronta para ser aplicada.');

        return self::SUCCESS;
    }

    private function safeReportPath(mixed $option): string|false|null
    {
        if (blank($option)) {
            return null;
        }

        $path = trim(str_replace('\\', '/', (string) $option), '/');

        if ($path === '' || str_contains($path, '..') || ! str_ends_with($path, '.json')) {
            $this->error('Informe em --report um caminho relativo seguro terminado em .json.');

            return false;
        }

        return $path;
    }
}
