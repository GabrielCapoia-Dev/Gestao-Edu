<x-filament-panels::page>
    @if ($this->abaUsuarios())
        @include('filament.pages.partials.access-management-styles')

        @php($cards = collect($this->getOverviewCards())->take(3)->values())

        <div class="am-page">
            <section class="am-panel">
                <div class="am-panel__header">
                    <p class="am-panel__eyebrow">Painel principal</p>
                    <h3 class="am-panel__title">Usuários cadastrados</h3>
                    <p class="am-panel__subtitle">Edite níveis, permissões e vínculos no mesmo fluxo de trabalho.</p>
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
    @else
        {{ $this->content }}
    @endif
</x-filament-panels::page>