<style>
    :is(.fi-resource-escolas, .fi-resource-lotacoes).fi-resource-list-records-page {
        --local-card-border: color-mix(in oklab, var(--gray-200) 78%, var(--primary-100));
        --local-card-surface: #ffffff;
        --local-card-muted: var(--gray-500);
        --local-card-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 10px 24px rgba(15, 23, 42, 0.06);
    }

    .dark :is(.fi-resource-escolas, .fi-resource-lotacoes).fi-resource-list-records-page {
        --local-card-border: var(--gray-700);
        --local-card-surface: color-mix(in srgb, var(--gray-900) 94%, var(--primary-950));
        --local-card-muted: var(--gray-400);
        --local-card-shadow: 0 12px 28px rgba(0, 0, 0, 0.16);
    }

    :is(.fi-resource-escolas, .fi-resource-lotacoes).fi-resource-list-records-page :is(
        .fi-page-content,
        .fi-ta,
        .fi-ta-content-ctn,
        .fi-ta-content,
        .fi-ta-record,
        .fi-ta-record-content-ctn,
        .fi-ta-record-content,
        .fi-ta-grid
    ) {
        max-width: 100%;
        min-width: 0;
    }

    :is(.fi-resource-escolas, .fi-resource-lotacoes).fi-resource-list-records-page .fi-ta-content-ctn {
        overflow-x: hidden !important;
    }

    :is(.fi-resource-escolas, .fi-resource-lotacoes).fi-resource-list-records-page .fi-ta-content {
        display: grid;
        gap: 0.65rem;
        padding: 0.65rem;
    }

    :is(.fi-resource-escolas, .fi-resource-lotacoes).fi-resource-list-records-page .fi-ta-content-header {
        flex-wrap: wrap;
        gap: 0.65rem;
        padding: 0.65rem;
    }

    :is(.fi-resource-escolas, .fi-resource-lotacoes).fi-resource-list-records-page .fi-ta-sorting-settings {
        display: flex;
        flex: 1 1 24rem;
        flex-wrap: wrap;
        gap: 0.5rem;
        min-width: 0;
    }

    :is(.fi-resource-escolas, .fi-resource-lotacoes).fi-resource-list-records-page .fi-ta-sorting-settings label {
        flex: 1 1 14rem;
        min-width: min(14rem, 100%);
    }

    :is(.fi-resource-escolas, .fi-resource-lotacoes).fi-resource-list-records-page .fi-ta-record {
        position: relative;
        border: 1px solid var(--local-card-border);
        border-radius: 0.65rem;
        background: var(--local-card-surface);
        box-shadow: var(--local-card-shadow);
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    :is(.fi-resource-escolas, .fi-resource-lotacoes).fi-resource-list-records-page .fi-ta-record:hover {
        border-color: color-mix(in oklab, var(--primary-300) 58%, var(--gray-200));
        background: var(--local-card-surface);
    }

    :is(.fi-resource-escolas, .fi-resource-lotacoes).fi-resource-list-records-page .fi-ta-record:focus-within {
        z-index: 30;
    }

    :is(.fi-resource-escolas, .fi-resource-lotacoes).fi-resource-list-records-page .fi-ta-record-content {
        display: grid;
        gap: 0.65rem;
        width: 100%;
        padding: 0.75rem;
    }

    :is(.fi-resource-escolas, .fi-resource-lotacoes).fi-resource-list-records-page .fi-ta-record-content-ctn > .fi-ta-actions {
        justify-content: flex-end;
        padding: 0.6rem 0.75rem;
        border-top: 1px solid var(--local-card-border);
    }

    :is(.fi-resource-escolas, .fi-resource-lotacoes).fi-resource-list-records-page .local-card-main-grid {
        gap: 0.65rem 0.85rem;
    }

    :is(.fi-resource-escolas, .fi-resource-lotacoes).fi-resource-list-records-page :is(
        .local-card-name .fi-ta-text-item,
        .local-card-field .fi-ta-text-item,
        .local-card-field .fi-ta-text-description
    ) {
        overflow-wrap: anywhere;
        word-break: normal;
    }

    :is(.fi-resource-escolas, .fi-resource-lotacoes).fi-resource-list-records-page :is(
        .local-card-name .fi-ta-text-description,
        .local-card-field .fi-ta-text-description
    ) {
        color: var(--local-card-muted);
        font-size: var(--text-xs);
        line-height: var(--text-xs--line-height);
        font-weight: var(--font-weight-semibold);
    }

    :is(.fi-resource-escolas, .fi-resource-lotacoes).fi-resource-list-records-page .local-card-name .fi-ta-text-item {
        color: var(--primary-800);
        font-size: var(--text-sm);
        line-height: var(--text-sm--line-height);
        font-weight: var(--font-weight-bold);
    }

    .dark :is(.fi-resource-escolas, .fi-resource-lotacoes).fi-resource-list-records-page .local-card-name .fi-ta-text-item {
        color: var(--primary-300);
    }

    :is(.fi-resource-escolas, .fi-resource-lotacoes).fi-resource-list-records-page :is(
        .local-card-field .fi-badge,
        .local-card-field .fi-ta-text-item
    ) {
        max-width: 100%;
        white-space: normal;
    }

    :is(.fi-resource-escolas, .fi-resource-lotacoes).fi-resource-list-records-page :is(
        .local-card-field--contact .fi-ta-text-item,
        .local-card-field--local .fi-ta-text-item
    ) {
        color: var(--gray-950);
        font-weight: var(--font-weight-medium);
    }

    .dark :is(.fi-resource-escolas, .fi-resource-lotacoes).fi-resource-list-records-page :is(
        .local-card-field--contact .fi-ta-text-item,
        .local-card-field--local .fi-ta-text-item
    ) {
        color: var(--gray-100);
    }

    @media (max-width: 48rem) {
        :is(.fi-resource-escolas, .fi-resource-lotacoes).fi-resource-list-records-page .fi-page-content {
            padding-inline: 0.75rem;
        }

        :is(.fi-resource-escolas, .fi-resource-lotacoes).fi-resource-list-records-page :is(
            .fi-ta-header,
            .fi-ta-content,
            .fi-ta-content-header
        ) {
            padding: 0.5rem;
        }

        :is(.fi-resource-escolas, .fi-resource-lotacoes).fi-resource-list-records-page .fi-ta-record-content {
            gap: 0.5rem;
            padding: 0.65rem;
        }

        :is(.fi-resource-escolas, .fi-resource-lotacoes).fi-resource-list-records-page .fi-ta-record-content-ctn > .fi-ta-actions {
            padding: 0.55rem 0.65rem;
        }
    }

    @media (max-width: 24rem) {
        :is(.fi-resource-escolas, .fi-resource-lotacoes).fi-resource-list-records-page .fi-page-content {
            padding-inline: 0.5rem;
        }

        :is(.fi-resource-escolas, .fi-resource-lotacoes).fi-resource-list-records-page :is(
            .fi-ta-content,
            .fi-ta-content-header
        ) {
            padding: 0.4rem;
        }

        :is(.fi-resource-escolas, .fi-resource-lotacoes).fi-resource-list-records-page .fi-ta-record-content {
            padding: 0.55rem;
        }
    }
</style>
