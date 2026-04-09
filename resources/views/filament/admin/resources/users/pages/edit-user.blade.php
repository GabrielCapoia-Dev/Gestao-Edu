<x-filament-panels::page>
    @include('filament.pages.partials.access-management-styles')

    <div class="am-page">
        <section class="am-hero">
            <div class="am-hero__content">
                <p class="am-kicker">Edicao de usuario</p>
                <h2 class="am-title">Ajuste niveis, excecoes e vinculos sem perder a visao completa do acesso atual.</h2>
                <p class="am-subtitle">
                    Esta tela ajuda a entender rapidamente como o usuario esta configurado hoje antes de aplicar qualquer
                    alteracao em niveis de acesso, permissoes adicionais ou escopo operacional.
                </p>

                <div class="am-pill-list">
                    @foreach ($this->getHighlights() as $highlight)
                        <span class="am-pill">{{ $highlight }}</span>
                    @endforeach
                </div>
            </div>

            <div class="am-hero__cards">
                <div class="am-cards">
                    @foreach ($this->getOverviewCards() as $card)
                        <article class="am-card am-card--{{ $card['tone'] }}">
                            <div class="am-card__icon">
                                <x-filament::icon :icon="$card['icon']" />
                            </div>
                            <p class="am-card__label">{{ $card['label'] }}</p>
                            <p class="am-card__value">{{ $card['value'] }}</p>
                            <p class="am-card__description">{{ $card['description'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <div class="am-grid am-grid--with-sidebar">
            <section class="am-panel">
                <div class="am-panel__header">
                    <p class="am-panel__eyebrow">Formulario de edicao</p>
                    <h3 class="am-panel__title">Revisao completa do perfil</h3>
                    <p class="am-panel__subtitle">
                        Atualize o que for necessario mantendo o uso intacto: niveis acumulativos, permissoes diretas e os
                        vinculos com escola e setor continuam funcionando exatamente como antes.
                    </p>
                </div>

                <div class="am-panel__body">
                    {{ $this->content }}
                </div>
            </section>

            <aside class="am-sidebar">
                @foreach ($this->getSupportItems() as $item)
                    <section class="am-note">
                        <h3 class="am-note__title">{{ $item['title'] }}</h3>
                        <p class="am-note__description">{{ $item['description'] }}</p>
                    </section>
                @endforeach
            </aside>
        </div>
    </div>
</x-filament-panels::page>
