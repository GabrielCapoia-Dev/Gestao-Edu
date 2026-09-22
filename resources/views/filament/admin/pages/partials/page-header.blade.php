@php
    $eyebrow ??= '';
    $title ??= '';
    $description ??= '';
    $actions ??= [];
    $mostrarEventoModal ??= false;
    $mostrarEventoGatilho ??= false;
@endphp

<div class="gi-page">
    <section class="gi-hero gi-page-header">
        <div class="gi-page-header__body">
            @if (filled($eyebrow))
                <p class="gi-eyebrow">{{ $eyebrow }}</p>
            @endif

            @if (filled($title))
                <h1 class="gi-page-header__title">{{ $title }}</h1>
            @endif

            @if (filled($description))
                <p class="gi-page-header__description">{{ $description }}</p>
            @endif
        </div>

        @if (filled($actions) || $mostrarEventoModal || $mostrarEventoGatilho)
            <div class="gi-actions gi-page-header__actions">
                @if (filled($actions))
                    <x-filament::actions :actions="$actions" />
                @endif
                @if ($mostrarEventoModal)
                    <livewire:home.evento-calendario-modal />
                @elseif ($mostrarEventoGatilho)
                    <button
                        type="button"
                        class="evento-custom-modal__trigger"
                        wire:click="$dispatch('abrir-evento-calendario')"
                    >
                        <x-heroicon-o-plus />
                        <span>Novo evento</span>
                    </button>
                @endif
            </div>
        @endif
    </section>
</div>
