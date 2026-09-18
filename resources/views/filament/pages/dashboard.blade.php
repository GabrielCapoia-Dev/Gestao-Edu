<x-filament-panels::page>
    <div class="welcome-root">
        @can('viewAny', \App\Models\Aviso::class)
            <livewire:home.avisos-banner lazy />
        @endcan

        @can('viewAny', \App\Models\EventoCalendario::class)
            <livewire:home.agenda-proximos-dias lazy />
        @endcan
    </div>
</x-filament-panels::page>
