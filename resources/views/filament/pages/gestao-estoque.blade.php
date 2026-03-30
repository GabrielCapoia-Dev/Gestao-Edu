<x-filament-panels::page>

    <style>
        /* ─── Variáveis ──────────────────────────────────────────── */
        :root {
            --ge-radius: 0.75rem;
            --ge-radius-sm: 0.5rem;
            --ge-trans: 150ms ease;
        }

        /* ─── Cards ──────────────────────────────────────────────── */
        .ge-cards {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(2, 1fr);
        }

        @media (min-width: 1024px) {
            .ge-cards {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        .ge-card {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.1rem 1.25rem;
            border-radius: var(--ge-radius);
            border: 1px solid var(--ge-card-ring);
            background: var(--ge-card-bg);
            transition: box-shadow var(--ge-trans);
        }

        .ge-card:hover {
            box-shadow: 0 4px 16px rgba(0, 0, 0, .07);
        }

        .ge-card--blue {
            --ge-card-bg: #eff6ff;
            --ge-card-ring: #bfdbfe;
        }

        .ge-card--amber {
            --ge-card-bg: #fffbeb;
            --ge-card-ring: #fde68a;
        }

        .ge-card--red {
            --ge-card-bg: #fef2f2;
            --ge-card-ring: #fecaca;
        }

        .ge-card--green {
            --ge-card-bg: #f0fdf4;
            --ge-card-ring: #bbf7d0;
        }

        .dark .ge-card--blue {
            --ge-card-bg: rgba(30, 58, 138, .25);
            --ge-card-ring: rgba(37, 99, 235, .35);
        }

        .dark .ge-card--amber {
            --ge-card-bg: rgba(120, 53, 15, .25);
            --ge-card-ring: rgba(217, 119, 6, .35);
        }

        .dark .ge-card--red {
            --ge-card-bg: rgba(127, 29, 29, .25);
            --ge-card-ring: rgba(220, 38, 38, .35);
        }

        .dark .ge-card--green {
            --ge-card-bg: rgba(20, 83, 45, .25);
            --ge-card-ring: rgba(22, 163, 74, .35);
        }

        .ge-card__icon {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 2.75rem;
            height: 2.75rem;
            border-radius: var(--ge-radius-sm);
            background: rgba(255, 255, 255, .6);
            border: 1px solid var(--ge-card-ring);
        }

        .dark .ge-card__icon {
            background: rgba(255, 255, 255, .08);
        }

        .ge-card__icon--blue svg {
            color: #2563eb;
        }

        .ge-card__icon--amber svg {
            color: #d97706;
        }

        .ge-card__icon--red svg {
            color: #dc2626;
        }

        .ge-card__icon--green svg {
            color: #16a34a;
        }

        .dark .ge-card__icon--blue svg {
            color: #93c5fd;
        }

        .dark .ge-card__icon--amber svg {
            color: #fcd34d;
        }

        .dark .ge-card__icon--red svg {
            color: #fca5a5;
        }

        .dark .ge-card__icon--green svg {
            color: #86efac;
        }

        .ge-card__label {
            font-size: .7rem;
            font-weight: 500;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: #6b7280;
            line-height: 1;
            margin-bottom: .25rem;
        }

        .dark .ge-card__label {
            color: #9ca3af;
        }

        .ge-card__value {
            font-size: 1.35rem;
            font-weight: 700;
            line-height: 1.1;
        }

        .ge-card--blue .ge-card__value {
            color: #1d4ed8;
        }

        .ge-card--amber .ge-card__value {
            color: #b45309;
        }

        .ge-card--red .ge-card__value {
            color: #b91c1c;
        }

        .ge-card--green .ge-card__value {
            color: #15803d;
        }

        .dark .ge-card--blue .ge-card__value {
            color: #93c5fd;
        }

        .dark .ge-card--amber .ge-card__value {
            color: #fcd34d;
        }

        .dark .ge-card--red .ge-card__value {
            color: #fca5a5;
        }

        .dark .ge-card--green .ge-card__value {
            color: #86efac;
        }

        .ge-card__sub {
            font-size: .7rem;
            color: #9ca3af;
            margin-top: .15rem;
        }

        .dark .ge-card__sub {
            color: #6b7280;
        }

        /* ─── Barra de busca ─────────────────────────────────────── */
        .ge-search-bar {
            padding: .5rem .75rem;
        }

        .ge-search-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        .ge-search-icon {
            position: absolute;
            left: .75rem;
            width: 1rem;
            height: 1rem;
            color: #9ca3af;
            pointer-events: none;
        }

        .dark .ge-search-icon {
            color: #6b7280;
        }

        .ge-search-input {
            width: 100%;
            padding: .6rem 2.5rem .6rem 2.4rem;
            border-radius: var(--ge-radius);
            border: 1px solid #d1d5db;
            background: #fff;
            font-size: .875rem;
            color: #111827;
            outline: none;
            font-family: inherit;
            -webkit-appearance: none;
            transition: border-color var(--ge-trans), box-shadow var(--ge-trans);
        }

        .ge-search-input::placeholder {
            color: #9ca3af;
        }

        .ge-search-input:focus {
            border-color: var(--primary-500, #6366f1);
            box-shadow: 0 0 0 3px var(--primary-100, #e0e7ff);
        }

        .dark .ge-search-input {
            background: #1f2937;
            border-color: #374151;
            color: #f9fafb;
        }

        .dark .ge-search-input:focus {
            border-color: var(--primary-400, #818cf8);
            box-shadow: 0 0 0 3px rgba(129, 140, 248, .15);
        }

        .ge-search-clear {
            position: absolute;
            right: .65rem;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 1.4rem;
            height: 1.4rem;
            border-radius: 9999px;
            border: none;
            background: #e5e7eb;
            color: #6b7280;
            cursor: pointer;
        }

        .ge-search-clear svg {
            width: .75rem;
            height: .75rem;
        }

        .ge-search-clear:hover {
            background: #d1d5db;
            color: #111827;
        }

        .dark .ge-search-clear {
            background: #374151;
            color: #9ca3af;
        }

        .dark .ge-search-clear:hover {
            background: #4b5563;
            color: #f9fafb;
        }

        /* ─── Painel + abas ──────────────────────────────────────── */
        .ge-panel {
            border-radius: var(--ge-radius) var(--ge-radius) 0 0;
            border: 1px solid #e5e7eb;
            border-bottom: none;
            background: #fff;
            overflow: visible;
            margin-top: .5rem;
        }

        .dark .ge-panel {
            border-color: #374151;
            background: #111827;
        }

        .ge-tabs-nav {
            position: relative;
            display: flex;
            align-items: stretch;
            border-bottom: 1px solid #e5e7eb;
        }

        .dark .ge-tabs-nav {
            border-bottom-color: #374151;
        }

        .ge-tabs {
            display: flex;
            padding: .75rem .25rem 0;
            border-bottom: none !important;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            flex: 1;
            min-width: 0;
        }

        .ge-tabs::-webkit-scrollbar {
            display: none;
        }

        .ge-tab {
            flex-shrink: 0;
            padding: .6rem 1rem;
            font-size: .8125rem;
            font-weight: 500;
            white-space: nowrap;
            border: none;
            border-bottom: 2px solid transparent;
            background: none;
            cursor: pointer;
            outline: none;
            color: #6b7280;
            transition: color var(--ge-trans), border-color var(--ge-trans);
        }

        .dark .ge-tab {
            color: #9ca3af;
        }

        .ge-tab:hover {
            color: #374151;
        }

        .dark .ge-tab:hover {
            color: #d1d5db;
        }

        .ge-tab--active {
            color: var(--primary-600, #4f46e5);
            border-bottom-color: var(--primary-600, #4f46e5);
        }

        .dark .ge-tab--active {
            color: var(--primary-400, #818cf8);
            border-bottom-color: var(--primary-400, #818cf8);
        }

        .ge-tabs-arrow {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            border: none;
            background: #fff;
            color: #6b7280;
            cursor: pointer;
            transition: color var(--ge-trans), background var(--ge-trans);
            z-index: 1;
        }

        .dark .ge-tabs-arrow {
            background: #111827;
            color: #9ca3af;
        }

        .ge-tabs-arrow:hover {
            color: #111827;
            background: #f3f4f6;
        }

        .dark .ge-tabs-arrow:hover {
            color: #f9fafb;
            background: #1f2937;
        }

        .ge-tabs-arrow svg {
            width: .625rem;
            height: .625rem;
        }

        /* ─── Tabela ─────────────────────────────────────────────── */
        .ge-table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .ge-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .8125rem;
            min-width: 480px;
        }

        .ge-table thead tr {
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
        }

        .dark .ge-table thead tr {
            border-bottom-color: #374151;
            background: rgba(255, 255, 255, .03);
        }

        .ge-table th {
            padding: .65rem 1rem;
            text-align: left;
            font-size: .68rem;
            font-weight: 600;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: #6b7280;
            white-space: nowrap;
        }

        .dark .ge-table th {
            color: #9ca3af;
        }

        .ge-table th.r {
            text-align: right;
        }

        .ge-table th.c {
            text-align: center;
        }

        .ge-table tbody tr {
            border-bottom: 1px solid #f3f4f6;
            transition: background var(--ge-trans);
        }

        .dark .ge-table tbody tr {
            border-bottom-color: #1f2937;
        }

        .ge-table tbody tr:last-child {
            border-bottom: none;
        }

        .ge-table tbody tr:hover {
            background: #f9fafb;
        }

        .dark .ge-table tbody tr:hover {
            background: rgba(255, 255, 255, .03);
        }

        .ge-table td {
            padding: .75rem 1rem;
            vertical-align: middle;
            color: #374151;
        }

        .dark .ge-table td {
            color: #d1d5db;
        }

        .ge-table td.r {
            text-align: right;
        }

        .ge-table td.c {
            text-align: center;
        }

        @media (max-width:660px) {
            .ge-c-cat {
                display: none;
            }
        }

        @media (max-width:520px) {
            .ge-c-upd {
                display: none;
            }
        }

        .ge-item-nome {
            font-weight: 600;
            color: #111827;
            line-height: 1.3;
        }

        .dark .ge-item-nome {
            color: #f9fafb;
        }

        .ge-item-un {
            font-size: .68rem;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: #9ca3af;
            margin-top: .1rem;
        }

        .ge-qty-badge {
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            padding: .2rem .6rem;
            border-radius: 9999px;
            font-size: .75rem;
            font-weight: 700;
        }

        .ge-qty--normal {
            background: #dcfce7;
            color: #15803d;
        }

        .ge-qty--critico {
            background: #fef9c3;
            color: #92400e;
        }

        .ge-qty--zerado {
            background: #fee2e2;
            color: #b91c1c;
        }

        .dark .ge-qty--normal {
            background: rgba(22, 163, 74, .2);
            color: #86efac;
        }

        .dark .ge-qty--critico {
            background: rgba(245, 158, 11, .15);
            color: #fcd34d;
        }

        .dark .ge-qty--zerado {
            background: rgba(185, 28, 28, .2);
            color: #fca5a5;
        }

        .ge-btn-ver {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .35rem .75rem;
            border-radius: var(--ge-radius-sm);
            font-size: .75rem;
            font-weight: 500;
            border: 1px solid var(--primary-200, #c7d2fe);
            background: var(--primary-50, #eef2ff);
            color: var(--primary-700, #4338ca);
            cursor: pointer;
            white-space: nowrap;
            transition: background var(--ge-trans);
        }

        .dark .ge-btn-ver {
            border-color: rgba(99, 102, 241, .4);
            background: rgba(99, 102, 241, .12);
            color: #a5b4fc;
        }

        .ge-btn-ver:hover {
            background: var(--primary-100, #e0e7ff);
        }

        .dark .ge-btn-ver:hover {
            background: rgba(99, 102, 241, .22);
        }

        .ge-btn-ver svg {
            width: .875rem;
            height: .875rem;
        }

        .ge-th-sort {
            cursor: pointer;
            user-select: none;
            white-space: nowrap;
        }

        .ge-th-sort:hover {
            color: #374151;
        }

        .dark .ge-th-sort:hover {
            color: #d1d5db;
        }

        .ge-th-sort-icon {
            display: inline-flex;
            flex-direction: column;
            vertical-align: middle;
            margin-left: .25rem;
            gap: 1px;
            opacity: .35;
            transition: opacity 150ms ease;
        }

        .ge-th-sort:hover .ge-th-sort-icon,
        .ge-th-sort--active .ge-th-sort-icon {
            opacity: 1;
        }

        .ge-th-sort-icon svg {
            width: .625rem;
            height: .625rem;
            display: block;
        }

        .ge-th-sort-icon--asc .icon-up {
            color: var(--primary-600, #4f46e5);
        }

        .ge-th-sort-icon--desc .icon-down {
            color: var(--primary-600, #4f46e5);
        }

        .dark .ge-th-sort-icon--asc .icon-up {
            color: var(--primary-400, #818cf8);
        }

        .dark .ge-th-sort-icon--desc .icon-down {
            color: var(--primary-400, #818cf8);
        }

        .ge-empty {
            padding: 3.5rem 1rem;
            text-align: center;
            color: #9ca3af;
        }

        .ge-empty svg {
            width: 2.5rem;
            height: 2.5rem;
            margin: 0 auto .75rem;
            opacity: .35;
            display: block;
        }

        .ge-empty p {
            font-size: .875rem;
        }

        /* ─── Paginação ──────────────────────────────────────────── */
        .ge-pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: .75rem;
            padding: .875rem 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 0 0 var(--ge-radius) var(--ge-radius);
            background: #f9fafb;
        }

        .dark .ge-pagination {
            border-color: #374151;
            background: rgba(255, 255, 255, .02);
        }

        .ge-pag-info {
            display: flex;
            align-items: center;
            gap: .75rem;
            flex-wrap: wrap;
        }

        .ge-pag-text {
            font-size: .75rem;
            color: #6b7280;
        }

        .dark .ge-pag-text {
            color: #9ca3af;
        }

        .ge-pag-text strong {
            color: #374151;
        }

        .dark .ge-pag-text strong {
            color: #d1d5db;
        }

        .ge-pag-sizes {
            display: flex;
            align-items: center;
            gap: .3rem;
        }

        .ge-pag-size-label {
            font-size: .75rem;
            color: #6b7280;
            margin-right: .1rem;
        }

        .dark .ge-pag-size-label {
            color: #9ca3af;
        }

        .ge-pag-size-btn {
            padding: .2rem .55rem;
            border-radius: .375rem;
            border: 1px solid #d1d5db;
            background: #fff;
            color: #6b7280;
            font-size: .75rem;
            font-weight: 400;
            cursor: pointer;
            transition: all 150ms ease;
            font-family: inherit;
        }

        .ge-pag-size-btn:hover {
            border-color: #9ca3af;
            color: #374151;
        }

        .ge-pag-size-btn--active {
            border-color: var(--primary-400, #818cf8);
            background: var(--primary-50, #eef2ff);
            color: var(--primary-700, #4338ca);
            font-weight: 700;
        }

        .dark .ge-pag-size-btn {
            background: #1f2937;
            border-color: #374151;
            color: #9ca3af;
        }

        .dark .ge-pag-size-btn:hover {
            border-color: #6b7280;
            color: #d1d5db;
        }

        .dark .ge-pag-size-btn--active {
            border-color: var(--primary-400, #818cf8);
            background: rgba(99, 102, 241, .15);
            color: #a5b4fc;
        }

        .ge-pag-nav {
            display: flex;
            align-items: center;
            gap: .3rem;
        }

        .ge-pag-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 2rem;
            height: 2rem;
            padding: 0 .4rem;
            border-radius: .375rem;
            border: 1px solid #d1d5db;
            background: #fff;
            color: #374151;
            font-size: .75rem;
            font-weight: 400;
            cursor: pointer;
            transition: all 150ms ease;
            font-family: inherit;
        }

        .ge-pag-btn:hover:not(:disabled):not(.ge-pag-btn--active) {
            border-color: #9ca3af;
            background: #f3f4f6;
        }

        .ge-pag-btn--active {
            border-color: var(--primary-400, #818cf8);
            background: var(--primary-600, #4f46e5);
            color: #fff;
            font-weight: 700;
            cursor: default;
        }

        .ge-pag-btn:disabled {
            opacity: .4;
            cursor: not-allowed;
        }

        .ge-pag-btn svg {
            width: .6rem;
            height: .6rem;
        }

        .ge-pag-ellipsis {
            font-size: .75rem;
            color: #9ca3af;
            padding: 0 .15rem;
            user-select: none;
        }

        .dark .ge-pag-btn {
            background: #1f2937;
            border-color: #374151;
            color: #d1d5db;
        }

        .dark .ge-pag-btn:hover:not(:disabled):not(.ge-pag-btn--active) {
            background: #374151;
        }

        .dark .ge-pag-btn--active {
            border-color: var(--primary-500, #6366f1);
            background: var(--primary-600, #4f46e5);
            color: #fff;
        }

        /* ─── SlideOver / Modal ──────────────────────────────────── */
        .ge-backdrop {
            position: fixed;
            inset: 0;
            z-index: 40;
            background: rgba(3, 7, 18, .6);
            backdrop-filter: blur(2px);
        }

        .ge-modal-wrap {
            position: fixed;
            inset: 0;
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            pointer-events: none;
        }

        .ge-modal {
            pointer-events: all;
            display: flex;
            flex-direction: column;
            width: 100%;
            max-width: 38rem;
            max-height: calc(100dvh - 2rem);
            border-radius: var(--ge-radius);
            border: 1px solid #e5e7eb;
            background: #fff;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .18), 0 4px 16px rgba(0, 0, 0, .08);
            overflow: hidden;
        }

        .dark .ge-modal {
            border-color: #374151;
            background: #1f2937;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .5);
        }

        .ge-modal-hd {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            flex-shrink: 0;
        }

        .dark .ge-modal-hd {
            border-bottom-color: #374151;
        }

        .ge-modal-title {
            font-size: .9375rem;
            font-weight: 700;
            color: #111827;
            line-height: 1.3;
            word-break: break-word;
        }

        .dark .ge-modal-title {
            color: #f9fafb;
        }

        .ge-modal-sub {
            font-size: .72rem;
            color: #9ca3af;
            margin-top: .25rem;
        }

        .ge-modal-close {
            flex-shrink: 0;
            padding: .35rem;
            border-radius: .375rem;
            border: none;
            background: none;
            cursor: pointer;
            color: #6b7280;
            transition: background var(--ge-trans), color var(--ge-trans);
        }

        .ge-modal-close:hover {
            background: #f3f4f6;
            color: #111827;
        }

        .dark .ge-modal-close:hover {
            background: #1f2937;
            color: #f9fafb;
        }

        .ge-modal-close svg {
            width: 1.1rem;
            height: 1.1rem;
        }

        .ge-modal-resumo {
            padding: 1rem 1.5rem;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            flex-shrink: 0;
        }

        .dark .ge-modal-resumo {
            background: rgba(255, 255, 255, .03);
            border-bottom-color: #374151;
        }

        .ge-modal-resumo-title {
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #6b7280;
            margin-bottom: .75rem;
        }

        .dark .ge-modal-resumo-title {
            color: #9ca3af;
        }

        .ge-resumo-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: .6rem .75rem;
        }

        .ge-stat-label {
            font-size: .68rem;
            color: #9ca3af;
            margin-bottom: .2rem;
        }

        .dark .ge-stat-label {
            color: #6b7280;
        }

        .ge-stat-val {
            font-size: .875rem;
            font-weight: 700;
            color: #111827;
        }

        .dark .ge-stat-val {
            color: #f9fafb;
        }

        .ge-stat-val--entrada {
            color: #15803d;
        }

        .ge-stat-val--saida {
            color: #b91c1c;
        }

        .dark .ge-stat-val--entrada {
            color: #86efac;
        }

        .dark .ge-stat-val--saida {
            color: #fca5a5;
        }

        .ge-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 1rem 1.5rem;
            display: flex;
            flex-direction: column;
            gap: .6rem;
            -webkit-overflow-scrolling: touch;
        }

        .ge-modal-sect-title {
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #6b7280;
            flex-shrink: 0;
            margin-bottom: .25rem;
        }

        .dark .ge-modal-sect-title {
            color: #9ca3af;
        }

        .ge-mov {
            display: flex;
            align-items: flex-start;
            gap: .75rem;
            padding: .75rem 1rem;
            border-radius: var(--ge-radius-sm);
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            transition: background var(--ge-trans);
        }

        .dark .ge-mov {
            border-color: #374151;
            background: rgba(255, 255, 255, .03);
        }

        .ge-mov:hover {
            background: #f3f4f6;
        }

        .dark .ge-mov:hover {
            background: rgba(255, 255, 255, .06);
        }

        .ge-mov-icon {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            border-radius: 9999px;
            margin-top: .1rem;
        }

        .ge-mov-icon--entrada {
            background: #dcfce7;
            color: #15803d;
        }

        .ge-mov-icon--saida {
            background: #fee2e2;
            color: #b91c1c;
        }

        .dark .ge-mov-icon--entrada {
            background: rgba(22, 163, 74, .2);
            color: #86efac;
        }

        .dark .ge-mov-icon--saida {
            background: rgba(185, 28, 28, .2);
            color: #fca5a5;
        }

        .ge-mov-icon svg {
            width: .875rem;
            height: .875rem;
        }

        .ge-mov-info {
            flex: 1;
            min-width: 0;
        }

        .ge-mov-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
        }

        .ge-mov-tipo {
            font-size: .8125rem;
            font-weight: 600;
        }

        .ge-mov-tipo--entrada {
            color: #15803d;
        }

        .ge-mov-tipo--saida {
            color: #b91c1c;
        }

        .dark .ge-mov-tipo--entrada {
            color: #86efac;
        }

        .dark .ge-mov-tipo--saida {
            color: #fca5a5;
        }

        .ge-mov-qty {
            font-size: .875rem;
            font-weight: 800;
            color: #111827;
        }

        .dark .ge-mov-qty {
            color: #f9fafb;
        }

        .ge-mov-meta {
            font-size: .7rem;
            color: #9ca3af;
            margin-top: .15rem;
        }

        .ge-mov-obs {
            font-size: .72rem;
            color: #6b7280;
            margin-top: .2rem;
            font-style: italic;
        }

        .dark .ge-mov-obs {
            color: #9ca3af;
        }

        .ge-modal-ft {
            flex-shrink: 0;
            padding: 1rem 1.5rem;
            border-top: 1px solid #e5e7eb;
        }

        .dark .ge-modal-ft {
            border-top-color: #374151;
        }

        .ge-btn-fechar {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            padding: .625rem 1rem;
            border-radius: var(--ge-radius-sm);
            font-size: .8125rem;
            font-weight: 500;
            border: 1px solid #d1d5db;
            background: #f9fafb;
            color: #374151;
            cursor: pointer;
            transition: background var(--ge-trans);
        }

        .dark .ge-btn-fechar {
            border-color: #374151;
            background: #1f2937;
            color: #d1d5db;
        }

        .ge-btn-fechar:hover {
            background: #f3f4f6;
        }

        .dark .ge-btn-fechar:hover {
            background: #374151;
        }

        .ge-btn-fechar svg {
            width: .875rem;
            height: .875rem;
        }

        [x-cloak] {
            display: none !important;
        }
    </style>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
        <h1 style="font-size:1.25rem;font-weight:600"></h1>

        <div style="display:flex;gap:.5rem">
            <x-filament::button
                tag="a"
                :href="route('filament.admin.resources.pedidos-merenda.create')"
                icon="heroicon-o-plus">
                Novo Pedido
            </x-filament::button>
        </div>
    </div>

    {{-- ============================================================= --}}
    {{-- CARDS DO TOPO                                                  --}}
    {{-- ============================================================= --}}
    <div class="ge-cards">
        @foreach ($this->cards as $card)
        <div class="ge-card ge-card--{{ $card['cor'] }}">
            <div class="ge-card__icon ge-card__icon--{{ $card['cor'] }}">
                <x-filament::icon :icon="$card['icone']" class="w-5 h-5" />
            </div>
            <div style="min-width:0">
                <p class="ge-card__label">{{ $card['titulo'] }}</p>
                <p class="ge-card__value">{{ $card['valor'] }}</p>
                <p class="ge-card__sub">{{ $card['descricao'] }}</p>
            </div>
        </div>
        @endforeach
    </div>

    {{-- ============================================================= --}}
    {{-- BARRA DE BUSCA                                                 --}}
    {{-- ============================================================= --}}
    <div class="ge-search-bar">
        <div class="ge-search-wrap">
            <svg class="ge-search-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m0 0A7.5 7.5 0 1 0 4.65 4.65a7.5 7.5 0 0 0 12 12Z" />
            </svg>
            <input
                type="search"
                wire:model.live.debounce.300ms="busca"
                placeholder="Buscar por nome do item…"
                class="ge-search-input"
                autocomplete="off" />
            @if($busca)
            <button wire:click="$set('busca', '')" class="ge-search-clear" type="button" aria-label="Limpar busca">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
            @endif
        </div>
    </div>

    {{-- ============================================================= --}}
    {{-- PAINEL: ABAS + TABELA                                         --}}
    {{-- ============================================================= --}}
    <div class="ge-panel">

        {{-- Abas com navegação por setas --}}
        <div class="ge-tabs-nav" x-data="{
            canLeft: false, canRight: false,
            check() {
                const el = this.$refs.tabs;
                this.canLeft  = el.scrollLeft > 1;
                this.canRight = el.scrollLeft + el.clientWidth < el.scrollWidth - 1;
            },
            scroll(dir) {
                const el   = this.$refs.tabs;
                const item = el.querySelector('.ge-tab');
                const step = item ? item.offsetWidth + 8 : 120;
                el.scrollBy({ left: dir * step, behavior: 'smooth' });
            }
        }" x-init="check(); $refs.tabs.addEventListener('scroll', () => check())">

            <button class="ge-tabs-arrow" x-show="canLeft" x-transition.opacity @click="scroll(-1)" type="button">
                <svg viewBox="0 0 6 10" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 1L1 5l4 4" />
                </svg>
            </button>

            <div class="ge-tabs" role="tablist" x-ref="tabs">
                @foreach ($this->abas as $aba)
                <button
                    wire:click="mudarAba('{{ $aba['value'] }}')"
                    class="ge-tab {{ $abaAtiva === $aba['value'] ? 'ge-tab--active' : '' }}"
                    role="tab">
                    {{ $aba['label'] }}
                </button>
                @endforeach
            </div>

            <button class="ge-tabs-arrow" x-show="canRight" x-transition.opacity @click="scroll(1)" type="button">
                <svg viewBox="0 0 6 10" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 1l4 4-4 4" />
                </svg>
            </button>
        </div>

        {{-- Tabela --}}
        <div class="ge-table-wrap">
            <table class="ge-table">
                <thead>
                    <tr>
                        @php
                        function geThIcon(string $col, string $active, string $dir): string {
                        $isActive = $col === $active;
                        $dirClass = $isActive ? 'ge-th-sort-icon--' . $dir : '';
                        return '
                        <span class="ge-th-sort-icon ' . $dirClass . '">
                            <svg class="icon-up" viewBox="0 0 10 6" fill="currentColor">
                                <path d="M5 0L10 6H0z" />
                            </svg>
                            <svg class="icon-down" viewBox="0 0 10 6" fill="currentColor">
                                <path d="M5 6L0 0h10z" />
                            </svg>
                        </span>';
                        }
                        @endphp

                        <th
                            wire:click="sortBy('nome')"
                            class="ge-th-sort {{ $sortCol === 'nome' ? 'ge-th-sort--active' : '' }}">
                            Item{!! geThIcon('nome', $sortCol, $sortDir) !!}
                        </th>
                        <th class="ge-c-cat">Categoria</th>
                        <th
                            wire:click="sortBy('quantidade')"
                            class="r ge-th-sort {{ $sortCol === 'quantidade' ? 'ge-th-sort--active' : '' }}">
                            Quantidade em Estoque{!! geThIcon('quantidade', $sortCol, $sortDir) !!}
                        </th>
                        <th class="ge-c-upd">Última Atualização</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->itensFiltrados as $item)
                    @php
                    $qtyClass = match($item['status']) {
                    'zerado' => 'ge-qty--zerado',
                    'critico' => 'ge-qty--critico',
                    default => 'ge-qty--normal',
                    };
                    @endphp
                    <tr>
                        <td>
                            <p class="ge-item-nome">{{ $item['nome'] }}</p>
                            <p class="ge-item-un">{{ $item['unidade'] }}</p>
                        </td>
                        <td class="ge-c-cat" style="color:#6b7280;font-size:.75rem">
                            {{ $item['tipo_label'] }}
                        </td>
                        <td class="r">
                            <span class="ge-qty-badge {{ $qtyClass }}">
                                {{ number_format($item['quantidade'], 3, ',', '.') }}
                            </span>
                        </td>
                        <td class="ge-c-upd" style="color:#9ca3af;font-size:.75rem">
                            {{ $item['atualizado'] }}
                        </td>
                        <td class="c">
                            <button wire:click="abrirSlideOver({{ $item['estoque_id'] }})" class="ge-btn-ver">
                                <x-filament::icon icon="heroicon-o-clock" />
                                <span class="hidden sm:inline">Movimentações</span>
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5">
                            <div class="ge-empty">
                                <x-filament::icon icon="heroicon-o-inbox" />
                                <p>Nenhum item no estoque.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ============================================================= --}}
    {{-- PAGINAÇÃO                                                      --}}
    {{-- ============================================================= --}}
    @php $pag = $this->paginacao; @endphp
    @if ($pag['total'] > 0)
    <div class="ge-pagination">

        {{-- Info + seletor de registros por página --}}
        <div class="ge-pag-info">
            <span class="ge-pag-text">
                Exibindo <strong>{{ $pag['de'] }}–{{ $pag['ate'] }}</strong>
                de <strong>{{ $pag['total'] }}</strong> {{ $pag['total'] === 1 ? 'item' : 'itens' }}
            </span>
            <div class="ge-pag-sizes">
                <span class="ge-pag-size-label">Exibir:</span>
                @foreach ([5, 10, 50, 100] as $opcao)
                <button
                    wire:click="$set('porPagina', {{ $opcao }})"
                    class="ge-pag-size-btn {{ $porPagina == $opcao ? 'ge-pag-size-btn--active' : '' }}"
                    type="button">
                    {{ $opcao }}
                </button>
                @endforeach
            </div>
        </div>

        {{-- Botões de navegação --}}
        @if ($pag['totalPaginas'] > 1)
        <div class="ge-pag-nav">

            {{-- Anterior --}}
            <button
                wire:click="mudarPagina({{ $pag['paginaAtual'] - 1 }})"
                class="ge-pag-btn"
                {{ $pag['paginaAtual'] <= 1 ? 'disabled' : '' }}
                type="button"
                aria-label="Página anterior">
                <svg viewBox="0 0 6 10" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 1L1 5l4 4" />
                </svg>
            </button>

            @php
            $inicio = max(1, $pag['paginaAtual'] - 2);
            $fim = min($pag['totalPaginas'], $pag['paginaAtual'] + 2);
            @endphp

            {{-- Primeira página + reticências --}}
            @if ($inicio > 1)
            <button wire:click="mudarPagina(1)" class="ge-pag-btn" type="button">1</button>
            @if ($inicio > 2)
            <span class="ge-pag-ellipsis">…</span>
            @endif
            @endif

            {{-- Páginas do intervalo --}}
            @for ($p = $inicio; $p <= $fim; $p++)
                <button
                wire:click="mudarPagina({{ $p }})"
                class="ge-pag-btn {{ $pag['paginaAtual'] == $p ? 'ge-pag-btn--active' : '' }}"
                type="button">
                {{ $p }}
                </button>
                @endfor

                {{-- Reticências + última página --}}
                @if ($fim < $pag['totalPaginas'])
                    @if ($fim < $pag['totalPaginas'] - 1)
                    <span class="ge-pag-ellipsis">…</span>
                    @endif
                    <button wire:click="mudarPagina({{ $pag['totalPaginas'] }})" class="ge-pag-btn" type="button">
                        {{ $pag['totalPaginas'] }}
                    </button>
                    @endif

                    {{-- Próximo --}}
                    <button
                        wire:click="mudarPagina({{ $pag['paginaAtual'] + 1 }})"
                        class="ge-pag-btn"
                        {{ $pag['paginaAtual'] >= $pag['totalPaginas'] ? 'disabled' : '' }}
                        type="button"
                        aria-label="Próxima página">
                        <svg viewBox="0 0 6 10" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 1l4 4-4 4" />
                        </svg>
                    </button>

        </div>
        @endif
    </div>
    @endif

    {{-- ============================================================= --}}
    {{-- MODAL DE MOVIMENTAÇÕES                                        --}}
    {{-- ============================================================= --}}
    @if ($slideOverAberto)

    {{-- Backdrop --}}
    <div
        x-data x-cloak x-show="true"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="ge-backdrop"
        wire:click="fecharSlideOver"></div>

    {{-- Modal --}}
    <div
        x-data x-cloak x-show="true"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="ge-modal-wrap"
        role="dialog" aria-modal="true">
        <div class="ge-modal">

            {{-- Header --}}
            <div class="ge-modal-hd">
                <div style="min-width:0">
                    <h2 class="ge-modal-title">{{ $itemSelecionadoNome }}</h2>
                    <p class="ge-modal-sub">
                        Unidade: <strong style="text-transform:uppercase">{{ $itemSelecionadoUnidade }}</strong>
                        &middot;
                        {{ count($movimentacoes) }} {{ count($movimentacoes) === 1 ? 'movimentação' : 'movimentações' }} (últimas 50)
                    </p>
                </div>
                <button class="ge-modal-close" wire:click="fecharSlideOver" aria-label="Fechar">
                    <x-filament::icon icon="heroicon-o-x-mark" />
                </button>
            </div>

            {{-- Resumo consolidado --}}
            @php
            $totalEntradas = collect($movimentacoes)->where('tipo', 'entrada')->sum('quantidade');
            $totalSaidas = collect($movimentacoes)->where('tipo', 'saida')->sum('quantidade');
            $saldoAtual = $totalEntradas - $totalSaidas;
            @endphp

            <div class="ge-modal-resumo">
                <p class="ge-modal-resumo-title">Resumo das Movimentações</p>
                <div class="ge-resumo-grid">
                    <div>
                        <p class="ge-stat-label">Total Entradas</p>
                        <p class="ge-stat-val ge-stat-val--entrada">+ {{ number_format($totalEntradas, 3, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="ge-stat-label">Total Saídas</p>
                        <p class="ge-stat-val ge-stat-val--saida">- {{ number_format($totalSaidas, 3, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="ge-stat-label">Saldo (neste histórico)</p>
                        <p class="ge-stat-val">{{ number_format($saldoAtual, 3, ',', '.') }}</p>
                    </div>
                </div>
            </div>

            {{-- Body: lista de movimentações --}}
            <div class="ge-modal-body">
                <p class="ge-modal-sect-title">Histórico de Movimentações</p>

                @forelse ($movimentacoes as $mov)
                <div class="ge-mov">
                    <div class="ge-mov-icon ge-mov-icon--{{ $mov['tipo'] }}">
                        @if($mov['tipo'] === 'entrada')
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        @else
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12h-15" />
                        </svg>
                        @endif
                    </div>

                    <div class="ge-mov-info">
                        <div class="ge-mov-top">
                            <span class="ge-mov-tipo ge-mov-tipo--{{ $mov['tipo'] }}">
                                {{ $mov['tipo_label'] }}
                                @if($mov['pedido_id'])
                                <span style="font-weight:400;color:#9ca3af"> — Pedido #{{ $mov['pedido_id'] }}</span>
                                @endif
                            </span>
                            <span class="ge-mov-qty">
                                {{ $mov['tipo'] === 'entrada' ? '+' : '-' }}{{ number_format($mov['quantidade'], 3, ',', '.') }}
                            </span>
                        </div>
                        <p class="ge-mov-meta">
                            {{ $mov['data'] }}
                            @if($mov['registrado_por'] && $mov['registrado_por'] !== '—')
                            &middot; {{ $mov['registrado_por'] }}
                            @endif
                        </p>
                        @if($mov['observacao'])
                        <p class="ge-mov-obs">{{ $mov['observacao'] }}</p>
                        @endif
                    </div>
                </div>
                @empty
                <div class="ge-empty">
                    <x-filament::icon icon="heroicon-o-inbox" />
                    <p>Nenhuma movimentação registrada.</p>
                </div>
                @endforelse
            </div>

            {{-- Footer --}}
            <div class="ge-modal-ft">
                <button class="ge-btn-fechar" wire:click="fecharSlideOver">
                    <x-filament::icon icon="heroicon-o-x-mark" />
                    Fechar
                </button>
            </div>

        </div>
    </div>
    @endif

</x-filament-panels::page>