<x-filament-panels::page>

    {{-- CARDS --}}
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 1.5rem;">

        <x-filament::card>
            <div class="text-sm text-gray-500">Média Geral</div>
            <div class="text-3xl font-bold mt-2">
                {{ $this->getMediaGeral() }}
            </div>
        </x-filament::card>

        <x-filament::card>
            <div class="text-sm text-gray-500">Total de Avaliações</div>
            <div class="text-3xl font-bold mt-2">
                {{ $this->getTotalAvaliacoes() }}
            </div>
        </x-filament::card>

        <x-filament::card>
            <div class="text-sm text-gray-500">Nível de Satisfação</div>
            <div class="text-3xl font-bold mt-2">
                {{ $this->getPercentualSatisfacao() }}%
            </div>
        </x-filament::card>

    </div>

    {{-- TABLE --}}
    {{ $this->table }}

</x-filament-panels::page>