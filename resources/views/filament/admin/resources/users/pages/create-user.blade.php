<x-filament-panels::page>
    @include('filament.pages.partials.access-management-styles')

    @php($cards = collect($this->getOverviewCards())->take(3)->values())
    @php($highlights = array_slice($this->getHighlights(), 0, 2))

    <div class="am-page">
        <section class="am-hero">
            <p class="am-kicker">Novo usuário</p>
            <h2 class="am-title">Cadastro claro, rápido e sem excesso de contexto.</h2>
            <p class="am-subtitle">
                Monte o acesso do usuário com níveis reutilizaveis e ajuste apenas o que for necessário.
            </p>

            <div class="am-pill-list">
                @foreach ($highlights as $highlight)
                    <span class="am-pill">{{ $highlight }}</span>
                @endforeach
            </div>
        </section>

        <section class="am-panel">
            <div class="am-panel__header">
                <p class="am-panel__eyebrow">Formulario</p>
                <h3 class="am-panel__title">Configuração inicial do acesso</h3>
                <p class="am-panel__subtitle">Dados, níveis e vinculos em uma estrutura mais limpa.</p>
            </div>

            <div class="am-summary">
                @foreach ($cards as $card)
                    <article class="am-summary-card am-summary-card--{{ $card['tone'] }}">
                        <div class="am-summary-card__top">
                            <div class="am-summary-card__icon">
                                <x-filament::icon :icon="$card['icon']" />
                            </div>
                            <div>
                                <p class="am-summary-card__label">{{ $card['label'] }}</p>
                                <p class="am-summary-card__value">{{ $card['value'] }}</p>
                            </div>
                        </div>

                        <p class="am-summary-card__description">{{ $card['description'] }}</p>
                    </article>
                @endforeach
            </div>

            <div class="am-panel__body">
                {{ $this->content }}
            </div>
        </section>
    </div>
</x-filament-panels::page>
