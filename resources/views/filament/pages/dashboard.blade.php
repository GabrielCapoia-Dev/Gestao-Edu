<x-filament-panels::page>
    <div class="welcome-root">
        @can('viewAny', \App\Models\Aviso::class)
            <livewire:home.avisos-banner lazy />
        @endcan

        @can('viewAny', \App\Models\EventoCalendario::class)
            <livewire:home.agenda-proximos-dias lazy />
            <livewire:home.evento-calendario-modal :mostrar-gatilho="false" />
            <livewire:home.evento-calendario-detalhes-modal />
        @endcan
    </div>
</x-filament-panels::page>
