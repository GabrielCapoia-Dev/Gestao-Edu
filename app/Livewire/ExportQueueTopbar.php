<?php

namespace App\Livewire;

use App\Models\ExportRequest;
use App\Models\User;
use App\Services\Exports\ExportSessionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;
use Livewire\Component;

class ExportQueueTopbar extends Component
{
    public bool $open = false;

    public function togglePanel(): void
    {
        $this->open = ! $this->open;

        if ($this->open) {
            $this->touchSession();
        }
    }

    public function closePanel(): void
    {
        $this->open = false;
    }

    public function pollQueue(): void
    {
        $this->dispatchPendingAutoDownloads();
    }

    public function cancel(string $exportRequestId): void
    {
        $exportRequest = $this->sessionQuery()->whereKey($exportRequestId)->firstOrFail();
        abort_unless(Auth::user()?->can('cancel', $exportRequest) ?? false, 403);

        $exportRequest->requestCancellation();
    }

    public function render()
    {
        $query = $this->sessionQuery();
        $activeCount = (clone $query)
            ->whereIn('status', [ExportRequest::STATUS_QUEUED, ExportRequest::STATUS_RUNNING])
            ->count();
        $readyCount = (clone $query)
            ->where('status', ExportRequest::STATUS_FINISHED)
            ->whereNotNull('file_path')
            ->count();
        $items = $this->open
            ? $query->latest()->get()
            : collect();

        return view('livewire.export-queue-topbar', [
            'items' => $items,
            'activeCount' => $activeCount,
            'readyCount' => $readyCount,
        ]);
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            ExportRequest::STATUS_QUEUED => 'Na fila',
            ExportRequest::STATUS_RUNNING => 'Processando',
            ExportRequest::STATUS_FINISHED => 'Pronto',
            ExportRequest::STATUS_FAILED => 'Falha',
            ExportRequest::STATUS_CANCELLED => 'Cancelado',
            ExportRequest::STATUS_EXPIRED => 'Expirado',
            default => $status,
        };
    }

    public function statusClass(string $status): string
    {
        return match ($status) {
            ExportRequest::STATUS_RUNNING => 'is-running',
            ExportRequest::STATUS_FINISHED => 'is-finished',
            ExportRequest::STATUS_FAILED => 'is-failed',
            ExportRequest::STATUS_CANCELLED, ExportRequest::STATUS_EXPIRED => 'is-muted',
            default => 'is-queued',
        };
    }

    public function formatSize(?int $size): string
    {
        return $size ? Number::fileSize($size) : '';
    }

    private function dispatchPendingAutoDownloads(): void
    {
        $items = $this->sessionQuery()
            ->where('status', ExportRequest::STATUS_FINISHED)
            ->whereNotNull('file_path')
            ->latest('finished_at')
            ->limit(5)
            ->get();

        foreach ($items as $item) {
            $metadata = is_array($item->metadata) ? $item->metadata : [];

            if (! (bool) ($metadata['auto_download'] ?? false)) {
                continue;
            }

            if (filled($metadata['auto_download_dispatched_at'] ?? null)) {
                continue;
            }

            $metadata['auto_download_dispatched_at'] = now()->toIso8601String();

            $item->forceFill(['metadata' => $metadata])->save();

            $this->dispatch(
                'export-auto-download',
                url: route('exports.download', $item),
                exportRequestId: (string) $item->getKey(),
            );
        }
    }

    private function sessionQuery(): Builder
    {
        /** @var User|null $user */
        $user = Auth::user();
        $sessionHash = app(ExportSessionService::class)->currentSessionHash();

        if (! $user || ! $sessionHash) {
            return ExportRequest::query()->whereRaw('1 = 0');
        }

        return ExportRequest::query()
            ->where('user_id', $user->getKey())
            ->where('session_hash', $sessionHash)
            ->whereNull('session_ended_at');
    }

    private function touchSession(): void
    {
        if (! request()->hasSession()) {
            return;
        }

        /** @var User|null $user */
        $user = Auth::user();
        app(ExportSessionService::class)->touchCurrentSession(request(), $user);
    }
}
