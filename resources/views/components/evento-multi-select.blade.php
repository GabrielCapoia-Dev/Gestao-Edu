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
    $optionLabels = $optionCollection->mapWithKeys(fn ($optionLabel, $optionValue): array => [(string) $optionValue => $optionLabel])->all();
@endphp

<div
    class="evento-custom-modal__field evento-custom-modal__multi-select-field"
    x-data="{ selecionados: @js($selectedValues), opcoes: @js($optionLabels) }"
>
    <label for="{{ $id }}">{{ $label }}</label>
    <details class="evento-custom-modal__multi-select">
        <summary id="{{ $id }}" aria-label="{{ $label }}">
            <span class="evento-custom-modal__multi-select-summary">
                <span
                    class="evento-custom-modal__multi-select-placeholder"
                    x-show="selecionados.length === 0"
                >{{ $placeholder }}</span>
                <template x-for="valor in selecionados.slice(0, 2)" :key="valor">
                    <span class="evento-custom-modal__multi-select-chip" x-text="opcoes[String(valor)] ?? valor"></span>
                </template>
                <span
                    class="evento-custom-modal__multi-select-count"
                    x-show="selecionados.length > 2"
                    x-text="`+${selecionados.length - 2}`"
                ></span>
            </span>
            <span class="evento-custom-modal__multi-select-chevron" aria-hidden="true">⌄</span>
        </summary>
        <div class="evento-custom-modal__multi-select-options" role="listbox" aria-label="Opções de {{ $label }}">
            @foreach ($optionCollection as $optionValue => $optionLabel)
                @if ($changeAction)
                    <label
                        class="evento-custom-modal__multi-select-option"
                        x-bind:class="{ 'is-selected': selecionados.includes(String(@js($optionValue))) }"
                    >
                        <input
                            type="checkbox"
                            value="{{ $optionValue }}"
                            x-model="selecionados"
                            wire:model.live="{{ $model }}"
                            wire:change="{{ $changeAction }}"
                        >
                        <span>{{ $optionLabel }}</span>
                    </label>
                @else
                    <label
                        class="evento-custom-modal__multi-select-option"
                        x-bind:class="{ 'is-selected': selecionados.includes(String(@js($optionValue))) }"
                    >
                        <input type="checkbox" value="{{ $optionValue }}" x-model="selecionados" wire:model.live="{{ $model }}">
                        <span>{{ $optionLabel }}</span>
                    </label>
                @endif
            @endforeach
        </div>
    </details>
</div>
