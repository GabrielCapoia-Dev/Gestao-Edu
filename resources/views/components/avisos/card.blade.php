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

<article @class([
    'ge-aviso-card',
    'ge-aviso-card--'.$prioridade,
    'ge-aviso-card--preview' => $preview,
])>
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
    </footer>
</article>
