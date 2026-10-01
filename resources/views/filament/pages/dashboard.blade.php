<x-filament-panels::page>
    <div class="welcome-root">
        @can('viewAny', \App\Models\Aviso::class)
            <livewire:home.avisos-banner lazy />
        @endcan

        @if ($this->podeVisualizarAgenda())
            <livewire:home.agenda-proximos-dias lazy />
            <livewire:home.evento-calendario-modal :mostrar-gatilho="false" />
            <livewire:home.evento-calendario-detalhes-modal />
        @endif
    </div>
</x-filament-panels::page>
