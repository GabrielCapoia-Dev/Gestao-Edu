<?php

namespace App\Services\Exports;

use App\Jobs\ProcessExportRequestJob;
use App\Models\ExportRequest;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExportRequestService
{
    public function __construct(
        private readonly ExportManager $manager,
        private readonly ExportSessionService $sessions,
    ) {}

    /**
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $metadata
     */
    public function queue(
        User $user,
        string $type,
        string $format,
        array $filters = [],
        ?string $label = null,
        array $metadata = [],
    ): ExportRequest {
        if (! $this->manager->hasHandler($type)) {
            throw new RuntimeException("Exportação [{$type}] ainda não possui processador assincrono.");
        }

        $filters = $this->normalizePayload($filters);
        $metadata = $this->normalizePayload($metadata);
        $ownership = $this->sessions->ownershipPayload();
        $fingerprint = $this->fingerprint($user, $type, $format, $filters, $ownership['session_hash']);

        return DB::transaction(function () use ($user, $type, $format, $filters, $metadata, $label, $fingerprint, $ownership): ExportRequest {
            $active = ExportRequest::query()
                ->where('user_id', $user->id)
                ->when(
                    $ownership['session_hash'],
                    fn ($query, string $sessionHash) => $query->where('session_hash', $sessionHash),
                    fn ($query) => $query->whereNull('session_hash'),
                )
                ->where('fingerprint', $fingerprint)
                ->whereIn('status', [ExportRequest::STATUS_QUEUED, ExportRequest::STATUS_RUNNING])
                ->latest()
                ->lockForUpdate()
                ->first();

            if ($active) {
                return $active;
            }

            $activeCount = ExportRequest::query()
                ->where('user_id', $user->id)
                ->when(
                    $ownership['session_hash'],
                    fn ($query, string $sessionHash) => $query->where('session_hash', $sessionHash),
                    fn ($query) => $query->whereNull('session_hash'),
                )
                ->whereIn('status', [ExportRequest::STATUS_QUEUED, ExportRequest::STATUS_RUNNING])
                ->lockForUpdate()
                ->count();

            $maxActive = (int) config('exports.max_active_per_user', 3);

            if ($maxActive > 0 && $activeCount >= $maxActive) {
                throw new RuntimeException("Você já possui {$activeCount} exportacoes em andamento. Aguarde uma delas terminar.");
            }

            $exportRequest = ExportRequest::query()->create([
                'user_id' => $user->id,
                'user_id_legado' => $user->id,
                'user_nome_snapshot' => $user->name,
                'user_email_snapshot' => $user->email,
                ...$ownership,
                'type' => $type,
                'format' => $format,
                'label' => $label,
                'filters' => $filters,
                'metadata' => $metadata,
                'fingerprint' => $fingerprint,
                'status' => ExportRequest::STATUS_QUEUED,
                'status_message' => 'Aguardando processamento.',
                'progress_current' => 0,
                'progress_total' => 100,
            ]);

            ProcessExportRequestJob::dispatch($exportRequest->getKey())->afterCommit();

            return $exportRequest;
        });
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        $payload = Arr::where($payload, static fn (mixed $value): bool => $value !== null && $value !== '');
        ksort($payload);

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->normalizePayload($value);
            }
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function fingerprint(User $user, string $type, string $format, array $filters, ?string $sessionHash): string
    {
        return hash('sha256', implode('|', [
            (string) $user->id,
            (string) $sessionHash,
            $type,
            $format,
            json_encode($filters, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]));
    }
}
