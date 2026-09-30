<?php

namespace App\Console\Commands;

use App\Models\ExportRequest;
use App\Services\Exports\ExportSessionService;
use App\Services\Exports\ExportTemporaryFiles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneExportRequests extends Command
{
    protected $signature = 'exports:prune {--days= : Quantidade de dias para manter arquivos prontos}';

    protected $description = 'Remove arquivos de exportacao expirados e marca registros antigos como expirados';

    public function handle(ExportSessionService $sessions, ExportTemporaryFiles $temporaryFiles): int
    {
        $sessionResult = $sessions->expireInactiveSessions();
        $temporaryRemoved = $temporaryFiles->pruneOrphans();
        $days = (int) ($this->option('days') ?: config('exports.expiration_days', 7));
        $cutoff = now()->subDays(max(1, $days));
        $removed = $sessionResult['files_deleted'];

        ExportRequest::query()
            ->whereNull('session_hash')
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

        $recordCutoff = now()->subHours(max(1, (int) config('exports.expired_record_retention_hours', 24)));
        $recordsRemoved = ExportRequest::query()
            ->where('status', ExportRequest::STATUS_EXPIRED)
            ->whereNotNull('session_ended_at')
            ->where('updated_at', '<=', $recordCutoff)
            ->delete();

        $this->info("Arquivos removidos: {$removed}");
        $this->info("Sessoes expiradas: {$sessionResult['expired']}");
        $this->info("Registros expirados removidos: {$recordsRemoved}");
        $this->info("Diretórios temporários removidos: {$temporaryRemoved}");

        return Command::SUCCESS;
    }
}
