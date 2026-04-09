<style>
    .am-page {
        display: grid;
        gap: 1.5rem;
    }

    .am-hero {
        position: relative;
        overflow: hidden;
        display: grid;
        gap: 1.5rem;
        padding: 1.6rem;
        border-radius: 1.5rem;
        background:
            radial-gradient(circle at top right, rgba(251, 191, 36, 0.24), transparent 28%),
            linear-gradient(135deg, #0f766e 0%, #155e75 42%, #0f172a 100%);
        color: #f8fafc;
        box-shadow: 0 24px 50px rgba(15, 23, 42, 0.18);
    }

    .am-hero::after {
        content: '';
        position: absolute;
        inset: auto -4rem -5rem auto;
        width: 16rem;
        height: 16rem;
        border-radius: 9999px;
        background: rgba(255, 255, 255, 0.08);
        filter: blur(10px);
    }

    .am-hero__content,
    .am-hero__cards {
        position: relative;
        z-index: 1;
    }

    .am-kicker {
        margin: 0 0 0.5rem;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: rgba(226, 232, 240, 0.86);
    }

    .am-title {
        margin: 0;
        max-width: 46rem;
        font-size: clamp(1.45rem, 1.1rem + 1vw, 2.15rem);
        line-height: 1.08;
        font-weight: 700;
    }

    .am-subtitle {
        margin: 0.8rem 0 0;
        max-width: 48rem;
        font-size: 0.96rem;
        line-height: 1.7;
        color: rgba(226, 232, 240, 0.88);
    }

    .am-pill-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.55rem;
        margin-top: 1rem;
    }

    .am-pill {
        display: inline-flex;
        align-items: center;
        padding: 0.45rem 0.85rem;
        border-radius: 9999px;
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.14);
        font-size: 0.79rem;
        color: #f8fafc;
        backdrop-filter: blur(10px);
    }

    .am-cards {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.85rem;
    }

    .am-card {
        min-height: 8.6rem;
        padding: 1rem;
        border-radius: 1.2rem;
        border: 1px solid rgba(255, 255, 255, 0.14);
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(12px);
    }

    .am-card__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.7rem;
        height: 2.7rem;
        margin-bottom: 0.9rem;
        border-radius: 0.9rem;
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.16);
    }

    .am-card__icon svg {
        width: 1.2rem;
        height: 1.2rem;
    }

    .am-card__label {
        margin: 0;
        font-size: 0.72rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: rgba(226, 232, 240, 0.74);
    }

    .am-card__value {
        margin: 0.35rem 0 0;
        font-size: clamp(1.15rem, 0.95rem + 0.7vw, 1.8rem);
        line-height: 1.1;
        font-weight: 700;
        color: #fff;
        word-break: break-word;
    }

    .am-card__description {
        margin: 0.55rem 0 0;
        font-size: 0.8rem;
        line-height: 1.55;
        color: rgba(226, 232, 240, 0.76);
    }

    .am-card--sky .am-card__icon {
        color: #93c5fd;
    }

    .am-card--emerald .am-card__icon {
        color: #6ee7b7;
    }

    .am-card--amber .am-card__icon {
        color: #fcd34d;
    }

    .am-card--rose .am-card__icon {
        color: #fda4af;
    }

    .am-grid {
        display: grid;
        gap: 1.25rem;
    }

    .am-panel,
    .am-note {
        border-radius: 1.3rem;
        border: 1px solid #dbe4ee;
        background: rgba(255, 255, 255, 0.96);
        box-shadow: 0 16px 32px rgba(15, 23, 42, 0.06);
    }

    .dark .am-panel,
    .dark .am-note {
        border-color: #1e293b;
        background: rgba(15, 23, 42, 0.92);
        box-shadow: none;
    }

    .am-panel__header {
        padding: 1.2rem 1.25rem 0;
    }

    .am-panel__eyebrow {
        margin: 0 0 0.45rem;
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
        font-size: 1.05rem;
        font-weight: 700;
        color: #0f172a;
    }

    .dark .am-panel__title {
        color: #f8fafc;
    }

    .am-panel__subtitle {
        margin: 0.45rem 0 0;
        font-size: 0.92rem;
        line-height: 1.65;
        color: #475569;
    }

    .dark .am-panel__subtitle {
        color: #cbd5e1;
    }

    .am-panel__body {
        padding: 1.25rem;
    }

    .am-sidebar {
        display: grid;
        gap: 1rem;
        align-content: start;
    }

    .am-note {
        padding: 1rem 1.05rem;
    }

    .am-note__title {
        margin: 0 0 0.45rem;
        font-size: 0.95rem;
        font-weight: 700;
        color: #0f172a;
    }

    .dark .am-note__title {
        color: #f8fafc;
    }

    .am-note__description {
        margin: 0;
        font-size: 0.84rem;
        line-height: 1.65;
        color: #475569;
    }

    .dark .am-note__description {
        color: #cbd5e1;
    }

    .am-panel .fi-section,
    .am-panel .fi-fo,
    .am-panel .fi-ta,
    .am-panel .fi-sc {
        border-radius: 1rem;
    }

    .am-panel .fi-section {
        box-shadow: none;
        background: transparent;
    }

    .am-panel .fi-fo {
        box-shadow: none;
        background: transparent;
    }

    .am-panel .fi-ta {
        box-shadow: none;
        border: 1px solid #e2e8f0;
        background: rgba(255, 255, 255, 0.68);
    }

    .dark .am-panel .fi-ta {
        border-color: #1e293b;
        background: rgba(15, 23, 42, 0.52);
    }

    .am-panel .fi-tabs {
        border-radius: 1rem;
    }

    @media (min-width: 1024px) {
        .am-hero {
            grid-template-columns: minmax(0, 1.15fr) minmax(22rem, 0.95fr);
            align-items: end;
        }

        .am-grid--with-sidebar {
            grid-template-columns: minmax(0, 1.65fr) minmax(19rem, 0.85fr);
            align-items: start;
        }
    }

    @media (max-width: 767px) {
        .am-hero {
            padding: 1.25rem;
        }

        .am-cards {
            grid-template-columns: 1fr;
        }

        .am-panel__header,
        .am-panel__body {
            padding-left: 1rem;
            padding-right: 1rem;
        }
    }
</style>
