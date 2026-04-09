<x-filament-panels::page>
    @include('filament.pages.partials.access-management-styles')

    <div class="am-page">
        <section class="am-hero">
            <div class="am-hero__content">
                <p class="am-kicker">Niveis de acesso</p>
                <h2 class="am-title">Organize os acessos por contexto e monte combinacoes mais inteligentes para cada usuario.</h2>
                <p class="am-subtitle">
                    Esta pagina continua com criacao e edicao em slide-over, mas agora apresenta a matriz de acesso com uma
                    leitura visual mais forte para facilitar a manutencao dos niveis do sistema.
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
                    <p class="am-panel__eyebrow">Biblioteca de niveis</p>
                    <h3 class="am-panel__title">Gestao dos perfis reutilizaveis</h3>
                    <p class="am-panel__subtitle">
                        Centralize a manutencao dos niveis por tema, agrupe permissoes relacionadas e reduza o trabalho de
                        montar acesso usuario por usuario quando a regra for compartilhada.
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
