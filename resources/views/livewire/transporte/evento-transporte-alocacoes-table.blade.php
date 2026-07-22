@php
    $resumo = $this->resumo();
    $diferenca = (int) ($resumo['diferenca'] ?? 0);
    $capacidadeInsuficiente = (bool) ($resumo['capacidade_insuficiente'] ?? ($diferenca < 0));
    $possuiRecursosInativos = (bool) ($resumo['possui_recursos_inativos'] ?? false);
@endphp

<x-filament-widgets::widget class="gi-transport-allocation">
    <section class="gi-transport-allocation__summary" aria-label="Resumo de capacidade do transporte">
        <article class="gi-transport-allocation__metric">
            <span>Estudantes estimados</span>
            <strong>{{ number_format((int) ($resumo['estudantes'] ?? 0), 0, ',', '.') }}</strong>
        </article>

        <article class="gi-transport-allocation__metric">
            <span>Capacidade alocada</span>
            <strong>{{ number_format((int) ($resumo['capacidade'] ?? 0), 0, ',', '.') }}</strong>
        </article>

        <article @class([
            'gi-transport-allocation__metric',
            'is-warning' => $capacidadeInsuficiente,
            'is-success' => ! $capacidadeInsuficiente,
        ])>
            <span>{{ $capacidadeInsuficiente ? 'Déficit de lugares' : 'Sobra de lugares' }}</span>
            <strong>{{ number_format(abs($diferenca), 0, ',', '.') }}</strong>
        </article>
    </section>

    @if ($capacidadeInsuficiente)
        <p class="gi-transport-allocation__alert" role="status">
            A capacidade atual não cobre todos os estudantes estimados. Você pode continuar o planejamento e ajustar os veículos depois.
        </p>
    @endif

    @if ($possuiRecursosInativos)
        <p class="gi-transport-allocation__alert" role="status">
            Há veículo ou motorista inativo nesta alocação. Ele permanece no histórico, mas precisa ser substituído antes da publicação.
        </p>
    @endif

    {{ $this->table }}
</x-filament-widgets::widget>
