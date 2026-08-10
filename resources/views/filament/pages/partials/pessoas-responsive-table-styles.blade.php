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
        gap: 0.65rem;
        padding: 0.65rem;
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
        gap: 0.65rem;
        width: 100%;
        padding: 0.75rem;
    }

    .pe-pessoas-page .fi-ta-record-content-ctn > .fi-ta-actions {
        justify-content: flex-end;
        padding: 0.6rem 0.75rem;
        border-top: 1px solid var(--pessoa-card-border);
    }

    .pe-pessoas-page .pessoa-card-main-grid {
        gap: 0.65rem 0.85rem;
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

    body:has(.pe-pessoas-page) .fi-dropdown-panel:not(.fi-select-dropdown-portal) {
        z-index: 80 !important;
    }

    .pe-pessoas-page .pessoa-card-field--email .fi-ta-text-item,
    .pe-pessoas-page .pessoa-card-field--matriculas .fi-ta-text-item {
        color: var(--gray-950);
        font-weight: var(--font-weight-medium);
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
            gap: 0.5rem;
            padding: 0.65rem;
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
