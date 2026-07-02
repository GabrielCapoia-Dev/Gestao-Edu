<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ExportRequest extends Model
{
    use HasFactory;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_FINISHED = 'finished';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'type',
        'format',
        'label',
        'filters',
        'metadata',
        'fingerprint',
        'status',
        'status_message',
        'progress_current',
        'progress_total',
        'file_disk',
        'file_path',
        'file_name',
        'mime',
        'size_bytes',
        'checksum',
        'error_message',
        'started_at',
        'finished_at',
        'expires_at',
        'cancel_requested_at',
    ];

    protected $appends = [
        'progress_percentage',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'metadata' => 'array',
            'progress_current' => 'integer',
            'progress_total' => 'integer',
            'size_bytes' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'expires_at' => 'datetime',
            'cancel_requested_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ExportRequest $exportRequest): void {
            if (! $exportRequest->getKey()) {
                $exportRequest->{$exportRequest->getKeyName()} = (string) Str::uuid();
            }
        });

        static::saved(fn (ExportRequest $exportRequest): mixed => static::forgetActiveExportsCache($exportRequest));
        static::deleted(fn (ExportRequest $exportRequest): mixed => static::forgetActiveExportsCache($exportRequest));
    }

    private static function forgetActiveExportsCache(ExportRequest $exportRequest): void
    {
        Cache::forget('exports:active:any');

        if (filled($exportRequest->user_id)) {
            Cache::forget('user:'.((int) $exportRequest->user_id).':active_exports');
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getProgressPercentageAttribute(): int
    {
        $total = (int) ($this->progress_total ?: 0);

        if ($total <= 0) {
            return match ($this->status) {
                self::STATUS_FINISHED => 100,
                self::STATUS_RUNNING => 50,
                default => 0,
            };
        }

        return min(100, max(0, (int) floor(((int) $this->progress_current / $total) * 100)));
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_RUNNING], true);
    }

    public function isFinished(): bool
    {
        return $this->status === self::STATUS_FINISHED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function markRunning(?string $message = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_RUNNING,
            'status_message' => $message ?? 'Processando arquivo.',
            'started_at' => $this->started_at ?? now(),
        ])->save();
    }

    public function updateProgress(int $current, ?int $total = null, ?string $message = null): void
    {
        $this->forceFill(array_filter([
            'progress_current' => max(0, $current),
            'progress_total' => $total,
            'status_message' => $message,
        ], static fn (mixed $value): bool => $value !== null))->save();
    }

    /**
     * @param array{disk:string,path:string,file_name:string,mime:string|null,size_bytes:int|null,checksum:string|null} $file
     */
    public function markFinished(array $file): void
    {
        $this->forceFill([
            'status' => self::STATUS_FINISHED,
            'status_message' => 'Arquivo pronto para download.',
            'progress_current' => 100,
            'progress_total' => 100,
            'file_disk' => $file['disk'],
            'file_path' => $file['path'],
            'file_name' => $file['file_name'],
            'mime' => $file['mime'],
            'size_bytes' => $file['size_bytes'],
            'checksum' => $file['checksum'],
            'error_message' => null,
            'finished_at' => now(),
            'expires_at' => $this->expires_at ?? now()->addDays((int) config('exports.expiration_days', 7)),
        ])->save();
    }

    public function markProcessFinished(?string $message = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_FINISHED,
            'status_message' => $message ?? 'Processamento concluido.',
            'progress_current' => 100,
            'progress_total' => 100,
            'error_message' => null,
            'finished_at' => now(),
            'expires_at' => $this->expires_at ?? now()->addDays((int) config('exports.expiration_days', 7)),
        ])->save();
    }

    public function markFailed(string $message): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'status_message' => $this->format === 'processo' ? 'Falha no processamento.' : 'Falha ao gerar arquivo.',
            'error_message' => Str::limit($message, 4000, ''),
            'finished_at' => now(),
        ])->save();
    }

    public function requestCancellation(): void
    {
        if (! $this->isActive()) {
            return;
        }

        $this->markCancelled('Cancelado pelo usuario.');
    }

    public function markCancelled(?string $message = null, ?string $errorMessage = null): void
    {
        $payload = [
            'status' => self::STATUS_CANCELLED,
            'cancel_requested_at' => now(),
            'status_message' => $message ?? 'Cancelado.',
            'finished_at' => now(),
        ];

        if ($errorMessage !== null) {
            $payload['error_message'] = Str::limit($errorMessage, 4000, '');
        }

        $this->forceFill($payload)->save();
    }
}
