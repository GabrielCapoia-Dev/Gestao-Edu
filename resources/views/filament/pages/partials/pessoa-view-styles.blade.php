<style>
    /* ========== Pessoas: ficha personalizada de visualização ========== */
    .pessoa-view-modal-window {
        --pv-primary: #074f9b;
        --pv-primary-strong: #123b75;
        --pv-border: #dce5f0;
        --pv-muted: #64748b;
        --pv-surface: #ffffff;
        --pv-soft: #f5f8fc;
    }

    .pessoa-view-modal-window .fi-modal-content {
        padding: 0 !important;
        background: var(--pv-soft) !important;
    }

    .pessoa-view-modal-window .fi-sc,
    .pessoa-view-modal-window .fi-sc-component {
        gap: 0 !important;
    }

    .pessoa-view-modal-window .fi-modal-footer {
        position: relative;
        z-index: 8;
        background: rgba(255, 255, 255, 0.98) !important;
        box-shadow: 0 -10px 24px rgba(15, 23, 42, 0.05);
    }

    .pessoa-custom-view {
        min-width: 0;
        color: #172033;
    }

    .pessoa-custom-view [x-cloak] {
        display: none !important;
    }

    .pessoa-custom-view__hero {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1.5rem;
        padding: 1.5rem;
        color: #fff;
        background:
            radial-gradient(circle at 88% 14%, rgba(96, 165, 250, 0.38), transparent 28%),
            radial-gradient(circle at 48% 120%, rgba(14, 165, 233, 0.2), transparent 36%),
            linear-gradient(135deg, #073b75 0%, var(--pv-primary) 48%, #123b75 100%);
    }

    .pessoa-custom-view__identity {
        display: flex;
        align-items: center;
        gap: 1rem;
        min-width: 0;
    }

    .pessoa-custom-view__avatar {
        display: grid;
        width: 4.25rem;
        height: 4.25rem;
        flex: 0 0 4.25rem;
        place-items: center;
        border: 1px solid rgba(255, 255, 255, 0.34);
        border-radius: 1.2rem;
        background: rgba(255, 255, 255, 0.15);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.24), 0 12px 28px rgba(2, 32, 71, 0.25);
        font-size: 1.35rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        backdrop-filter: blur(12px);
    }

    .pessoa-custom-view__identity-copy {
        min-width: 0;
    }

    .pessoa-custom-view__eyebrow {
        margin: 0 0 0.25rem;
        color: rgba(219, 234, 254, 0.9);
        font-size: 0.7rem;
        font-weight: 750;
        letter-spacing: 0.14em;
        text-transform: uppercase;
    }

    .pessoa-custom-view__identity-copy h2 {
        margin: 0;
        max-width: 38rem;
        color: #fff;
        font-size: clamp(1.15rem, 1rem + 0.65vw, 1.6rem);
        font-weight: 780;
        line-height: 1.2;
        overflow-wrap: anywhere;
    }

    .pessoa-custom-view__badges {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        margin-top: 0.65rem;
    }

    .pessoa-custom-view__badge {
        display: inline-flex;
        align-items: center;
        gap: 0.38rem;
        min-height: 1.7rem;
        padding: 0.25rem 0.6rem;
        border: 1px solid transparent;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 700;
        line-height: 1.2;
    }

    .pessoa-custom-view__badge--primary,
    .pessoa-custom-view__badge--neutral {
        color: #eff6ff;
        border-color: rgba(255, 255, 255, 0.22);
        background: rgba(255, 255, 255, 0.12);
    }

    .pessoa-custom-view__badge--success {
        color: #166534;
        border-color: #bbf7d0;
        background: #dcfce7;
    }

    .pessoa-custom-view__badge--warning {
        color: #92400e;
        border-color: #fde68a;
        background: #fef3c7;
    }

    .pessoa-custom-view__badge--danger {
        color: #9f1239;
        border-color: #fecdd3;
        background: #ffe4e6;
    }

    .pessoa-custom-view__badge--gray {
        color: #475569;
        border-color: #e2e8f0;
        background: #f1f5f9;
    }

    .pessoa-custom-view__status-dot {
        width: 0.42rem;
        height: 0.42rem;
        flex: 0 0 0.42rem;
        border-radius: 999px;
        background: currentColor;
    }

    .pessoa-custom-view__contacts {
        display: grid;
        min-width: min(22rem, 36%);
        gap: 0.55rem;
    }

    .pessoa-custom-view__contacts > div {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        min-width: 0;
        padding: 0.65rem 0.75rem;
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 0.8rem;
        background: rgba(3, 35, 75, 0.2);
        backdrop-filter: blur(10px);
    }

    .pessoa-custom-view__contacts svg {
        width: 1.05rem;
        height: 1.05rem;
        flex: 0 0 1.05rem;
        color: #bfdbfe;
    }

    .pessoa-custom-view__contacts span {
        display: grid;
        min-width: 0;
    }

    .pessoa-custom-view__contacts small {
        color: #bfdbfe;
        font-size: 0.66rem;
        line-height: 1.2;
    }

    .pessoa-custom-view__contacts strong {
        overflow: hidden;
        color: #fff;
        font-size: 0.75rem;
        font-weight: 650;
        line-height: 1.35;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pessoa-custom-view__summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.75rem;
        padding: 1rem 1.25rem;
        border-bottom: 1px solid var(--pv-border);
        background: #fff;
    }

    .pessoa-custom-view__summary article {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        min-width: 0;
        padding: 0.8rem;
        border: 1px solid var(--pv-border);
        border-radius: 0.9rem;
        background: linear-gradient(180deg, #fff, #f8fafc);
    }

    .pessoa-custom-view__summary-icon,
    .pessoa-custom-view__card-icon,
    .pessoa-custom-view__pedagogical-heading > span {
        display: grid;
        place-items: center;
        color: var(--pv-primary);
        background: #eaf2fb;
    }

    .pessoa-custom-view__summary-icon {
        width: 2.25rem;
        height: 2.25rem;
        flex: 0 0 2.25rem;
        border-radius: 0.72rem;
    }

    .pessoa-custom-view__summary-icon svg,
    .pessoa-custom-view__card-icon svg,
    .pessoa-custom-view__pedagogical-heading > span svg {
        width: 1.1rem;
        height: 1.1rem;
    }

    .pessoa-custom-view__summary article > div {
        display: grid;
        min-width: 0;
        gap: 0.12rem;
    }

    .pessoa-custom-view__summary small,
    .pessoa-custom-view__placement small,
    .pessoa-custom-view__roles > small {
        color: var(--pv-muted);
        font-size: 0.67rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .pessoa-custom-view__summary strong {
        overflow: hidden;
        color: #172033;
        font-size: 0.88rem;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pessoa-custom-view__tabs {
        position: sticky;
        top: 0;
        z-index: 6;
        display: flex;
        gap: 0.35rem;
        overflow-x: auto;
        padding: 0.7rem 1.25rem;
        border-bottom: 1px solid var(--pv-border);
        background: rgba(255, 255, 255, 0.96);
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.045);
        backdrop-filter: blur(12px);
        scrollbar-width: thin;
    }

    .pessoa-custom-view__tabs button {
        display: inline-flex;
        min-height: 2.35rem;
        flex: 0 0 auto;
        align-items: center;
        gap: 0.45rem;
        padding: 0.5rem 0.75rem;
        border: 1px solid transparent;
        border-radius: 0.7rem;
        background: transparent;
        color: #64748b;
        font-size: 0.77rem;
        font-weight: 700;
        cursor: pointer;
        transition: color 0.15s ease, border-color 0.15s ease, background 0.15s ease;
    }

    .pessoa-custom-view__tabs button:hover {
        color: var(--pv-primary);
        background: #f1f6fc;
    }

    .pessoa-custom-view__tabs button.is-active {
        color: var(--pv-primary);
        border-color: #c7dcf3;
        background: #eaf2fb;
    }

    .pessoa-custom-view__tabs button:focus-visible {
        outline: 2px solid #2563eb;
        outline-offset: 2px;
    }

    .pessoa-custom-view__tabs svg {
        width: 1rem;
        height: 1rem;
    }

    .pessoa-custom-view__tab-count {
        display: grid;
        min-width: 1.25rem;
        height: 1.25rem;
        place-items: center;
        padding-inline: 0.3rem;
        border-radius: 999px;
        color: #fff;
        background: var(--pv-primary);
        font-size: 0.65rem;
    }

    .pessoa-custom-view__content {
        padding: 1.1rem 1.25rem 1.35rem;
    }

    .pessoa-custom-view__panel {
        min-width: 0;
    }

    .pessoa-custom-view__exports {
        display: flex;
        justify-content: flex-end;
        gap: 0.5rem;
        padding: 0.75rem 1.25rem 0;
    }

    .pessoa-custom-view__exports a {
        padding: 0.45rem 0.7rem;
        border: 1px solid var(--pv-border);
        border-radius: 0.55rem;
        color: var(--pv-primary);
        font-size: 0.75rem;
        font-weight: 700;
    }

    .pessoa-custom-view__history-item {
        display: grid;
        gap: 0.65rem;
        padding: 0.9rem 0;
        border-bottom: 1px solid var(--pv-border);
    }

    .pessoa-custom-view__history-item > div {
        display: grid;
        gap: 0.2rem;
        padding-left: 0.8rem;
        border-left: 2px solid #c7dcf3;
        overflow-wrap: anywhere;
    }

    .pessoa-custom-view__history-item small {
        color: var(--pv-muted);
    }

    .pessoa-custom-view__card-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
    }

    .pessoa-custom-view__card {
        min-width: 0;
        padding: 1rem;
        border: 1px solid var(--pv-border);
        border-radius: 1rem;
        background: var(--pv-surface);
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.045);
    }

    .pessoa-custom-view__card--full {
        grid-column: 1 / -1;
    }

    .pessoa-custom-view__card > header,
    .pessoa-custom-view__pedagogical-heading {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        min-width: 0;
        padding-bottom: 0.8rem;
        border-bottom: 1px solid #edf1f6;
    }

    .pessoa-custom-view__card-icon,
    .pessoa-custom-view__pedagogical-heading > span {
        width: 2.45rem;
        height: 2.45rem;
        flex: 0 0 2.45rem;
        border-radius: 0.8rem;
    }

    .pessoa-custom-view__card > header > div,
    .pessoa-custom-view__pedagogical-heading > div {
        min-width: 0;
    }

    .pessoa-custom-view__card > header h3,
    .pessoa-custom-view__card > header p,
    .pessoa-custom-view__pedagogical-heading h3,
    .pessoa-custom-view__pedagogical-heading p {
        margin: 0;
    }

    .pessoa-custom-view__card > header h3,
    .pessoa-custom-view__pedagogical-heading h3 {
        color: #172033;
        font-size: 0.91rem;
        font-weight: 750;
    }

    .pessoa-custom-view__card > header p,
    .pessoa-custom-view__pedagogical-heading p {
        margin-top: 0.15rem;
        color: var(--pv-muted);
        font-size: 0.72rem;
        line-height: 1.4;
    }

    .pessoa-custom-view__card > header > .pessoa-custom-view__badge {
        width: auto;
        height: auto;
        margin-left: auto;
    }

    .pessoa-custom-view__details-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0;
        margin: 0.35rem 0 0;
    }

    .pessoa-custom-view__details-grid > div {
        min-width: 0;
        padding: 0.75rem 0.55rem;
        border-bottom: 1px solid #edf1f6;
    }

    .pessoa-custom-view__details-grid > div:nth-last-child(-n + 2) {
        border-bottom: 0;
    }

    .pessoa-custom-view__details-grid dt {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        color: var(--pv-muted);
        font-size: 0.68rem;
        font-weight: 650;
    }

    .pessoa-custom-view__details-grid dt svg {
        width: 0.9rem;
        height: 0.9rem;
        color: #8292aa;
    }

    .pessoa-custom-view__details-grid dd {
        margin: 0.25rem 0 0;
        color: #172033;
        font-size: 0.79rem;
        font-weight: 650;
        line-height: 1.45;
        overflow-wrap: anywhere;
    }

    .pessoa-custom-view__notes {
        margin: 0.9rem 0 0;
        color: #475569;
        font-size: 0.79rem;
        line-height: 1.65;
        white-space: pre-wrap;
    }

    .pessoa-custom-view__item-list,
    .pessoa-custom-view__school-list {
        display: grid;
        gap: 0.6rem;
        margin-top: 0.85rem;
    }

    .pessoa-custom-view__list-item {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        align-items: center;
        gap: 0.65rem;
        min-width: 0;
        padding: 0.75rem;
        border: 1px solid #e5ebf3;
        border-radius: 0.8rem;
        background: #f9fbfd;
    }

    .pessoa-custom-view__list-icon {
        display: grid;
        width: 2rem;
        height: 2rem;
        place-items: center;
        border-radius: 0.62rem;
        color: var(--pv-primary);
        background: #eaf2fb;
    }

    .pessoa-custom-view__list-icon svg {
        width: 1rem;
        height: 1rem;
    }

    .pessoa-custom-view__list-item > div {
        display: grid;
        min-width: 0;
    }

    .pessoa-custom-view__list-item small {
        color: var(--pv-muted);
        font-size: 0.65rem;
    }

    .pessoa-custom-view__list-item strong {
        color: #172033;
        font-size: 0.8rem;
        overflow-wrap: anywhere;
    }

    .pessoa-custom-view__list-tag {
        padding: 0.28rem 0.55rem;
        border-radius: 999px;
        color: #1e40af;
        background: #dbeafe;
        font-size: 0.67rem;
        font-weight: 700;
    }

    .pessoa-custom-view__school-list > div {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        min-width: 0;
        padding: 0.72rem 0.8rem;
        border: 1px solid #e5ebf3;
        border-radius: 0.75rem;
        color: #334155;
        background: #f9fbfd;
        font-size: 0.76rem;
        font-weight: 650;
    }

    .pessoa-custom-view__school-list svg {
        width: 1rem;
        height: 1rem;
        flex: 0 0 1rem;
        color: var(--pv-primary);
    }

    .pessoa-custom-view__placement {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        color: #173b69;
        border-color: #c7dcf3;
        background: linear-gradient(135deg, #eef6ff, #f8fbff);
    }

    .pessoa-custom-view__placement-icon {
        display: grid;
        width: 2.8rem;
        height: 2.8rem;
        flex: 0 0 2.8rem;
        place-items: center;
        border-radius: 0.85rem;
        color: #fff;
        background: var(--pv-primary);
    }

    .pessoa-custom-view__placement-icon svg {
        width: 1.2rem;
        height: 1.2rem;
    }

    .pessoa-custom-view__placement > div {
        display: grid;
        gap: 0.15rem;
    }

    .pessoa-custom-view__placement strong {
        font-size: 0.9rem;
    }

    .pessoa-custom-view__placement > div > span {
        color: var(--pv-muted);
        font-size: 0.73rem;
    }

    .pessoa-custom-view__roles {
        margin-top: 0.9rem;
        padding: 0.85rem;
        border-radius: 0.8rem;
        background: #f7f9fc;
    }

    .pessoa-custom-view__roles > div {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        margin-top: 0.5rem;
    }

    .pessoa-custom-view__roles > div > span {
        padding: 0.28rem 0.55rem;
        border: 1px solid #c7dcf3;
        border-radius: 999px;
        color: #174c83;
        background: #eaf2fb;
        font-size: 0.68rem;
        font-weight: 700;
    }

    .pessoa-custom-view__roles > div > span.is-empty {
        color: var(--pv-muted);
        border-color: #e2e8f0;
        background: #fff;
    }

    .pessoa-custom-view__empty {
        display: flex !important;
        flex-direction: column;
        align-items: center !important;
        justify-content: center;
        gap: 0.3rem !important;
        min-height: 7rem;
        padding: 1rem !important;
        border: 1px dashed #cbd5e1 !important;
        border-radius: 0.8rem !important;
        color: var(--pv-muted) !important;
        background: #f8fafc !important;
        text-align: center;
    }

    .pessoa-custom-view__empty svg {
        width: 1.35rem;
        height: 1.35rem;
    }

    .pessoa-custom-view__empty strong {
        color: #334155;
        font-size: 0.78rem;
    }

    .pessoa-custom-view__empty span {
        font-size: 0.7rem;
    }

    .pessoa-custom-view__empty--large {
        min-height: 11rem;
        margin-top: 0.9rem;
    }

    .pessoa-custom-view__pedagogical-heading {
        margin-bottom: 0.9rem;
        padding: 0.9rem;
        border: 1px solid var(--pv-border);
        border-radius: 0.95rem;
        background: #fff;
    }

    .dark .pessoa-view-modal-window {
        --pv-border: #334155;
        --pv-muted: #94a3b8;
        --pv-surface: #111827;
        --pv-soft: #0f172a;
    }

    .dark .pessoa-view-modal-window .fi-modal-footer,
    .dark .pessoa-custom-view__summary,
    .dark .pessoa-custom-view__tabs {
        background: rgba(15, 23, 42, 0.97) !important;
    }

    .dark .pessoa-custom-view,
    .dark .pessoa-custom-view__summary strong,
    .dark .pessoa-custom-view__card > header h3,
    .dark .pessoa-custom-view__pedagogical-heading h3,
    .dark .pessoa-custom-view__details-grid dd,
    .dark .pessoa-custom-view__list-item strong {
        color: #e5e7eb;
    }

    .dark .pessoa-custom-view__summary article,
    .dark .pessoa-custom-view__list-item,
    .dark .pessoa-custom-view__school-list > div,
    .dark .pessoa-custom-view__roles,
    .dark .pessoa-custom-view__pedagogical-heading {
        border-color: #334155;
        background: #172033;
    }

    .dark .pessoa-custom-view__card > header,
    .dark .pessoa-custom-view__details-grid > div {
        border-color: #273449;
    }

    .dark .pessoa-custom-view__notes,
    .dark .pessoa-custom-view__school-list > div {
        color: #cbd5e1;
    }

    .dark .pessoa-custom-view__placement {
        color: #dbeafe;
        border-color: #1e4f83;
        background: linear-gradient(135deg, #102845, #172033);
    }

    .dark .pessoa-custom-view__empty {
        color: #94a3b8 !important;
        border-color: #475569 !important;
        background: #172033 !important;
    }

    .dark .pessoa-custom-view__empty strong {
        color: #e2e8f0;
    }

    @media (max-width: 900px) {
        .pessoa-custom-view__hero {
            align-items: stretch;
            flex-direction: column;
        }

        .pessoa-custom-view__contacts {
            min-width: 0;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .pessoa-custom-view__summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 700px) {
        .pessoa-custom-view__hero,
        .pessoa-custom-view__content {
            padding: 1rem;
        }

        .pessoa-custom-view__identity {
            align-items: flex-start;
        }

        .pessoa-custom-view__avatar {
            width: 3.25rem;
            height: 3.25rem;
            flex-basis: 3.25rem;
            border-radius: 0.95rem;
            font-size: 1rem;
        }

        .pessoa-custom-view__contacts,
        .pessoa-custom-view__card-grid {
            grid-template-columns: 1fr;
        }

        .pessoa-custom-view__summary {
            padding: 0.85rem 1rem;
        }

        .pessoa-custom-view__tabs {
            padding-inline: 1rem;
        }

        .pessoa-custom-view__details-grid {
            grid-template-columns: 1fr;
        }

        .pessoa-custom-view__details-grid > div:nth-last-child(-n + 2) {
            border-bottom: 1px solid #edf1f6;
        }

        .pessoa-custom-view__details-grid > div:last-child {
            border-bottom: 0;
        }
    }

    @media (max-width: 460px) {
        .pessoa-custom-view__summary {
            grid-template-columns: 1fr;
        }

        .pessoa-custom-view__contacts {
            grid-template-columns: 1fr;
        }

        .pessoa-custom-view__card {
            padding: 0.85rem;
        }

        .pessoa-custom-view__card > header {
            align-items: flex-start;
            flex-wrap: wrap;
        }

        .pessoa-custom-view__card > header > .pessoa-custom-view__badge {
            margin-left: 0;
        }
    }
</style>
