<x-filament-panels::page>
    <div
        class="notification-center"
        data-notification-center>

        <div class="nc-stats">
            <button type="button" class="nc-stat is-active" data-mode="todas">
                <span>Total</span>
                <strong data-stat="total">0</strong>
                <x-heroicon-o-inbox />
            </button>
            <button type="button" class="nc-stat" data-mode="ativas">
                <span>Não lidas</span>
                <strong data-stat="ativas">0</strong>
                <x-heroicon-o-envelope />
            </button>
            <button type="button" class="nc-stat" data-mode="historico">
                <span>Lidas</span>
                <strong data-stat="historico">0</strong>
                <x-heroicon-o-envelope-open />
            </button>
            <div class="nc-stat nc-stat--plain">
                <span>Hoje</span>
                <strong data-stat="hoje">0</strong>
                <x-heroicon-o-clock />
            </div>
            @if($canCreateNotifications)
            <button type="button" class="nc-stat" data-mode="enviadas">
                <span>Enviadas</span>
                <strong data-stat="enviadas">0</strong>
                <x-heroicon-o-megaphone />
            </button>
            @endif
        </div>

        <div class="nc-toolbar">
            <div class="nc-tabs" aria-label="Filtro rápido">
                <button type="button" class="is-active" data-tab-mode="todas">Todas</button>
                <button type="button" data-tab-mode="ativas">Não lidas</button>
                <button type="button" data-tab-mode="historico">Histórico</button>
                @if($canCreateNotifications)
                <button type="button" data-tab-mode="enviadas">Enviadas</button>
                @endif
            </div>

            <label class="nc-field nc-field--search">
                <span>Pesquisar</span>
                <div class="nc-input-wrap">
                    <x-heroicon-o-magnifying-glass />
                    <input type="search" data-filter="busca" placeholder="Título, mensagem ou público">
                </div>
            </label>

            <label class="nc-field">
                <span>Prioridade</span>
                <select data-filter="prioridade">
                    <option value="todas">Todas</option>
                    @foreach($priorityOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="nc-field">
                <span>Período</span>
                <select data-filter="periodo">
                    <option value="7">Últimos 7 dias</option>
                    <option value="30" selected>Últimos 30 dias</option>
                    <option value="90">Últimos 90 dias</option>
                    <option value="todos">Todo o histórico</option>
                </select>
            </label>

            <button type="button" class="nc-action nc-action--ghost" data-action="clear-filters">
                <x-heroicon-o-x-mark />
                <span>Limpar</span>
            </button>
        </div>

        <div class="nc-status" data-status hidden>
            <span class="nc-spinner"></span>
            <span data-status-text>Carregando notificações...</span>
        </div>

        <div class="nc-list" data-list></div>

        <div class="nc-pagination" data-pagination hidden>
            <span data-pagination-label>0 registros</span>
            <div class="nc-pagination__actions">
                <button type="button" class="nc-action nc-action--ghost" data-action="previous-page">
                    <x-heroicon-o-chevron-left />
                    <span>Anterior</span>
                </button>
                <strong data-pagination-page>1 / 1</strong>
                <button type="button" class="nc-action nc-action--ghost" data-action="next-page">
                    <span>Próxima</span>
                    <x-heroicon-o-chevron-right />
                </button>
            </div>
        </div>

        <audio data-notification-sound preload="auto">
            <source src="{{ $soundUrl }}" type="audio/mpeg">
        </audio>

        @if($canCreateNotifications)
        <div class="nc-modal" data-create-modal hidden aria-hidden="true">
            <div class="nc-modal__backdrop" data-action="close-create"></div>
            <form class="nc-modal__panel" data-create-form>
                <div class="nc-modal__header">
                    <div>
                        <p class="nc-kicker">Disparo manual</p>
                        <h3>Nova notificação</h3>
                    </div>
                    <button type="button" class="nc-icon-btn" data-action="close-create" aria-label="Fechar">
                        <x-heroicon-o-x-mark />
                    </button>
                </div>

                <div class="nc-form-grid">
                    <label class="nc-field nc-field--full">
                        <span>Título</span>
                        <input type="text" name="titulo" maxlength="120" required>
                    </label>

                    <label class="nc-field nc-field--full">
                        <span>Mensagem</span>
                        <textarea name="mensagem" rows="4" maxlength="1500" required></textarea>
                    </label>

                    <label class="nc-field">
                        <span>Prioridade</span>
                        <select name="prioridade" required>
                            @foreach($priorityOptions as $value => $label)
                            <option value="{{ $value }}" @selected($value==='normal' )>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="nc-field">
                        <span>Enviar para</span>
                        <select name="destino_tipo" data-destination-select required>
                            @foreach($destinationTypeOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="nc-field nc-field--full">
                        <span>Link de ação</span>
                        <input type="text" name="url" maxlength="2048" placeholder="https://...">
                    </label>

                    <label class="nc-field nc-field--full">
                        <span>Texto do botão</span>
                        <input type="text" name="label" maxlength="80" placeholder="Ver detalhes">
                    </label>

                    @foreach([
                    'usuarios' => 'Usuários',
                    'roles' => 'Níveis de acesso',
                    'escolas' => 'Escolas',
                    'turmas' => 'Turmas',
                    'permissoes' => 'Permissões',
                    ] as $group => $label)
                    <label class="nc-field nc-field--full" data-recipient-group="{{ $group }}" hidden>
                        <span>{{ $label }}</span>
                        <select multiple size="7" data-recipient-select="{{ $group }}">
                            @foreach($recipientOptions[$group] ?? [] as $option)
                            <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    @endforeach
                </div>

                <div class="nc-form-error" data-form-error hidden></div>

                <div class="nc-modal__footer">
                    <button type="button" class="nc-action nc-action--ghost" data-action="close-create">Cancelar</button>
                    <button type="submit" class="nc-action nc-action--primary">
                        <x-heroicon-o-paper-airplane />
                        <span>Enviar notificação</span>
                    </button>
                </div>
            </form>
        </div>
        @endif

        <style>
            .notification-center,
            .notification-center * {
                box-sizing: border-box;
            }

            .notification-center {
                --nc-ink: #111827;
                --nc-muted: #667085;
                --nc-line: #d7deea;
                --nc-surface: #fff;
                --nc-soft: #f7f9fc;
                --nc-primary: #17368d;
                --nc-primary-soft: #eef4ff;
                --nc-green: #0f766e;
                --nc-amber: #b45309;
                --nc-red: #b91c1c;
                display: flex;
                flex-direction: column;
                gap: 12px;
                color: var(--nc-ink);
            }

            .nc-header,
            .nc-toolbar,
            .nc-card,
            .nc-empty,
            .nc-status {
                border: 1px solid var(--nc-line);
                border-radius: 8px;
                background: var(--nc-surface);
            }

            .nc-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                padding: 16px;
            }

            .nc-header__main,
            .nc-header__actions,
            .nc-card__meta,
            .nc-card__actions,
            .nc-card__top,
            .nc-inline {
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .nc-header__main {
                min-width: 0;
                gap: 12px;
            }

            .nc-header__actions {
                flex-wrap: wrap;
                justify-content: flex-end;
            }

            .nc-header__icon {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 42px;
                height: 42px;
                flex: 0 0 42px;
                border-radius: 8px;
                background: var(--nc-primary-soft);
                color: var(--nc-primary);
            }

            .nc-header svg,
            .nc-action svg,
            .nc-stat svg,
            .nc-input-wrap svg,
            .nc-card svg,
            .nc-icon-btn svg {
                width: 17px;
                height: 17px;
                flex: 0 0 auto;
            }

            .nc-header h2,
            .nc-modal h3 {
                margin: 0;
                font-size: 1.2rem;
                font-weight: 760;
            }

            .nc-kicker,
            .nc-subtitle {
                margin: 0;
                color: var(--nc-muted);
            }

            .nc-kicker {
                font-size: .72rem;
                font-weight: 760;
                text-transform: uppercase;
            }

            .nc-subtitle {
                font-size: .86rem;
            }

            .nc-stats {
                display: grid;
                grid-template-columns: repeat(5, minmax(0, 1fr));
                gap: 10px;
            }

            .nc-stat {
                position: relative;
                min-height: 72px;
                padding: 11px 12px;
                border: 1px solid var(--nc-line);
                border-radius: 8px;
                background: var(--nc-surface);
                color: var(--nc-ink);
                text-align: left;
                cursor: pointer;
            }

            .nc-stat--plain {
                cursor: default;
            }

            .nc-stat.is-active,
            .nc-stat:not(.nc-stat--plain):hover {
                border-color: var(--nc-primary);
                background: var(--nc-primary-soft);
            }

            .nc-stat span {
                display: block;
                color: var(--nc-muted);
                font-size: .74rem;
                font-weight: 740;
            }

            .nc-stat strong {
                display: block;
                margin-top: 7px;
                font-size: 1.35rem;
                line-height: 1;
            }

            .nc-stat svg {
                position: absolute;
                right: 12px;
                bottom: 12px;
                color: #98a2b3;
            }

            .nc-toolbar {
                display: grid;
                grid-template-columns: auto minmax(220px, 1fr) minmax(145px, 180px) minmax(145px, 180px) auto;
                align-items: end;
                gap: 10px;
                padding: 12px;
                background: var(--nc-soft);
            }

            .nc-tabs {
                display: inline-flex;
                gap: 3px;
                min-height: 40px;
                padding: 3px;
                border: 1px solid #cbd5e1;
                border-radius: 8px;
                background: #fff;
            }

            .nc-tabs button {
                min-width: 78px;
                border: 0;
                border-radius: 6px;
                background: transparent;
                color: #475467;
                font-size: .82rem;
                font-weight: 740;
                cursor: pointer;
            }

            .nc-tabs button.is-active {
                background: var(--nc-primary);
                color: #fff;
            }

            .nc-field {
                display: flex;
                flex-direction: column;
                gap: 5px;
                min-width: 0;
                color: var(--nc-muted);
                font-size: .74rem;
                font-weight: 740;
            }

            .nc-field input,
            .nc-field textarea,
            .nc-field select,
            .nc-input-wrap {
                width: 100%;
                min-height: 38px;
                border: 1px solid #cbd5e1;
                border-radius: 8px;
                background: #fff;
                color: var(--nc-ink);
                font-size: .88rem;
                outline: 0;
            }

            .nc-field input,
            .nc-field textarea,
            .nc-field select {
                padding: 8px 10px;
            }

            .nc-field select[multiple] {
                min-height: 150px;
            }

            .nc-input-wrap {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 0 10px;
                color: var(--nc-muted);
            }

            .nc-input-wrap input {
                min-height: 0;
                padding: 0;
                border: 0;
                border-radius: 0;
            }

            .nc-list {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }

            .nc-card {
                display: grid;
                grid-template-columns: minmax(0, 1fr) minmax(180px, auto);
                align-items: center;
                gap: 14px;
                width: 100%;
                min-height: 78px;
                padding: 12px 14px;
                border-left-width: 4px;
            }

            .nc-card.is-unread {
                border-left-color: var(--nc-primary);
                background: #f3f7ff;
            }

            .nc-card.is-read,
            .nc-card.is-sent {
                border-left-color: #cbd5e1;
                background: #fff;
            }

            .nc-card__main {
                min-width: 0;
            }

            .nc-card__top {
                flex-wrap: wrap;
                margin-bottom: 3px;
                color: var(--nc-muted);
                font-size: .74rem;
                font-weight: 740;
            }

            .nc-card__title {
                color: var(--nc-ink);
                font-size: .92rem;
                font-weight: 760;
            }

            .nc-card__message {
                display: -webkit-box;
                margin: 0;
                overflow: hidden;
                color: #475467;
                font-size: .84rem;
                line-height: 1.35;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
            }

            .nc-card__meta {
                flex-wrap: wrap;
                margin-top: 6px;
                color: #7b8798;
                font-size: .73rem;
            }

            .nc-card__meta span,
            .nc-badge {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                min-width: 0;
            }

            .nc-card__actions {
                align-self: center;
                justify-content: flex-end;
                flex-wrap: wrap;
                max-width: 360px;
            }

            .nc-badge {
                min-height: 22px;
                padding: 2px 8px;
                border-radius: 999px;
                background: #eef2f7;
                color: #344054;
                font-size: .72rem;
                font-weight: 760;
            }

            .nc-badge--urgente {
                background: #fef2f2;
                color: var(--nc-red);
            }

            .nc-badge--alta {
                background: #fffbeb;
                color: var(--nc-amber);
            }

            .nc-badge--informativa {
                background: #eef6fc;
                color: var(--nc-primary);
            }

            .nc-action {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
                min-height: 34px;
                padding: 7px 10px;
                border: 1px solid transparent;
                border-radius: 8px;
                font-size: .82rem;
                font-weight: 760;
                text-decoration: none;
                cursor: pointer;
            }

            .nc-action--primary {
                background: var(--nc-primary);
                color: #fff;
            }

            .nc-action--ghost {
                border-color: #cbd5e1;
                background: #fff;
                color: #344054;
            }

            .nc-action--link {
                background: var(--nc-primary-soft);
                color: var(--nc-primary);
            }

            .nc-action:disabled {
                cursor: wait;
                opacity: .62;
            }

            .nc-status {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                width: fit-content;
                padding: 7px 10px;
                color: var(--nc-muted);
                font-size: .78rem;
            }

            .nc-status[hidden],
            .nc-pagination[hidden],
            .nc-modal[hidden],
            [hidden] {
                display: none !important;
            }

            .nc-spinner {
                width: 14px;
                height: 14px;
                border: 2px solid #d0d5dd;
                border-top-color: var(--nc-primary);
                border-radius: 999px;
                animation: nc-spin .7s linear infinite;
            }

            @keyframes nc-spin {
                to {
                    transform: rotate(360deg);
                }
            }

            .nc-empty {
                grid-column: 1 / -1;
                padding: 30px 16px;
                text-align: center;
                color: var(--nc-muted);
            }

            .nc-empty strong {
                display: block;
                margin-bottom: 4px;
                color: var(--nc-ink);
            }

            .nc-pagination {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                padding: 10px 12px;
                border: 1px solid var(--nc-line);
                border-radius: 8px;
                background: #fff;
                color: var(--nc-muted);
                font-size: .82rem;
                font-weight: 700;
            }

            .nc-pagination__actions {
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }

            .nc-pagination__actions strong {
                min-width: 68px;
                text-align: center;
                color: var(--nc-ink);
                font-size: .84rem;
            }

            .nc-card.is-updating {
                position: relative;
                opacity: .72;
                transform: scale(.995);
                transition: opacity .16s ease, transform .16s ease;
            }

            .nc-card.is-updating::after {
                content: '';
                position: absolute;
                inset: 0;
                border-radius: 8px;
                background: rgba(238, 244, 255, .5);
                pointer-events: none;
            }

            .nc-card.is-done {
                animation: nc-done .45s ease;
            }

            @keyframes nc-done {
                0% {
                    box-shadow: 0 0 0 0 rgba(15, 118, 110, .28);
                }

                100% {
                    box-shadow: 0 0 0 8px rgba(15, 118, 110, 0);
                }
            }

            .nc-card .nc-action.is-loading {
                background: var(--nc-primary);
                color: #fff;
                pointer-events: none;
            }

            .nc-card .nc-action.is-loading svg {
                animation: nc-spin .7s linear infinite;
            }

            .nc-modal {
                position: fixed;
                inset: 0;
                z-index: 99999;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 18px;
            }

            .nc-modal__backdrop {
                position: absolute;
                inset: 0;
                background: rgba(15, 23, 42, .48);
            }

            .nc-modal__panel {
                position: relative;
                z-index: 1;
                width: min(760px, 100%);
                max-height: 92vh;
                overflow: auto;
                padding: 16px;
                border-radius: 8px;
                background: #fff;
                box-shadow: 0 24px 64px rgba(15, 23, 42, .24);
            }

            .nc-modal__header,
            .nc-modal__footer {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
            }

            .nc-modal__footer {
                justify-content: flex-end;
                margin-top: 14px;
            }

            .nc-form-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
                margin-top: 14px;
            }

            .nc-field--full {
                grid-column: 1 / -1;
            }

            .nc-icon-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 34px;
                height: 34px;
                border: 1px solid #cbd5e1;
                border-radius: 8px;
                background: #fff;
                color: #475467;
                cursor: pointer;
            }

            .nc-form-error {
                margin-top: 10px;
                padding: 10px 12px;
                border: 1px solid #fecaca;
                border-radius: 8px;
                background: #fef2f2;
                color: var(--nc-red);
                font-size: .84rem;
                font-weight: 700;
            }

            @media (max-width: 1180px) {

                .nc-stats,
                .nc-toolbar {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }

                .nc-tabs {
                    grid-column: 1 / -1;
                }
            }

            @media (max-width: 760px) {

                .nc-header,
                .nc-card,
                .nc-modal__header,
                .nc-modal__footer {
                    align-items: stretch;
                    flex-direction: column;
                }

                .nc-stats,
                .nc-toolbar,
                .nc-list,
                .nc-form-grid {
                    grid-template-columns: 1fr;
                }

                .nc-card {
                    display: flex;
                }

                .nc-card__actions,
                .nc-action {
                    width: 100%;
                    max-width: none;
                }
            }
        </style>

        <script>
            (() => {
                const root = document.querySelector('[data-notification-center]');

                if (!root || root.dataset.ready === '1') {
                    return;
                }

                root.dataset.ready = '1';

                const config = @js([
                    'endpoints' => $endpoints,
                    'canCreate' => $canCreateNotifications,
                    'pollIntervalMs' => $pollIntervalMs,
                    'soundUrl' => $soundUrl,
                ]);

                const state = {
                    modo: 'todas',
                    busca: '',
                    prioridade: 'todas',
                    periodo: '30',
                    page: 1,
                    per_page: 18,
                };

                const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                const list = root.querySelector('[data-list]');
                const status = root.querySelector('[data-status]');
                const statusText = root.querySelector('[data-status-text]');
                const pagination = root.querySelector('[data-pagination]');
                const paginationLabel = root.querySelector('[data-pagination-label]');
                const paginationPage = root.querySelector('[data-pagination-page]');
                const markAllButton = document.querySelector('[data-nc-action="mark-all-read"]');
                const audio = root.querySelector('[data-notification-sound]');
                const modal = root.querySelector('[data-create-modal]');
                const form = root.querySelector('[data-create-form]');
                const formError = root.querySelector('[data-form-error]');
                let searchTimer = null;
                let loadVersion = 0;
                let lastChangeToken = null;
                let lastUnreadPollToken = null;
                let soundReady = false;
                let suppressNextSound = false;

                const escapeHtml = (value) => String(value ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');

                const svg = {
                    check: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>',
                    envelope: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5A2.25 2.25 0 0 1 19.5 19.5h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0-9.75 6-9.75-6"/></svg>',
                    external: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H18m0 0v4.5M18 6 9.75 14.25M6 7.5v10.5h10.5"/></svg>',
                    calendar: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3.75 8.25h16.5M5.25 5.25h13.5A1.5 1.5 0 0 1 20.25 6.75v12A1.5 1.5 0 0 1 18.75 20.25H5.25A1.5 1.5 0 0 1 3.75 18.75v-12A1.5 1.5 0 0 1 5.25 5.25Z"/></svg>',
                    users: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.5a6 6 0 0 0-12 0m12 0h6m-6 0a6 6 0 0 0-9 0m7.5-10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 1.5a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/></svg>',
                };

                const priorityClass = (priority) => `nc-badge nc-badge--${priority || 'normal'}`;

                const setStatus = (message = '', loading = false, visible = true) => {
                    status.hidden = !visible;
                    status.querySelector('.nc-spinner').hidden = !loading;
                    statusText.textContent = message;
                };

                const updateBadge = (count) => {
                    window.dispatchEvent(new CustomEvent('gestaoedu:notifications-count', {
                        detail: {
                            unread: count
                        },
                    }));
                };

                const rememberUnreadCounter = (counter, fallbackUnread = 0) => {
                    const unread = Number(counter?.unread ?? fallbackUnread ?? 0);
                    const token = counter?.change_token ?? null;

                    updateBadge(unread);

                    if (token) {
                        lastUnreadPollToken = token;
                    }
                };

                const playNotificationSound = () => {
                    if (!audio || !soundReady) {
                        return;
                    }

                    if (window.__gestaoEduLastNotificationSoundAt && Date.now() - window.__gestaoEduLastNotificationSoundAt < 3000) {
                        return;
                    }

                    audio.currentTime = 0;
                    window.__gestaoEduLastNotificationSoundAt = Date.now();
                    audio.play().catch(() => {});
                };

                const handleSoundSignal = (stats, silent) => {
                    const token = stats?.change_token ?? null;

                    if (!token) {
                        return;
                    }

                    if (lastChangeToken === null) {
                        lastChangeToken = token;
                        return;
                    }

                    if (token !== lastChangeToken) {
                        lastChangeToken = token;

                        if (silent && !suppressNextSound) {
                            playNotificationSound();
                        }
                    }

                    suppressNextSound = false;
                };

                const request = async (url, options = {}) => {
                    const response = await fetch(url, {
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            ...(options.body ? {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrf
                            } : {}),
                            ...(options.headers ?? {}),
                        },
                        ...options,
                    });

                    const payload = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        const message = payload.message ||
                            Object.values(payload.errors ?? {})?.flat()?.[0] ||
                            'Não foi possível concluir a operação.';

                        throw new Error(message);
                    }

                    return payload;
                };

                const load = async ({
                    silent = false
                } = {}) => {
                    const version = ++loadVersion;

                    if (!silent) {
                        setStatus('Carregando notificações...', true);
                    }

                    const params = new URLSearchParams(state);

                    try {
                        const data = await request(`${config.endpoints.index}?${params.toString()}`);

                        if (version !== loadVersion) {
                            return;
                        }

                        state.modo = data.mode;
                        renderStats(data.stats ?? {});
                        renderItems(data.items ?? []);
                        renderPagination(data.pagination ?? {});
                        handleSoundSignal(data.stats ?? {}, silent);
                        setStatus('', false, false);
                        if (markAllButton) {
                            markAllButton.style.display = Number(data.stats?.ativas ?? 0) > 0 ? 'inline-flex' : 'none';
                        }
                        updateModeButtons();
                        rememberUnreadCounter(data.unread_counter ?? null, data.stats?.ativas ?? 0);
                    } catch (error) {
                        if (version !== loadVersion) {
                            return;
                        }

                        setStatus(error.message, false, true);
                    }
                };

                const refreshIfChanged = async () => {
                    if (document.visibilityState !== 'visible') {
                        return;
                    }

                    try {
                        const data = await request(config.endpoints.unreadCount);
                        const token = data.change_token ?? null;

                        updateBadge(Number(data.unread ?? 0));

                        if (lastUnreadPollToken === null) {
                            lastUnreadPollToken = token;
                            return;
                        }

                        if (token && token !== lastUnreadPollToken) {
                            lastUnreadPollToken = token;
                            await load({
                                silent: true
                            });
                        }
                    } catch (error) {
                        setStatus(error.message, false, true);
                    }
                };

                const renderStats = (stats) => {
                    root.querySelectorAll('[data-stat]').forEach((element) => {
                        element.textContent = stats[element.dataset.stat] ?? 0;
                    });
                };

                const renderPagination = (data) => {
                    const total = Number(data.total ?? 0);
                    const page = Number(data.page ?? 1);
                    const lastPage = Number(data.last_page ?? 1);
                    const from = Number(data.from ?? 0);
                    const to = Number(data.to ?? 0);

                    state.page = page;
                    pagination.hidden = false;
                    paginationLabel.textContent = total > 0 ?
                        `Mostrando ${from} a ${to} de ${total}` :
                        '0 registros';
                    paginationPage.textContent = `${page} / ${lastPage}`;
                    root.querySelector('[data-action="previous-page"]').disabled = !data.has_previous;
                    root.querySelector('[data-action="next-page"]').disabled = !data.has_next;
                };

                const renderItems = (items) => {
                    if (!items.length) {
                        list.innerHTML = '<div class="nc-empty"><strong>Nenhum registro encontrado</strong><span>Ajuste os filtros ou altere a visualização.</span></div>';
                        return;
                    }

                    list.innerHTML = items
                        .map((item) => item.kind === 'sent' ? sentCard(item) : notificationCard(item))
                        .join('');
                };

                const notificationCard = (item) => {
                    const statusLabel = item.lida ? 'Lida' : 'Não lida';
                    const readAction = item.lida ?
                        `<button type="button" class="nc-action nc-action--ghost" data-action="mark-unread" data-id="${escapeHtml(item.id)}">${svg.envelope}<span>Ativar</span></button>` :
                        `<button type="button" class="nc-action nc-action--ghost" data-action="mark-read" data-id="${escapeHtml(item.id)}">${svg.check}<span>Lida</span></button>`;
                    const link = item.url ?
                        `<a class="nc-action nc-action--link" href="${escapeHtml(item.url)}" target="_blank" rel="noopener noreferrer" data-action="open-link" data-id="${escapeHtml(item.id)}">${svg.external}<span>${escapeHtml(item.label)}</span></a>` :
                        '';
                    const extraMeta = [
                        item.escopo ? `${svg.users}<span>${escapeHtml(item.escopo)}</span>` : '',
                        item.enviado_por_nome ? `<span>${escapeHtml(item.enviado_por_nome)}</span>` : '',
                        item.lida_em ? `<span>Lida em ${escapeHtml(item.lida_em)}</span>` : '',
                    ].filter(Boolean).join('');

                    return `
                    <article class="nc-card ${item.lida ? 'is-read' : 'is-unread'}" data-id="${escapeHtml(item.id)}">
                        <div class="nc-card__main">
                            <div class="nc-card__top">
                                <span class="nc-card__title">${escapeHtml(item.titulo)}</span>
                                <span class="${priorityClass(item.prioridade)}">${escapeHtml(item.prioridade_label)}</span>
                                <span>${statusLabel}</span>
                                <span>${escapeHtml(item.criada_em_humano)}</span>
                            </div>
                            <p class="nc-card__message">${escapeHtml(item.mensagem)}</p>
                            <div class="nc-card__meta">
                                <span>${svg.calendar}${escapeHtml(item.criada_em)}</span>
                                ${extraMeta}
                            </div>
                        </div>
                        <div class="nc-card__actions">
                            ${link}
                            ${readAction}
                        </div>
                    </article>
                `;
                };

                const sentCard = (item) => `
                <article class="nc-card is-sent" data-id="${escapeHtml(item.id)}">
                    <div class="nc-card__main">
                        <div class="nc-card__top">
                            <span class="nc-card__title">${escapeHtml(item.titulo)}</span>
                            <span class="${priorityClass(item.prioridade)}">${escapeHtml(item.prioridade_label)}</span>
                            <span>${escapeHtml(item.destinatarios_count)} destinatário(s)</span>
                            <span>${escapeHtml(item.criada_em_humano)}</span>
                        </div>
                        <p class="nc-card__message">${escapeHtml(item.mensagem)}</p>
                        <div class="nc-card__meta">
                            <span>${svg.users}${escapeHtml(item.destino_label)}</span>
                            <span>${escapeHtml(item.autor)}</span>
                            <span>${svg.calendar}${escapeHtml(item.criada_em)}</span>
                        </div>
                    </div>
                    <div class="nc-card__actions">
                        ${item.url ? `<a class="nc-action nc-action--link" href="${escapeHtml(item.url)}" target="_blank" rel="noopener noreferrer">${svg.external}<span>${escapeHtml(item.label)}</span></a>` : ''}
                    </div>
                </article>
            `;

                const updateModeButtons = () => {
                    root.querySelectorAll('[data-mode], [data-tab-mode]').forEach((button) => {
                        const mode = button.dataset.mode || button.dataset.tabMode;
                        button.classList.toggle('is-active', mode === state.modo);
                    });
                };

                const setMode = (mode) => {
                    state.modo = mode;
                    state.page = 1;
                    updateModeButtons();
                    load();
                };

                const mark = async (id, read, trigger = null) => {
                    const action = read ? 'mark-read' : 'mark-unread';
                    const url = `${config.endpoints.markReadBase}/${encodeURIComponent(id)}/${action}`;
                    const card = root.querySelector(`.nc-card[data-id="${CSS.escape(id)}"]`);
                    const label = trigger?.querySelector('span');
                    const originalLabel = label?.textContent;

                    suppressNextSound = true;
                    window.__gestaoEduSuppressNotificationSoundUntil = Date.now() + 3000;
                    card?.classList.add('is-updating');
                    trigger?.classList.add('is-loading');
                    trigger?.setAttribute('disabled', 'disabled');

                    if (label) {
                        label.textContent = 'Salvando';
                    }

                    try {
                        await request(url, {
                            method: 'POST',
                            body: '{}'
                        });
                        card?.classList.add('is-done');
                        await load({
                            silent: true
                        });
                    } catch (error) {
                        setStatus(error.message, false, true);
                        card?.classList.remove('is-updating');
                        trigger?.classList.remove('is-loading');
                        trigger?.removeAttribute('disabled');

                        if (label && originalLabel) {
                            label.textContent = originalLabel;
                        }
                    }
                };

                const syncRecipientGroups = () => {
                    const value = root.querySelector('[data-destination-select]')?.value ?? 'todos';
                    const map = {
                        usuarios: 'usuarios',
                        roles: 'roles',
                        escolas: 'escolas',
                        professores_turmas: 'turmas',
                        permissoes: 'permissoes',
                    };

                    root.querySelectorAll('[data-recipient-group]').forEach((group) => {
                        group.hidden = group.dataset.recipientGroup !== map[value];
                    });
                };

                const closeCreateModal = () => {
                    if (!modal) {
                        return;
                    }

                    modal.hidden = true;
                    modal.setAttribute('aria-hidden', 'true');
                    formError.hidden = true;
                };

                const openCreateModal = () => {
                    if (!modal) {
                        return;
                    }

                    modal.hidden = false;
                    modal.setAttribute('aria-hidden', 'false');
                    syncRecipientGroups();
                    modal.querySelector('input[name="titulo"]')?.focus();
                };

                const selectedValues = (name) => Array.from(root.querySelector(`[data-recipient-select="${name}"]`)?.selectedOptions ?? [])
                    .map((option) => option.value);

                const submitCreate = async (event) => {
                    event.preventDefault();

                    const submit = form.querySelector('button[type="submit"]');
                    const formData = new FormData(form);
                    const data = {
                        titulo: formData.get('titulo'),
                        mensagem: formData.get('mensagem'),
                        prioridade: formData.get('prioridade'),
                        destino_tipo: formData.get('destino_tipo'),
                        url: formData.get('url'),
                        label: formData.get('label'),
                        usuarios_ids: selectedValues('usuarios'),
                        roles_ids: selectedValues('roles'),
                        escolas_ids: selectedValues('escolas'),
                        turmas_ids: selectedValues('turmas'),
                        permissoes: selectedValues('permissoes'),
                    };

                    submit.disabled = true;
                    formError.hidden = true;

                    try {
                        await request(config.endpoints.send, {
                            method: 'POST',
                            body: JSON.stringify(data),
                        });

                        form.reset();
                        closeCreateModal();
                        state.modo = 'todas';
                        state.page = 1;
                        await load();
                    } catch (error) {
                        formError.textContent = error.message;
                        formError.hidden = false;
                    } finally {
                        submit.disabled = false;
                    }
                };

                document.addEventListener('click', async (event) => {
                    const target = event.target.closest('[data-mode], [data-tab-mode], [data-action], [data-nc-action]');

                    if (!target) {
                        return;
                    }

                    const action = target.dataset.ncAction || target.dataset.action;

                    const isInsideNotificationCenter = root.contains(target);
                    const isHeaderAction = ['mark-all-read', 'open-create'].includes(action);

                    if (!isInsideNotificationCenter && !isHeaderAction) {
                        return;
                    }

                    if (isHeaderAction) {
                        event.preventDefault();
                    }

                    if (target.dataset.mode || target.dataset.tabMode) {
                        setMode(target.dataset.mode || target.dataset.tabMode);
                        return;
                    }

                    if (action === 'clear-filters') {
                        state.busca = '';
                        state.prioridade = 'todas';
                        state.periodo = '30';
                        state.page = 1;
                        root.querySelector('[data-filter="busca"]').value = '';
                        root.querySelector('[data-filter="prioridade"]').value = 'todas';
                        root.querySelector('[data-filter="periodo"]').value = '30';
                        load();
                    } else if (action === 'previous-page') {
                        state.page = Math.max(1, state.page - 1);
                        load();
                    } else if (action === 'next-page') {
                        state.page += 1;
                        load();
                    } else if (action === 'mark-read') {
                        await mark(target.dataset.id, true, target);
                    } else if (action === 'mark-unread') {
                        await mark(target.dataset.id, false, target);
                    } else if (action === 'open-link') {
                        suppressNextSound = true;
                        window.__gestaoEduSuppressNotificationSoundUntil = Date.now() + 3000;
                        mark(target.dataset.id, true);
                    } else if (action === 'mark-all-read') {
                        suppressNextSound = true;
                        window.__gestaoEduSuppressNotificationSoundUntil = Date.now() + 3000;
                        target.disabled = true;
                        try {
                            await request(config.endpoints.markAllRead, {
                                method: 'POST',
                                body: '{}'
                            });
                            state.page = 1;
                            await load({
                                silent: true
                            });
                        } finally {
                            target.disabled = false;
                        }
                    } else if (action === 'open-create') {
                        openCreateModal();
                    } else if (action === 'close-create') {
                        closeCreateModal();
                    }
                });

                root.querySelectorAll('[data-filter]').forEach((field) => {
                    field.addEventListener('input', () => {
                        const apply = () => {
                            state[field.dataset.filter] = field.value;
                            state.page = 1;
                            load();
                        };

                        if (field.dataset.filter === 'busca') {
                            clearTimeout(searchTimer);
                            searchTimer = setTimeout(apply, 250);
                        } else {
                            apply();
                        }
                    });
                });

                root.querySelector('[data-destination-select]')?.addEventListener('change', syncRecipientGroups);
                form?.addEventListener('submit', submitCreate);

                window.addEventListener('pointerdown', () => {
                    soundReady = true;
                }, {
                    once: true
                });

                window.addEventListener('keydown', () => {
                    soundReady = true;
                }, {
                    once: true
                });

                window.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        closeCreateModal();
                    }
                });

                load();

                setInterval(() => {
                    refreshIfChanged();
                }, Math.max(15000, Number(config.pollIntervalMs ?? 30000)));

                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'visible') {
                        refreshIfChanged();
                    }
                });
            })();
        </script>
    </div>
</x-filament-panels::page>
