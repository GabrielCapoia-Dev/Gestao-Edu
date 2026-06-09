<x-filament-panels::page>
    <form wire:submit="startPreview">
        {{ $this->form }}

        <div class="mt-6 flex items-center gap-3">
            <x-filament::button type="submit" color="primary">
                Ativar visualização
            </x-filament::button>

            @if (app(\App\Services\ProfilePreviewService::class)->isActive())
                <x-filament::button type="button" color="danger" wire:click="stopPreview" outlined>
                    Voltar à normalidade
                </x-filament::button>
            @endif
        </div>
    </form>

    @if (app(\App\Services\ProfilePreviewService::class)->isActive())
        @php($preview = app(\App\Services\ProfilePreviewService::class))
        @php($target = $preview->targetUser())
        @if ($target)
            <div class="mt-6 rounded-lg bg-warning-50 p-4 text-warning-700 dark:bg-warning-500/10 dark:text-warning-500">
                <p class="font-semibold">Modo visualização ativo</p>
                <p class="mt-1 text-sm">
                    Você está navegando como <strong>{{ $target->name }}</strong>.
                    Todas as ações de escrita estão bloqueadas.
                </p>
            </div>
        @endif
    @endif
</x-filament-panels::page>
