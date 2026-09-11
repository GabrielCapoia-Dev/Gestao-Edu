<style>
    .av-professor-list-heading,
    .av-professor-context__row,
    .av-professor-evaluation-card__top,
    .av-professor-class-toggle,
    .av-professor-components__heading,
    .av-professor-component-card__meta,
    .av-professor-modal__header,
    .av-professor-workspace-heading,
    .av-professor-answer-card__header,
    .av-professor-modal__footer,
    .av-professor-modal__footer > div {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .av-professor-list-heading,
    .av-professor-context {
        margin-bottom: 1.25rem;
    }

    .av-professor-list-heading h2,
    .av-professor-context h2,
    .av-professor-modal h2,
    .av-professor-workspace-heading h3 {
        margin: .25rem 0 0;
        color: #0f172a;
        font-weight: 750;
        letter-spacing: -.025em;
    }

    .av-professor-list-heading h2,
    .av-professor-context h2 {
        font-size: clamp(1.25rem, 2vw, 1.65rem);
    }

    .av-professor-list-heading p,
    .av-professor-context p,
    .av-professor-modal p,
    .av-professor-evaluation-card p,
    .av-professor-class-toggle p {
        margin: .35rem 0 0;
        color: #64748b;
        font-size: .85rem;
    }

    .av-professor-eyebrow {
        color: #2563eb;
        font-size: .72rem;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .av-professor-evaluation-list {
        display: grid;
        gap: .75rem;
    }

    .av-professor-evaluation-card,
    .av-professor-class-card,
    .av-professor-empty {
        border: 1px solid #dbe4f0;
        border-radius: 1rem;
        background: #fff;
        box-shadow: 0 8px 24px rgba(15, 23, 42, .05);
    }

    .av-professor-evaluation-card {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(12rem, 18rem) auto;
        align-items: center;
        gap: 1.5rem;
        min-height: 0;
        padding: 1rem 1.1rem;
    }

    .av-professor-evaluation-card__top {
        color: #64748b;
        font-size: .78rem;
    }

    .av-professor-status {
        display: inline-flex;
        align-items: center;
        width: fit-content;
        padding: .3rem .6rem;
        border-radius: 999px;
        background: #dcfce7;
        color: #047857;
        font-size: .72rem;
        font-weight: 750;
    }

    .av-professor-status--blocked {
        background: #fee2e2;
        color: #b91c1c;
    }

    .av-professor-evaluation-card__body {
        min-width: 0;
        padding: 0;
    }

    .av-professor-evaluation-card__body > span {
        color: #2563eb;
        font-size: .76rem;
        font-weight: 700;
    }

    .av-professor-evaluation-card h3 {
        margin: .45rem 0 0;
        color: #0f172a;
        font-size: 1.05rem;
        line-height: 1.4;
    }

    .av-professor-evaluation-card .gi-action {
        justify-content: center;
        min-width: 7rem;
    }

    .av-professor-evaluation-progress > div:first-child {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: .35rem;
        color: #64748b;
        font-size: .72rem;
    }

    .av-professor-evaluation-progress strong {
        color: #1d4ed8;
        font-size: .8rem;
    }

    .av-professor-evaluation-progress small {
        display: block;
        margin-top: .3rem;
        color: #64748b;
        font-size: .68rem;
        text-align: right;
    }

    .av-professor-empty {
        padding: 3.5rem 1.5rem;
        text-align: center;
    }

    .av-professor-empty strong {
        display: block;
        color: #0f172a;
        font-size: 1rem;
    }

    .av-professor-empty p {
        margin: .4rem 0 0;
        color: #64748b;
    }

    .av-professor-back {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        margin-bottom: 1rem;
        color: #2563eb;
        font-size: .82rem;
        font-weight: 700;
        text-decoration: none;
        border: 0;
        background: transparent;
        padding: 0;
        cursor: pointer;
    }

    .av-professor-index-list {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: .5rem;
    }

    .av-professor-index-list > button {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) minmax(10rem, 14rem) auto;
        align-items: center;
        gap: .8rem;
        border: 1px solid #dbe4f0;
        border-radius: .85rem;
        background: #fff;
        padding: .8rem 1rem;
        color: #2563eb;
        cursor: pointer;
        text-align: left;
        transition: border-color .15s ease, background .15s ease;
    }

    .av-professor-index-list > button:hover {
        border-color: #93c5fd;
        background: #f8fbff;
    }

    .av-professor-index-list strong,
    .av-professor-index-list small {
        display: block;
    }

    .av-professor-index-list strong {
        color: #0f172a;
        font-size: .86rem;
    }

    .av-professor-index-list small {
        margin-top: .2rem;
        color: #64748b;
        font-size: .72rem;
    }

    .av-professor-class-list {
        display: grid;
        gap: .8rem;
    }

    .av-professor-class-card {
        overflow: hidden;
        transition: border-color .2s ease, box-shadow .2s ease;
    }

    .av-professor-class-card.is-open {
        border-color: #93c5fd;
        box-shadow: 0 14px 36px rgba(37, 99, 235, .09);
    }

    .av-professor-class-toggle {
        width: 100%;
        border: 0;
        background: transparent;
        padding: 1rem 1.15rem;
        color: inherit;
        cursor: pointer;
        text-align: left;
    }

    .av-professor-class-toggle__title,
    .av-professor-class-toggle__side {
        display: flex;
        align-items: center;
        gap: .8rem;
    }

    .av-professor-class-icon,
    .av-professor-navigation-avatar,
    .av-professor-navigation-index {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        width: 2.25rem;
        height: 2.25rem;
        border-radius: .7rem;
        background: #eff6ff;
        color: #1d4ed8;
        font-size: .8rem;
        font-weight: 800;
    }

    .av-professor-class-toggle h3 {
        margin: 0;
        color: #0f172a;
        font-size: .98rem;
        font-weight: 750;
    }

    .av-professor-load-hint {
        color: #64748b;
        font-size: .78rem;
    }

    .av-professor-compact-progress {
        display: grid;
        grid-template-columns: auto auto;
        width: 12rem;
        gap: .25rem .75rem;
        color: #475569;
        font-size: .72rem;
    }

    .av-professor-compact-progress > span:nth-child(2) {
        text-align: right;
    }

    .av-professor-compact-progress .av-progress-track {
        display: block;
        grid-column: 1 / -1;
    }

    .av-professor-compact-progress .av-progress-bar {
        display: block;
        min-height: 100%;
        background: linear-gradient(90deg, #2563eb 0%, #1e40af 100%);
    }

    .av-professor-components {
        border-top: 1px solid #e2e8f0;
        padding: 1rem 1.15rem 1.2rem;
        background: #f8fafc;
    }

    .av-professor-components.is-loading {
        opacity: .65;
        pointer-events: none;
    }

    .av-professor-components__heading {
        margin-bottom: .85rem;
        color: #475569;
        font-size: .78rem;
    }

    .av-professor-components__heading strong,
    .av-professor-components__heading span {
        display: block;
    }

    .av-professor-components__heading strong {
        color: #0f172a;
        font-size: .88rem;
    }

    .av-professor-component-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(17rem, 22rem));
        gap: .7rem;
    }

    .av-professor-component-card {
        display: flex;
        min-width: 0;
        flex-direction: column;
        align-items: stretch;
        gap: .35rem;
        border: 1px solid #dbe4f0;
        border-radius: .8rem;
        background: #fff;
        padding: .9rem;
        color: inherit;
        cursor: pointer;
        text-align: left;
        transition: transform .15s ease, border-color .15s ease, box-shadow .15s ease;
    }

    .av-professor-component-card:hover {
        transform: translateY(-1px);
        border-color: #93c5fd;
        box-shadow: 0 8px 20px rgba(37, 99, 235, .08);
    }

    .av-professor-component-card__name {
        overflow: hidden;
        color: #0f172a;
        font-size: .9rem;
        font-weight: 750;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .av-professor-component-card__teacher,
    .av-professor-component-card__meta {
        color: #64748b;
        font-size: .72rem;
    }

    .av-professor-component-card__action {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: .35rem;
        color: #2563eb;
        font-size: .76rem;
        font-weight: 750;
    }

    .av-professor-component-card__action strong {
        font-size: .78rem;
    }

    .av-professor-component-card__progress {
        display: block;
        padding-top: .35rem;
    }

    .av-professor-component-card__progress .av-progress-track {
        display: block;
        width: 100%;
    }

    .av-professor-component-card__progress .av-progress-bar {
        display: block;
        min-height: 100%;
        background: linear-gradient(90deg, #2563eb 0%, #1e40af 100%);
    }

    .av-professor-modal-backdrop {
        position: fixed;
        z-index: 70;
        inset: 0;
        display: grid;
        place-items: center;
        padding: 1.25rem;
        background: rgba(15, 23, 42, .62);
        backdrop-filter: blur(3px);
    }

    .av-professor-modal {
        display: flex;
        width: min(96rem, 100%);
        max-height: calc(100vh - 2.5rem);
        max-height: calc(100dvh - 2.5rem);
        flex-direction: column;
        overflow: hidden;
        border: 1px solid #dbe4f0;
        border-radius: 1rem;
        background: #fff;
        box-shadow: 0 28px 70px rgba(15, 23, 42, .3);
    }

    .av-professor-modal__header {
        padding: 1rem 1.2rem;
        border-bottom: 1px solid #e2e8f0;
    }

    .av-professor-modal__header h2 {
        font-size: 1.2rem;
    }

    .av-professor-modal__close {
        display: grid;
        place-items: center;
        width: 2.35rem;
        height: 2.35rem;
        border: 1px solid #dbe4f0;
        border-radius: .7rem;
        background: #fff;
        color: #475569;
        font-size: 1.4rem;
        cursor: pointer;
    }

    .av-professor-modal__tabs {
        display: flex;
        gap: .35rem;
        padding: .65rem 1.2rem;
        border-bottom: 1px solid #e2e8f0;
        background: #f8fafc;
    }

    .av-professor-modal__tabs button {
        border: 1px solid transparent;
        border-radius: .65rem;
        background: transparent;
        padding: .55rem .85rem;
        color: #64748b;
        font-size: .8rem;
        font-weight: 750;
        cursor: pointer;
    }

    .av-professor-modal__tabs button.is-active {
        border-color: #bfdbfe;
        background: #fff;
        color: #1d4ed8;
        box-shadow: 0 3px 10px rgba(37, 99, 235, .08);
    }

    .av-professor-modal__body {
        display: grid;
        grid-template-columns: minmax(17rem, 22rem) minmax(0, 1fr);
        min-height: 0;
        flex: 1;
        overflow: hidden;
    }

    .av-professor-modal__navigation,
    .av-professor-modal__workspace {
        min-height: 0;
        overflow-y: auto;
    }

    .av-professor-modal__navigation {
        padding: 1rem;
        border-right: 1px solid #e2e8f0;
        background: #f8fafc;
    }

    .av-professor-modal__navigation > strong {
        display: block;
        margin-bottom: .7rem;
        color: #475569;
        font-size: .76rem;
        text-transform: uppercase;
        letter-spacing: .055em;
    }

    .av-professor-navigation-list {
        display: grid;
        gap: .4rem;
    }

    .av-professor-mobile-selector {
        display: none;
    }

    .av-professor-navigation-list button {
        display: flex;
        align-items: center;
        width: 100%;
        gap: .65rem;
        border: 1px solid transparent;
        border-radius: .75rem;
        background: transparent;
        padding: .65rem;
        color: inherit;
        cursor: pointer;
        text-align: left;
    }

    .av-professor-navigation-list button:hover,
    .av-professor-navigation-list button.is-active {
        border-color: #bfdbfe;
        background: #fff;
    }

    .av-professor-navigation-list button.is-active {
        box-shadow: inset 3px 0 #2563eb;
    }

    .av-professor-navigation-list button > span:last-child {
        min-width: 0;
    }

    .av-professor-navigation-list strong,
    .av-professor-navigation-list small {
        display: block;
    }

    .av-professor-navigation-list strong {
        display: -webkit-box;
        overflow: hidden;
        color: #0f172a;
        font-size: .78rem;
        line-height: 1.35;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
    }

    .av-professor-navigation-list small {
        margin-top: .2rem;
        color: #64748b;
        font-size: .68rem;
    }

    .av-professor-navigation-index {
        width: 1.8rem;
        height: 1.8rem;
        border-radius: .55rem;
    }

    .av-professor-modal__workspace {
        padding: 0 1.15rem 1.5rem;
    }

    .av-professor-workspace-heading {
        position: sticky;
        z-index: 5;
        top: 0;
        align-items: flex-start;
        margin-bottom: 1rem;
        padding: 1rem 0 .8rem;
        border-bottom: 1px solid #e2e8f0;
        background: #fff;
    }

    .av-professor-workspace-heading h3 {
        max-width: 58rem;
        font-size: 1rem;
        line-height: 1.45;
    }

    .av-professor-workspace-heading > span {
        flex: 0 0 auto;
        border-radius: 999px;
        background: #eff6ff;
        padding: .35rem .65rem;
        color: #1d4ed8;
        font-size: .7rem;
        font-weight: 750;
    }

    .av-professor-answer-list,
    .av-professor-answer-group {
        display: grid;
        gap: .75rem;
    }

    .av-professor-student-table {
        border: 1px solid #dbe4f0;
        border-radius: .8rem;
    }

    .av-professor-student-table .gi-table td:first-child {
        width: 28%;
    }

    .av-professor-student-table .gi-table td:nth-child(2) {
        width: 31%;
    }

    .av-professor-student-table .gi-table td strong,
    .av-professor-student-table .gi-table td small {
        display: block;
    }

    .av-professor-student-table .gi-table td small {
        margin-top: .2rem;
        color: #64748b;
        font-size: .68rem;
    }

    .av-professor-student-table .av-textarea-input {
        min-height: 3.25rem;
    }

    .av-professor-pauta-table {
        margin-bottom: .75rem;
    }

    .av-professor-pauta-table .gi-table td:first-child {
        width: 42%;
    }

    .av-professor-pauta-table .gi-table td:nth-child(2) {
        width: 25%;
    }

    .av-professor-pauta-title {
        display: flex;
        align-items: flex-start;
        gap: .65rem;
    }

    .av-professor-pauta-title > span {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        width: 1.75rem;
        height: 1.75rem;
        border-radius: .5rem;
        background: #eff6ff;
        color: #1d4ed8;
        font-size: .72rem;
        font-weight: 800;
    }

    .av-professor-pauta-title > div {
        min-width: 0;
    }

    .av-professor-pauta-title strong {
        line-height: 1.4;
    }

    .av-professor-student-table .av-textarea-input:disabled,
    .av-professor-answer-fields .av-textarea-input:disabled {
        border-style: dashed;
        background: #f8fafc;
        color: #94a3b8;
        cursor: not-allowed;
    }

    .av-professor-bulk {
        display: grid;
        grid-template-columns: minmax(14rem, 1fr) minmax(14rem, 20rem) auto;
        align-items: center;
        gap: .75rem;
        margin: 0 0 1rem;
        border: 1px solid #bfdbfe;
        border-radius: .8rem;
        background: #eff6ff;
        padding: .75rem;
    }

    .av-professor-bulk strong,
    .av-professor-bulk span {
        display: block;
    }

    .av-professor-bulk strong {
        color: #1e3a8a;
        font-size: .78rem;
    }

    .av-professor-bulk div > span {
        margin-top: .2rem;
        color: #64748b;
        font-size: .68rem;
    }

    .av-professor-row-blocked {
        color: #b91c1c !important;
    }

    .av-professor-row-placeholder {
        color: #94a3b8;
    }

    .av-professor-answer-group {
        gap: 0;
        overflow: hidden;
        border: 1px solid #dbe4f0;
        border-radius: .85rem;
    }

    .av-professor-answer-group .av-professor-answer-card,
    .av-professor-answer-group .av-professor-complementary {
        border: 0;
        border-radius: 0;
    }

    .av-professor-answer-group .av-professor-complementary {
        border-top: 1px solid #e2e8f0;
    }

    .av-professor-answer-card,
    .av-professor-complementary {
        border: 1px solid #dbe4f0;
        border-radius: .85rem;
        background: #fff;
        padding: .9rem;
    }

    .av-professor-answer-card__header {
        align-items: flex-start;
        margin-bottom: .75rem;
    }

    .av-professor-answer-card__header strong,
    .av-professor-answer-card__header span {
        display: block;
    }

    .av-professor-answer-card__header strong {
        color: #0f172a;
        font-size: .85rem;
        line-height: 1.4;
    }

    .av-professor-answer-card__header div > span,
    .av-professor-origin {
        margin-top: .2rem;
        color: #64748b;
        font-size: .7rem;
    }

    .av-professor-origin {
        margin: -.3rem 0 .7rem;
    }

    .av-professor-answer-fields {
        display: grid;
        grid-template-columns: minmax(13rem, .8fr) minmax(16rem, 1.2fr);
        gap: .75rem;
    }

    .av-professor-answer-fields .gi-field > span,
    .av-professor-complementary > span {
        display: block;
        margin-bottom: .35rem;
        color: #475569;
        font-size: .72rem;
        font-weight: 700;
    }

    .av-professor-field-placeholder {
        display: flex;
        min-height: 2.75rem;
        align-items: center;
        border: 1px dashed #cbd5e1;
        border-radius: .65rem;
        padding: .65rem .75rem;
        color: #94a3b8;
        font-size: .72rem;
    }

    .av-professor-complementary {
        display: block;
        background: #f8fafc;
    }

    .av-professor-selection-empty {
        display: grid;
        min-height: 20rem;
        place-content: center;
        justify-items: center;
        color: #64748b;
        text-align: center;
    }

    .av-professor-selection-empty > span {
        margin-bottom: .75rem;
        color: #93c5fd;
        font-size: 2rem;
    }

    .av-professor-selection-empty strong {
        color: #0f172a;
    }

    .av-professor-selection-empty p {
        max-width: 22rem;
    }

    .av-professor-modal__footer {
        justify-content: flex-end;
        min-height: 4.2rem;
        padding: .75rem 1.2rem;
        border-top: 1px solid #e2e8f0;
        color: #047857;
        font-size: .74rem;
    }

    .av-professor-modal__footer .is-error {
        color: #b91c1c;
    }

    .av-professor-modal__footer > span {
        margin-right: auto;
    }

    .av-professor-modal__footer > div {
        margin-left: auto;
    }

    @media (max-width: 800px) {
        .av-professor-component-grid {
            grid-template-columns: minmax(0, 1fr);
        }

        .av-professor-list-heading,
        .av-professor-context__row,
        .av-professor-evaluation-card,
        .av-professor-class-toggle,
        .av-professor-modal__header,
        .av-professor-workspace-heading {
            align-items: flex-start;
        }

        .av-professor-class-toggle__side {
            align-self: stretch;
            justify-content: space-between;
        }

        .av-professor-class-toggle,
        .av-professor-evaluation-card {
            flex-direction: column;
        }

        .av-professor-evaluation-card {
            display: flex;
            align-items: stretch;
            gap: 1rem;
        }

        .av-professor-evaluation-card .gi-action {
            width: 100%;
        }

        .av-professor-index-list > button {
            grid-template-columns: auto minmax(0, 1fr) auto;
        }

        .av-professor-index-list > button > .av-professor-compact-progress {
            grid-row: 2;
            grid-column: 2 / 4;
            width: 100%;
        }

        .av-professor-index-list > button > span:last-child {
            grid-row: 1;
            grid-column: 3;
        }

        .av-professor-bulk,
        .av-professor-bulk label,
        .av-professor-bulk .gi-action {
            width: 100%;
        }

        .av-professor-bulk {
            grid-template-columns: 1fr;
        }

        .av-professor-modal-backdrop {
            padding: 0;
        }

        .av-professor-modal {
            width: 100%;
            height: 100vh;
            height: 100dvh;
            max-height: 100vh;
            max-height: 100dvh;
            border-radius: 0;
        }

        .av-professor-modal__header {
            flex: 0 0 auto;
            gap: .65rem;
            padding: .75rem;
        }

        .av-professor-modal__header h2 {
            display: -webkit-box;
            overflow: hidden;
            font-size: 1rem;
            line-height: 1.3;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
        }

        .av-professor-modal__header p {
            font-size: .7rem;
        }

        .av-professor-modal__tabs {
            flex: 0 0 auto;
            padding: .5rem .75rem;
        }

        .av-professor-modal__tabs button {
            flex: 1;
            text-align: center;
        }

        .av-professor-modal__body {
            display: flex;
            min-height: 0;
            flex-direction: column;
            overflow: hidden;
        }

        .av-professor-modal__navigation {
            flex: 0 0 auto;
            overflow: hidden;
            padding: .65rem .75rem .5rem;
            border-right: 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .av-professor-modal__navigation > strong {
            display: none;
        }

        .av-professor-mobile-selector {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            gap: .75rem;
            border: 1px solid #bfdbfe;
            border-radius: .7rem;
            background: #fff;
            padding: .65rem .75rem;
            color: #1d4ed8;
            cursor: pointer;
            text-align: left;
        }

        .av-professor-mobile-selector > span:first-child {
            min-width: 0;
        }

        .av-professor-mobile-selector small,
        .av-professor-mobile-selector strong {
            display: block;
        }

        .av-professor-mobile-selector small {
            margin-bottom: .15rem;
            color: #64748b;
            font-size: .62rem;
            font-weight: 750;
            letter-spacing: .045em;
            text-transform: uppercase;
        }

        .av-professor-mobile-selector strong {
            overflow: hidden;
            color: #0f172a;
            font-size: .76rem;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .av-professor-navigation-list {
            display: none;
            max-height: 32vh;
            max-height: 32dvh;
            overflow-y: auto;
            margin-top: .45rem;
            padding: .25rem;
            border: 1px solid #dbe4f0;
            border-radius: .7rem;
            background: #fff;
            scrollbar-width: thin;
        }

        .av-professor-navigation-list.is-mobile-open {
            display: grid;
        }

        .av-professor-navigation-list button {
            min-width: 0;
            padding: .5rem;
        }

        .av-professor-modal__workspace {
            min-height: 0;
            flex: 1 1 auto;
            overflow-y: auto;
            padding: 0 .75rem 1rem;
            overscroll-behavior: contain;
        }

        .av-professor-workspace-heading {
            margin-bottom: .65rem;
            padding: .7rem 0 .6rem;
        }

        .av-professor-workspace-heading h3 {
            font-size: .88rem;
        }

        .av-professor-workspace-heading > span {
            padding: .25rem .45rem;
            font-size: .62rem;
        }

        .av-professor-answer-fields {
            grid-template-columns: 1fr;
        }

        .av-professor-bulk {
            gap: .55rem;
            margin-bottom: .65rem;
            padding: .65rem;
        }

        .av-professor-student-table {
            overflow: visible;
            border-radius: .7rem;
        }

        .av-professor-student-table .gi-table,
        .av-professor-student-table .gi-table tbody,
        .av-professor-student-table .gi-table tr,
        .av-professor-student-table .gi-table td {
            display: block;
            width: 100% !important;
        }

        .av-professor-student-table .gi-table thead {
            display: none;
        }

        .av-professor-student-table .gi-table tr {
            padding: .75rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .av-professor-student-table .gi-table tr:last-child {
            border-bottom: 0;
        }

        .av-professor-student-table .gi-table td {
            padding: 0 0 .65rem;
            border: 0;
        }

        .av-professor-student-table .gi-table td:last-child {
            padding-bottom: 0;
        }

        .av-professor-student-table .gi-table td::before {
            display: block;
            margin-bottom: .3rem;
            color: #64748b;
            content: attr(data-label);
            font-size: .66rem;
            font-weight: 750;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .av-professor-pauta-title > span {
            width: 1.55rem;
            height: 1.55rem;
        }

        .av-professor-student-table .av-textarea-input {
            min-height: 4rem;
        }

        .av-professor-complementary {
            padding: .75rem;
        }

        .av-professor-modal__footer > div,
        .av-professor-modal__footer .gi-action {
            width: 100%;
        }

        .av-professor-modal__footer {
            flex: 0 0 auto;
            min-height: auto;
            padding: .6rem .75rem;
        }

        .av-professor-modal__footer > span {
            display: none !important;
        }

        .av-professor-modal__footer > div {
            display: grid;
            grid-template-columns: minmax(0, .8fr) minmax(0, 1.2fr);
            gap: .5rem;
            margin-left: 0;
        }

        .av-professor-modal__footer .gi-action {
            justify-content: center;
        }
    }

    :root.dark .av-professor-evaluation-card,
    :root.dark .av-professor-class-card,
    :root.dark .av-professor-modal,
    :root.dark .av-professor-component-card,
    :root.dark .av-professor-answer-card,
    :root.dark .av-professor-navigation-list button:hover,
    :root.dark .av-professor-navigation-list button.is-active,
    :root.dark .av-professor-index-list > button,
    :root.dark .av-professor-bulk {
        border-color: rgba(148, 163, 184, .25);
        background: #111827;
    }

    :root.dark .av-professor-components,
    :root.dark .av-professor-modal__tabs,
    :root.dark .av-professor-modal__navigation,
    :root.dark .av-professor-complementary {
        border-color: rgba(148, 163, 184, .2);
        background: #0f172a;
    }

    :root.dark .av-professor-workspace-heading {
        border-color: rgba(148, 163, 184, .2);
        background: #111827;
    }

    :root.dark .av-professor-list-heading h2,
    :root.dark .av-professor-context h2,
    :root.dark .av-professor-modal h2,
    :root.dark .av-professor-workspace-heading h3,
    :root.dark .av-professor-evaluation-card h3,
    :root.dark .av-professor-index-list strong,
    :root.dark .av-professor-bulk strong,
    :root.dark .av-professor-class-toggle h3,
    :root.dark .av-professor-components__heading strong,
    :root.dark .av-professor-component-card__name,
    :root.dark .av-professor-navigation-list strong,
    :root.dark .av-professor-answer-card__header strong,
    :root.dark .av-professor-selection-empty strong,
    :root.dark .av-professor-empty strong {
        color: #f8fafc;
    }
</style>
