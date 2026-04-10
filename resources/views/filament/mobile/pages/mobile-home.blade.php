<x-filament-panels::page>
    @php($accessCards = $this->getAccessCards())
    @php($reportCards = $this->getReportCards())

    <div class="mobile-home">
        <section class="mobile-home__hero">
            <div class="mobile-home__hero-grid"></div>
            <div class="mobile-home__hero-glow"></div>

            <div class="mobile-home__hero-content">
                <p class="mobile-home__eyebrow">Workspace mobile</p>
                <h1 class="mobile-home__title">Acesso rapido para operacao e leitura.</h1>
                <p class="mobile-home__subtitle">
                    Este painel foi isolado do <code>/admin</code> para voce trabalhar no celular sem mexer na
                    experiencia desktop atual.
                </p>

                <div class="mobile-home__meta">
                    <span class="mobile-home__meta-pill">{{ $this->getSectionsCount() }} areas ativas</span>
                    <span class="mobile-home__meta-pill">{{ count($accessCards) + count($reportCards) }} atalhos visiveis</span>
                </div>
            </div>
        </section>

        <section class="mobile-home__section">
            <div class="mobile-home__section-header">
                <div>
                    <p class="mobile-home__section-kicker">Acesso</p>
                    <h2 class="mobile-home__section-title">Usuarios, dominios e niveis</h2>
                </div>
            </div>

            @if (count($accessCards))
                <div class="mobile-home__cards">
                    @foreach ($accessCards as $card)
                        <a href="{{ $card['url'] }}" class="mobile-link-card mobile-link-card--{{ $card['tone'] }}">
                            <span class="mobile-link-card__icon">
                                <x-filament::icon :icon="$card['icon']" />
                            </span>

                            <span class="mobile-link-card__content">
                                <span class="mobile-link-card__title">{{ $card['title'] }}</span>
                                <span class="mobile-link-card__description">{{ $card['description'] }}</span>
                            </span>

                            <span class="mobile-link-card__arrow">Abrir</span>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="mobile-empty-state">
                    Seu perfil nao possui itens de acesso liberados neste painel.
                </div>
            @endif
        </section>

        <section class="mobile-home__section">
            <div class="mobile-home__section-header">
                <div>
                    <p class="mobile-home__section-kicker">Relatorios</p>
                    <h2 class="mobile-home__section-title">Leitura, filtros e exportacoes</h2>
                </div>
            </div>

            @if (count($reportCards))
                <div class="mobile-home__cards">
                    @foreach ($reportCards as $card)
                        <a href="{{ $card['url'] }}" class="mobile-link-card mobile-link-card--{{ $card['tone'] }}">
                            <span class="mobile-link-card__icon">
                                <x-filament::icon :icon="$card['icon']" />
                            </span>

                            <span class="mobile-link-card__content">
                                <span class="mobile-link-card__title">{{ $card['title'] }}</span>
                                <span class="mobile-link-card__description">{{ $card['description'] }}</span>
                            </span>

                            <span class="mobile-link-card__arrow">Abrir</span>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="mobile-empty-state">
                    Nenhum relatorio foi liberado para o seu perfil neste painel.
                </div>
            @endif
        </section>
    </div>

    <style>
        .mobile-home {
            --mobile-slate: #0f172a;
            --mobile-ink: #14213d;
            --mobile-muted: #64748b;
            --mobile-surface: #ffffff;
            --mobile-border: rgba(148, 163, 184, 0.22);
            --mobile-shadow: 0 18px 36px rgba(15, 23, 42, 0.08);
            display: grid;
            gap: 1rem;
            padding-bottom: calc(1rem + env(safe-area-inset-bottom));
        }

        .mobile-home__hero {
            position: relative;
            overflow: hidden;
            padding: 1.4rem;
            border-radius: 1.75rem;
            background:
                radial-gradient(circle at top right, rgba(250, 204, 21, 0.2), transparent 26%),
                linear-gradient(160deg, #082f49 0%, #0f3d68 52%, #0f172a 100%);
            color: #f8fafc;
            box-shadow: 0 24px 48px rgba(8, 47, 73, 0.24);
        }

        .mobile-home__hero-grid {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255, 255, 255, 0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.04) 1px, transparent 1px);
            background-size: 22px 22px;
            mask-image: radial-gradient(circle at center, black 35%, transparent 90%);
        }

        .mobile-home__hero-glow {
            position: absolute;
            right: -3.5rem;
            top: -3.5rem;
            width: 11rem;
            height: 11rem;
            border-radius: 9999px;
            background: radial-gradient(circle, rgba(96, 165, 250, 0.38) 0%, transparent 72%);
        }

        .mobile-home__hero-content {
            position: relative;
            z-index: 1;
        }

        .mobile-home__eyebrow {
            margin: 0 0 0.5rem;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: rgba(226, 232, 240, 0.82);
        }

        .mobile-home__title {
            margin: 0;
            max-width: 18rem;
            font-size: clamp(1.5rem, 1.2rem + 1.2vw, 2.3rem);
            line-height: 1.08;
            font-weight: 700;
            letter-spacing: -0.03em;
        }

        .mobile-home__subtitle {
            margin: 0.8rem 0 0;
            max-width: 28rem;
            font-size: 0.93rem;
            line-height: 1.65;
            color: rgba(226, 232, 240, 0.9);
        }

        .mobile-home__subtitle code {
            padding: 0.12rem 0.4rem;
            border-radius: 9999px;
            background: rgba(255, 255, 255, 0.12);
            color: #f8fafc;
        }

        .mobile-home__meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .mobile-home__meta-pill {
            display: inline-flex;
            align-items: center;
            min-height: 2rem;
            padding: 0.45rem 0.8rem;
            border-radius: 9999px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.12);
            font-size: 0.78rem;
            color: #f8fafc;
        }

        .mobile-home__section {
            display: grid;
            gap: 0.75rem;
        }

        .mobile-home__section-header {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 1rem;
        }

        .mobile-home__section-kicker {
            margin: 0 0 0.25rem;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: #1d4ed8;
        }

        .mobile-home__section-title {
            margin: 0;
            font-size: 1.05rem;
            line-height: 1.2;
            color: var(--mobile-slate);
        }

        .dark .mobile-home__section-title {
            color: #f8fafc;
        }

        .mobile-home__cards {
            display: grid;
            gap: 0.75rem;
        }

        .mobile-link-card {
            display: grid;
            grid-template-columns: auto 1fr auto;
            align-items: center;
            gap: 0.9rem;
            padding: 1rem;
            border-radius: 1.3rem;
            border: 1px solid var(--mobile-border);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(248, 250, 252, 0.98));
            box-shadow: var(--mobile-shadow);
            text-decoration: none;
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
        }

        .mobile-link-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 22px 40px rgba(15, 23, 42, 0.12);
        }

        .dark .mobile-link-card {
            background: linear-gradient(180deg, rgba(15, 23, 42, 0.94), rgba(15, 23, 42, 0.88));
            border-color: rgba(71, 85, 105, 0.44);
            box-shadow: none;
        }

        .mobile-link-card__icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 3rem;
            height: 3rem;
            border-radius: 1rem;
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid rgba(191, 219, 254, 0.9);
        }

        .mobile-link-card__icon svg {
            width: 1.35rem;
            height: 1.35rem;
        }

        .mobile-link-card__content {
            display: grid;
            gap: 0.18rem;
            min-width: 0;
        }

        .mobile-link-card__title {
            font-size: 0.96rem;
            font-weight: 700;
            color: var(--mobile-ink);
        }

        .dark .mobile-link-card__title {
            color: #f8fafc;
        }

        .mobile-link-card__description {
            font-size: 0.82rem;
            line-height: 1.55;
            color: var(--mobile-muted);
        }

        .dark .mobile-link-card__description {
            color: #cbd5e1;
        }

        .mobile-link-card__arrow {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2.2rem;
            padding: 0.45rem 0.7rem;
            border-radius: 9999px;
            background: rgba(15, 23, 42, 0.05);
            color: #0f172a;
            font-size: 0.78rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .dark .mobile-link-card__arrow {
            background: rgba(255, 255, 255, 0.08);
            color: #f8fafc;
        }

        .mobile-link-card--slate .mobile-link-card__icon {
            background: #e2e8f0;
            color: #334155;
            border-color: #cbd5e1;
        }

        .mobile-link-card--amber .mobile-link-card__icon {
            background: #fef3c7;
            color: #b45309;
            border-color: #fde68a;
        }

        .mobile-link-card--sky .mobile-link-card__icon {
            background: #dbeafe;
            color: #2563eb;
            border-color: #bfdbfe;
        }

        .mobile-link-card--emerald .mobile-link-card__icon {
            background: #d1fae5;
            color: #047857;
            border-color: #a7f3d0;
        }

        .mobile-link-card--violet .mobile-link-card__icon {
            background: #ede9fe;
            color: #6d28d9;
            border-color: #ddd6fe;
        }

        .mobile-link-card--rose .mobile-link-card__icon {
            background: #ffe4e6;
            color: #be123c;
            border-color: #fecdd3;
        }

        .mobile-empty-state {
            padding: 1rem;
            border-radius: 1.1rem;
            border: 1px dashed rgba(148, 163, 184, 0.42);
            background: rgba(248, 250, 252, 0.9);
            font-size: 0.86rem;
            line-height: 1.6;
            color: #475569;
        }

        .dark .mobile-empty-state {
            background: rgba(15, 23, 42, 0.72);
            color: #cbd5e1;
            border-color: rgba(71, 85, 105, 0.5);
        }

        @media (min-width: 900px) {
            .mobile-home {
                max-width: 48rem;
                margin-inline: auto;
            }

            .mobile-home__cards {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .mobile-home__hero {
                padding: 1.2rem;
                border-radius: 1.45rem;
            }

            .mobile-link-card {
                grid-template-columns: auto 1fr;
            }

            .mobile-link-card__arrow {
                grid-column: 1 / -1;
                justify-self: start;
                margin-left: 3.9rem;
            }
        }
    </style>
</x-filament-panels::page>
