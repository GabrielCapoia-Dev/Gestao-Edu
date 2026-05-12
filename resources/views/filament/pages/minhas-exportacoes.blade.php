<x-filament-panels::page>
    <div class="av-livewire-root">
        @if ($autoDownload && ! $autoDownloadDispatched)
            <div wire:poll.3s="pollAutoDownload"></div>
        @endif

        {{ $this->table }}
    </div>
</x-filament-panels::page>
