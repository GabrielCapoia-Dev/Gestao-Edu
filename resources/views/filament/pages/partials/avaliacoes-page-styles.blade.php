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

    .av-professor-control-panel {
        border-color: color-mix(in oklab, var(--gray-300) 78%, var(--primary-200));
        background:
            linear-gradient(180deg, #eef2f7 0%, #dad9d9 70%);
        box-shadow:
            0 1px 2px rgba(15, 23, 42, 0.06),
            0 14px 32px rgba(15, 23, 42, 0.07);
    }

    .av-professor-control-panel .gi-field input,
    .av-professor-control-panel .gi-field select,
    .av-professor-control-panel .gi-field textarea {
        background: #fff;
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

    .av-export-overlay {
        position: fixed;
        inset: 0;
        z-index: 60;
        display: grid;
        place-items: center;
        padding: 1rem;
        background: color-mix(in oklab, var(--gray-950) 46%, transparent);
    }

    .av-export-overlay[hidden] {
        display: none;
    }

    .av-export-dialog {
        width: min(34rem, 100%);
        padding: 1rem;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-xl);
        background: #fff;
        box-shadow: var(--shadow-xl);
    }

    .av-export-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 0.85rem;
    }

    .av-export-head p {
        margin: 0 0 0.2rem;
        color: var(--gray-500);
        font-size: var(--text-xs);
        line-height: var(--text-xs--line-height);
        font-weight: var(--font-weight-semibold);
        text-transform: uppercase;
    }

    .av-export-head h3 {
        margin: 0;
        color: var(--gray-950);
        font-size: var(--text-base);
        line-height: var(--text-base--line-height);
        font-weight: var(--font-weight-semibold);
    }

    .av-export-head button {
        min-height: 2rem;
        padding: 0.35rem 0.65rem;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-md);
        background: var(--gray-50);
        color: var(--gray-700);
        font-size: var(--text-xs);
        line-height: var(--text-xs--line-height);
        font-weight: var(--font-weight-medium);
    }

    .av-export-progress {
        overflow: hidden;
        height: 0.55rem;
        border-radius: 999px;
        background: var(--gray-100);
    }

    .av-export-progress span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: var(--primary-600);
        transition: width 0.35s ease;
    }

    .av-export-log {
        display: grid;
        gap: 0.45rem;
        margin: 0.85rem 0 0;
        padding-left: 1.2rem;
        color: var(--gray-700);
        font-size: var(--text-sm);
        line-height: 1.45;
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

    .av-modal-step-tabs {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        border-top: 1px solid var(--gray-200);
        border-bottom: 1px solid var(--gray-200);
        background: var(--gray-50);
    }

    .av-modal-step-tabs button {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        min-height: 3.15rem;
        padding: 0.75rem 1.25rem;
        border: 0;
        border-right: 1px solid var(--gray-200);
        background: transparent;
        color: var(--gray-500);
        font-size: var(--text-sm);
        line-height: var(--text-sm--line-height);
        font-weight: var(--font-weight-semibold);
        text-align: left;
        transition:
            background-color 0.15s ease,
            color 0.15s ease;
    }

    .av-modal-step-tabs button:last-child {
        border-right: 0;
    }

    .av-modal-step-tabs button.is-active {
        background: #fff;
        color: var(--primary-700);
    }

    .av-tab-index {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.8rem;
        height: 1.8rem;
        flex: 0 0 auto;
        border: 2px solid var(--gray-300);
        border-radius: 999px;
        color: var(--gray-500);
        font-size: var(--text-xs);
        line-height: 1;
        font-weight: var(--font-weight-bold);
    }

    .av-modal-step-tabs button.is-active .av-tab-index {
        border-color: var(--primary-600);
        color: var(--primary-700);
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

    .av-form-section--plain {
        background: #fff;
    }

    .av-repeater-item {
        display: grid;
        gap: 0.65rem;
    }

    .av-repeater-item + .av-repeater-item {
        padding-top: 0.85rem;
        border-top: 1px solid var(--gray-200);
    }

    .av-repeater-item .gi-row-actions {
        justify-content: flex-end;
    }

    .av-segmented-control {
        display: inline-grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        padding: 0.2rem;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-lg);
        background: var(--gray-100);
    }

    .av-segmented-control button {
        min-height: 2.25rem;
        padding: 0.45rem 0.85rem;
        border: 0;
        border-radius: calc(var(--radius-lg) - 0.2rem);
        background: transparent;
        color: var(--gray-600);
        font-size: var(--text-sm);
        line-height: var(--text-sm--line-height);
        font-weight: var(--font-weight-semibold);
        white-space: nowrap;
        transition:
            background-color 0.15s ease,
            color 0.15s ease,
            box-shadow 0.15s ease;
    }

    .av-segmented-control button.is-active {
        background: #fff;
        color: var(--primary-700);
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
    }

    .av-mode-actions {
        display: flex;
        align-items: end;
        justify-content: end;
        gap: 0.75rem;
        flex-wrap: nowrap;
    }

    .av-bulk-control {
        display: grid;
        grid-template-columns: minmax(10rem, 14rem) minmax(18rem, 22rem) auto;
        align-items: end;
        gap: 0.5rem;
        flex: 0 1 auto;
    }

    .av-bulk-turma-select,
    .av-bulk-select {
        min-width: 0;
        width: 100%;
    }

    .av-bulk-control .gi-action {
        min-height: 2.75rem;
        align-self: end;
        white-space: nowrap;
    }

    .av-override-select {
        grid-column: span 2;
        min-width: min(22rem, 100%);
    }

    .av-filament-scope-form {
        display: grid;
        gap: 0.75rem;
    }

    .av-filament-scope-form .fi-fo-field-wrp {
        margin-bottom: 0;
    }

    .av-filament-scope-form .fi-input-wrp {
        background: #fff;
    }

    .av-filament-scope-form .fi-fo-field-wrp-helper-text {
        font-size: var(--text-xs);
        line-height: 1.5;
    }

    :root.dark .av-filament-scope-form .fi-input-wrp {
        background: var(--gray-950);
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

    .av-pauta-section {
        display: grid;
        gap: 0.85rem;
    }

    .av-turma-section {
        display: grid;
        gap: 0.85rem;
    }

    .av-turma-content {
        display: grid;
        gap: 0.85rem;
        padding-top: 0.15rem;
    }

    .av-pauta-section--nested {
        padding-top: 0.85rem;
        border-top: 1px solid var(--gray-200);
    }

    .av-pauta-section--nested:first-child {
        padding-top: 0;
        border-top: 0;
    }

    .av-pauta-toggle {
        width: 100%;
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(13rem, 18rem);
        gap: 1rem;
        align-items: center;
        padding: 0;
        border: 0;
        background: transparent;
        text-align: left;
        cursor: pointer;
    }

    .av-pauta-toggle-main {
        display: grid;
        gap: 0.25rem;
    }

    .av-pauta-toggle-side {
        display: grid;
        gap: 0.45rem;
        justify-items: stretch;
    }

    .av-pauta-progress-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.6rem;
        color: var(--gray-600);
        font-size: var(--text-xs);
        line-height: var(--text-xs--line-height);
    }

    .av-progress-track--compact {
        height: 0.45rem;
    }

    .av-pauta-toggle-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
    }

    .av-pauta-check {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.2rem 0.55rem;
        border: 1px solid var(--success-200);
        border-radius: 999px;
        background: var(--success-50);
        color: var(--success-700);
        font-size: var(--text-xs);
        line-height: var(--text-xs--line-height);
        font-weight: var(--font-weight-semibold);
        white-space: nowrap;
    }

    .av-pauta-check::before {
        content: "✓";
        font-weight: var(--font-weight-bold);
    }

    .av-pauta-check--pending {
        border-color: var(--warning-200);
        background: var(--warning-50);
        color: var(--warning-700);
    }

    .av-pauta-check--pending::before {
        content: "•";
    }

    .av-pauta-arrow {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.8rem;
        height: 1.8rem;
        border: 1px solid var(--gray-300);
        border-radius: 999px;
        color: var(--gray-600);
        font-size: 1rem;
        line-height: 1;
        transition:
            transform 0.15s ease,
            border-color 0.15s ease,
            color 0.15s ease;
    }

    .av-pauta-arrow.is-open {
        transform: rotate(180deg);
        border-color: var(--primary-300);
        color: var(--primary-700);
    }

    .av-pauta-content {
        display: grid;
        gap: 0.85rem;
        padding-top: 0.15rem;
    }

    .av-aluno-componente {
        display: grid;
        gap: 0.65rem;
    }

    .av-aluno-componente + .av-aluno-componente {
        padding-top: 0.85rem;
        border-top: 1px solid var(--gray-200);
    }

    .av-aluno-componente h4 {
        margin: 0;
        color: var(--gray-950);
        font-size: var(--text-sm);
        line-height: var(--text-sm--line-height);
        font-weight: var(--font-weight-semibold);
    }

    .av-complementary-section {
        padding-top: 0.85rem;
        border-top: 1px solid var(--gray-200);
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

    .av-textarea-input {
        min-height: 5.5rem;
        resize: vertical;
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

    .av-field-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
    }

    .av-field-meta .av-field-hint {
        margin-top: 0;
    }

    .av-char-count {
        flex: 0 0 auto;
        color: var(--gray-500);
        font-size: var(--text-xs);
        line-height: var(--text-xs--line-height);
        font-weight: var(--font-weight-medium);
        white-space: nowrap;
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
        .av-modal-step-tabs {
            grid-template-columns: 1fr;
        }

        .av-modal-step-tabs button {
            border-right: 0;
            border-bottom: 1px solid var(--gray-200);
        }

        .av-modal-step-tabs button:last-child {
            border-bottom: 0;
        }

        .av-form-grid,
        .av-form-grid--three,
        .av-form-grid--two,
        .av-subitem {
            grid-template-columns: 1fr;
        }

        .av-selection-grid {
            grid-template-columns: 1fr;
        }

        .av-segmented-control {
            width: 100%;
        }

        .av-mode-actions,
        .av-bulk-control,
        .av-bulk-select,
        .av-bulk-control .gi-action {
            width: 100%;
        }

        .av-mode-actions {
            flex-direction: column;
            align-items: stretch;
            justify-content: stretch;
            flex-wrap: wrap;
        }

        .av-bulk-control {
            grid-template-columns: 1fr;
            gap: 0.5rem;
        }

        .av-pauta-toggle {
            grid-template-columns: 1fr;
        }

        .av-span-2 {
            grid-column: span 1;
        }

        .av-override-select {
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

    :root.dark .av-modal-step-tabs {
        border-color: var(--gray-800);
        background: var(--gray-900);
    }

    :root.dark .av-professor-control-panel {
        border-color: var(--gray-700);
        background:
            linear-gradient(180deg, color-mix(in oklab, var(--gray-800) 86%, var(--primary-950)) 0%, var(--gray-900) 100%);
    }

    :root.dark .av-modal-step-tabs button {
        border-color: var(--gray-800);
        color: var(--gray-400);
    }

    :root.dark .av-modal-step-tabs button.is-active {
        background: var(--gray-950);
        color: var(--primary-200);
    }

    :root.dark .av-tab-index {
        border-color: var(--gray-700);
        color: var(--gray-400);
    }

    :root.dark .av-modal-step-tabs button.is-active .av-tab-index {
        border-color: var(--primary-300);
        color: var(--primary-200);
    }

    :root.dark .av-form-section--plain {
        background: var(--gray-950);
    }

    :root.dark .av-segmented-control {
        border-color: var(--gray-800);
        background: var(--gray-950);
    }

    :root.dark .av-segmented-control button {
        color: var(--gray-400);
    }

    :root.dark .av-segmented-control button.is-active {
        background: var(--gray-800);
        color: var(--primary-200);
        box-shadow: none;
    }

    :root.dark .av-repeater-item + .av-repeater-item {
        border-color: var(--gray-800);
    }

    :root.dark .av-total-card strong,
    :root.dark .av-form-section h4,
    :root.dark .av-aluno-componente h4,
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

    :root.dark .av-export-dialog {
        border-color: var(--gray-800);
        background: var(--gray-950);
    }

    :root.dark .av-export-head h3 {
        color: #fff;
    }

    :root.dark .av-export-head button {
        border-color: var(--gray-700);
        background: var(--gray-900);
        color: var(--gray-200);
    }

    :root.dark .av-export-progress {
        background: var(--gray-800);
    }

    :root.dark .av-export-log {
        color: var(--gray-300);
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

    :root.dark .av-char-count {
        color: var(--gray-400);
    }

    :root.dark .av-selection-title {
        color: var(--gray-400);
    }

    :root.dark .av-field-hint--danger {
        color: var(--danger-200);
    }

    :root.dark .av-pauta-progress-head,
    :root.dark .av-pauta-arrow {
        color: var(--gray-300);
    }

    :root.dark .av-pauta-arrow {
        border-color: var(--gray-700);
        background: var(--gray-900);
    }

    :root.dark .av-pauta-arrow.is-open {
        border-color: color-mix(in oklab, var(--primary-400) 45%, var(--gray-700));
        color: var(--primary-200);
    }

    :root.dark .av-pauta-check {
        border-color: color-mix(in oklab, var(--success-400) 40%, var(--gray-700));
        background: color-mix(in oklab, var(--success-800) 24%, var(--gray-950));
        color: var(--success-200);
    }

    :root.dark .av-pauta-check--pending {
        border-color: color-mix(in oklab, var(--warning-400) 40%, var(--gray-700));
        background: color-mix(in oklab, var(--warning-800) 24%, var(--gray-950));
        color: var(--warning-200);
    }

    :root.dark .av-aluno-componente + .av-aluno-componente {
        border-color: var(--gray-800);
    }

    :root.dark .av-pauta-section--nested,
    :root.dark .av-complementary-section {
        border-color: var(--gray-800);
    }

    :root.dark .av-saving-indicator {
        color: var(--primary-300);
    }

    :root.dark .av-progress-track {
        background: var(--gray-800);
    }
</style>
