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

    /* Modal de Pessoas alinhado ao padrão de formulários do CRM. */
    .pessoa-modal-window {
        border-radius: 1.5rem !important;
        overflow: hidden;
        border: 1px solid rgba(148, 163, 184, 0.22);
        box-shadow: 0 28px 80px rgba(15, 23, 42, 0.16) !important;
    }

    .pessoa-modal-window .fi-modal-header {
        padding: 1.5rem 1.5rem 1rem !important;
        border-bottom: 1px solid rgba(226, 232, 240, 0.9);
        background:
            linear-gradient(180deg, rgba(248, 250, 252, 0.98), rgba(255, 255, 255, 0.98)),
            linear-gradient(135deg, rgba(15, 23, 42, 0.04), rgba(59, 130, 246, 0.08));
    }

    .pessoa-modal-window .fi-modal-content {
        padding: 1.25rem 1.5rem 1.5rem !important;
        background:
            radial-gradient(circle at top right, rgba(59, 130, 246, 0.08), transparent 24%),
            linear-gradient(180deg, rgba(248, 250, 252, 0.92), rgba(255, 255, 255, 1));
    }

    .pessoa-modal-window .fi-modal-footer {
        border-top: 1px solid rgba(226, 232, 240, 0.9);
        background: rgba(255, 255, 255, 0.98);
    }

    .pessoa-modal-window .fi-sc-tabs {
        overflow: hidden;
        border: 1px solid rgba(226, 232, 240, 0.92);
        border-radius: 1rem;
        background: rgba(255, 255, 255, 0.98);
        box-shadow: 0 14px 32px rgba(15, 23, 42, 0.04);
    }

    .pessoa-modal-window .fi-sc-tabs > .fi-tabs {
        padding-inline: 0.65rem;
        border-bottom: 1px solid rgba(226, 232, 240, 0.92);
        background: rgba(248, 250, 252, 0.9);
    }

    .pessoa-modal-window .fi-sc-tabs-tab {
        padding: 1.25rem 1.5rem 1.5rem;
    }

    .pessoa-modal-window .fi-sc-component > .fi-section,
    .pessoa-modal-window .fi-sc-component > .fi-section-content-ctn > .fi-section {
        position: relative;
        overflow: visible;
        border-radius: 1.25rem;
        border: 1px solid rgba(226, 232, 240, 0.92);
        box-shadow: 0 14px 32px rgba(15, 23, 42, 0.05);
        background: rgba(255, 255, 255, 0.96);
    }

    .dark .pessoa-modal-window .fi-modal-header,
    .dark .pessoa-modal-window .fi-modal-content,
    .dark .pessoa-modal-window .fi-modal-footer,
    .dark .pessoa-modal-window .fi-sc-tabs,
    .dark .pessoa-modal-window .fi-sc-tabs-tab {
        border-color: rgba(148, 163, 184, 0.16);
        background: rgba(15, 23, 42, 0.94);
    }

    .dark .pessoa-modal-window .fi-sc-tabs > .fi-tabs {
        border-color: rgba(148, 163, 184, 0.16);
        background: rgba(30, 41, 59, 0.88);
    }

    .pessoa-view-groups {
        display: grid;
        gap: 1rem;
    }

    .pessoa-view-group {
        overflow: hidden;
        border: 1px solid #dbe4f0;
        border-radius: 1rem;
        background: #fff;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.05);
    }

    .pessoa-view-group__header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.1rem;
        border-bottom: 1px solid #e5eaf1;
        background: linear-gradient(135deg, #f5f8ff 0%, #f8fafc 100%);
    }

    .pessoa-view-group__school {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        min-width: 0;
    }

    .pessoa-view-group__icon {
        display: grid;
        place-items: center;
        flex: 0 0 2.35rem;
        width: 2.35rem;
        height: 2.35rem;
        border-radius: 0.75rem;
        color: #1d4ed8;
        background: #dbeafe;
    }

    .pessoa-view-group__icon svg,
    .pessoa-view-class__title svg,
    .pessoa-view-group__empty svg,
    .pessoa-view-groups__empty svg {
        width: 1.15rem;
        height: 1.15rem;
    }

    .pessoa-view-group__school h3 {
        margin: 0;
        color: #172033;
        font-size: 0.95rem;
        font-weight: 750;
        line-height: 1.35;
    }

    .pessoa-view-group__meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        margin-top: 0.45rem;
    }

    .pessoa-view-group__shift,
    .pessoa-view-group__registration,
    .pessoa-view-group__count {
        display: inline-flex;
        align-items: center;
        min-height: 1.6rem;
        padding: 0.2rem 0.55rem;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 700;
        line-height: 1.2;
    }

    .pessoa-view-group__shift {
        color: #1e40af;
        background: #dbeafe;
    }

    .pessoa-view-group__registration {
        color: #475569;
        border: 1px solid #dbe4f0;
        background: rgba(255, 255, 255, 0.86);
    }

    .pessoa-view-group__count {
        flex: 0 0 auto;
        color: #475569;
        background: #e9eef5;
    }

    .pessoa-view-group__classes {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
        padding: 0.9rem;
    }

    .pessoa-view-class {
        min-width: 0;
        padding: 0.85rem;
        border: 1px solid #e2e8f0;
        border-radius: 0.8rem;
        background: #fbfdff;
    }

    .pessoa-view-class__title {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #1e293b;
        font-size: 0.82rem;
        line-height: 1.35;
    }

    .pessoa-view-class__title svg {
        flex: 0 0 auto;
        color: #64748b;
    }

    .pessoa-view-class__components {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        margin-top: 0.65rem;
    }

    .pessoa-view-class__components span {
        padding: 0.25rem 0.5rem;
        border-radius: 0.45rem;
        color: #334155;
        font-size: 0.7rem;
        line-height: 1.25;
        background: #eef2f7;
    }

    .pessoa-view-group__empty,
    .pessoa-view-groups__empty {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        min-height: 4.5rem;
        color: #64748b;
        font-size: 0.8rem;
        text-align: center;
    }

    .pessoa-view-groups__empty {
        flex-direction: column;
        min-height: 10rem;
        padding: 2rem;
        border: 1px dashed #cbd5e1;
        border-radius: 1rem;
        background: #f8fafc;
    }

    .pessoa-view-groups__empty strong {
        color: #334155;
        font-size: 0.9rem;
    }

    .dark .pessoa-view-group,
    .dark .pessoa-view-class {
        border-color: #334155;
        background: #111827;
    }

    .dark .pessoa-view-group__header {
        border-color: #334155;
        background: linear-gradient(135deg, #172033 0%, #111827 100%);
    }

    .dark .pessoa-view-group__school h3,
    .dark .pessoa-view-class__title,
    .dark .pessoa-view-groups__empty strong {
        color: #e5e7eb;
    }

    .dark .pessoa-view-group__registration,
    .dark .pessoa-view-group__count,
    .dark .pessoa-view-class__components span {
        color: #cbd5e1;
        border-color: #475569;
        background: #1e293b;
    }

    @media (max-width: 720px) {
        .pessoa-view-group__header {
            flex-direction: column;
        }

        .pessoa-view-group__classes {
            grid-template-columns: 1fr;
        }
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

        .pe-pessoas-page .fi-ta-actions {
            flex-wrap: wrap;
        }

        .pessoa-modal-window .fi-modal-content,
        .pessoa-modal-window .fi-modal-header,
        .pessoa-modal-window .fi-modal-footer,
        .pessoa-modal-window .fi-sc-tabs-tab {
            padding-left: 0.85rem !important;
            padding-right: 0.85rem !important;
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

    /* Formulário Livewire unificado de Pessoas. */
    .pe-person-form,
    .pe-person-form * {
        box-sizing: border-box;
    }

    .pe-person-form {
        position: relative;
        margin: -1.5rem;
        color: #111827;
    }

    .pe-person-form [x-cloak],
    .pe-person-form [wire\:loading].pe-person-form__loading {
        display: none;
    }

    .pe-person-form__form {
        display: grid;
        min-height: min(720px, calc(100vh - 10rem));
        background: #f8fafc;
    }

    .pe-person-form__main-tabs {
        display: flex;
        gap: 0.35rem;
        padding: 1rem 1.5rem 0;
        border-bottom: 1px solid #dbe3ef;
        background: #fff;
    }

    .pe-person-form__main-tabs button {
        border: 0;
        border-radius: 0.65rem 0.65rem 0 0;
        background: transparent;
        color: #687594;
        padding: 0.7rem 0.9rem;
        font-size: 0.875rem;
        font-weight: 650;
        cursor: pointer;
    }

    .pe-person-form__main-tabs button.is-active {
        background: #f4f6fb;
        color: #173f91;
    }

    .pe-person-form__main-tabs button:focus-visible,
    .pe-person-form__tab button:focus-visible,
    .pe-person-form__button:focus-visible,
    .pe-person-form__text-button:focus-visible,
    .pe-person-form__icon-button:focus-visible,
    .pe-person-form__remove-assignment:focus-visible {
        outline: 2px solid #2563eb;
        outline-offset: 2px;
    }

    .pe-person-form__body {
        display: grid;
        align-content: start;
        gap: 1.25rem;
        padding: 1.5rem;
        overflow: auto;
    }

    .pe-person-form__section {
        overflow: hidden;
        border: 1px solid #d6e0ef;
        border-radius: 1rem;
        background: #fff;
        box-shadow: 0 12px 34px rgba(15, 23, 42, 0.045);
    }

    .pe-person-form__section + .pe-person-form__section {
        margin-top: 1.25rem;
    }

    .pe-person-form__section-header,
    .pe-person-form__section-title {
        display: flex;
        align-items: center;
        gap: 0.9rem;
    }

    .pe-person-form__section-header {
        padding: 1rem 1.2rem;
        border-bottom: 1px solid #d6e0ef;
    }

    .pe-person-form__section-header--actions {
        justify-content: space-between;
    }

    .pe-person-form__section-header h3,
    .pe-person-form__section-header p,
    .pe-person-form__subheader h4,
    .pe-person-form__subheader p {
        margin: 0;
    }

    .pe-person-form__section-header h3 {
        font-size: 1rem;
        font-weight: 700;
    }

    .pe-person-form__section-header p,
    .pe-person-form__subheader p {
        margin-top: 0.2rem;
        color: #71809d;
        font-size: 0.8rem;
    }

    .pe-person-form__section-icon {
        display: grid;
        width: 2rem;
        height: 2rem;
        flex: 0 0 2rem;
        place-items: center;
        border-radius: 0.6rem;
        background: #eff5ff;
        color: #49658f;
    }

    .pe-person-form__section-icon svg {
        width: 1.15rem;
        height: 1.15rem;
    }

    .pe-person-form__grid {
        display: grid;
        gap: 1rem;
        padding: 1.15rem;
    }

    .pe-person-form__grid--2 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .pe-person-form__grid--flush {
        padding: 0;
    }

    .pe-person-form__span-2 {
        grid-column: span 2;
    }

    .pe-person-form__field {
        display: grid;
        min-width: 0;
        gap: 0.4rem;
        margin: 0;
        padding: 0;
        border: 0;
    }

    .pe-person-form__field > span,
    .pe-person-form__field legend,
    .pe-person-form__field-heading > span {
        color: #111827;
        font-size: 0.875rem;
        font-weight: 600;
    }

    .pe-person-form__field b {
        color: #e11d48;
    }

    .pe-person-form__field input[type='text'],
    .pe-person-form__field input[type='email'],
    .pe-person-form__field select,
    .pe-person-form__field textarea {
        width: 100%;
        min-height: 2.55rem;
        border: 1px solid #cfd8e6;
        border-radius: 0.65rem;
        outline: none;
        background: #fff;
        color: #111827;
        padding: 0.6rem 0.75rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }

    .pe-person-form__field textarea {
        min-height: 5rem;
        resize: vertical;
    }

    .pe-person-form__field input:focus,
    .pe-person-form__field select:focus,
    .pe-person-form__field textarea:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }

    .pe-person-form__field input[aria-invalid='true'],
    .pe-person-form__field select[aria-invalid='true'],
    .pe-person-form__field textarea[aria-invalid='true'] {
        border-color: #e11d48;
    }

    .pe-person-form__field input:disabled,
    .pe-person-form__field select:disabled,
    .pe-person-form__field textarea:disabled {
        background: #f3f5f8;
        color: #667085;
        cursor: not-allowed;
    }

    .pe-person-form__field small {
        color: #71809d;
        font-size: 0.76rem;
    }

    .pe-person-form__field small.is-error,
    .pe-person-form__error-block {
        color: #e11d48;
    }

    .pe-person-form__field-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .pe-person-form__alert {
        margin: 1rem 1.5rem 0;
        border: 1px solid #fecdd3;
        border-radius: 0.75rem;
        background: #fff1f2;
        color: #9f1239;
        padding: 0.8rem 1rem;
    }

    .pe-person-form__alert ul {
        margin: 0.4rem 0 0 1.1rem;
        font-size: 0.82rem;
    }

    .pe-person-form__tab-strip {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr));
        border-bottom: 1px solid #d6e0ef;
        background: #f8fafc;
    }

    .pe-person-form__tab-strip--schools {
        border: 1px solid #d6e0ef;
        border-radius: 0.8rem 0.8rem 0 0;
    }

    .pe-person-form__tab {
        display: flex;
        min-width: 0;
        border-top: 3px solid transparent;
        border-right: 1px solid #d6e0ef;
    }

    .pe-person-form__tab:last-child {
        border-right: 0;
    }

    .pe-person-form__tab.is-active {
        border-top-color: #1670dc;
        background: #fff;
    }

    .pe-person-form__tab > button:first-child {
        flex: 1;
        min-width: 0;
        overflow: hidden;
        border: 0;
        background: transparent;
        color: #111827;
        padding: 0.7rem 0.85rem;
        text-align: left;
        text-overflow: ellipsis;
        white-space: nowrap;
        cursor: pointer;
    }

    .pe-person-form__icon-button {
        display: grid;
        width: 2.6rem;
        flex: 0 0 2.6rem;
        place-items: center;
        border: 0;
        background: transparent;
        color: #e11d48;
        cursor: pointer;
    }

    .pe-person-form__icon-button svg,
    .pe-person-form__remove-assignment svg,
    .pe-person-form__button svg {
        width: 1rem;
        height: 1rem;
    }

    .pe-person-form__error-block {
        margin: 0.65rem 1rem 0;
        font-size: 0.82rem;
    }

    .pe-person-form__registration {
        padding: 1rem;
    }

    .pe-person-form__subheader {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-top: 1.2rem;
        padding: 0.9rem 0 0.65rem;
    }

    .pe-person-form__subheader--assignments {
        margin-top: 0.8rem;
    }

    .pe-person-form__subheader h4 {
        font-size: 0.9rem;
    }

    .pe-person-form__placement {
        border: 1px solid #d6e0ef;
        border-top: 0;
        border-radius: 0 0 0.8rem 0.8rem;
        padding: 1rem;
    }

    .pe-person-form__assignments {
        display: grid;
        gap: 0.75rem;
    }

    .pe-person-form__assignment {
        position: relative;
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
        border: 1px solid #dbe3ef;
        border-radius: 0.8rem;
        background: #fbfcfe;
        padding: 0.9rem 3rem 0.9rem 0.9rem;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
    }

    .pe-person-form__remove-assignment {
        position: absolute;
        top: 0.65rem;
        right: 0.7rem;
        display: grid;
        width: 1.8rem;
        height: 1.8rem;
        place-items: center;
        border: 0;
        border-radius: 0.45rem;
        background: #fff1f2;
        color: #e11d48;
        cursor: pointer;
    }

    .pe-person-form__empty {
        margin: 1rem;
        border: 1px dashed #cbd5e1;
        border-radius: 0.8rem;
        background: #f8fafc;
        color: #64748b;
        padding: 1.2rem;
        text-align: center;
    }

    .pe-person-form__empty--small {
        margin: 0.5rem 0;
        padding: 0.8rem;
        font-size: 0.82rem;
    }

    .pe-person-form__button {
        display: inline-flex;
        min-height: 2.35rem;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        border-radius: 0.65rem;
        padding: 0.5rem 0.85rem;
        font-size: 0.85rem;
        font-weight: 700;
        cursor: pointer;
    }

    .pe-person-form__button:disabled,
    .pe-person-form__text-button:disabled,
    .pe-person-form__icon-button:disabled,
    .pe-person-form__remove-assignment:disabled {
        opacity: 0.55;
        cursor: not-allowed;
    }

    .pe-person-form__button--primary {
        border: 1px solid #123b98;
        background: #123b98;
        color: #fff;
        box-shadow: 0 5px 14px rgba(18, 59, 152, 0.22);
    }

    .pe-person-form__button--secondary,
    .pe-person-form__button--cancel {
        border: 1px solid #d4dce8;
        background: #fff;
        color: #26334d;
        box-shadow: 0 2px 5px rgba(15, 23, 42, 0.08);
    }

    .pe-person-form__text-button {
        border: 0;
        background: transparent;
        color: #1759b0;
        font-size: 0.8rem;
        font-weight: 650;
        cursor: pointer;
    }

    .pe-person-form__checks {
        display: grid;
        gap: 0.6rem;
    }

    .pe-person-form__checks--3 {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .pe-person-form__checks--2 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .pe-person-form__checks label {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        border: 1px solid #d6e0ef;
        border-radius: 0.65rem;
        padding: 0.65rem 0.75rem;
        font-size: 0.84rem;
    }

    .pe-person-form__checks input[type='checkbox'] {
        width: 1rem;
        height: 1rem;
        accent-color: #1759b0;
    }

    .pe-person-form__checks--scroll {
        max-height: 15rem;
        overflow: auto;
    }

    .pe-person-form__school-groups {
        display: grid;
        gap: 0.85rem;
    }

    .pe-person-form__school-group {
        border: 1px solid #d6e0ef;
        border-radius: 0.75rem;
        padding: 0.8rem;
    }

    .pe-person-form__school-group--selected {
        background: #f4f8ff;
        border-color: #bfd4f5;
    }

    .pe-person-form__school-group > header {
        align-items: center;
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.65rem;
    }

    .pe-person-form__school-group h4 {
        color: #26334d;
        font-size: 0.78rem;
        font-weight: 700;
        margin: 0;
    }

    .pe-person-form__school-group > header > span {
        background: #e8f0fc;
        border-radius: 999px;
        color: #1759b0;
        font-size: 0.7rem;
        font-weight: 700;
        min-width: 1.45rem;
        padding: 0.15rem 0.4rem;
        text-align: center;
    }

    .pe-person-form__school-group .pe-person-form__checks--scroll {
        max-height: 11rem;
    }

    .pe-person-form__footer {
        position: sticky;
        bottom: 0;
        z-index: 4;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        border-top: 1px solid #d6e0ef;
        background: rgba(255, 255, 255, 0.97);
        padding: 0.8rem 1.5rem;
        backdrop-filter: blur(8px);
    }

    .pe-person-form__footer p {
        margin: 0;
        color: #71809d;
        font-size: 0.76rem;
    }

    .pe-person-form__footer-actions {
        display: flex;
        gap: 0.65rem;
    }

    .pe-person-form__loading {
        position: fixed;
        inset: 0;
        z-index: 100;
        align-items: center;
        justify-content: center;
        background: rgba(15, 23, 42, 0.45);
    }

    .pe-person-form__loading-card {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        min-width: 18rem;
        border-radius: 0.9rem;
        background: #fff;
        padding: 1rem 1.2rem;
        box-shadow: 0 24px 70px rgba(15, 23, 42, 0.3);
    }

    .pe-person-form__loading-card div {
        display: grid;
        gap: 0.2rem;
    }

    .pe-person-form__loading-card small {
        color: #71809d;
    }

    .pe-person-form__spinner {
        width: 1.5rem;
        height: 1.5rem;
        border: 3px solid #dbe7f8;
        border-top-color: #2563eb;
        border-radius: 999px;
        animation: pe-person-form-spin 0.7s linear infinite;
    }

    @keyframes pe-person-form-spin {
        to {
            transform: rotate(360deg);
        }
    }

    .dark .pe-person-form__form,
    .dark .pe-person-form__body {
        background: #0f172a;
    }

    .dark .pe-person-form__main-tabs,
    .dark .pe-person-form__section,
    .dark .pe-person-form__tab.is-active,
    .dark .pe-person-form__footer,
    .dark .pe-person-form__loading-card {
        border-color: #334155;
        background: #111827;
        color: #e5e7eb;
    }

    .dark .pe-person-form__tab-strip,
    .dark .pe-person-form__assignment,
    .dark .pe-person-form__empty,
    .dark .pe-person-form__field input:disabled,
    .dark .pe-person-form__field select:disabled,
    .dark .pe-person-form__field textarea:disabled {
        border-color: #334155;
        background: #1e293b;
        color: #cbd5e1;
    }

    .dark .pe-person-form__field > span,
    .dark .pe-person-form__field legend,
    .dark .pe-person-form__field-heading > span,
    .dark .pe-person-form__tab > button:first-child {
        color: #e5e7eb;
    }

    .dark .pe-person-form__field input,
    .dark .pe-person-form__field select,
    .dark .pe-person-form__field textarea {
        border-color: #475569;
        background: #111827;
        color: #e5e7eb;
    }

    @media (max-width: 760px) {
        .pe-person-form {
            margin: -1rem;
        }

        .pe-person-form__grid--2,
        .pe-person-form__assignment,
        .pe-person-form__checks--2,
        .pe-person-form__checks--3 {
            grid-template-columns: 1fr;
        }

        .pe-person-form__span-2 {
            grid-column: span 1;
        }

        .pe-person-form__section-header--actions,
        .pe-person-form__footer {
            align-items: stretch;
            flex-direction: column;
        }

        .pe-person-form__footer-actions {
            justify-content: flex-end;
        }
    }
</style>
