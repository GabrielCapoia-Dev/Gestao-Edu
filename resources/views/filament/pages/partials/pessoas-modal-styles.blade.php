<style>
    /* ========== Pessoas: página + slide-over / modais ========== */
    .pe-pessoas-page {
        gap: 1rem;
    }

    .pe-pessoas-page .fi-ta-ctn,
    .pe-pessoas-page .fi-section {
        border-radius: 1.1rem;
        border-color: #dbe4ee;
        box-shadow: 0 10px 28px rgba(15, 23, 42, 0.05);
    }

    .dark .pe-pessoas-page .fi-ta-ctn,
    .dark .pe-pessoas-page .fi-section {
        border-color: #1e293b;
        box-shadow: none;
    }

    /* Slide-over / modal shell */
    .fi-modal-window,
    .fi-slide-over-window {
        border-radius: 1.15rem !important;
        overflow: hidden;
        box-shadow: 0 24px 60px rgba(15, 23, 42, 0.22) !important;
    }

    .fi-modal-header,
    .fi-slide-over-header {
        padding: 1.1rem 1.25rem !important;
        border-bottom: 1px solid #e2e8f0;
        background:
            linear-gradient(135deg, rgba(23, 54, 141, 0.06), transparent 55%),
            #f8fafc;
    }

    .dark .fi-modal-header,
    .dark .fi-slide-over-header {
        border-bottom-color: #1e293b;
        background:
            linear-gradient(135deg, rgba(58, 109, 214, 0.12), transparent 55%),
            rgba(15, 23, 42, 0.95);
    }

    .fi-modal-heading,
    .fi-slide-over-heading {
        font-size: 1.05rem !important;
        font-weight: 700 !important;
        letter-spacing: -0.01em;
        color: #0f172a !important;
    }

    .dark .fi-modal-heading,
    .dark .fi-slide-over-heading {
        color: #f8fafc !important;
    }

    .fi-modal-content,
    .fi-slide-over-content {
        padding: 1rem 1.15rem 1.25rem !important;
        background: #fff;
    }

    .dark .fi-modal-content,
    .dark .fi-slide-over-content {
        background: rgba(15, 23, 42, 0.96);
    }

    .fi-modal-footer,
    .fi-slide-over-footer {
        padding: 0.85rem 1.15rem !important;
        border-top: 1px solid #e2e8f0;
        background: #f8fafc;
        gap: 0.6rem;
        flex-wrap: wrap;
    }

    .dark .fi-modal-footer,
    .dark .fi-slide-over-footer {
        border-top-color: #1e293b;
        background: rgba(15, 23, 42, 0.9);
    }

    /* Sections do formulário de pessoa */
    .fi-slide-over-content .fi-section,
    .fi-modal-content .fi-section {
        border-radius: 0.95rem;
        border: 1px solid #e2e8f0;
        background: #fbfdff;
        margin-bottom: 0.75rem;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    .fi-slide-over-content .fi-section:hover,
    .fi-modal-content .fi-section:hover {
        border-color: #cbd5e1;
        box-shadow: 0 6px 16px rgba(15, 23, 42, 0.04);
    }

    .dark .fi-slide-over-content .fi-section,
    .dark .fi-modal-content .fi-section {
        border-color: #1e293b;
        background: rgba(15, 23, 42, 0.55);
    }

    .fi-slide-over-content .fi-section-header-heading,
    .fi-modal-content .fi-section-header-heading {
        font-size: 0.95rem !important;
        font-weight: 700 !important;
    }

    .fi-slide-over-content .fi-section-header-description,
    .fi-modal-content .fi-section-header-description {
        font-size: 0.8rem !important;
        line-height: 1.45 !important;
        color: #64748b !important;
    }

    /* Repeaters mais legíveis */
    .fi-slide-over-content .fi-fo-repeater-item,
    .fi-modal-content .fi-fo-repeater-item {
        border-radius: 0.85rem !important;
        border: 1px solid #e2e8f0 !important;
        background: #fff !important;
        padding: 0.75rem !important;
        margin-bottom: 0.55rem !important;
    }

    .dark .fi-slide-over-content .fi-fo-repeater-item,
    .dark .fi-modal-content .fi-fo-repeater-item {
        border-color: #334155 !important;
        background: rgba(15, 23, 42, 0.75) !important;
    }

    .fi-slide-over-content .fi-fo-repeater-item-header,
    .fi-modal-content .fi-fo-repeater-item-header {
        gap: 0.5rem;
    }

    /* Tabela da listagem de pessoas */
    .pe-pessoas-page .fi-ta-header-toolbar {
        gap: 0.65rem;
        flex-wrap: wrap;
    }

    .pe-pessoas-page .fi-ta-filters {
        gap: 0.65rem;
    }

    .pe-pessoas-page .fi-ta-record {
        transition: background-color 0.12s ease;
    }

    .pe-pessoas-page .fi-ta-record:hover {
        background: rgba(23, 54, 141, 0.03);
    }

    .dark .pe-pessoas-page .fi-ta-record:hover {
        background: rgba(58, 109, 214, 0.08);
    }

    /* Responsivo */
    @media (max-width: 768px) {
        .fi-slide-over-window,
        .fi-modal-window {
            border-radius: 0.85rem !important;
            max-width: 100vw !important;
        }

        .fi-modal-content,
        .fi-slide-over-content,
        .fi-modal-header,
        .fi-slide-over-header,
        .fi-modal-footer,
        .fi-slide-over-footer {
            padding-left: 0.85rem !important;
            padding-right: 0.85rem !important;
        }

        .fi-slide-over-content .fi-fo-repeater-item,
        .fi-modal-content .fi-fo-repeater-item {
            padding: 0.6rem !important;
        }

        .pe-pessoas-page .fi-ta-actions {
            flex-wrap: wrap;
        }
    }

    @media (max-width: 480px) {
        .fi-modal-heading,
        .fi-slide-over-heading {
            font-size: 0.95rem !important;
        }
    }

    /* Ações do rodapé mais confortáveis */
    .fi-modal-footer .fi-btn,
    .fi-slide-over-footer .fi-btn {
        border-radius: 0.7rem;
        font-weight: 600;
    }

    /* Collapse chevron feedback */
    .fi-section-collapse-button {
        border-radius: 0.55rem;
        transition: background-color 0.12s ease, transform 0.12s ease;
    }

    .fi-section-collapse-button:hover {
        background: rgba(23, 54, 141, 0.06);
    }
</style>
