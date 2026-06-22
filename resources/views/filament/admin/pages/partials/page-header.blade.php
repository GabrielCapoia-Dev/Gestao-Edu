@php
    $eyebrow ??= '';
    $title ??= '';
    $description ??= '';
    $actions ??= [];
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

        @if (filled($actions))
            <div class="gi-actions gi-page-header__actions">
                <x-filament::actions :actions="$actions" />
            </div>
        @endif
    </section>
</div>
