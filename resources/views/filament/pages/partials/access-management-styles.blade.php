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
