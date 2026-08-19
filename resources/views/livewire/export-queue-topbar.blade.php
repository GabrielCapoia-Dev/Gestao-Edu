@php
    $pollSeconds = $open
        ? (int) config('performance.livewire_polling.exports_topbar_open', 2)
        : (int) config('performance.livewire_polling.exports_topbar_closed', 15);
@endphp

<div
    class="export-queue-wrapper"
    wire:poll.visible.{{ $pollSeconds }}s="pollQueue"
    x-data
    x-on:export-auto-download.window="
        const payload = $event.detail || {};

        if (payload.url) {
            const key = 'gestao-edu:auto-download:' + (payload.exportRequestId || payload.url);

            if (! sessionStorage.getItem(key)) {
                sessionStorage.setItem(key, '1');

                const frame = document.createElement('iframe');
                frame.style.display = 'none';
                frame.src = payload.url;
                document.body.appendChild(frame);

                window.setTimeout(() => frame.remove(), 60000);
            }
        }
    "
>
    <button
        type="button"
        class="export-queue-trigger {{ $activeCount > 0 ? 'is-active' : '' }} {{ $readyCount > 0 ? 'has-ready' : '' }}"
        wire:click="togglePanel"
        aria-expanded="{{ $open ? 'true' : 'false' }}"
        aria-label="Abrir downloads e processos"
        title="Downloads e processos"
    >
        <span class="export-queue-trigger-icon" aria-hidden="true">
            <x-heroicon-o-arrow-down-tray />
        </span>

        @if ($activeCount > 0)
            <span class="export-queue-badge">{{ $activeCount > 99 ? '99+' : $activeCount }}</span>
        @elseif ($readyCount > 0)
            <span class="export-queue-ready-dot" aria-hidden="true"></span>
        @endif
    </button>

    @if ($open)
        <div class="export-queue-popover" wire:click.outside="closePanel">
            <div class="export-queue-header">
                <div>
                    <strong>Downloads e processos</strong>
                    <span>
                        @if ($activeCount > 0)
                            {{ $activeCount }} {{ $activeCount === 1 ? 'item em andamento' : 'itens em andamento' }}
                        @elseif ($readyCount > 0)
                            {{ $readyCount }} {{ $readyCount === 1 ? 'arquivo disponível' : 'arquivos disponíveis' }}
                        @else
                            Nenhum processo em andamento
                        @endif
                    </span>
                </div>

                <button type="button" wire:click="closePanel" aria-label="Fechar downloads e processos">
                    <x-heroicon-o-x-mark />
                </button>
            </div>

            <div class="export-queue-list">
                @forelse ($items as $item)
                    @php
                        $statusClass = $this->statusClass($item->status);
                        $progress = $item->status === \App\Models\ExportRequest::STATUS_QUEUED
                            ? 8
                            : $item->progress_percentage;
                        $isProcess = $item->format === 'processo';
                    @endphp

                    <article
                        class="export-queue-item {{ $statusClass }}"
                        wire:key="export-queue-{{ $item->getKey() }}"
                    >
                        <div class="export-queue-item-top">
                            <span class="export-queue-item-icon" aria-hidden="true">
                                @if ($isProcess)
                                    <x-heroicon-o-cog-6-tooth />
                                @else
                                    <x-heroicon-o-document-arrow-down />
                                @endif
                            </span>

                            <div class="export-queue-item-title">
                                <strong>{{ $item->label ?: ($isProcess ? 'Processamento' : 'Exportação') }}</strong>
                                <span>{{ $this->statusLabel($item->status) }}</span>
                            </div>

                            <time datetime="{{ $item->created_at?->toIso8601String() }}">
                                {{ $item->created_at?->format('H:i') }}
                            </time>
                        </div>

                        <div
                            class="export-queue-progress {{ $item->status === \App\Models\ExportRequest::STATUS_QUEUED ? 'is-indeterminate' : '' }}"
                            role="progressbar"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            aria-valuenow="{{ $item->progress_percentage }}"
                        >
                            <span style="width: {{ $progress }}%"></span>
                        </div>

                        <div class="export-queue-item-meta">
                            <span>{{ $item->status_message ?: 'Aguardando atualização.' }}</span>

                            @if ($item->isActive())
                                <strong>{{ $item->progress_percentage }}%</strong>
                            @elseif ($item->size_bytes)
                                <strong>{{ $this->formatSize($item->size_bytes) }}</strong>
                            @endif
                        </div>

                        @if ($item->isFinished() && $item->file_path)
                            <a class="export-queue-download" href="{{ route('exports.download', $item) }}">
                                <x-heroicon-o-arrow-down-tray />
                                Baixar arquivo
                            </a>
                        @elseif ($item->isActive())
                            <button
                                type="button"
                                class="export-queue-cancel"
                                wire:click="cancel('{{ $item->getKey() }}')"
                                wire:confirm="Deseja cancelar este processo?"
                            >
                                Cancelar
                            </button>
                        @elseif ($item->error_message)
                            <p class="export-queue-error">{{ $item->error_message }}</p>
                        @endif
                    </article>
                @empty
                    <div class="export-queue-empty">
                        <x-heroicon-o-arrow-down-tray />
                        <strong>Nenhum download nesta sessão</strong>
                        <span>As exportações e os processos iniciados aparecerão aqui.</span>
                    </div>
                @endforelse
            </div>

            <div class="export-queue-footer">
                Acompanhe sua fila de processos.
            </div>
        </div>
    @endif
</div>
