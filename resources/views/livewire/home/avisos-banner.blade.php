@php
    $semAvisos = ! $erroAoCarregar && $paginas->isEmpty();
@endphp

<section
    @class([
        'ge-avisos',
        'ge-avisos--somente-atalho' => $semAvisos && $podeGerenciar,
        'is-hidden' => $semAvisos && ! $podeGerenciar,
    ])
    x-data="{ pagina: 0 }"
    @if (! $semAvisos)
        aria-labelledby="ge-avisos-titulo"
    @endif
    @if ($paginas->isNotEmpty())
        x-on:keydown.right.prevent="pagina = (pagina + 1) % {{ $paginas->count() }}"
        x-on:keydown.left.prevent="pagina = (pagina - 1 + {{ $paginas->count() }}) % {{ $paginas->count() }}"
        tabindex="0"
        role="region"
        aria-roledescription="carrossel"
    @endif
>
    @if ($semAvisos)
        @if ($podeGerenciar)
            <a href="{{ $urlGerenciar }}" class="gi-action ge-avisos__gerenciar">
                <x-heroicon-o-cog-6-tooth />
                <span>Gerenciar avisos</span>
            </a>
        @endif
    @else
    <header class="ge-avisos__cabecalho">
        <div>
            <p class="ge-avisos__eyebrow">Comunicação</p>
            <h2 id="ge-avisos-titulo">Avisos</h2>
            <p>Informações relevantes para o seu contexto de acesso.</p>
        </div>

        @if ($podeGerenciar)
            <a href="{{ $urlGerenciar }}" class="gi-action ge-avisos__gerenciar">
                <x-heroicon-o-cog-6-tooth />
                <span>Gerenciar avisos</span>
            </a>
        @endif
    </header>

    @if ($erroAoCarregar)
        <div class="gi-empty ge-avisos__vazio" role="alert">
            <x-heroicon-o-exclamation-triangle />
            <div>
                <strong>Não foi possível carregar os avisos</strong>
                <span>Tente novamente. Os demais recursos da página continuam disponíveis.</span>
            </div>
            <button type="button" class="gi-action" wire:click="$refresh">Tentar novamente</button>
        </div>
    @else
        <div class="ge-avisos__viewport" aria-live="polite">
            @foreach ($paginas as $indice => $avisos)
                <div
                    @class([
                        'ge-avisos__pagina',
                        'is-active' => $indice === 0,
                        'ge-avisos__pagina--um' => $avisos->count() === 1,
                        'ge-avisos__pagina--dois' => $avisos->count() === 2,
                        'ge-avisos__pagina--tres' => $avisos->count() >= 3,
                    ])
                    x-bind:class="{ 'is-active': pagina === {{ $indice }} }"
                    x-bind:aria-hidden="pagina !== {{ $indice }}"
                    x-bind:inert="pagina !== {{ $indice }}"
                >
                    @foreach ($avisos as $aviso)
                        <x-avisos.card
                            :aviso="$aviso"
                            wire:key="aviso-{{ $aviso->getKey() }}-{{ $aviso->versao_envio }}"
                        />
                    @endforeach
                </div>
            @endforeach
        </div>

        @if ($paginas->count() > 1)
            <footer class="ge-avisos__navegacao">
                <span class="ge-avisos__contador">
                    {{ $quantidadeAvisos }} {{ $quantidadeAvisos === 1 ? 'aviso' : 'avisos' }}
                    <span aria-hidden="true">·</span>
                    página <span x-text="pagina + 1">1</span> de {{ $paginas->count() }}
                </span>

                <div class="ge-avisos__indicadores" aria-label="Selecionar página de avisos">
                    @foreach ($paginas as $indice => $avisos)
                        <button
                            type="button"
                            x-on:click="pagina = {{ $indice }}"
                            x-bind:class="{ 'is-active': pagina === {{ $indice }} }"
                            x-bind:aria-current="pagina === {{ $indice }} ? 'true' : null"
                            aria-label="Exibir página {{ $indice + 1 }}"
                        ></button>
                    @endforeach
                </div>

                <div class="ge-avisos__setas">
                    <button
                        type="button"
                        x-on:click="pagina = (pagina - 1 + {{ $paginas->count() }}) % {{ $paginas->count() }}"
                        aria-label="Página anterior de avisos"
                    >
                        <x-heroicon-o-chevron-left />
                    </button>
                    <button
                        type="button"
                        x-on:click="pagina = (pagina + 1) % {{ $paginas->count() }}"
                        aria-label="Próxima página de avisos"
                    >
                        <x-heroicon-o-chevron-right />
                    </button>
                </div>
            </footer>
        @endif
    @endif
    @endif
</section>
