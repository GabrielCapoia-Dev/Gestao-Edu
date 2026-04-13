<style>
    .am-page {
        display: grid;
        gap: 1.25rem;
    }

    .am-hero {
        position: relative;
        overflow: hidden;
        padding: 1.4rem 1.5rem;
        border-radius: 1.4rem;
        background:
            radial-gradient(circle at top right, rgba(251, 191, 36, 0.18), transparent 24%),
            linear-gradient(135deg, #0f766e 0%, #155e75 48%, #0f172a 100%);
        color: #f8fafc;
        box-shadow: 0 20px 42px rgba(15, 23, 42, 0.14);
    }

    .am-kicker {
        margin: 0 0 0.45rem;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: rgba(226, 232, 240, 0.82);
    }

    .am-title {
        margin: 0;
        max-width: 42rem;
        font-size: clamp(1.3rem, 1.1rem + 0.8vw, 1.95rem);
        line-height: 1.1;
        font-weight: 700;
    }

    .am-subtitle {
        margin: 0.75rem 0 0;
        max-width: 44rem;
        font-size: 0.92rem;
        line-height: 1.65;
        color: rgba(226, 232, 240, 0.88);
    }

    .am-pill-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 0.95rem;
    }

    .am-pill {
        display: inline-flex;
        align-items: center;
        padding: 0.4rem 0.78rem;
        border-radius: 9999px;
        border: 1px solid rgba(255, 255, 255, 0.14);
        background: rgba(255, 255, 255, 0.1);
        font-size: 0.78rem;
        color: #f8fafc;
    }

    .am-panel {
        border-radius: 1.25rem;
        border: 1px solid #dbe4ee;
        background: rgba(255, 255, 255, 0.96);
        box-shadow: 0 14px 28px rgba(15, 23, 42, 0.06);
        overflow: hidden;
    }

    .dark .am-panel {
        border-color: #1e293b;
        background: rgba(15, 23, 42, 0.92);
        box-shadow: none;
    }

    .am-panel__header {
        padding: 1.15rem 1.2rem 0;
    }

    .am-panel__eyebrow {
        margin: 0 0 0.4rem;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: #0f766e;
    }

    .dark .am-panel__eyebrow {
        color: #5eead4;
    }

    .am-panel__title {
        margin: 0;
        font-size: 1.02rem;
        font-weight: 700;
        color: #0f172a;
    }

    .dark .am-panel__title {
        color: #f8fafc;
    }

    .am-panel__subtitle {
        margin: 0.42rem 0 0;
        font-size: 0.9rem;
        line-height: 1.6;
        color: #475569;
    }

    .dark .am-panel__subtitle {
        color: #cbd5e1;
    }

    .am-summary {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.8rem;
        padding: 1rem 1.2rem 0;
    }

    .am-summary-card {
        padding: 0.9rem 0.95rem;
        border-radius: 1rem;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
    }

    .dark .am-summary-card {
        border-color: #1e293b;
        background: rgba(15, 23, 42, 0.6);
    }

    .am-summary-card__top {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        margin-bottom: 0.55rem;
    }

    .am-summary-card__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
        border-radius: 0.8rem;
        background: #fff;
        border: 1px solid #e2e8f0;
    }

    .dark .am-summary-card__icon {
        background: rgba(15, 23, 42, 0.8);
        border-color: #1e293b;
    }

    .am-summary-card__icon svg {
        width: 1rem;
        height: 1rem;
    }

    .am-summary-card--sky .am-summary-card__icon {
        color: #2563eb;
    }

    .dark .am-summary-card--sky .am-summary-card__icon {
        color: #93c5fd;
    }

    .am-summary-card--emerald .am-summary-card__icon {
        color: #059669;
    }

    .dark .am-summary-card--emerald .am-summary-card__icon {
        color: #6ee7b7;
    }

    .am-summary-card--amber .am-summary-card__icon {
        color: #d97706;
    }

    .dark .am-summary-card--amber .am-summary-card__icon {
        color: #fcd34d;
    }

    .am-summary-card--rose .am-summary-card__icon {
        color: #e11d48;
    }

    .dark .am-summary-card--rose .am-summary-card__icon {
        color: #fda4af;
    }

    .am-summary-card__label {
        margin: 0;
        font-size: 0.72rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #64748b;
    }

    .dark .am-summary-card__label {
        color: #94a3b8;
    }

    .am-summary-card__value {
        margin: 0;
        font-size: 1.15rem;
        line-height: 1.1;
        font-weight: 700;
        color: #0f172a;
        word-break: break-word;
    }

    .dark .am-summary-card__value {
        color: #f8fafc;
    }

    .am-summary-card__description {
        margin: 0;
        font-size: 0.79rem;
        line-height: 1.55;
        color: #64748b;
    }

    .dark .am-summary-card__description {
        color: #94a3b8;
    }

    .am-panel__body {
        padding: 1.2rem;
    }

    .am-desktop-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
    }

    .am-toolbar {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-left: auto;
    }

    .am-search {
        display: inline-flex;
        align-items: center;
        gap: 0.55rem;
        min-width: 21rem;
        padding: 0.8rem 0.95rem;
        border: 1px solid #d7dee8;
        border-radius: 0.9rem;
        background: #fff;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.75);
    }

    .dark .am-search {
        border-color: #334155;
        background: rgba(15, 23, 42, 0.72);
    }

    .am-search__icon {
        width: 1rem;
        height: 1rem;
        color: #94a3b8;
    }

    .am-search input {
        width: 100%;
        border: none;
        outline: none;
        background: transparent;
        padding: 0;
        font-size: 0.9rem;
        color: #0f172a;
    }

    .dark .am-search input {
        color: #f8fafc;
    }

    .am-roles-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
    }

    .am-role-card {
        display: flex;
        flex-direction: column;
        border-radius: 1.2rem;
        border: 1px solid #dbe4ee;
        background:
            radial-gradient(circle at top right, rgba(255, 255, 255, 0.92), transparent 38%),
            linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.05);
        padding: 1rem;
    }

    .am-role-card.is-expanded {
        min-height: 26rem;
        max-height: 26rem;
    }

    .am-role-card.is-collapsed {
        min-height: auto;
        max-height: none;
    }

    .dark .am-role-card {
        border-color: #1e293b;
        background:
            radial-gradient(circle at top right, rgba(30, 41, 59, 0.55), transparent 38%),
            linear-gradient(180deg, rgba(15, 23, 42, 0.92) 0%, rgba(15, 23, 42, 0.85) 100%);
        box-shadow: none;
    }

    .am-role-card__header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.9rem;
        margin-bottom: 0.95rem;
    }

    .am-role-card__identity {
        display: flex;
        gap: 0.85rem;
        min-width: 0;
    }

    .am-role-card__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.6rem;
        height: 2.6rem;
        border-radius: 0.95rem;
        flex-shrink: 0;
    }

    .am-role-card__icon svg {
        width: 1.2rem;
        height: 1.2rem;
    }

    .am-role-card__identity h4 {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        color: #0f172a;
    }

    .dark .am-role-card__identity h4 {
        color: #f8fafc;
    }

    .am-role-card__identity p {
        margin: 0.35rem 0 0;
        font-size: 0.78rem;
        line-height: 1.55;
        color: #64748b;
    }

    .dark .am-role-card__identity p {
        color: #94a3b8;
    }

    .am-role-card__actions {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        flex-shrink: 0;
        flex-wrap: wrap;
    }

    .am-role-card__permissions {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        align-content: start;
        overflow-y: auto;
        padding-right: 0.25rem;
        max-height: 13.25rem;
        scrollbar-width: thin;
        scrollbar-color: rgba(148, 163, 184, 0.7) transparent;
    }

    .am-permission-group {
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        background: rgba(255, 255, 255, 0.82);
        padding: 0.8rem;
    }

    .dark .am-permission-group {
        border-color: #334155;
        background: rgba(15, 23, 42, 0.55);
    }

    .am-permission-group__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 0.7rem;
    }

    .am-permission-group__header h5 {
        margin: 0;
        font-size: 0.86rem;
        font-weight: 700;
        color: #0f172a;
    }

    .dark .am-permission-group__header h5 {
        color: #f8fafc;
    }

    .am-permission-group__header p {
        margin: 0.2rem 0 0;
        font-size: 0.75rem;
        color: #64748b;
    }

    .dark .am-permission-group__header p {
        color: #94a3b8;
    }

    .am-permission-group__items {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.55rem;
    }

    .am-role-card__permissions::-webkit-scrollbar {
        width: 0.45rem;
    }

    .am-role-card__permissions::-webkit-scrollbar-track {
        background: transparent;
    }

    .am-role-card__permissions::-webkit-scrollbar-thumb {
        border-radius: 9999px;
        background: rgba(148, 163, 184, 0.6);
    }

    .dark .am-role-card__permissions::-webkit-scrollbar-thumb {
        background: rgba(100, 116, 139, 0.8);
    }

    .am-permission-chip {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.7rem;
        padding: 0.72rem 0.8rem;
        border-radius: 0.9rem;
        border: 1px solid rgba(74, 222, 128, 0.3);
        background: rgba(240, 253, 244, 0.95);
    }

    .dark .am-permission-chip {
        border-color: rgba(74, 222, 128, 0.2);
        background: rgba(20, 83, 45, 0.2);
    }

    .am-permission-chip__label {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        min-width: 0;
        color: #166534;
        font-size: 0.83rem;
        font-weight: 500;
    }

    .dark .am-permission-chip__label {
        color: #bbf7d0;
    }

    .am-permission-chip__label svg,
    .am-permission-chip__status svg {
        width: 0.95rem;
        height: 0.95rem;
        flex-shrink: 0;
    }

    .am-permission-chip__label span {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .am-permission-chip__status {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.35rem;
        height: 1.35rem;
        border-radius: 9999px;
        color: #16a34a;
        background: rgba(255, 255, 255, 0.88);
        border: 1px solid rgba(34, 197, 94, 0.2);
        flex-shrink: 0;
    }

    .dark .am-permission-chip__status {
        background: rgba(15, 23, 42, 0.6);
    }

    .am-empty-state {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1.2rem;
        border-radius: 1rem;
        border: 1px dashed #cbd5e1;
        background: #f8fafc;
    }

    .dark .am-empty-state {
        border-color: #334155;
        background: rgba(15, 23, 42, 0.45);
    }

    .am-empty-state__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 3rem;
        height: 3rem;
        border-radius: 1rem;
        background: rgba(79, 70, 229, 0.1);
        color: #4f46e5;
        flex-shrink: 0;
    }

    .am-empty-state__icon svg {
        width: 1.4rem;
        height: 1.4rem;
    }

    .am-empty-state h4 {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        color: #0f172a;
    }

    .dark .am-empty-state h4 {
        color: #f8fafc;
    }

    .am-empty-state p {
        margin: 0.35rem 0 0;
        color: #64748b;
        font-size: 0.88rem;
        line-height: 1.6;
    }

    .dark .am-empty-state p {
        color: #94a3b8;
    }

    .am-role-card--indigo .am-role-card__icon {
        background: rgba(79, 70, 229, 0.12);
        color: #4338ca;
    }

    .am-role-card--amber .am-role-card__icon {
        background: rgba(245, 158, 11, 0.15);
        color: #d97706;
    }

    .am-role-card--rose .am-role-card__icon {
        background: rgba(244, 63, 94, 0.12);
        color: #e11d48;
    }

    .am-role-card--emerald .am-role-card__icon {
        background: rgba(16, 185, 129, 0.12);
        color: #059669;
    }

    .am-role-card--sky .am-role-card__icon {
        background: rgba(14, 165, 233, 0.12);
        color: #0284c7;
    }

    .am-role-card--violet .am-role-card__icon {
        background: rgba(139, 92, 246, 0.14);
        color: #7c3aed;
    }

    .am-panel .fi-section,
    .am-panel .fi-fo,
    .am-panel .fi-ta,
    .am-panel .fi-sc {
        border-radius: 1rem;
    }

    .am-panel .fi-section,
    .am-panel .fi-fo {
        box-shadow: none;
        background: transparent;
    }

    .am-panel .fi-ta {
        box-shadow: none;
        border: 1px solid #e2e8f0;
        background: rgba(255, 255, 255, 0.72);
    }

    .dark .am-panel .fi-ta {
        border-color: #1e293b;
        background: rgba(15, 23, 42, 0.56);
    }

    @media (max-width: 900px) {
        .am-summary {
            grid-template-columns: 1fr;
        }

        .am-desktop-header {
            flex-direction: column;
        }

        .am-toolbar {
            width: 100%;
            flex-direction: column;
            align-items: stretch;
            margin-left: 0;
        }

        .am-search {
            min-width: 0;
        }

        .am-roles-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 767px) {
        .am-hero {
            padding: 1.15rem;
        }

        .am-panel__header,
        .am-panel__body,
        .am-summary {
            padding-left: 1rem;
            padding-right: 1rem;
        }
    }
</style>
