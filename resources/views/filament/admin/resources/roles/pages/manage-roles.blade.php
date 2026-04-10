<x-filament-panels::page>
    @include('filament.pages.partials.access-management-styles')

    @php($cards = collect($this->getOverviewCards())->take(3)->values())
    @php($highlights = array_slice($this->getHighlights(), 0, 2))

    <div class="am-page">
        <section class="am-hero">
            <p class="am-kicker">Niveis de acesso</p>
            <h2 class="am-title">Perfis reutilizaveis com uma visao mais limpa da matriz de acesso.</h2>
            <p class="am-subtitle">
                A tela agora prioriza o que realmente importa: manter niveis bem organizados e faceis de revisar.
            </p>

            <div class="am-pill-list">
                @foreach ($highlights as $highlight)
                    <span class="am-pill">{{ $highlight }}</span>
                @endforeach
            </div>
        </section>

        <section class="am-panel">
            <div class="am-panel__header">
                <p class="am-panel__eyebrow">Biblioteca de niveis</p>
                <h3 class="am-panel__title">Gestao dos perfis de acesso</h3>
                <p class="am-panel__subtitle">Crie, ajuste e revise niveis sem perder agilidade na listagem.</p>
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
