<x-filament-panels::page>
    @include('filament.pages.partials.access-management-styles')

    @php($cards = collect($this->getOverviewCards())->take(3)->values())
    @php($highlights = array_slice($this->getHighlights(), 0, 2))

    <div class="am-page">
        <section class="am-panel">
            <div class="am-panel__header">
                <p class="am-panel__eyebrow">Painel principal</p>
                <h3 class="am-panel__title">Usuarios cadastrados</h3>
                <p class="am-panel__subtitle">Edite niveis, permissoes e vinculos no mesmo fluxo de trabalho.</p>
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
