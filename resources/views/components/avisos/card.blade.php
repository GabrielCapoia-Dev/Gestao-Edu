@props([
    'aviso',
    'preview' => false,
])

@php
    $prioridade = $aviso->prioridade?->value ?? (string) $aviso->prioridade;
    $prioridadeLabel = match ($prioridade) {
        'urgente' => 'Urgente',
        'alta' => 'Alta prioridade',
        'baixa' => 'Baixa prioridade',
        default => 'Prioridade normal',
    };
    $link = $aviso->linkAcaoSeguro();
    $linkExterno = $link
        && in_array(parse_url($link, PHP_URL_SCHEME), ['http', 'https'], true)
        && parse_url($link, PHP_URL_HOST) !== request()->getHost();
@endphp

<article
    {{ $attributes->class([
        'ge-aviso-card',
        'ge-aviso-card--'.$prioridade,
        'ge-aviso-card--preview' => $preview,
    ]) }}
    @if (! $preview)
        x-data="{ descartando: false }"
        x-bind:class="{ 'is-dismissing': descartando }"
    @endif
>
    <div class="ge-aviso-card__topo">
        <span class="ge-aviso-card__prioridade">{{ $prioridadeLabel }}</span>

        @if ($preview)
            <span class="ge-aviso-card__status">{{ $aviso->statusExibicaoLabel() }}</span>
        @endif
    </div>

    <div class="ge-aviso-card__conteudo">
        <h3>{{ $aviso->titulo }}</h3>
        <p>{{ $aviso->descricao }}</p>
    </div>

    <footer class="ge-aviso-card__rodape">
        <span>
            Disponível até
            <time datetime="{{ $aviso->fim_exibicao?->toIso8601String() }}">
                {{ $aviso->fim_exibicao?->format('d/m/Y \à\s H:i') }}
            </time>
        </span>

        <div class="ge-aviso-card__acoes">
            @if (! $preview)
                <label class="ge-aviso-card__lido">
                    <input
                        type="checkbox"
                        x-bind:disabled="descartando"
                        wire:loading.attr="disabled"
                        wire:target="marcarComoLido"
                        x-on:change="
                            descartando = true;
                            const espera = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 520;
                            window.setTimeout(() => {
                                $wire.marcarComoLido({{ $aviso->getKey() }}, {{ (int) $aviso->versao_envio }})
                                    .catch(() => { descartando = false });
                            }, espera);
                        "
                    >
                    <span>Lido</span>
                </label>
            @endif

            @if ($link)
                <a
                    href="{{ $link }}"
                    class="ge-aviso-card__acao"
                    @if ($linkExterno) target="_blank" rel="noopener noreferrer" @endif
                >
                    {{ filled($aviso->texto_botao) ? $aviso->texto_botao : 'Saiba mais' }}
                    <x-heroicon-o-arrow-right />
                </a>
            @endif
        </div>
    </footer>
</article>
