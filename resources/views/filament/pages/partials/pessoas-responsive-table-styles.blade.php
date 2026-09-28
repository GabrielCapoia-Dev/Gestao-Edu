<style>
    .pe-pessoas-page {
        --pessoa-card-border: color-mix(in oklab, var(--gray-200) 78%, var(--primary-100));
        --pessoa-card-surface: #ffffff;
        --pessoa-card-muted: var(--gray-500);
        --pessoa-card-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 10px 24px rgba(15, 23, 42, 0.06);
    }

    .dark .pe-pessoas-page {
        --pessoa-card-border: var(--gray-700);
        --pessoa-card-surface: color-mix(in srgb, var(--gray-900) 94%, var(--primary-950));
        --pessoa-card-muted: var(--gray-400);
        --pessoa-card-shadow: 0 12px 28px rgba(0, 0, 0, 0.16);
    }

    .pe-pessoas-page,
    .pe-pessoas-page .fi-ta,
    .pe-pessoas-page .fi-ta-content-ctn,
    .pe-pessoas-page .fi-ta-content,
    .pe-pessoas-page .fi-ta-record,
    .pe-pessoas-page .fi-ta-record-content-ctn,
    .pe-pessoas-page .fi-ta-record-content,
    .pe-pessoas-page .fi-ta-grid {
        max-width: 100%;
        min-width: 0;
    }

    .pe-pessoas-page .fi-ta-content-ctn {
        overflow: visible !important;
    }

    .pe-pessoas-page .fi-ta-content {
        display: grid;
        gap: 0.5rem;
        padding: 0.5rem;
    }

    .pe-pessoas-page .fi-ta-content-header {
        flex-wrap: wrap;
        gap: 0.65rem;
        padding: 0.65rem;
    }

    .pe-pessoas-page .fi-ta-sorting-settings {
        display: flex;
        flex: 1 1 24rem;
        flex-wrap: wrap;
        gap: 0.5rem;
        min-width: 0;
    }

    .pe-pessoas-page .fi-ta-sorting-settings label {
        flex: 1 1 14rem;
        min-width: min(14rem, 100%);
    }

    .pe-pessoas-page .fi-ta-record {
        position: relative;
        border: 1px solid var(--pessoa-card-border);
        border-radius: 0.65rem;
        background: var(--pessoa-card-surface);
        box-shadow: var(--pessoa-card-shadow);
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    .pe-pessoas-page .fi-ta-record:hover {
        border-color: color-mix(in oklab, var(--primary-300) 58%, var(--gray-200));
        background: var(--pessoa-card-surface);
    }

    .pe-pessoas-page .fi-ta-record:focus-within {
        z-index: 30;
    }

    .dark .pe-pessoas-page .fi-ta-record:hover {
        border-color: color-mix(in oklab, var(--primary-500) 50%, var(--gray-700));
        background: var(--pessoa-card-surface);
    }

    .pe-pessoas-page .fi-ta-record-content {
        display: grid;
        gap: 0.3rem;
        width: 100%;
        padding: 0.5rem 0.65rem;
    }

    .pe-pessoas-page .fi-ta-record-content-ctn > .fi-ta-actions {
        justify-content: flex-end;
        min-height: 0;
        padding: 0.4rem 0.55rem;
        border-top: 1px solid var(--pessoa-card-border);
    }

    .pe-pessoas-page .pessoa-card-main-grid {
        align-items: start;
        gap: 0.25rem 0.55rem;
    }

    .pe-pessoas-page .pessoa-card-field .fi-ta-text-description {
        margin-bottom: 0.1rem;
    }

    .pe-pessoas-page .pessoa-card-field .fi-ta-text-item,
    .pe-pessoas-page .pessoa-card-field .fi-badge {
        line-height: 1.25;
    }

    .pe-pessoas-page .pessoa-card-name .fi-ta-text-item,
    .pe-pessoas-page .pessoa-card-field .fi-ta-text-item,
    .pe-pessoas-page .pessoa-card-field .fi-ta-text-description {
        overflow-wrap: anywhere;
        word-break: normal;
    }

    .pe-pessoas-page .pessoa-card-name .fi-ta-text-description,
    .pe-pessoas-page .pessoa-card-field .fi-ta-text-description {
        color: var(--pessoa-card-muted);
        font-size: var(--text-xs);
        line-height: var(--text-xs--line-height);
        font-weight: var(--font-weight-semibold);
    }

    .pe-pessoas-page .pessoa-card-name .fi-ta-text-item {
        color: var(--primary-800);
        font-size: var(--text-sm);
        line-height: var(--text-sm--line-height);
        font-weight: var(--font-weight-bold);
    }

    .dark .pe-pessoas-page .pessoa-card-name .fi-ta-text-item {
        color: var(--primary-300);
    }

    .pe-pessoas-page .pessoa-card-field .fi-badge,
    .pe-pessoas-page .pessoa-card-field .fi-ta-text-item {
        max-width: 100%;
        white-space: normal;
    }

    .pe-pessoas-page .pessoa-card-field--niveis .fi-ta-text-has-badges,
    .pe-pessoas-page .pessoa-card-field--niveis .fi-ta-text-has-badges ul {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        gap: 0.35rem;
    }

    .pe-pessoas-page .pessoa-card-field--niveis .fi-badge {
        width: auto;
        max-width: 100%;
        flex: 0 1 auto;
        white-space: normal;
        overflow-wrap: normal;
        word-break: normal;
        hyphens: none;
    }

    .pe-pessoas-page .pessoa-card-field--niveis .fi-badge-label,
    .pe-pessoas-page .pessoa-card-field--niveis .fi-badge-label-ctn {
        max-width: 100%;
        white-space: normal;
        overflow-wrap: normal;
        word-break: normal;
        hyphens: none;
    }

    /*
     * O menu de ações é teleportado para o body. A camada alta evita que ele fique
     * atrás da busca, cabeçalhos sticky ou qualquer outro elemento da tabela.
     */
    body:has(.pe-pessoas-page) .fi-dropdown-panel:not(.fi-select-dropdown-portal) {
        z-index: 2200 !important;
    }

    .pe-pessoas-page .pessoa-card-field--email .fi-ta-text-item,
    .pe-pessoas-page .pessoa-card-field--matriculas .fi-ta-text-item {
        color: var(--gray-950);
        font-weight: var(--font-weight-medium);
        line-height: 1.3;
    }

    .dark .pe-pessoas-page .pessoa-card-field--email .fi-ta-text-item,
    .dark .pe-pessoas-page .pessoa-card-field--matriculas .fi-ta-text-item {
        color: var(--gray-100);
    }

    @media (max-width: 48rem) {
        .pe-pessoas-page .fi-ta-header,
        .pe-pessoas-page .fi-ta-content,
        .pe-pessoas-page .fi-ta-content-header {
            padding: 0.5rem;
        }

        .pe-pessoas-page .fi-ta-record-content {
            gap: 0.4rem;
            padding: 0.55rem 0.65rem;
        }

        .pe-pessoas-page .fi-ta-record-content-ctn > .fi-ta-actions {
            padding: 0.55rem 0.65rem;
        }
    }

    @media (max-width: 24rem) {
        .pe-pessoas-page .fi-ta-content,
        .pe-pessoas-page .fi-ta-content-header {
            padding: 0.4rem;
        }

        .pe-pessoas-page .fi-ta-record-content {
            padding: 0.55rem;
        }
    }
</style>
<style>
    .pe-pessoas-page .servidores-lw {
        display: grid;
        gap: 1rem;
        width: 100%;
    }

    .pe-pessoas-page .servidores-lw__toolbar,
    .pe-pessoas-page .servidores-lw__table-header,
    .pe-pessoas-page .servidores-lw__footer,
    .pe-pessoas-page .servidores-lw__bulkbar {
        align-items: center;
        display: flex;
        gap: .75rem;
        justify-content: space-between;
    }

    .pe-pessoas-page .servidores-lw__toolbar,
    .pe-pessoas-page .servidores-lw__table-header,
    .pe-pessoas-page .servidores-lw__footer {
        border: 1px solid var(--pessoa-card-border);
        border-radius: .75rem;
        padding: .75rem 1rem;
        background: var(--pessoa-card-surface);
    }

    .pe-pessoas-page .servidores-lw__search { flex: 1 1 24rem; }
    .pe-pessoas-page .servidores-lw__search label,
    .pe-pessoas-page .servidores-lw__filter-grid label { color: var(--gray-600); display: grid; font-size: .75rem; font-weight: 600; gap: .35rem; }
    .pe-pessoas-page .servidores-lw__search-control { align-items: center; border: 1px solid var(--gray-300); border-radius: .6rem; display: flex; gap: .5rem; padding: .55rem .7rem; }
    .pe-pessoas-page .servidores-lw__search-control input { background: transparent; border: 0; min-width: 0; outline: 0; width: 100%; }
    .pe-pessoas-page .servidores-lw__toolbar-actions { align-items: end; display: flex; flex-wrap: wrap; gap: .5rem; }
    .pe-pessoas-page .servidores-lw__button { align-items: center; border: 1px solid var(--gray-300); border-radius: .55rem; display: inline-flex; font-size: .78rem; font-weight: 600; gap: .35rem; padding: .55rem .75rem; transition: background .15s ease, border-color .15s ease; }
    .pe-pessoas-page .servidores-lw__button--ghost { background: var(--gray-50); color: var(--gray-700); }
    .pe-pessoas-page .servidores-lw__button--primary { background: var(--primary-600); border-color: var(--primary-600); color: white; }
    .pe-pessoas-page .servidores-lw__button--link { background: transparent; border-color: transparent; color: var(--primary-600); }
    .pe-pessoas-page .servidores-lw__page-size { align-items: center; color: var(--gray-500); display: flex; font-size: .75rem; gap: .35rem; }
    .pe-pessoas-page .servidores-lw select { background: var(--pessoa-card-surface); border: 1px solid var(--gray-300); border-radius: .5rem; color: var(--gray-700); font-size: .8rem; max-width: 100%; padding: .45rem .55rem; }
    .pe-pessoas-page .servidores-lw__filters { border: 1px solid var(--pessoa-card-border); border-radius: .75rem; background: var(--pessoa-card-surface); }
    .pe-pessoas-page .servidores-lw__filters summary { align-items: center; cursor: pointer; display: flex; font-size: .82rem; font-weight: 700; gap: .45rem; justify-content: space-between; list-style: none; padding: .75rem 1rem; }
    .pe-pessoas-page .servidores-lw__filters summary span:first-child { align-items: center; display: inline-flex; gap: .4rem; }
    .pe-pessoas-page .servidores-lw__filter-count { background: var(--primary-50); border-radius: 999px; color: var(--primary-700); font-size: .7rem; padding: .15rem .45rem; }
    .pe-pessoas-page .servidores-lw__filter-grid { border-top: 1px solid var(--pessoa-card-border); display: grid; gap: .75rem; grid-template-columns: repeat(4, minmax(0, 1fr)); padding: 1rem; }
    .pe-pessoas-page .servidores-lw__filter-grid--filament { display: block; }
    .pe-pessoas-page .servidores-lw__filter-grid--filament > .fi-sc { width: 100%; }
    .pe-pessoas-page .servidores-lw__filter-grid--filament .fi-fo-field-wrp,
    .pe-pessoas-page .servidores-lw__filter-grid--filament .fi-input-wrp { min-width: 0; }
    .pe-pessoas-page .servidores-lw__checkbox-label { align-items: center !important; display: flex !important; align-self: end; }
    .pe-pessoas-page .servidores-lw__filter-actions { align-items: center; border-top: 1px solid var(--pessoa-card-border); display: flex; gap: .5rem; justify-content: flex-end; padding: .65rem 1rem; }
    .pe-pessoas-page .servidores-lw__selection-summary { align-items: center; display: flex; flex-wrap: wrap; gap: .65rem; }
    .pe-pessoas-page .servidores-lw__select-page { align-items: center; display: inline-flex; font-size: .78rem; gap: .4rem; }
    .pe-pessoas-page .servidores-lw__columns { position: relative; }
    .pe-pessoas-page .servidores-lw__columns summary { align-items: center; cursor: pointer; display: flex; font-size: .78rem; gap: .35rem; list-style: none; }
    .pe-pessoas-page .servidores-lw__columns-menu { background: var(--pessoa-card-surface); border: 1px solid var(--pessoa-card-border); border-radius: .6rem; box-shadow: var(--pessoa-card-shadow); display: grid; gap: .45rem; min-width: 12rem; padding: .7rem; position: absolute; right: 0; top: 1.5rem; z-index: 3; }
    .pe-pessoas-page .servidores-lw__columns-menu label { align-items: center; display: flex; font-size: .75rem; gap: .4rem; white-space: nowrap; }
    .pe-pessoas-page .servidores-lw__bulkbar { background: var(--primary-50); border: 1px solid var(--primary-100); border-radius: .65rem; justify-content: flex-start; padding: .6rem .8rem; }
    .pe-pessoas-page .servidores-lw__table-wrap { border: 1px solid var(--pessoa-card-border); border-radius: .75rem; background: var(--pessoa-card-surface); max-width: 100%; overflow: visible; }
    .pe-pessoas-page .servidores-lw__table { border-collapse: separate; border-spacing: 0; min-width: 0; table-layout: fixed; width: 100%; }
    .pe-pessoas-page .servidores-lw__table th { background: var(--gray-50); color: var(--gray-500); font-size: .68rem; font-weight: 700; letter-spacing: .02em; padding: .65rem .5rem; text-align: left; text-transform: uppercase; white-space: nowrap; }
    .pe-pessoas-page .servidores-lw__table th button { align-items: center; background: transparent; border: 0; color: inherit; cursor: pointer; display: inline-flex; font: inherit; gap: .25rem; padding: 0; text-transform: inherit; }
    .pe-pessoas-page .servidores-lw__table td { border-top: 1px solid var(--gray-100); color: var(--gray-700); font-size: .76rem; line-height: 1.35; padding: .6rem .5rem; vertical-align: middle; overflow-wrap: anywhere; word-break: normal; }
    .pe-pessoas-page .servidores-lw__table tbody tr:nth-child(even) { background: color-mix(in srgb, var(--gray-50) 72%, white); }
    .pe-pessoas-page .servidores-lw__table tbody tr:hover { background: color-mix(in srgb, var(--primary-50) 72%, white); }
    .pe-pessoas-page .servidores-lw__table td strong { color: var(--gray-950); display: block; font-size: .82rem; }
    .pe-pessoas-page .servidores-lw__table td small { color: var(--gray-500); display: block; font-size: .7rem; margin-top: .15rem; }
    .pe-pessoas-page .servidores-lw__copy { align-items: center; appearance: none; background: transparent; border: 0; border-radius: .2rem; color: inherit; cursor: copy; display: flex; flex-wrap: wrap; gap: .3rem; max-width: 100%; min-width: 0; padding: .1rem; text-align: left; white-space: normal; overflow-wrap: anywhere; width: 100%; }
    .pe-pessoas-page .servidores-lw__copy-feedback { align-items: center; border: 1px solid transparent; border-radius: .7rem; box-shadow: 0 8px 24px rgba(15,23,42,.16); display: inline-flex; font-size: .82rem; font-weight: 650; gap: .5rem; padding: .65rem .9rem; position: fixed; right: 1.25rem; top: 5.5rem; z-index: 80; }
    .pe-pessoas-page .servidores-lw__copy-feedback svg { height: 1.1rem; width: 1.1rem; }
    .pe-pessoas-page .servidores-lw__copy-feedback--success { background: var(--success-50); border-color: color-mix(in srgb, var(--success-700) 24%, white); color: var(--success-700); }
    .pe-pessoas-page .servidores-lw__copy-feedback--error { background: var(--danger-50); border-color: color-mix(in srgb, var(--danger-700) 24%, white); color: var(--danger-700); }
    .pe-pessoas-page .servidores-lw__copy:hover { color: var(--primary-700); }
    .pe-pessoas-page .servidores-lw__copy:focus-visible { outline: 1px solid var(--primary-400); outline-offset: 1px; }
    .pe-pessoas-page .servidores-lw__copy--identity { display: block; }
    .pe-pessoas-page .servidores-lw__copy strong { display: block; width: 100%; }
    .pe-pessoas-page .servidores-lw__copy small { display: block; width: 100%; }
    .pe-pessoas-page .servidores-lw__copy svg { flex: 0 0 .85rem; height: .85rem; opacity: .45; width: .85rem; }
    .pe-pessoas-page .servidores-lw__copy:hover svg { opacity: 1; }
    .pe-pessoas-page .servidores-lw__copy-label { min-width: 0; overflow-wrap: anywhere; }
    .pe-pessoas-page .servidores-lw__check-col { text-align: center !important; width: 2.6rem; }
    .pe-pessoas-page .servidores-lw__actions-col { box-sizing: border-box; text-align: center !important; white-space: nowrap; width: 8rem; }
    .pe-pessoas-page .servidores-lw__email { overflow-wrap: anywhere; }
    .pe-pessoas-page .servidores-lw__badge { border-radius: 999px; display: inline-flex; font-size: .68rem; font-weight: 600; padding: .2rem .45rem; }
    .pe-pessoas-page .servidores-lw__badge--blue { background: var(--primary-50); color: var(--primary-700); }
    .pe-pessoas-page .servidores-lw__badge--green { background: var(--success-50); color: var(--success-700); }
    .pe-pessoas-page .servidores-lw__badge--gray { background: var(--gray-100); color: var(--gray-600); }
    .pe-pessoas-page .servidores-lw__badge--amber { background: var(--warning-50); color: var(--warning-700); }
    .pe-pessoas-page .servidores-lw__badge--red { background: var(--danger-50); color: var(--danger-700); }
    .pe-pessoas-page .servidores-lw__action-group { align-items: center; display: inline-flex; gap: .2rem; justify-content: center; max-width: 100%; }
    .pe-pessoas-page .servidores-lw__action { align-items: center; background: transparent; border: 1px solid transparent; border-radius: .4rem; box-sizing: border-box; color: var(--gray-500); cursor: pointer; display: inline-flex; height: 2rem; justify-content: center; margin: 0; padding: .3rem; width: 2rem; }
    .pe-pessoas-page .servidores-lw__action:hover { background: var(--gray-100); color: var(--primary-600); }
    .pe-pessoas-page .servidores-lw__row-menu { display: inline-block; position: relative; }
    .pe-pessoas-page .servidores-lw__row-menu summary { list-style: none; }
    .pe-pessoas-page .servidores-lw__row-menu summary::-webkit-details-marker { display: none; }
    .pe-pessoas-page .servidores-lw__row-menu-panel { background: var(--pessoa-card-surface); border: 1px solid var(--pessoa-card-border); border-radius: .55rem; box-shadow: var(--pessoa-card-shadow); display: grid; min-width: 11rem; padding: .3rem; position: absolute; right: 0; top: 2rem; z-index: 5; }
    .pe-pessoas-page .servidores-lw__row-menu-panel button { background: transparent; border: 0; border-radius: .35rem; color: var(--gray-700); cursor: pointer; font-size: .75rem; padding: .45rem .55rem; text-align: left; white-space: nowrap; }
    .pe-pessoas-page .servidores-lw__row-menu-panel button:hover { background: var(--gray-100); color: var(--primary-600); }
    .pe-pessoas-page .servidores-lw__empty { color: var(--gray-500); padding: 3rem 1rem !important; text-align: center; }
    .pe-pessoas-page .servidores-lw__footer { flex-wrap: wrap; font-size: .75rem; color: var(--gray-500); }
    .pe-pessoas-page .servidores-lw__pagination { align-items: center; display: flex; flex-wrap: wrap; gap: .25rem; justify-content: flex-end; margin-left: auto; }
    .pe-pessoas-page .servidores-lw__pagination button { align-items: center; background: var(--pessoa-card-surface); border: 1px solid var(--pessoa-card-border); border-radius: .4rem; color: var(--gray-700); cursor: pointer; display: inline-flex; font-size: .75rem; justify-content: center; min-height: 2rem; min-width: 2rem; padding: .35rem .55rem; }
    .pe-pessoas-page .servidores-lw__pagination button:hover:not(:disabled) { background: var(--primary-50); border-color: var(--primary-200); color: var(--primary-700); }
    .pe-pessoas-page .servidores-lw__pagination button[aria-current="page"] { background: var(--primary-600); border-color: var(--primary-600); color: #fff; font-weight: 700; }
    .pe-pessoas-page .servidores-lw__pagination button:disabled { cursor: not-allowed; opacity: .45; }
    .pe-pessoas-page .servidores-lw__pagination-ellipsis { padding: 0 .25rem; }
    .pe-pessoas-page .servidores-lw__pagination svg { height: 1rem !important; max-height: 1rem; max-width: 1rem; width: 1rem !important; }
    @media (max-width: 70rem) { .pe-pessoas-page .servidores-lw__filter-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } .pe-pessoas-page .servidores-lw__table th, .pe-pessoas-page .servidores-lw__table td { padding-left: .35rem; padding-right: .35rem; } }
    @media (max-width: 48rem) {
        .pe-pessoas-page .servidores-lw__toolbar, .pe-pessoas-page .servidores-lw__table-header { align-items: stretch; flex-direction: column; }
        .pe-pessoas-page .servidores-lw__search { flex: 0 1 auto; width: 100%; }
        .pe-pessoas-page .servidores-lw__toolbar-actions { justify-content: space-between; }
        .pe-pessoas-page .servidores-lw__filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .pe-pessoas-page .servidores-lw__table-wrap { background: transparent; border: 0; border-radius: 0; }
        .pe-pessoas-page .servidores-lw__table, .pe-pessoas-page .servidores-lw__table tbody { display: block; width: 100%; }
        .pe-pessoas-page .servidores-lw__table thead { display: none; }
        .pe-pessoas-page .servidores-lw__table tbody { display: grid; gap: .7rem; }
        .pe-pessoas-page .servidores-lw__table tbody tr { background: var(--pessoa-card-surface); border: 1px solid var(--pessoa-card-border); border-radius: .8rem; box-shadow: 0 2px 8px rgba(15, 23, 42, .05); display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); min-width: 0; overflow: hidden; }
        .pe-pessoas-page .servidores-lw__table tbody tr:nth-child(even) { background: color-mix(in srgb, var(--gray-50) 55%, white); }
        .pe-pessoas-page .servidores-lw__table tbody tr:hover { background: color-mix(in srgb, var(--primary-50) 58%, white); }
        .pe-pessoas-page .servidores-lw__table tbody td { border-top: 1px solid var(--gray-100); display: flex; flex-direction: column; justify-content: center; min-width: 0; padding: .65rem .75rem; }
        .pe-pessoas-page .servidores-lw__table tbody td[data-label]::before { color: var(--gray-500); content: attr(data-label); font-size: .64rem; font-weight: 700; letter-spacing: .03em; margin-bottom: .25rem; text-transform: uppercase; }
        .pe-pessoas-page .servidores-lw__table .servidores-lw__check-col, .pe-pessoas-page .servidores-lw__table .servidores-lw__actions-col { grid-column: 1 / -1; }
        .pe-pessoas-page .servidores-lw__table .servidores-lw__check-col { align-items: flex-start; border-top: 0; border-bottom: 1px solid var(--gray-100); min-height: 2.3rem; padding: .45rem .75rem; text-align: left !important; width: 100%; }
        .pe-pessoas-page .servidores-lw__table .servidores-lw__actions-col { align-items: center; flex-direction: row; justify-content: space-between; text-align: left !important; width: 100%; }
        .pe-pessoas-page .servidores-lw__table .servidores-lw__action-group { margin-left: auto; }
        .pe-pessoas-page .servidores-lw__table tbody td[data-label="Pessoa / CPF"] { align-items: flex-start; }
        .pe-pessoas-page .servidores-lw__table .servidores-lw__empty { grid-column: 1 / -1; }
    }
    @media (max-width: 32rem) {
        .pe-pessoas-page .servidores-lw__filter-grid { grid-template-columns: 1fr; }
        .pe-pessoas-page .servidores-lw__toolbar-actions { align-items: stretch; flex-direction: row; justify-content: space-between; }
        .pe-pessoas-page .servidores-lw__toolbar-actions .servidores-lw__button { flex: 1 1 auto; }
        .pe-pessoas-page .servidores-lw__button { justify-content: center; }
        .pe-pessoas-page .servidores-lw__footer { align-items: stretch; flex-direction: column; }
        .pe-pessoas-page .servidores-lw__pagination { justify-content: center; margin-left: 0; }
        .pe-pessoas-page .servidores-lw__table tbody tr { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .pe-pessoas-page .servidores-lw__table .servidores-lw__check-col, .pe-pessoas-page .servidores-lw__table .servidores-lw__actions-col { grid-column: 1 / -1; }
    }
</style>
