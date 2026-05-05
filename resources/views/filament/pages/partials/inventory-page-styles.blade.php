<style>
    .inv-bars--limit-10 {
        --item-height: 56px;
        /* ajuste fino se cada linha estiver maior/menor */
        --item-gap: 12px;
        max-height: calc((var(--item-height) * 10) + (var(--item-gap) * 9));
        overflow-y: auto;
        padding-right: 6px;
    }

    .inv-bars--limit-10::-webkit-scrollbar {
        width: 8px;
    }

    .inv-bars--limit-10::-webkit-scrollbar-thumb {
        background: rgba(15, 23, 42, 0.18);
        border-radius: 999px;
    }

    .inv-bars--limit-10::-webkit-scrollbar-track {
        background: transparent;
    }

    
    .inv-page,
    .gi-page {
        display: grid;
        gap: 1.5rem;
        color: var(--gray-700);
    }

    .inv-hero,
    .gi-hero,
    .inv-panel,
    .gi-panel,
    .inv-card,
    .gi-card,
    .inv-slideover,
    .gi-slideover,
    .gi-modal {
        border: 1px solid var(--gray-200);
        background:
            linear-gradient(180deg, var(--gray-50) 0%, #074f9b 100%);
        box-shadow:
            0 1px 2px rgba(15, 23, 42, 0.05),
            0 18px 40px rgba(15, 23, 42, 0.06);
    }

    .inv-hero,
    .gi-hero {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 1.5rem;
        padding: 1.5rem;
        border-radius: 1rem;
    }

    .inv-hero h1,
    .gi-hero h1,
    .inv-panel h2,
    .gi-modal h3,
    .gi-slideover h3,
    .inv-slideover h3 {
        margin: 0;
        color: var(--gray-950);
        font-size: clamp(1.5rem, 2vw, 2rem);
        line-height: 1.2;
        font-weight: var(--font-weight-bold);
        letter-spacing: -0.02em;
    }

    .inv-panel h2,
    .gi-modal h3,
    .gi-slideover h3,
    .inv-slideover h3 {
        font-size: var(--text-lg);
        line-height: var(--text-lg--line-height);
    }

    .inv-hero p,
    .gi-hero p,
    .inv-panel-head p,
    .inv-card small,
    .gi-card small,
    .inv-field span,
    .gi-field span,
    .inv-table small,
    .gi-table small,
    .gi-slideover small,
    .inv-empty,
    .inv-empty-copy,
    .gi-empty {
        margin: 0;
        color: var(--gray-500);
        font-size: var(--text-sm);
        line-height: 1.55;
    }

    .inv-eyebrow,
    .inv-panel-kicker,
    .gi-eyebrow {
        margin: 0 0 0.35rem;
        color: var(--primary-600);
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-size: var(--text-xs);
        line-height: var(--text-xs--line-height);
        font-weight: var(--font-weight-semibold);
    }

    .inv-actions,
    .gi-actions,
    .gi-row-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    .gi-row-actions {
        justify-content: flex-end;
    }

    .inv-action,
    .gi-action,
    .inv-link,
    .gi-row-actions button,
    .gi-row-actions a,
    .inv-pagination button,
    .gi-pagination button,
    .inv-slideover header button,
    .gi-slideover header button,
    .gi-modal header button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        min-height: 2.5rem;
        padding: 0.625rem 0.95rem;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-lg);
        background: #fff;
        color: var(--gray-700);
        font-size: var(--text-sm);
        line-height: var(--text-sm--line-height);
        font-weight: var(--font-weight-medium);
        text-decoration: none;
        transition:
            background-color 0.15s ease,
            border-color 0.15s ease,
            color 0.15s ease,
            box-shadow 0.15s ease,
            transform 0.15s ease;
        cursor: pointer;
    }

    .inv-action--primary,
    .gi-action--primary {
        border-color: var(--primary-600);
        background: var(--primary-600);
        color: #fff;
    }

    .inv-action--ghost,
    .gi-action--ghost,
    .inv-link,
    .gi-row-actions button,
    .gi-row-actions a,
    .inv-pagination button,
    .gi-pagination button,
    .inv-slideover header button,
    .gi-slideover header button,
    .gi-modal header button {
        background: #fff;
    }

    .inv-action:focus-visible,
    .gi-action:focus-visible,
    .inv-link:focus-visible,
    .gi-row-actions button:focus-visible,
    .gi-row-actions a:focus-visible,
    .inv-pagination button:focus-visible,
    .gi-pagination button:focus-visible,
    .inv-field input:focus,
    .inv-field select:focus,
    .gi-field input:focus,
    .gi-field select:focus,
    .gi-field textarea:focus,
    .gi-tabs button:focus-visible {
        outline: none;
        border-color: var(--primary-400);
        box-shadow: 0 0 0 3px color-mix(in oklab, var(--primary-200) 70%, transparent);
    }

    @media (hover: hover) {

        .inv-action:hover,
        .gi-action:hover,
        .inv-link:hover,
        .gi-row-actions button:hover,
        .gi-row-actions a:hover,
        .inv-pagination button:hover,
        .gi-pagination button:hover,
        .inv-slideover header button:hover,
        .gi-slideover header button:hover,
        .gi-modal header button:hover,
        .gi-tabs button:hover {
            border-color: var(--gray-300);
            background: var(--gray-50);
            color: var(--gray-950);
        }

        .inv-action--primary:hover,
        .gi-action--primary:hover {
            border-color: var(--primary-700);
            background: var(--primary-700);
            color: #fff;
        }
    }

    .inv-cards,
    .gi-cards {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(auto-fit, minmax(13.5rem, 1fr));
    }

    .inv-card,
    .gi-card {
        display: grid;
        gap: 0.4rem;
        padding: 1.25rem;
        border-radius: var(--radius-xl);
    }

    .inv-card span,
    .gi-card span {
        color: var(--gray-500);
        font-size: var(--text-sm);
        line-height: var(--text-sm--line-height);
    }

    .inv-card strong,
    .gi-card strong {
        color: var(--gray-950);
        font-size: clamp(1.35rem, 1.8vw, 1.85rem);
        line-height: 1.15;
        font-weight: var(--font-weight-bold);
        letter-spacing: -0.03em;
    }

    .inv-grid {
        display: grid;
        gap: 1rem;
        grid-template-columns: minmax(0, 1.45fr) minmax(20rem, 1fr);
        align-items: start;
    }

    .inv-panel,
    .gi-panel {
        display: grid;
        gap: 1rem;
        padding: 1.25rem;
        border-radius: var(--radius-xl);
    }

    .inv-panel-head,
    .gi-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
    }

    .inv-panel-tools {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        align-items: end;
        justify-content: flex-end;
        min-width: min(14rem, 100%);
    }

    .gi-toolbar-left,
    .gi-toolbar-right {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        align-items: end;
    }

    .gi-toolbar-left {
        flex: 1 1 28rem;
    }

    .gi-toolbar-right {
        justify-content: flex-end;
        flex: 0 0 auto;
    }

    .inv-filter-grid {
        display: grid;
        gap: 0.85rem;
        grid-template-columns: minmax(0, 1fr) minmax(9rem, 11rem);
    }

    .inv-field,
    .gi-field {
        display: grid;
        gap: 0.45rem;
        color: var(--gray-700);
        font-size: var(--text-sm);
        line-height: var(--text-sm--line-height);
        font-weight: var(--font-weight-medium);
        min-width: 0;
    }

    .gi-field {
        min-width: min(15rem, 100%);
    }

    .gi-field--small,
    .inv-field--small {
        min-width: 8.5rem;
    }

    .inv-field input,
    .inv-field select,
    .gi-field input,
    .gi-field select,
    .gi-field textarea {
        width: 100%;
        min-height: 2.75rem;
        padding: 0.75rem 0.875rem;
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

    .gi-field textarea {
        min-height: 8rem;
        resize: vertical;
    }

    .gi-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 0.65rem;
    }

    .gi-tabs button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 2.5rem;
        padding: 0.55rem 0.95rem;
        border: 1px solid var(--gray-200);
        border-radius: 999px;
        background: var(--gray-50);
        color: var(--gray-600);
        font-size: var(--text-sm);
        line-height: var(--text-sm--line-height);
        font-weight: var(--font-weight-medium);
        transition:
            background-color 0.15s ease,
            border-color 0.15s ease,
            color 0.15s ease,
            box-shadow 0.15s ease;
    }

    .gi-tabs .is-active {
        border-color: var(--primary-600);
        background: var(--primary-600);
        color: #fff;
    }

    .inv-table-wrap,
    .gi-table-wrap {
        overflow: auto;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-xl);
        background: #fff;
    }

    .inv-table,
    .gi-table {
        width: 100%;
        min-width: 46rem;
        border-collapse: separate;
        border-spacing: 0;
    }

    .inv-table th,
    .inv-table td,
    .gi-table th,
    .gi-table td {
        padding: 0.95rem 1rem;
        text-align: left;
        vertical-align: top;
        border-bottom: 1px solid var(--gray-200);
        font-size: var(--text-sm);
        line-height: 1.55;
    }

    .inv-table th,
    .gi-table th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: var(--gray-50);
        color: var(--gray-600);
        text-transform: uppercase;
        letter-spacing: 0.06em;
        font-size: var(--text-xs);
        line-height: var(--text-xs--line-height);
        font-weight: var(--font-weight-semibold);
    }

    .gi-table th button {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        border: 0;
        padding: 0;
        background: transparent;
        color: inherit;
        font: inherit;
        cursor: pointer;
    }

    .inv-table tbody tr:last-child td,
    .gi-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .inv-table td strong,
    .gi-table td strong {
        display: block;
        color: var(--gray-950);
        font-weight: var(--font-weight-semibold);
    }

    .inv-table .text-right,
    .gi-table .text-right {
        text-align: right;
    }

    .inv-pagination,
    .gi-pagination {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        color: var(--gray-500);
        font-size: var(--text-sm);
        line-height: var(--text-sm--line-height);
    }

    .inv-pagination div,
    .gi-pagination div {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .inv-pagination button:disabled,
    .gi-pagination button:disabled,
    .gi-row-actions button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .inv-bars {
        display: grid;
        gap: 0.9rem;
    }

    .inv-bar {
        display: grid;
        gap: 0.45rem;
    }

    .inv-bar-label {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.75rem;
        color: var(--gray-700);
        font-size: var(--text-sm);
        line-height: var(--text-sm--line-height);
    }

    .inv-bar-label strong {
        color: var(--gray-950);
        font-weight: var(--font-weight-semibold);
    }

    .inv-bar-track {
        height: 0.75rem;
        border-radius: 999px;
        background: var(--gray-100);
        overflow: hidden;
    }

    .inv-bar-track span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, var(--primary-500) 0%, var(--primary-600) 100%);
    }

    .inv-bar-track--rose span {
        background: linear-gradient(90deg, var(--warning-500) 0%, var(--danger-600) 100%);
    }

    .badge,
    .status {
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

    .badge-entrada,
    .status-normal {
        border-color: var(--success-200);
        background: var(--success-50);
        color: var(--success-700);
    }

    .badge-saida,
    .status-zerado {
        border-color: var(--danger-200);
        background: var(--danger-50);
        color: var(--danger-700);
    }

    .badge-transferencia {
        border-color: var(--primary-200);
        background: var(--primary-50);
        color: var(--primary-700);
    }

    .status-critico {
        border-color: var(--warning-200);
        background: var(--warning-50);
        color: var(--warning-700);
    }

    .inv-overlay,
    .gi-overlay {
        position: fixed;
        inset: 0;
        z-index: 40;
        background: rgba(15, 23, 42, 0.45);
        backdrop-filter: blur(3px);
    }

    .inv-slideover,
    .gi-slideover {
        position: fixed;
        top: 0;
        right: 0;
        z-index: 50;
        width: min(40rem, 100vw);
        height: 100dvh;
        display: grid;
        grid-template-rows: auto 1fr;
        border-left: 1px solid var(--gray-200);
        border-radius: 0;
    }

    .inv-slideover header,
    .gi-slideover header,
    .gi-modal header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        padding: 1.25rem;
        border-bottom: 1px solid var(--gray-200);
    }

    .inv-slideover-body,
    .gi-slideover-body,
    .gi-modal-body {
        overflow: auto;
        padding: 1.25rem;
        display: grid;
        gap: 0.85rem;
    }

    .inv-mov,
    .gi-mov {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: start;
        gap: 1rem;
        padding: 1rem;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-xl);
        background: var(--gray-50);
    }

    .inv-mov small,
    .gi-mov small {
        display: block;
        margin-top: 0.25rem;
        color: var(--gray-500);
    }

    .inv-mov-meta,
    .gi-mov-meta {
        display: grid;
        justify-items: end;
        gap: 0.25rem;
        text-align: right;
        color: var(--gray-700);
    }

    .gi-modal {
        position: fixed;
        inset: 50% auto auto 50%;
        z-index: 60;
        width: min(40rem, calc(100vw - 1.5rem));
        max-height: calc(100dvh - 1.5rem);
        display: grid;
        grid-template-rows: auto 1fr auto;
        transform: translate(-50%, -50%);
        overflow: hidden;
        border-radius: 1rem;
    }

    .gi-modal footer {
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
        padding: 0 1.25rem 1.25rem;
    }

    .error {
        color: var(--danger-600);
        font-size: var(--text-xs);
        line-height: var(--text-xs--line-height);
    }

    @media (max-width: 80rem) {
        .inv-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 64rem) {

        .inv-hero,
        .gi-hero,
        .inv-panel-head,
        .gi-toolbar {
            align-items: stretch;
            flex-direction: column;
        }

        .inv-panel-tools {
            width: 100%;
            justify-content: stretch;
        }

        .gi-row-actions {
            justify-content: flex-start;
        }
    }

    @media (max-width: 48rem) {

        .inv-page,
        .gi-page {
            gap: 1rem;
        }

        .inv-hero,
        .gi-hero,
        .inv-panel,
        .gi-panel,
        .inv-card,
        .gi-card {
            padding: 1rem;
        }

        .inv-actions,
        .gi-actions,
        .gi-row-actions,
        .inv-pagination div,
        .gi-pagination div,
        .gi-modal footer {
            width: 100%;
        }

        .inv-actions>*,
        .gi-actions>*,
        .gi-row-actions>*,
        .inv-panel-tools>*,
        .inv-pagination button,
        .gi-pagination button,
        .gi-modal footer>* {
            flex: 1 1 100%;
        }

        .inv-filter-grid,
        .gi-toolbar-left,
        .gi-toolbar-right {
            grid-template-columns: 1fr;
            flex-direction: column;
            width: 100%;
        }

        .inv-field,
        .gi-field,
        .gi-field--small,
        .inv-field--small {
            min-width: 0;
            width: 100%;
        }

        .inv-table,
        .gi-table {
            min-width: 40rem;
        }

        .inv-table .text-right,
        .gi-table .text-right,
        .inv-mov-meta,
        .gi-mov-meta {
            text-align: left;
            justify-items: start;
        }

        .inv-bar-label,
        .inv-mov,
        .gi-mov {
            grid-template-columns: 1fr;
        }

        .inv-slideover,
        .gi-slideover {
            width: 100vw;
        }

        .gi-modal {
            inset: auto 0 0 0;
            width: 100vw;
            max-height: 88dvh;
            transform: none;
            border-radius: 1rem 1rem 0 0;
        }

        .gi-modal footer {
            flex-direction: column-reverse;
        }
    }

    :root.dark .inv-hero,
    :root.dark .gi-hero,
    :root.dark .inv-panel,
    :root.dark .gi-panel,
    :root.dark .inv-card,
    :root.dark .gi-card,
    :root.dark .inv-slideover,
    :root.dark .gi-slideover,
    :root.dark .gi-modal {
        border-color: var(--gray-800);
        background: linear-gradient(180deg, var(--gray-900) 0%, var(--gray-950) 100%);
        box-shadow:
            0 1px 2px rgba(0, 0, 0, 0.35),
            0 18px 40px rgba(0, 0, 0, 0.22);
    }

    :root.dark .inv-hero h1,
    :root.dark .gi-hero h1,
    :root.dark .inv-panel h2,
    :root.dark .gi-modal h3,
    :root.dark .gi-slideover h3,
    :root.dark .inv-slideover h3,
    :root.dark .inv-card strong,
    :root.dark .gi-card strong,
    :root.dark .inv-table td strong,
    :root.dark .gi-table td strong,
    :root.dark .inv-bar-label strong {
        color: #fff;
    }

    :root.dark .inv-hero p,
    :root.dark .gi-hero p,
    :root.dark .inv-panel-head p,
    :root.dark .inv-card small,
    :root.dark .gi-card small,
    :root.dark .inv-field span,
    :root.dark .gi-field span,
    :root.dark .inv-table small,
    :root.dark .gi-table small,
    :root.dark .gi-slideover small,
    :root.dark .inv-empty,
    :root.dark .inv-empty-copy,
    :root.dark .gi-empty,
    :root.dark .inv-card span,
    :root.dark .gi-card span,
    :root.dark .inv-pagination,
    :root.dark .gi-pagination,
    :root.dark .inv-bar-label,
    :root.dark .inv-mov-meta,
    :root.dark .gi-mov-meta,
    :root.dark .inv-page,
    :root.dark .gi-page {
        color: var(--gray-400);
    }

    :root.dark .inv-action,
    :root.dark .gi-action,
    :root.dark .inv-link,
    :root.dark .gi-row-actions button,
    :root.dark .gi-row-actions a,
    :root.dark .inv-pagination button,
    :root.dark .gi-pagination button,
    :root.dark .inv-slideover header button,
    :root.dark .gi-slideover header button,
    :root.dark .gi-modal header button,
    :root.dark .gi-tabs button {
        border-color: var(--gray-700);
        background: var(--gray-900);
        color: var(--gray-200);
    }

    :root.dark .inv-action--primary,
    :root.dark .gi-action--primary,
    :root.dark .gi-tabs .is-active {
        border-color: var(--primary-500);
        background: var(--primary-600);
        color: #fff;
    }

    :root.dark .inv-field input,
    :root.dark .inv-field select,
    :root.dark .gi-field input,
    :root.dark .gi-field select,
    :root.dark .gi-field textarea,
    :root.dark .inv-table-wrap,
    :root.dark .gi-table-wrap,
    :root.dark .inv-table th,
    :root.dark .gi-table th,
    :root.dark .inv-mov,
    :root.dark .gi-mov {
        border-color: var(--gray-800);
        background: var(--gray-900);
        color: var(--gray-100);
    }

    :root.dark .inv-bar-track {
        background: var(--gray-800);
    }

    :root.dark .inv-overlay,
    :root.dark .gi-overlay {
        background: rgba(2, 6, 23, 0.7);
    }

    .inv-hero,
    .gi-hero {
        position: relative;
        overflow: hidden;
        isolation: isolate;
        min-height: 10rem;
        align-items: end;
        border-color: rgba(191, 219, 254, 0.28);
        background:
            linear-gradient(105deg, #11366f 0%, #174f8f 54%, #2f72ad 100%);
        box-shadow:
            0 1px 2px rgba(15, 23, 42, 0.08),
            0 18px 44px rgba(15, 23, 42, 0.12);
    }

    .inv-hero::before,
    .gi-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        z-index: -1;
        opacity: 0.24;
        background-image:
            linear-gradient(rgba(255, 255, 255, 0.12) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255, 255, 255, 0.12) 1px, transparent 1px);
        background-size: 3.9rem 3.9rem;
    }

    .inv-hero::after,
    .gi-hero::after {
        content: '';
        position: absolute;
        inset: 0;
        z-index: -1;
        background:
            linear-gradient(90deg, rgba(8, 25, 57, 0.2), transparent 54%),
            radial-gradient(circle at 88% 8%, rgba(125, 184, 239, 0.34), transparent 28rem);
    }

    .inv-hero h1,
    .gi-hero h1,
    :root.dark .inv-hero h1,
    :root.dark .gi-hero h1 {
        color: #fff;
        letter-spacing: 0;
    }

    .inv-hero p,
    .gi-hero p,
    :root.dark .inv-hero p,
    :root.dark .gi-hero p {
        color: rgba(229, 242, 255, 0.9);
    }

    .inv-eyebrow,
    .gi-eyebrow {
        color: rgba(232, 244, 255, 0.94);
    }

    .inv-hero .inv-action,
    .gi-hero .gi-action,
    .gi-hero .fi-btn {
        border-color: rgba(219, 234, 254, 0.82);
        background: rgba(255, 255, 255, 0.96);
        color: #0f2d5c;
        box-shadow: 0 8px 22px rgba(5, 20, 46, 0.16);
    }

    .inv-hero .inv-action--primary,
    .gi-hero .gi-action--primary,
    .gi-hero .fi-color-primary {
        border-color: #0f4e9b;
        background: #0f4e9b;
        color: #fff;
    }

    .gi-hero .fi-ac {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        justify-content: flex-end;
    }

    :root.dark .inv-hero,
    :root.dark .gi-hero {
        border-color: rgba(96, 165, 250, 0.28);
        background:
            linear-gradient(105deg, #0b2349 0%, #0d3a72 54%, #17568b 100%);
        box-shadow:
            0 1px 2px rgba(0, 0, 0, 0.35),
            0 18px 44px rgba(0, 0, 0, 0.28);
    }

    :root.dark .inv-eyebrow,
    :root.dark .gi-eyebrow {
        color: rgba(232, 244, 255, 0.94);
    }

    :root.dark .inv-hero .inv-action,
    :root.dark .gi-hero .gi-action,
    :root.dark .gi-hero .fi-btn {
        border-color: rgba(219, 234, 254, 0.78);
        background: rgba(255, 255, 255, 0.95);
        color: #0f2d5c;
    }

    :root.dark .inv-hero .inv-action--primary,
    :root.dark .gi-hero .gi-action--primary,
    :root.dark .gi-hero .fi-color-primary {
        border-color: #2563eb;
        background: #1d4ed8;
        color: #fff;
    }

    .fi-page-header-main-ctn > .fi-header {
        position: relative;
        overflow: hidden;
        isolation: isolate;
        align-items: end;
        min-height: 10rem;
        padding: 1.5rem;
        border: 1px solid rgba(191, 219, 254, 0.28);
        border-radius: 1rem;
        background:
            linear-gradient(105deg, #11366f 0%, #174f8f 54%, #2f72ad 100%);
        box-shadow:
            0 1px 2px rgba(15, 23, 42, 0.08),
            0 18px 44px rgba(15, 23, 42, 0.12);
    }

    .fi-page-header-main-ctn > .fi-header::before {
        content: '';
        position: absolute;
        inset: 0;
        z-index: -1;
        opacity: 0.24;
        background-image:
            linear-gradient(rgba(255, 255, 255, 0.12) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255, 255, 255, 0.12) 1px, transparent 1px);
        background-size: 3.9rem 3.9rem;
    }

    .fi-page-header-main-ctn > .fi-header::after {
        content: '';
        position: absolute;
        inset: 0;
        z-index: -1;
        background:
            linear-gradient(90deg, rgba(8, 25, 57, 0.2), transparent 54%),
            radial-gradient(circle at 88% 8%, rgba(125, 184, 239, 0.34), transparent 28rem);
    }

    .fi-page-header-main-ctn > .fi-header .fi-header-heading {
        color: #fff;
        letter-spacing: 0;
    }

    .fi-page-header-main-ctn > .fi-header .fi-header-subheading,
    .fi-page-header-main-ctn > .fi-header .fi-breadcrumbs,
    .fi-page-header-main-ctn > .fi-header .fi-breadcrumbs a {
        color: rgba(229, 242, 255, 0.9);
    }

    .fi-page-header-main-ctn > .fi-header .fi-btn {
        border-color: rgba(219, 234, 254, 0.82);
        background: rgba(255, 255, 255, 0.96);
        color: #0f2d5c;
        box-shadow: 0 8px 22px rgba(5, 20, 46, 0.16);
    }

    .fi-page-header-main-ctn > .fi-header .fi-color-primary {
        border-color: #0f4e9b;
        background: #0f4e9b;
        color: #fff;
    }

    .fi-resource-list-records-page .fi-ta {
        overflow: hidden;
        border: 1px solid var(--gray-200);
        border-radius: 1rem;
        background: #fff;
        box-shadow:
            0 1px 2px rgba(15, 23, 42, 0.05),
            0 18px 40px rgba(15, 23, 42, 0.08);
    }

    .fi-resource-list-records-page .fi-ta-header {
        padding: 0.9rem;
        background: #fff;
    }

    .fi-resource-list-records-page .fi-ta-header-toolbar {
        gap: 0.75rem;
    }

    .fi-resource-list-records-page .fi-ta-table thead {
        background: #15358a;
    }

    .fi-resource-list-records-page .fi-ta-table thead th,
    .fi-resource-list-records-page .fi-ta-table thead th button,
    .fi-resource-list-records-page .fi-ta-table thead label {
        color: #fff;
    }

    .fi-resource-list-records-page .fi-ta-row:nth-child(even) {
        background: #f6f8fc;
    }

    .fi-resource-list-records-page .fi-ta-search-field input {
        border-radius: 0.65rem;
    }

    :root.dark .fi-page-header-main-ctn > .fi-header {
        border-color: rgba(96, 165, 250, 0.28);
        background:
            linear-gradient(105deg, #0b2349 0%, #0d3a72 54%, #17568b 100%);
    }

    :root.dark .fi-page-header-main-ctn > .fi-header .fi-header-heading {
        color: #fff;
    }

    :root.dark .fi-resource-list-records-page .fi-ta,
    :root.dark .fi-resource-list-records-page .fi-ta-header {
        border-color: var(--gray-800);
        background: var(--gray-900);
    }

    :root.dark .fi-resource-list-records-page .fi-ta-row:nth-child(even) {
        background: rgba(15, 23, 42, 0.68);
    }

    @media (max-width: 48rem) {
        .inv-hero,
        .gi-hero,
        .fi-page-header-main-ctn > .fi-header {
            min-height: auto;
        }
    }
</style>
