<x-filament-panels::page>
    <div class="av-livewire-root">
        @if ($autoDownload && ! $autoDownloadDispatched)
            <div wire:poll.{{ (int) config('performance.livewire_polling.exports_auto_download', 10) }}s="pollAutoDownload"></div>
        @endif

        {{ $this->table }}

        <script>
            (() => {
                if (window.__gestaoEduExportsPollReady) {
                    return;
                }
                window.__gestaoEduExportsPollReady = true;

                const togglePolling = () => {
                    const isHidden = document.visibilityState !== 'visible';

                    document.querySelectorAll('[wire\\:poll]').forEach((el) => {
                        const interval = el.getAttribute('wire:poll');
                        if (isHidden && interval && ! el.dataset.savedPoll) {
                            el.dataset.savedPoll = interval;
                            el.setAttribute('wire:poll', '120000');
                        } else if (! isHidden && el.dataset.savedPoll) {
                            el.setAttribute('wire:poll', el.dataset.savedPoll);
                            delete el.dataset.savedPoll;
                        }
                    });
                };

                document.addEventListener('visibilitychange', togglePolling);
                togglePolling();
            })();
        </script>
    </div>
</x-filament-panels::page>
