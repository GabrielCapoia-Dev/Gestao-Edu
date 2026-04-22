@include('filament.pages.partials.inventory-page-styles')

<style>
    .av-page {
        display: grid;
        gap: 1rem;
    }

    .av-total-card {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        min-height: 2.5rem;
        padding: 0.6rem 0.85rem;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-lg);
        background: var(--gray-50);
        color: var(--gray-700);
        font-size: var(--text-sm);
        line-height: var(--text-sm--line-height);
        font-weight: var(--font-weight-medium);
    }

    .av-total-card strong {
        color: var(--gray-950);
        font-weight: var(--font-weight-semibold);
    }

    .av-note {
        padding: 0.9rem 1rem;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-xl);
        background: var(--gray-50);
        color: var(--gray-700);
        font-size: var(--text-sm);
        line-height: 1.55;
    }

    .av-note--warning {
        border-color: var(--warning-200);
        background: color-mix(in oklab, var(--warning-50) 86%, #fff);
        color: var(--warning-800);
    }

    .av-note--danger {
        border-color: var(--danger-200);
        background: color-mix(in oklab, var(--danger-50) 88%, #fff);
        color: var(--danger-800);
    }

    .av-status {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        padding: 0.25rem 0.625rem;
        border: 1px solid transparent;
        border-radius: 999px;
        font-size: var(--text-xs);
        line-height: var(--text-xs--line-height);
        font-weight: var(--font-weight-medium);
        white-space: nowrap;
    }

    .av-status--active {
        border-color: var(--success-200);
        background: var(--success-50);
        color: var(--success-700);
    }

    .av-status--inactive {
        border-color: var(--gray-300);
        background: var(--gray-100);
        color: var(--gray-700);
    }

    .av-status--closed {
        border-color: var(--warning-200);
        background: var(--warning-50);
        color: var(--warning-700);
    }

    .av-status--canceled {
        border-color: var(--danger-200);
        background: var(--danger-50);
        color: var(--danger-700);
    }

    .av-form-grid {
        display: grid;
        gap: 0.9rem;
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .av-form-grid--three {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .av-form-grid--two {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .av-span-2 {
        grid-column: span 2;
    }

    .av-form-section {
        display: grid;
        gap: 0.75rem;
        padding: 1rem;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-xl);
        background: var(--gray-50);
    }

    .av-form-section h4 {
        margin: 0;
        color: var(--gray-950);
        font-size: var(--text-sm);
        line-height: var(--text-sm--line-height);
        font-weight: var(--font-weight-semibold);
    }

    .av-form-section p {
        margin: 0;
        color: var(--gray-500);
        font-size: var(--text-xs);
        line-height: 1.55;
    }

    .av-multi-select {
        min-height: 11rem !important;
    }

    .av-multi-select option {
        padding: 0.25rem 0.3rem;
    }

    .av-selection-grid {
        display: grid;
        gap: 0.85rem;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .av-selection-title {
        display: inline-block;
        margin-bottom: 0.5rem;
        color: var(--gray-500);
        font-size: var(--text-xs);
        line-height: var(--text-xs--line-height);
        font-weight: var(--font-weight-semibold);
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }

    .av-subitem {
        display: grid;
        gap: 0.9rem;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        padding: 0.85rem;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-xl);
        background: #fff;
    }

    .av-progress {
        display: grid;
        gap: 0.5rem;
    }

    .av-progress-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.75rem;
        color: var(--gray-600);
        font-size: var(--text-xs);
        line-height: var(--text-xs--line-height);
    }

    .av-progress-track {
        height: 0.55rem;
        border-radius: 999px;
        overflow: hidden;
        background: var(--gray-200);
    }

    .av-progress-bar {
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, var(--primary-500) 0%, var(--primary-600) 100%);
        transition: width 0.2s ease;
    }

    .av-stack {
        display: grid;
        gap: 0.85rem;
    }

    .av-pauta-head {
        display: grid;
        gap: 0.25rem;
    }

    .av-pauta-title {
        margin: 0;
        color: var(--gray-950);
        font-size: var(--text-base);
        line-height: 1.45;
        font-weight: var(--font-weight-semibold);
    }

    .av-pauta-meta {
        margin: 0;
        color: var(--gray-500);
        font-size: var(--text-xs);
        line-height: 1.5;
    }

    .av-no-wrap {
        white-space: nowrap;
    }

    .av-modal--wide {
        width: min(74rem, calc(100vw - 1.5rem));
    }

    .av-chip-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
    }

    .av-chip {
        display: inline-flex;
        align-items: center;
        min-height: 1.8rem;
        padding: 0.2rem 0.55rem;
        border: 1px solid var(--primary-200);
        border-radius: 999px;
        background: var(--primary-50);
        color: var(--primary-700);
        font-size: var(--text-xs);
        line-height: var(--text-xs--line-height);
        font-weight: var(--font-weight-medium);
        white-space: nowrap;
    }

    .av-chip--muted {
        border-color: var(--gray-300);
        background: var(--gray-100);
        color: var(--gray-700);
    }

    .av-table-input {
        width: 100%;
        min-height: 2.45rem;
        padding: 0.55rem 0.75rem;
        border: 1px solid var(--gray-300);
        border-radius: var(--radius-lg);
        background: #fff;
        color: var(--gray-950);
        font-size: var(--text-sm);
        line-height: 1.5;
        transition:
            border-color 0.15s ease,
            box-shadow 0.15s ease,
            background-color 0.15s ease;
    }

    .av-table-input:focus {
        outline: none;
        border-color: var(--primary-400);
        box-shadow: 0 0 0 3px color-mix(in oklab, var(--primary-200) 70%, transparent);
    }

    .av-input-wrap {
        display: grid;
        gap: 0.35rem;
    }

    .av-saving-indicator {
        display: none;
        align-items: center;
        gap: 0.35rem;
        color: var(--primary-600);
        font-size: var(--text-xs);
        line-height: var(--text-xs--line-height);
        font-weight: var(--font-weight-medium);
    }

    .av-spinner {
        width: 0.85rem;
        height: 0.85rem;
        border: 2px solid color-mix(in oklab, var(--primary-200) 70%, transparent);
        border-top-color: var(--primary-600);
        border-radius: 999px;
        animation: av-spin 0.7s linear infinite;
    }

    @keyframes av-spin {
        from {
            transform: rotate(0deg);
        }

        to {
            transform: rotate(360deg);
        }
    }

    .av-field-hint {
        display: block;
        margin-top: 0.45rem;
        color: var(--gray-500);
        font-size: var(--text-xs);
        line-height: 1.45;
    }

    .av-field-hint--danger {
        color: var(--danger-700);
    }

    @media (max-width: 64rem) {
        .av-form-grid,
        .av-form-grid--three,
        .av-form-grid--two,
        .av-subitem {
            grid-template-columns: 1fr 1fr;
        }

        .av-span-2 {
            grid-column: span 2;
        }
    }

    @media (max-width: 48rem) {
        .av-form-grid,
        .av-form-grid--three,
        .av-form-grid--two,
        .av-subitem {
            grid-template-columns: 1fr;
        }

        .av-selection-grid {
            grid-template-columns: 1fr;
        }

        .av-span-2 {
            grid-column: span 1;
        }
    }

    :root.dark .av-total-card,
    :root.dark .av-form-section,
    :root.dark .av-subitem {
        border-color: var(--gray-800);
        background: var(--gray-900);
        color: var(--gray-300);
    }

    :root.dark .av-total-card strong,
    :root.dark .av-form-section h4,
    :root.dark .av-pauta-title {
        color: #fff;
    }

    :root.dark .av-note {
        border-color: var(--gray-800);
        background: var(--gray-900);
        color: var(--gray-300);
    }

    :root.dark .av-note--warning {
        border-color: color-mix(in oklab, var(--warning-400) 35%, var(--gray-800));
        background: color-mix(in oklab, var(--warning-800) 32%, var(--gray-950));
        color: var(--warning-200);
    }

    :root.dark .av-note--danger {
        border-color: color-mix(in oklab, var(--danger-400) 35%, var(--gray-800));
        background: color-mix(in oklab, var(--danger-800) 30%, var(--gray-950));
        color: var(--danger-200);
    }

    :root.dark .av-status--inactive {
        border-color: var(--gray-700);
        background: var(--gray-800);
        color: var(--gray-200);
    }

    :root.dark .av-table-input {
        border-color: var(--gray-700);
        background: var(--gray-900);
        color: var(--gray-100);
    }

    :root.dark .av-chip {
        border-color: color-mix(in oklab, var(--primary-400) 35%, var(--gray-800));
        background: color-mix(in oklab, var(--primary-700) 20%, var(--gray-950));
        color: var(--primary-200);
    }

    :root.dark .av-chip--muted {
        border-color: var(--gray-700);
        background: var(--gray-800);
        color: var(--gray-200);
    }

    :root.dark .av-field-hint {
        color: var(--gray-400);
    }

    :root.dark .av-selection-title {
        color: var(--gray-400);
    }

    :root.dark .av-field-hint--danger {
        color: var(--danger-200);
    }

    :root.dark .av-saving-indicator {
        color: var(--primary-300);
    }

    :root.dark .av-progress-track {
        background: var(--gray-800);
    }
</style>
