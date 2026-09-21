@props([
    'id',
    'label',
    'model',
    'options' => [],
    'selected' => [],
    'changeAction' => null,
    'placeholder' => 'Selecione uma opção',
])

@php
    $optionCollection = collect($options);
    $selectedValues = collect($selected)->map(fn ($value): string => (string) $value)->values()->all();
    $selectedLabels = $optionCollection
        ->filter(fn ($optionLabel, $optionValue): bool => in_array((string) $optionValue, $selectedValues, true))
        ->values();
@endphp

<div class="evento-custom-modal__field evento-custom-modal__multi-select-field">
    <label for="{{ $id }}">{{ $label }}</label>
    <details class="evento-custom-modal__multi-select">
        <summary id="{{ $id }}" aria-label="{{ $label }}">
            <span class="evento-custom-modal__multi-select-summary">
                @if ($selectedLabels->isEmpty())
                    <span class="evento-custom-modal__multi-select-placeholder">{{ $placeholder }}</span>
                @else
                    @foreach ($selectedLabels->take(2) as $selectedLabel)
                        <span class="evento-custom-modal__multi-select-chip">{{ $selectedLabel }}</span>
                    @endforeach
                    @if ($selectedLabels->count() > 2)
                        <span class="evento-custom-modal__multi-select-count">+{{ $selectedLabels->count() - 2 }}</span>
                    @endif
                @endif
            </span>
            <span class="evento-custom-modal__multi-select-chevron" aria-hidden="true">⌄</span>
        </summary>
        <div class="evento-custom-modal__multi-select-options" role="listbox" aria-label="Opções de {{ $label }}">
            @foreach ($optionCollection as $optionValue => $optionLabel)
                @if ($changeAction)
                    <label class="evento-custom-modal__multi-select-option">
                        <input
                            type="checkbox"
                            value="{{ $optionValue }}"
                            wire:model="{{ $model }}"
                            wire:change="{{ $changeAction }}"
                        >
                        <span>{{ $optionLabel }}</span>
                    </label>
                @else
                    <label class="evento-custom-modal__multi-select-option">
                        <input type="checkbox" value="{{ $optionValue }}" wire:model="{{ $model }}">
                        <span>{{ $optionLabel }}</span>
                    </label>
                @endif
            @endforeach
        </div>
    </details>
</div>
