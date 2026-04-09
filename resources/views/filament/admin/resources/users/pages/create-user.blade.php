<x-filament-panels::page>
    @include('filament.pages.partials.access-management-styles')

    <div class="am-page">
        <section class="am-hero">
            <div class="am-hero__content">
                <p class="am-kicker">Novo usuario</p>
                <h2 class="am-title">Cadastre pessoas com niveis combinaveis, vinculos definidos e excecoes controladas.</h2>
                <p class="am-subtitle">
                    A estrutura do formulario permanece a mesma, mas agora a tela destaca com mais clareza como acesso,
                    escola e setor trabalham juntos no cadastro.
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
                    <p class="am-panel__eyebrow">Formulario de cadastro</p>
                    <h3 class="am-panel__title">Configuracao inicial do acesso</h3>
                    <p class="am-panel__subtitle">
                        Preencha os dados principais e monte o acesso do usuario combinando niveis reutilizaveis com ajustes
                        pontuais somente quando eles forem realmente necessarios.
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
