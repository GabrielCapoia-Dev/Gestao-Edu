<style>
    .fi-resource-pedidos.fi-resource-list-records-page {
        --pedido-card-border: color-mix(in oklab, var(--gray-200) 78%, var(--primary-100));
        --pedido-card-surface: #ffffff;
        --pedido-card-muted: var(--gray-500);
        --pedido-card-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 10px 24px rgba(15, 23, 42, 0.06);
    }

    .fi-resource-pedidos.fi-resource-list-records-page .fi-page-content,
    .fi-resource-pedidos.fi-resource-list-records-page .fi-ta,
    .fi-resource-pedidos.fi-resource-list-records-page .fi-ta-content-ctn,
    .fi-resource-pedidos.fi-resource-list-records-page .fi-ta-content,
    .fi-resource-pedidos.fi-resource-list-records-page .fi-ta-record,
    .fi-resource-pedidos.fi-resource-list-records-page .fi-ta-record-content-ctn,
    .fi-resource-pedidos.fi-resource-list-records-page .fi-ta-record-content,
    .fi-resource-pedidos.fi-resource-list-records-page .fi-ta-grid,
    .fi-resource-pedidos.fi-resource-list-records-page .fi-ta-split,
    .fi-resource-pedidos.fi-resource-list-records-page .fi-ta-stack {
        max-width: 100%;
        min-width: 0;
    }

    .fi-resource-pedidos.fi-resource-list-records-page .fi-ta-content-ctn {
        overflow-x: hidden !important;
    }

    .fi-resource-pedidos.fi-resource-list-records-page .fi-ta-content {
        display: grid;
        gap: 0.75rem;
        padding: 0.75rem;
    }

    .fi-resource-pedidos.fi-resource-list-records-page .fi-ta-content-header {
        flex-wrap: wrap;
        gap: 0.75rem;
        padding: 0.75rem;
    }

    .fi-resource-pedidos.fi-resource-list-records-page .fi-ta-sorting-settings {
        display: flex;
        flex: 1 1 24rem;
        flex-wrap: wrap;
        gap: 0.5rem;
        min-width: 0;
    }

    .fi-resource-pedidos.fi-resource-list-records-page .fi-ta-sorting-settings label {
        flex: 1 1 14rem;
        min-width: min(14rem, 100%);
    }

    .fi-resource-pedidos.fi-resource-list-records-page .fi-ta-record {
        border: 1px solid var(--pedido-card-border);
        border-radius: 0.5rem;
        background: var(--pedido-card-surface);
        box-shadow: var(--pedido-card-shadow);
    }

    .fi-resource-pedidos.fi-resource-list-records-page .fi-ta-record:hover {
        border-color: color-mix(in oklab, var(--primary-300) 58%, var(--gray-200));
    }

    .fi-resource-pedidos.fi-resource-list-records-page .fi-ta-record.pedido-card--has-additionals {
        --pedido-card-surface: color-mix(in srgb, #ffffff 92%, #fef3c7);
        --pedido-card-border: #f59e0b;
        border-left-width: 0.35rem;
        box-shadow: 0 2px 4px rgba(146, 64, 14, 0.1), 0 14px 30px rgba(146, 64, 14, 0.14);
    }

    .dark .fi-resource-pedidos.fi-resource-list-records-page .fi-ta-record.pedido-card--has-additionals {
        --pedido-card-surface: color-mix(in srgb, var(--gray-900) 90%, #78350f);
        --pedido-card-border: #d97706;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.24), 0 14px 30px rgba(120, 53, 15, 0.2);
    }

    .fi-resource-pedidos.fi-resource-list-records-page .fi-ta-record-content {
        display: grid;
        gap: 0.8rem;
        width: 100%;
        padding: 0.85rem;
    }

    .fi-resource-pedidos.fi-resource-list-records-page .fi-ta-record-content-ctn > .fi-ta-actions {
        display: none !important;
    }

    .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-sort-only {
        display: none !important;
    }

    .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-top.fi-ta-split {
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid var(--pedido-card-border);
    }

    .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-comment-slot {
        flex: 1 1 30rem;
        min-width: min(24rem, 100%);
    }

    .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-comment,
    .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-description {
        display: grid;
        gap: 0.3rem;
        min-width: 0;
        border: 1px solid color-mix(in srgb, var(--pedido-card-border) 82%, transparent);
        border-radius: 0.5rem;
        padding: 0.65rem 0.75rem;
    }

    .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-comment {
        background: color-mix(in srgb, #fff 92%, #fee2e2);
        border-color: color-mix(in srgb, #fca5a5 68%, var(--pedido-card-border));
    }

    .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-description {
        background: color-mix(in srgb, #fff 91%, #dcfce7);
        border-color: color-mix(in srgb, #86efac 64%, var(--pedido-card-border));
    }

    .dark .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-comment {
        background: color-mix(in srgb, var(--gray-900) 86%, #7f1d1d);
        border-color: color-mix(in srgb, #b91c1c 72%, var(--gray-700));
    }

    .dark .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-description {
        background: color-mix(in srgb, var(--gray-900) 86%, #14532d);
        border-color: color-mix(in srgb, #15803d 72%, var(--gray-700));
    }

    .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-block-label {
        color: var(--pedido-card-muted);
        font-size: var(--text-xs);
        line-height: var(--text-xs--line-height);
        font-weight: var(--font-weight-semibold);
    }

    .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-comment-text,
    .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-description-text,
    .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-comment-empty,
    .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-description-empty {
        color: var(--gray-950);
        font-size: var(--text-sm);
        line-height: 1.35;
        overflow-wrap: anywhere;
        white-space: pre-line;
    }

    .dark .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-comment-text,
    .dark .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-description-text,
    .dark .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-comment-empty,
    .dark .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-description-empty {
        color: var(--gray-100);
    }

    .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-comment-empty,
    .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-description-empty,
    .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-comment-meta {
        color: var(--pedido-card-muted);
    }

    .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-comment-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.25rem 0.5rem;
        font-size: var(--text-xs);
        line-height: var(--text-xs--line-height);
    }

    .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-main-grid,
    .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-meta-grid {
        gap: 0.7rem 0.85rem;
    }

    .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-meta-grid {
        padding-top: 0.2rem;
    }

    .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.45rem;
        min-width: 0;
    }

    .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-actions--footer {
        justify-content: flex-start;
        padding-top: 0.75rem;
        border-top: 1px solid var(--pedido-card-border);
    }

    .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-actions .fi-btn {
        max-width: 100%;
        min-height: 2.25rem;
        border-radius: 0.5rem;
        white-space: normal;
    }

    .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-actions .fi-btn span,
    .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-protocol .fi-ta-text-item,
    .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-field .fi-ta-text-item,
    .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-field .fi-ta-text-description {
        overflow-wrap: anywhere;
        word-break: normal;
    }

    .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-protocol .fi-ta-text-description,
    .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-field .fi-ta-text-description {
        color: var(--pedido-card-muted);
        font-size: var(--text-xs);
        line-height: var(--text-xs--line-height);
        font-weight: var(--font-weight-semibold);
        letter-spacing: 0;
    }

    .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-protocol .fi-ta-text-item {
        color: var(--primary-800);
        font-size: var(--text-base);
        line-height: var(--text-base--line-height);
        font-weight: var(--font-weight-bold);
    }

    .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-field .fi-badge,
    .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-field .fi-ta-text-item {
        max-width: 100%;
        white-space: normal;
    }

    .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-field--school .fi-ta-text-item {
        color: var(--gray-950);
        font-weight: var(--font-weight-medium);
    }

    .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-field--additionals .fi-badge {
        border: 1px solid color-mix(in srgb, #f59e0b 55%, transparent);
        box-shadow: 0 1px 3px rgba(146, 64, 14, 0.12);
    }

    .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-original-stack:empty,
    .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-footer-slot:empty {
        display: none;
    }

    @media (max-width: 48rem) {
        .fi-resource-pedidos.fi-resource-list-records-page .fi-page-content {
            padding-inline: 0.75rem;
        }

        .fi-resource-pedidos.fi-resource-list-records-page .fi-ta-header,
        .fi-resource-pedidos.fi-resource-list-records-page .fi-ta-content,
        .fi-resource-pedidos.fi-resource-list-records-page .fi-ta-content-header {
            padding: 0.65rem;
        }

        .fi-resource-pedidos.fi-resource-list-records-page .fi-ta-record-content {
            gap: 0.7rem;
            padding: 0.75rem;
        }

        .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-top.fi-ta-split {
            align-items: stretch;
        }

        .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-comment-slot {
            min-width: 0;
            width: 100%;
        }

        .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-actions,
        .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-actions .fi-btn {
            width: 100%;
        }

        .fi-resource-pedidos.fi-resource-list-records-page .pedido-card-actions .fi-btn {
            justify-content: center;
        }
    }

    @media (max-width: 24rem) {
        .fi-resource-pedidos.fi-resource-list-records-page .fi-page-content {
            padding-inline: 0.5rem;
        }

        .fi-resource-pedidos.fi-resource-list-records-page .fi-ta-content,
        .fi-resource-pedidos.fi-resource-list-records-page .fi-ta-content-header {
            padding: 0.5rem;
        }

        .fi-resource-pedidos.fi-resource-list-records-page .fi-ta-record-content {
            padding: 0.65rem;
        }
    }
</style>
