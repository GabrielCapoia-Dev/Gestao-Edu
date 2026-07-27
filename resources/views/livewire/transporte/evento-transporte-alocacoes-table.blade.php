@php
    $resumo = $this->resumo();
    $possuiRecursosInativos = (bool) ($resumo['possui_recursos_inativos'] ?? false);
@endphp

<x-filament-widgets::widget class="gi-transport-allocation">
    @if ($possuiRecursosInativos)
        <p class="gi-transport-allocation__alert" role="status">
            Há veículo ou motorista inativo nesta alocação. Ele permanece no histórico, mas precisa ser substituído antes da publicação.
        </p>
    @endif

    {{ $this->table }}
</x-filament-widgets::widget>
