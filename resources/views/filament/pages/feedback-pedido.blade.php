<x-filament-panels::page>

    {{-- CARDS --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">

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
            <div class="text-sm text-gray-500">Satisfação (≥ 8)</div>
            <div class="text-3xl font-bold mt-2">
                {{ $this->getPercentualSatisfacao() }}%
            </div>
        </x-filament::card>

    </div>

    {{-- TABLE --}}
    {{ $this->table }}

</x-filament-panels::page>