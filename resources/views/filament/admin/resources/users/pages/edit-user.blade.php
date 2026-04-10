<x-filament-panels::page>
    @include('filament.pages.partials.access-management-styles')

    @php($cards = collect($this->getOverviewCards())->take(3)->values())
    @php($highlights = array_slice($this->getHighlights(), 0, 2))

    <div class="am-page">
        <section class="am-hero">
            <p class="am-kicker">Edicao de usuario</p>
            <h2 class="am-title">Ajustes de acesso com leitura mais objetiva.</h2>
            <p class="am-subtitle">
                Revise niveis, permissoes e vinculos sem disputar atencao com informacoes secundarias.
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
                <h3 class="am-panel__title">Perfil atual do usuario</h3>
                <p class="am-panel__subtitle">O essencial fica visivel logo acima do formulario.</p>
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
