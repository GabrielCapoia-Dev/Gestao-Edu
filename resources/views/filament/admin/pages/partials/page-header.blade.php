@php
    $eyebrow ??= '';
    $title ??= '';
    $description ??= '';
    $actions ??= [];
@endphp

<div class="gi-page">
    <section class="gi-hero">
        <div>
            <p class="gi-eyebrow">{{ $eyebrow }}</p>
            <h1>{{ $title }}</h1>
            <p>{{ $description }}</p>
        </div>

        @if (filled($actions))
            <div class="gi-actions">
                <x-filament::actions :actions="$actions" />
            </div>
        @endif
    </section>
</div>
