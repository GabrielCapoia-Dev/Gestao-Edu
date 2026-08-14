<?php

namespace App\Services\Exports;

use App\Models\ExportRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ExportSessionService
{
    private const LOGIN_AT_KEY = 'auth.login_at';

    /** @return array{session_hash: string|null, session_expires_at: Carbon, expires_at: Carbon} */
    public function ownershipPayload(?Request $request = null): array
    {
        $expiresAt = $this->sessionExpiresAt($request);

        return [
            'session_hash' => $this->currentSessionHash($request),
            'session_expires_at' => $expiresAt,
            'expires_at' => $expiresAt,
        ];
    }

    public function attachOwnership(array $payload, ?Request $request = null): array
    {
        return array_merge($payload, $this->ownershipPayload($request));
    }

    public function currentSessionHash(?Request $request = null): ?string
    {
        $request ??= $this->currentRequest();

        if (! $request?->hasSession()) {
            return null;
        }

        $sessionId = $request->session()->getId();

        return filled($sessionId) ? $this->hashSessionId($sessionId) : null;
    }

    public function hashSessionId(string $sessionId): string
    {
        return hash_hmac('sha256', $sessionId, (string) config('app.key'));
    }

    public function sessionExpiresAt(?Request $request = null): Carbon
    {
        $request ??= $this->currentRequest();
        $idleExpiration = now()->addMinutes(max(1, (int) config('session.lifetime', 120)));

        if (! $request?->hasSession()) {
            return now()->addMinutes(max(1, (int) config('exports.unbound_lifetime_minutes', 15)));
        }

        $absoluteMinutes = (int) config('session.absolute_lifetime_minutes', 0);

        if ($absoluteMinutes < 1) {
            return $idleExpiration;
        }

        $loginAt = $request->session()->get(self::LOGIN_AT_KEY);
        $loginTimestamp = is_numeric($loginAt) ? (int) $loginAt : now()->timestamp;
        $absoluteExpiration = Carbon::createFromTimestamp($loginTimestamp)->addMinutes($absoluteMinutes);

        return $absoluteExpiration->lessThan($idleExpiration) ? $absoluteExpiration : $idleExpiration;
    }

    public function touchCurrentSession(Request $request, ?User $user): void
    {
        if (! $user) {
            return;
        }

        $sessionHash = $this->currentSessionHash($request);

        if (! $sessionHash) {
            return;
        }

        $cacheKey = "exports:session-touch:{$sessionHash}";

        try {
            if (Cache::has($cacheKey)) {
                return;
            }
        } catch (Throwable) {
            // A renovacao continua sem cache.
        }

        $expiresAt = $this->sessionExpiresAt($request);

        ExportRequest::query()
            ->where('user_id', $user->getKey())
            ->where('session_hash', $sessionHash)
            ->whereNull('session_ended_at')
            ->update([
                'session_expires_at' => $expiresAt,
                'expires_at' => $expiresAt,
            ]);

        try {
            Cache::put($cacheKey, true, now()->addSeconds(20));
        } catch (Throwable) {
            // A renovacao ja foi persistida no banco.
        }
    }

    public function endCurrentSession(Request $request, ?User $user): int
    {
        $sessionHash = $this->currentSessionHash($request);

        if (! $sessionHash) {
            return 0;
        }

        return $this->endSession($sessionHash, $user?->getKey());
    }

    public function endSession(string $sessionHash, int|string|null $userId = null): int
    {
        $query = ExportRequest::query()
            ->where('session_hash', $sessionHash)
            ->whereNull('session_ended_at')
            ->when(filled($userId), fn ($builder) => $builder->where('user_id', $userId));

        $ids = $query->pluck('id');
        $processed = 0;

        foreach ($ids as $id) {
            $exportRequest = ExportRequest::query()->find($id);

            if (! $exportRequest) {
                continue;
            }

            $exportRequest->forceFill([
                'session_ended_at' => now(),
                'session_expires_at' => now(),
                'expires_at' => now(),
            ])->save();

            $exportRequest->refresh();
            $this->expireForEndedSession($exportRequest);
            $processed++;
        }

        return $processed;
    }

    /** @return array{expired:int,files_deleted:int} */
    public function expireInactiveSessions(): array
    {
        $expired = 0;
        $filesDeleted = 0;

        ExportRequest::query()
            ->whereNull('session_ended_at')
            ->whereNotNull('session_expires_at')
            ->where('session_expires_at', '<=', now())
            ->orderBy('session_expires_at')
            ->chunkById(100, function ($exports) use (&$expired, &$filesDeleted): void {
                foreach ($exports as $exportRequest) {
                    $exportRequest->forceFill([
                        'session_ended_at' => now(),
                        'expires_at' => now(),
                    ])->save();

                    $exportRequest->refresh();
                    $filesDeleted += $this->expireForEndedSession($exportRequest) ? 1 : 0;
                    $expired++;
                }
            });

        return compact('expired', 'filesDeleted');
    }

    public function belongsToCurrentSession(ExportRequest $exportRequest, ?Request $request = null): bool
    {
        $sessionHash = $this->currentSessionHash($request);

        return filled($sessionHash)
            && hash_equals((string) $exportRequest->session_hash, $sessionHash)
            && $this->isActive($exportRequest);
    }

    public function isActive(ExportRequest $exportRequest): bool
    {
        return blank($exportRequest->session_ended_at)
            && filled($exportRequest->session_expires_at)
            && $exportRequest->session_expires_at->isFuture();
    }

    public function discardGeneratedFile(ExportRequest $exportRequest, ExportFileResult $result): void
    {
        try {
            Storage::disk($result->disk)->delete($result->path);
        } catch (Throwable $exception) {
            Log::warning('Nao foi possivel remover arquivo gerado para uma sessao encerrada.', [
                'export_request_id' => $exportRequest->getKey(),
                'disk' => $result->disk,
                'path' => $result->path,
                'exception' => $exception,
            ]);
        }

        $this->markExpired($exportRequest, 'Sessão encerrada. O arquivo gerado foi removido.');
    }

    public function expireForEndedSession(ExportRequest $exportRequest): bool
    {
        $fileDeleted = $this->deleteStoredFile($exportRequest);

        if (
            $exportRequest->status === ExportRequest::STATUS_RUNNING
            && $exportRequest->format === 'processo'
        ) {
            return $fileDeleted;
        }

        $this->markExpired($exportRequest, 'Sessão encerrada. Arquivo e processo removidos da central.');

        return $fileDeleted;
    }

    public function markExpired(ExportRequest $exportRequest, string $message): void
    {
        $exportRequest->forceFill([
            'status' => ExportRequest::STATUS_EXPIRED,
            'status_message' => $message,
            'file_disk' => null,
            'file_path' => null,
            'file_name' => null,
            'mime' => null,
            'size_bytes' => null,
            'checksum' => null,
            'finished_at' => $exportRequest->finished_at ?? now(),
            'expires_at' => now(),
            'session_ended_at' => $exportRequest->session_ended_at ?? now(),
        ])->save();
    }

    private function deleteStoredFile(ExportRequest $exportRequest): bool
    {
        if (! $exportRequest->file_disk || ! $exportRequest->file_path) {
            return false;
        }

        try {
            $disk = Storage::disk($exportRequest->file_disk);

            if (! $disk->exists($exportRequest->file_path)) {
                return false;
            }

            return $disk->delete($exportRequest->file_path);
        } catch (Throwable $exception) {
            Log::warning('Nao foi possivel remover arquivo de exportacao da sessao encerrada.', [
                'export_request_id' => $exportRequest->getKey(),
                'disk' => $exportRequest->file_disk,
                'path' => $exportRequest->file_path,
                'exception' => $exception,
            ]);

            return false;
        }
    }

    private function currentRequest(): ?Request
    {
        return app()->bound('request') ? request() : null;
    }
}
