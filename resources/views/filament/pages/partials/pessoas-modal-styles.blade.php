<style>
    /* ========== Pessoas: página + slide-over / modais ========== */
    .pe-pessoas-page {
        gap: 1rem;
    }

    .pe-pessoas-page .fi-ta-ctn,
    .pe-pessoas-page .fi-section {
        border-radius: 1.1rem;
        border-color: #dbe4ee;
        box-shadow: 0 10px 28px rgba(15, 23, 42, 0.05);
    }

    .dark .pe-pessoas-page .fi-ta-ctn,
    .dark .pe-pessoas-page .fi-section {
        border-color: #1e293b;
        box-shadow: none;
    }

    /* Slide-over / modal shell */
    .fi-modal-window,
    .fi-slide-over-window {
        border-radius: 1.15rem !important;
        overflow: hidden;
        box-shadow: 0 24px 60px rgba(15, 23, 42, 0.22) !important;
    }

    .fi-modal-header,
    .fi-slide-over-header {
        padding: 1.1rem 1.25rem !important;
        border-bottom: 1px solid #e2e8f0;
        background:
            linear-gradient(135deg, rgba(23, 54, 141, 0.06), transparent 55%),
            #f8fafc;
    }

    .dark .fi-modal-header,
    .dark .fi-slide-over-header {
        border-bottom-color: #1e293b;
        background:
            linear-gradient(135deg, rgba(58, 109, 214, 0.12), transparent 55%),
            rgba(15, 23, 42, 0.95);
    }

    .fi-modal-heading,
    .fi-slide-over-heading {
        font-size: 1.05rem !important;
        font-weight: 700 !important;
        letter-spacing: -0.01em;
        color: #0f172a !important;
    }

    .dark .fi-modal-heading,
    .dark .fi-slide-over-heading {
        color: #f8fafc !important;
    }

    .fi-modal-content,
    .fi-slide-over-content {
        padding: 1rem 1.15rem 1.25rem !important;
        background: #fff;
    }

    .dark .fi-modal-content,
    .dark .fi-slide-over-content {
        background: rgba(15, 23, 42, 0.96);
    }

    .fi-modal-footer,
    .fi-slide-over-footer {
        padding: 0.85rem 1.15rem !important;
        border-top: 1px solid #e2e8f0;
        background: #f8fafc;
        gap: 0.6rem;
        flex-wrap: wrap;
    }

    .dark .fi-modal-footer,
    .dark .fi-slide-over-footer {
        border-top-color: #1e293b;
        background: rgba(15, 23, 42, 0.9);
    }

    /* Modal de Pessoas alinhado ao padrão de formulários do CRM. */
    .pessoa-modal-window {
        border-radius: 1.5rem !important;
        overflow: hidden;
        border: 1px solid rgba(148, 163, 184, 0.22);
        box-shadow: 0 28px 80px rgba(15, 23, 42, 0.16) !important;
    }

    .pessoa-modal-window .fi-modal-header {
        padding: 1.5rem 1.5rem 1rem !important;
        border-bottom: 1px solid rgba(226, 232, 240, 0.9);
        background:
            linear-gradient(180deg, rgba(248, 250, 252, 0.98), rgba(255, 255, 255, 0.98)),
            linear-gradient(135deg, rgba(15, 23, 42, 0.04), rgba(59, 130, 246, 0.08));
    }

    .pessoa-modal-window .fi-modal-content {
        padding: 1.25rem 1.5rem 1.5rem !important;
        background:
            radial-gradient(circle at top right, rgba(59, 130, 246, 0.08), transparent 24%),
            linear-gradient(180deg, rgba(248, 250, 252, 0.92), rgba(255, 255, 255, 1));
    }

    .pessoa-modal-window .fi-modal-footer {
        border-top: 1px solid rgba(226, 232, 240, 0.9);
        background: rgba(255, 255, 255, 0.98);
    }

    .pessoa-modal-window .fi-sc-tabs {
        overflow: hidden;
        border: 1px solid rgba(226, 232, 240, 0.92);
        border-radius: 1rem;
        background: rgba(255, 255, 255, 0.98);
        box-shadow: 0 14px 32px rgba(15, 23, 42, 0.04);
    }

    .pessoa-modal-window .fi-sc-tabs > .fi-tabs {
        padding-inline: 0.65rem;
        border-bottom: 1px solid rgba(226, 232, 240, 0.92);
        background: rgba(248, 250, 252, 0.9);
    }

    .pessoa-modal-window .fi-sc-tabs-tab {
        padding: 1.25rem 1.5rem 1.5rem;
    }

    .pessoa-modal-window .fi-sc-component > .fi-section,
    .pessoa-modal-window .fi-sc-component > .fi-section-content-ctn > .fi-section {
        position: relative;
        overflow: visible;
        border-radius: 1.25rem;
        border: 1px solid rgba(226, 232, 240, 0.92);
        box-shadow: 0 14px 32px rgba(15, 23, 42, 0.05);
        background: rgba(255, 255, 255, 0.96);
    }

    .dark .pessoa-modal-window .fi-modal-header,
    .dark .pessoa-modal-window .fi-modal-content,
    .dark .pessoa-modal-window .fi-modal-footer,
    .dark .pessoa-modal-window .fi-sc-tabs,
    .dark .pessoa-modal-window .fi-sc-tabs-tab {
        border-color: rgba(148, 163, 184, 0.16);
        background: rgba(15, 23, 42, 0.94);
    }

    .dark .pessoa-modal-window .fi-sc-tabs > .fi-tabs {
        border-color: rgba(148, 163, 184, 0.16);
        background: rgba(30, 41, 59, 0.88);
    }

    /* Sections do formulário de pessoa */
    .fi-slide-over-content .fi-section,
    .fi-modal-content .fi-section {
        border-radius: 0.95rem;
        border: 1px solid #e2e8f0;
        background: #fbfdff;
        margin-bottom: 0.75rem;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    .fi-slide-over-content .fi-section:hover,
    .fi-modal-content .fi-section:hover {
        border-color: #cbd5e1;
        box-shadow: 0 6px 16px rgba(15, 23, 42, 0.04);
    }

    .dark .fi-slide-over-content .fi-section,
    .dark .fi-modal-content .fi-section {
        border-color: #1e293b;
        background: rgba(15, 23, 42, 0.55);
    }

    .fi-slide-over-content .fi-section-header-heading,
    .fi-modal-content .fi-section-header-heading {
        font-size: 0.95rem !important;
        font-weight: 700 !important;
    }

    .fi-slide-over-content .fi-section-header-description,
    .fi-modal-content .fi-section-header-description {
        font-size: 0.8rem !important;
        line-height: 1.45 !important;
        color: #64748b !important;
    }

    /* Repeaters mais legíveis */
    .fi-slide-over-content .fi-fo-repeater-item,
    .fi-modal-content .fi-fo-repeater-item {
        border-radius: 0.85rem !important;
        border: 1px solid #e2e8f0 !important;
        background: #fff !important;
        padding: 0.75rem !important;
        margin-bottom: 0.55rem !important;
    }

    .dark .fi-slide-over-content .fi-fo-repeater-item,
    .dark .fi-modal-content .fi-fo-repeater-item {
        border-color: #334155 !important;
        background: rgba(15, 23, 42, 0.75) !important;
    }

    .fi-slide-over-content .fi-fo-repeater-item-header,
    .fi-modal-content .fi-fo-repeater-item-header {
        gap: 0.5rem;
    }

    /* Matrículas e escolas: repeater hierárquico com navegação em abas. */
    .pe-tabbed-repeater > .fi-fo-repeater-items {
        display: grid !important;
        grid-template-columns: repeat(var(--pe-tab-count, 1), minmax(0, 1fr));
        grid-template-rows: auto auto;
        gap: 0 !important;
        overflow: hidden;
        border: 1px solid #dbe4ee;
        border-radius: 0.9rem;
        background: #fff;
    }

    .pe-tabbed-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item {
        display: contents !important;
    }

    .pe-tabbed-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item > .fi-fo-repeater-item-header {
        grid-row: 1;
        min-width: 0;
        padding: 0.7rem 0.85rem;
        border: 0;
        border-right: 1px solid #dbe4ee;
        border-bottom: 1px solid #dbe4ee;
        border-radius: 0;
        background: #f8fafc;
        color: #475569;
        cursor: pointer;
        transition: background-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
    }

    .pe-tabbed-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item:last-child > .fi-fo-repeater-item-header {
        border-right: 0;
    }

    .pe-tabbed-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item.pe-tab-active > .fi-fo-repeater-item-header {
        background: #fff;
        color: #17368d;
        box-shadow: inset 0 3px 0 #1a6bc7;
    }

    .pe-tabbed-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item > .fi-fo-repeater-item-header:focus-visible {
        position: relative;
        z-index: 1;
        outline: 2px solid #1a6bc7;
        outline-offset: -2px;
    }

    .pe-tabbed-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item > .fi-fo-repeater-item-header .fi-fo-repeater-item-header-label {
        white-space: normal;
        line-height: 1.25;
    }

    .pe-tabbed-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item > .fi-fo-repeater-item-content {
        grid-row: 2;
        grid-column: 1 / -1;
        padding: 1rem;
        background: #fff;
    }

    .pe-tabbed-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item.pe-tab-inactive > .fi-fo-repeater-item-content {
        display: none !important;
    }

    .pe-tabbed-repeater > .fi-fo-repeater-add {
        justify-content: flex-end;
        padding-top: 0.65rem;
    }

    .pe-tabbed-repeater > .fi-fo-repeater-add .fi-btn {
        border-radius: 0.65rem;
        font-weight: 650;
    }

    .pe-escolas-tabs {
        margin-top: 0.25rem;
    }

    .pe-escolas-tabs > .fi-fo-repeater-items > .fi-fo-repeater-item > .fi-fo-repeater-item-header {
        padding-block: 0.6rem;
        background: #f1f5f9;
    }

    .dark .pe-tabbed-repeater > .fi-fo-repeater-items {
        border-color: #334155;
        background: rgba(15, 23, 42, 0.75);
    }

    .dark .pe-tabbed-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item > .fi-fo-repeater-item-header {
        border-color: #334155;
        background: rgba(30, 41, 59, 0.9);
        color: #cbd5e1;
    }

    .dark .pe-tabbed-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item.pe-tab-active > .fi-fo-repeater-item-header,
    .dark .pe-tabbed-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item > .fi-fo-repeater-item-content {
        background: rgba(15, 23, 42, 0.92);
        color: #dbeafe;
    }

    /* Tabela da listagem de pessoas */
    .pe-pessoas-page .fi-ta-header-toolbar {
        gap: 0.65rem;
        flex-wrap: wrap;
    }

    .pe-pessoas-page .fi-ta-filters {
        gap: 0.65rem;
    }

    .pe-pessoas-page .fi-ta-record {
        transition: background-color 0.12s ease;
    }

    .pe-pessoas-page .fi-ta-record:hover {
        background: rgba(23, 54, 141, 0.03);
    }

    .dark .pe-pessoas-page .fi-ta-record:hover {
        background: rgba(58, 109, 214, 0.08);
    }

    /* Responsivo */
    @media (max-width: 768px) {
        .fi-slide-over-window,
        .fi-modal-window {
            border-radius: 0.85rem !important;
            max-width: 100vw !important;
        }

        .fi-modal-content,
        .fi-slide-over-content,
        .fi-modal-header,
        .fi-slide-over-header,
        .fi-modal-footer,
        .fi-slide-over-footer {
            padding-left: 0.85rem !important;
            padding-right: 0.85rem !important;
        }

        .fi-slide-over-content .fi-fo-repeater-item,
        .fi-modal-content .fi-fo-repeater-item {
            padding: 0.6rem !important;
        }

        .pe-pessoas-page .fi-ta-actions {
            flex-wrap: wrap;
        }

        .pe-tabbed-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item > .fi-fo-repeater-item-header {
            padding: 0.6rem;
            font-size: 0.78rem;
        }

        .pe-tabbed-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item > .fi-fo-repeater-item-content {
            padding: 0.75rem;
        }

        .pessoa-modal-window .fi-modal-content,
        .pessoa-modal-window .fi-modal-header,
        .pessoa-modal-window .fi-modal-footer,
        .pessoa-modal-window .fi-sc-tabs-tab {
            padding-left: 0.85rem !important;
            padding-right: 0.85rem !important;
        }
    }

    @media (max-width: 480px) {
        .fi-modal-heading,
        .fi-slide-over-heading {
            font-size: 0.95rem !important;
        }
    }

    /* Ações do rodapé mais confortáveis */
    .fi-modal-footer .fi-btn,
    .fi-slide-over-footer .fi-btn {
        border-radius: 0.7rem;
        font-weight: 600;
    }

    /* Collapse chevron feedback */
    .fi-section-collapse-button {
        border-radius: 0.55rem;
        transition: background-color 0.12s ease, transform 0.12s ease;
    }

    .fi-section-collapse-button:hover {
        background: rgba(23, 54, 141, 0.06);
    }
</style>

<script>
    (() => {
        if (window.__peTabbedRepeatersInitialized) {
            return;
        }

        window.__peTabbedRepeatersInitialized = true;

        const itemSelector = ':scope > .fi-fo-repeater-items > .fi-fo-repeater-item';
        const itemCounts = new WeakMap();
        const activeTabs = new Map();

        const directItems = (root) => Array.from(root.querySelectorAll(itemSelector));
        const itemKey = (item, index) => item.getAttribute('x-sortable-item') || String(index);
        const rootKey = (root) => {
            const parentItem = root.parentElement?.closest('[x-sortable-item]');

            return [
                root.dataset.peTabsLabel || 'tabs',
                parentItem?.getAttribute('x-sortable-item') || 'root',
            ].join(':');
        };

        const activate = (root, selectedItem) => {
            const items = directItems(root);

            items.forEach((item, index) => {
                const active = item === selectedItem;
                const header = item.querySelector(':scope > .fi-fo-repeater-item-header');
                const content = item.querySelector(':scope > .fi-fo-repeater-item-content');

                item.classList.toggle('pe-tab-active', active);
                item.classList.toggle('pe-tab-inactive', ! active);

                if (header) {
                    header.setAttribute('role', 'tab');
                    header.setAttribute('aria-selected', active ? 'true' : 'false');
                    header.setAttribute('tabindex', active ? '0' : '-1');
                }

                if (content) {
                    content.setAttribute('role', 'tabpanel');
                    content.toggleAttribute('hidden', ! active);
                }

                if (active) {
                    const key = itemKey(item, index);
                    root.dataset.peActiveTab = key;
                    activeTabs.set(rootKey(root), key);
                }
            });
        };

        const initializeRoot = (root) => {
            const items = directItems(root);
            const previousCount = itemCounts.get(root);
            const savedKey = root.dataset.peActiveTab || activeTabs.get(rootKey(root));
            let selected = items.find((item, index) => itemKey(item, index) === savedKey);

            if (
                root.dataset.peActivateNewest === 'true'
                && previousCount !== undefined
                && items.length > previousCount
            ) {
                selected = items.at(-1);
                delete root.dataset.peActivateNewest;
            }

            selected ??= items[0];
            root.style.setProperty('--pe-tab-count', Math.max(items.length, 1));

            const list = root.querySelector(':scope > .fi-fo-repeater-items');
            if (list) {
                list.setAttribute('role', 'tablist');
                list.setAttribute('aria-label', root.dataset.peTabsLabel || 'Opções');
            }

            if (selected) {
                activate(root, selected);
            }

            itemCounts.set(root, items.length);
        };

        const initializeAll = () => {
            document.querySelectorAll('.pe-tabbed-repeater').forEach(initializeRoot);
        };

        document.addEventListener('click', (event) => {
            const addContainer = event.target.closest('.pe-tabbed-repeater > .fi-fo-repeater-add');
            const addRoot = addContainer?.parentElement;

            if (addRoot?.matches('.pe-tabbed-repeater') && event.target.closest('button')) {
                addRoot.dataset.peActivateNewest = 'true';
                window.setTimeout(() => {
                    delete addRoot.dataset.peActivateNewest;
                }, 15000);
            }

            const header = event.target.closest('.pe-tabbed-repeater .fi-fo-repeater-item-header');
            if (! header || event.target.closest('button, a, input, select, textarea')) {
                return;
            }

            const item = header.closest('.fi-fo-repeater-item');
            const root = item?.parentElement?.closest('.pe-tabbed-repeater');

            if (root && directItems(root).includes(item)) {
                activate(root, item);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (! ['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
                return;
            }

            const header = event.target.closest('.pe-tabbed-repeater .fi-fo-repeater-item-header');
            const item = header?.closest('.fi-fo-repeater-item');
            const root = item?.parentElement?.closest('.pe-tabbed-repeater');

            if (! root) {
                return;
            }

            const items = directItems(root);
            const currentIndex = items.indexOf(item);
            if (currentIndex < 0) {
                return;
            }

            event.preventDefault();
            const nextIndex = event.key === 'Home'
                ? 0
                : event.key === 'End'
                    ? items.length - 1
                    : (currentIndex + (event.key === 'ArrowRight' ? 1 : -1) + items.length) % items.length;

            activate(root, items[nextIndex]);
            items[nextIndex].querySelector(':scope > .fi-fo-repeater-item-header')?.focus();
        });

        new MutationObserver((mutations) => {
            if (mutations.some((mutation) => mutation.type === 'childList')) {
                requestAnimationFrame(initializeAll);
            }
        }).observe(document.body, { childList: true, subtree: true });

        document.addEventListener('livewire:navigated', initializeAll);
        requestAnimationFrame(initializeAll);
    })();
</script>
