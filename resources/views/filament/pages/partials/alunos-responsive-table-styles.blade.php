<style>
    .fi-resource-alunos.fi-resource-list-records-page {
        --aluno-card-border: color-mix(in oklab, var(--gray-200) 78%, var(--primary-100));
        --aluno-card-surface: #ffffff;
        --aluno-card-muted: var(--gray-500);
        --aluno-card-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 10px 24px rgba(15, 23, 42, 0.06);
    }

    .fi-resource-alunos.fi-resource-list-records-page .fi-page-content,
    .fi-resource-alunos.fi-resource-list-records-page .fi-ta,
    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-content-ctn,
    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-content,
    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-record,
    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-record-content-ctn,
    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-record-content,
    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-grid,
    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-split,
    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-stack {
        max-width: 100%;
        min-width: 0;
    }

    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-content-ctn {
        overflow-x: visible !important;
    }

    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-content {
        padding: 0.75rem;
    }

    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-table {
        display: block;
        width: 100%;
        min-width: 0 !important;
        border-collapse: separate;
        border-spacing: 0;
    }

    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-table thead {
        display: none !important;
    }

    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-table tbody {
        display: grid;
        width: 100%;
        gap: 0.75rem;
    }

    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-table .fi-ta-row {
        display: block;
        width: 100%;
        overflow: hidden;
        border: 1px solid var(--aluno-card-border);
        border-radius: 0.5rem;
        background: var(--aluno-card-surface);
        box-shadow: var(--aluno-card-shadow);
    }

    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-table .fi-ta-row:hover {
        border-color: color-mix(in oklab, var(--primary-300) 58%, var(--gray-200));
    }

    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-table .fi-ta-cell {
        display: block;
        width: 100%;
        padding: 0 !important;
    }

    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-table .fi-ta-cell:has(> .fi-ta-actions) {
        display: none !important;
    }

    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-table .fi-ta-selection-cell {
        padding: 0.75rem 0.85rem 0 !important;
    }

    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-content-header {
        flex-wrap: wrap;
        gap: 0.75rem;
        padding: 0.75rem;
    }

    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-sorting-settings {
        display: flex;
        flex: 1 1 24rem;
        flex-wrap: wrap;
        gap: 0.5rem;
        min-width: 0;
    }

    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-sorting-settings label {
        flex: 1 1 14rem;
        min-width: min(14rem, 100%);
    }

    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-record {
        border: 1px solid var(--aluno-card-border);
        border-radius: 0.5rem;
        background: var(--aluno-card-surface);
        box-shadow: var(--aluno-card-shadow);
    }

    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-record:hover {
        border-color: color-mix(in oklab, var(--primary-300) 58%, var(--gray-200));
    }

    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-record-content {
        display: grid;
        gap: 0.8rem;
        width: 100%;
        padding: 0.85rem;
    }

    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-table .fi-ta-col {
        display: block;
        width: 100%;
    }

    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-table .fi-ta-col > .fi-ta-text,
    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-table .fi-ta-col > .fi-ta-layout,
    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-table .fi-ta-col > .fi-ta-view {
        padding: 0.85rem;
    }

    .fi-resource-alunos.fi-resource-list-records-page .fi-ta-record-content-ctn > .fi-ta-actions {
        display: none !important;
    }

    .fi-resource-alunos.fi-resource-list-records-page .aluno-card-top.fi-ta-split {
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid var(--aluno-card-border);
    }

    .fi-resource-alunos.fi-resource-list-records-page .aluno-card-main-grid,
    .fi-resource-alunos.fi-resource-list-records-page .aluno-card-meta-grid {
        gap: 0.7rem 0.85rem;
    }

    .fi-resource-alunos.fi-resource-list-records-page .aluno-card-meta-grid {
        padding-top: 0.2rem;
    }

    .fi-resource-alunos.fi-resource-list-records-page .aluno-card-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.45rem;
        min-width: 0;
    }

    .fi-resource-alunos.fi-resource-list-records-page .aluno-card-actions--footer {
        justify-content: flex-start;
        padding-top: 0.75rem;
        border-top: 1px solid var(--aluno-card-border);
    }

    .fi-resource-alunos.fi-resource-list-records-page .aluno-card-actions .fi-btn {
        max-width: 100%;
        min-height: 2.25rem;
        border-radius: 0.5rem;
        white-space: normal;
    }

    .fi-resource-alunos.fi-resource-list-records-page .aluno-card-actions .fi-btn span,
    .fi-resource-alunos.fi-resource-list-records-page .aluno-card-name .fi-ta-text-item,
    .fi-resource-alunos.fi-resource-list-records-page .aluno-card-field .fi-ta-text-item,
    .fi-resource-alunos.fi-resource-list-records-page .aluno-card-field .fi-ta-text-description {
        overflow-wrap: anywhere;
        word-break: normal;
    }

    .fi-resource-alunos.fi-resource-list-records-page .aluno-card-name .fi-ta-text-description,
    .fi-resource-alunos.fi-resource-list-records-page .aluno-card-field .fi-ta-text-description {
        color: var(--aluno-card-muted);
        font-size: var(--text-xs);
        line-height: var(--text-xs--line-height);
        font-weight: var(--font-weight-semibold);
        letter-spacing: 0;
    }

    .fi-resource-alunos.fi-resource-list-records-page .aluno-card-name .fi-ta-text-item {
        color: var(--primary-800);
        font-size: var(--text-base);
        line-height: var(--text-base--line-height);
        font-weight: var(--font-weight-bold);
    }

    .fi-resource-alunos.fi-resource-list-records-page .aluno-card-field .fi-badge,
    .fi-resource-alunos.fi-resource-list-records-page .aluno-card-field .fi-ta-text-item {
        max-width: 100%;
        white-space: normal;
    }

    .fi-resource-alunos.fi-resource-list-records-page .aluno-card-field--school .fi-ta-text-item {
        color: var(--gray-950);
        font-weight: var(--font-weight-medium);
    }

    .fi-resource-alunos.fi-resource-list-records-page .aluno-card-footer-slot:empty,
    .fi-resource-alunos.fi-resource-list-records-page .aluno-card-actions-slot:empty {
        display: none;
    }

    @media (max-width: 48rem) {
        .fi-resource-alunos.fi-resource-list-records-page .fi-page-content {
            padding-inline: 0.75rem;
        }

        .fi-resource-alunos.fi-resource-list-records-page .fi-ta-header,
        .fi-resource-alunos.fi-resource-list-records-page .fi-ta-content,
        .fi-resource-alunos.fi-resource-list-records-page .fi-ta-content-header {
            padding: 0.65rem;
        }

        .fi-resource-alunos.fi-resource-list-records-page .fi-ta-record-content {
            gap: 0.7rem;
            padding: 0.75rem;
        }

        .fi-resource-alunos.fi-resource-list-records-page .aluno-card-top.fi-ta-split {
            align-items: stretch;
        }

        .fi-resource-alunos.fi-resource-list-records-page .aluno-card-actions,
        .fi-resource-alunos.fi-resource-list-records-page .aluno-card-actions .fi-btn {
            width: 100%;
        }

        .fi-resource-alunos.fi-resource-list-records-page .aluno-card-actions .fi-btn {
            justify-content: center;
        }
    }

    @media (max-width: 24rem) {
        .fi-resource-alunos.fi-resource-list-records-page .fi-page-content {
            padding-inline: 0.5rem;
        }

        .fi-resource-alunos.fi-resource-list-records-page .fi-ta-content,
        .fi-resource-alunos.fi-resource-list-records-page .fi-ta-content-header {
            padding: 0.5rem;
        }

        .fi-resource-alunos.fi-resource-list-records-page .fi-ta-record-content {
            padding: 0.65rem;
        }
    }
</style>
