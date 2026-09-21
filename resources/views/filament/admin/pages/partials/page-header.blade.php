@php
    $eyebrow ??= '';
    $title ??= '';
    $description ??= '';
    $actions ??= [];
    $mostrarEventoModal ??= false;
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

        @if (filled($actions) || $mostrarEventoModal)
            <div class="gi-actions gi-page-header__actions">
                @if (filled($actions))
                    <x-filament::actions :actions="$actions" />
                @endif
                @if ($mostrarEventoModal)
                    <livewire:home.evento-calendario-modal />
                @endif
            </div>
        @endif
    </section>
</div>
