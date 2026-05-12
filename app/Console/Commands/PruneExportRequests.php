<?php

namespace App\Console\Commands;

use App\Models\ExportRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneExportRequests extends Command
{
    protected $signature = 'exports:prune {--days= : Quantidade de dias para manter arquivos prontos}';

    protected $description = 'Remove arquivos de exportacao expirados e marca registros antigos como expirados';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('exports.expiration_days', 7));
        $cutoff = now()->subDays(max(1, $days));
        $removed = 0;

        ExportRequest::query()
            ->where(function ($query) use ($cutoff): void {
                $query
                    ->where('expires_at', '<=', now())
                    ->orWhere(function ($old) use ($cutoff): void {
                        $old->whereIn('status', [
                            ExportRequest::STATUS_FAILED,
                            ExportRequest::STATUS_CANCELLED,
                            ExportRequest::STATUS_EXPIRED,
                        ])->where('updated_at', '<=', $cutoff);
                    });
            })
            ->orderBy('updated_at')
            ->chunkById(100, function ($exports) use (&$removed): void {
                foreach ($exports as $export) {
                    if ($export->file_disk && $export->file_path && Storage::disk($export->file_disk)->exists($export->file_path)) {
                        Storage::disk($export->file_disk)->delete($export->file_path);
                        $removed++;
                    }

                    $export->forceFill([
                        'status' => ExportRequest::STATUS_EXPIRED,
                        'status_message' => 'Arquivo expirado e removido.',
                        'file_path' => null,
                        'file_disk' => null,
                    ])->save();
                }
            });

        $this->info("Arquivos removidos: {$removed}");

        return Command::SUCCESS;
    }
}
