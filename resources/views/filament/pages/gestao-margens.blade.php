<x-filament-panels::page>

    <style>
        /* ─── Variáveis ─────────────────────────────────────────── */
        :root {
            --gm-radius: 0.75rem;
            --gm-radius-sm: 0.5rem;
            --gm-trans: 150ms ease;
            --gm-bar-h: 6px;
        }

        /* ─── Cards do topo ─────────────────────────────────────── */
        .gm-cards {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(2, 1fr);
        }

        @media (min-width: 1024px) {
            .gm-cards {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        .gm-card {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.1rem 1.25rem;
            border-radius: var(--gm-radius);
            border: 1px solid var(--gm-card-ring);
            background: var(--gm-card-bg);
            transition: box-shadow var(--gm-trans);
        }

        .gm-card:hover {
            box-shadow: 0 4px 16px rgba(0, 0, 0, .07);
        }

        .gm-card--blue {
            --gm-card-bg: #eff6ff;
            --gm-card-ring: #bfdbfe;
        }

        .gm-card--amber {
            --gm-card-bg: #fffbeb;
            --gm-card-ring: #fde68a;
        }

        .gm-card--red {
            --gm-card-bg: #fef2f2;
            --gm-card-ring: #fecaca;
        }

        .gm-card--green {
            --gm-card-bg: #f0fdf4;
            --gm-card-ring: #bbf7d0;
        }

        .dark .gm-card--blue {
            --gm-card-bg: rgba(30, 58, 138, .25);
            --gm-card-ring: rgba(37, 99, 235, .35);
        }

        .dark .gm-card--amber {
            --gm-card-bg: rgba(120, 53, 15, .25);
            --gm-card-ring: rgba(217, 119, 6, .35);
        }

        .dark .gm-card--red {
            --gm-card-bg: rgba(127, 29, 29, .25);
            --gm-card-ring: rgba(220, 38, 38, .35);
        }

        .dark .gm-card--green {
            --gm-card-bg: rgba(20, 83, 45, .25);
            --gm-card-ring: rgba(22, 163, 74, .35);
        }

        .gm-card__icon {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 2.75rem;
            height: 2.75rem;
            border-radius: var(--gm-radius-sm);
            background: rgba(255, 255, 255, .6);
            border: 1px solid var(--gm-card-ring);
        }

        .dark .gm-card__icon {
            background: rgba(255, 255, 255, .08);
        }

        .gm-card__icon--blue svg {
            color: #2563eb;
        }

        .gm-card__icon--amber svg {
            color: #d97706;
        }

        .gm-card__icon--red svg {
            color: #dc2626;
        }

        .gm-card__icon--green svg {
            color: #16a34a;
        }

        .dark .gm-card__icon--blue svg {
            color: #93c5fd;
        }

        .dark .gm-card__icon--amber svg {
            color: #fcd34d;
        }

        .dark .gm-card__icon--red svg {
            color: #fca5a5;
        }

        .dark .gm-card__icon--green svg {
            color: #86efac;
        }

        .gm-card__label {
            font-size: .7rem;
            font-weight: 500;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: #6b7280;
            line-height: 1;
            margin-bottom: .25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dark .gm-card__label {
            color: #9ca3af;
        }

        .gm-card__value {
            font-size: 1.35rem;
            font-weight: 700;
            line-height: 1.1;
            word-break: break-all;
        }

        @media (max-width:420px) {
            .gm-card__value {
                font-size: 1.1rem;
            }
        }

        .gm-card--blue .gm-card__value {
            color: #1d4ed8;
        }

        .gm-card--amber .gm-card__value {
            color: #b45309;
        }

        .gm-card--red .gm-card__value {
            color: #b91c1c;
        }

        .gm-card--green .gm-card__value {
            color: #15803d;
        }

        .dark .gm-card--blue .gm-card__value {
            color: #93c5fd;
        }

        .dark .gm-card--amber .gm-card__value {
            color: #fcd34d;
        }

        .dark .gm-card--red .gm-card__value {
            color: #fca5a5;
        }

        .dark .gm-card--green .gm-card__value {
            color: #86efac;
        }

        .gm-card__sub {
            font-size: .7rem;
            color: #9ca3af;
            margin-top: .15rem;
        }

        .dark .gm-card__sub {
            color: #6b7280;
        }

        /* ─── Painel principal ──────────────────────────────────── */
        .gm-panel {
            border-radius: var(--gm-radius);
            border: 1px solid #e5e7eb;
            background: #fff;
            overflow: visible;
            /* era hidden — isso bloqueava o scroll das abas */
            margin-top: .5rem;
        }

        .dark .gm-panel {
            border-color: #374151;
            background: #111827;
        }

        /* ─── Abas ──────────────────────────────────────────────── */
        .gm-tabs {
            display: flex;
            padding: .75rem 1rem 0;
            border-bottom: 1px solid #e5e7eb;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            gap: 0;
        }

        .fi-page-content,
        .fi-main {
            overflow: visible !important;
        }

        .gm-tabs::-webkit-scrollbar {
            display: none;
        }

        .dark .gm-tabs {
            border-bottom-color: #374151;
        }

        .gm-tab {
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
            transition: color var(--gm-trans), border-color var(--gm-trans);
        }

        .dark .gm-tab {
            color: #9ca3af;
        }

        .gm-tab:hover {
            color: #374151;
        }

        .dark .gm-tab:hover {
            color: #d1d5db;
        }

        .gm-tab--active {
            color: var(--primary-600, #4f46e5);
            border-bottom-color: var(--primary-600, #4f46e5);
        }

        .dark .gm-tab--active {
            color: var(--primary-400, #818cf8);
            border-bottom-color: var(--primary-400, #818cf8);
        }

        /* ─── Tabela ─────────────────────────────────────────────── */
        .gm-table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border-radius: 0 0 var(--gm-radius) var(--gm-radius);
            /* mantém arredondamento inferior */
        }

        .gm-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .8125rem;
            min-width: 480px;
        }

        .gm-table thead tr {
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
        }

        .dark .gm-table thead tr {
            border-bottom-color: #374151;
            background: rgba(255, 255, 255, .03);
        }

        .gm-table th {
            padding: .65rem 1rem;
            text-align: left;
            font-size: .68rem;
            font-weight: 600;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: #6b7280;
            white-space: nowrap;
        }

        .dark .gm-table th {
            color: #9ca3af;
        }

        .gm-table th.r {
            text-align: right;
        }

        .gm-table th.c {
            text-align: center;
        }

        .gm-table tbody tr {
            border-bottom: 1px solid #f3f4f6;
            transition: background var(--gm-trans);
        }

        .dark .gm-table tbody tr {
            border-bottom-color: #1f2937;
        }

        .gm-table tbody tr:last-child {
            border-bottom: none;
        }

        .gm-table tbody tr:hover {
            background: #f9fafb;
        }

        .dark .gm-table tbody tr:hover {
            background: rgba(255, 255, 255, .03);
        }

        .gm-table td {
            padding: .75rem 1rem;
            vertical-align: middle;
            color: #374151;
        }

        .dark .gm-table td {
            color: #d1d5db;
        }

        .gm-table td.r {
            text-align: right;
        }

        .gm-table td.c {
            text-align: center;
        }

        /* colunas responsivas */
        .gm-c-cat {
            display: table-cell;
        }

        .gm-c-tot {
            display: table-cell;
        }

        .gm-c-bar {
            display: table-cell;
        }

        .gm-c-contr {
            display: table-cell;
        }

        @media (max-width:880px) {
            .gm-c-tot {
                display: none;
            }
        }

        @media (max-width:660px) {
            .gm-c-cat {
                display: none;
            }

            .gm-c-contr {
                display: none;
            }
        }

        @media (max-width:520px) {
            .gm-c-bar {
                display: none;
            }
        }

        .gm-item-nome {
            font-weight: 600;
            color: #111827;
            line-height: 1.3;
        }

        .dark .gm-item-nome {
            color: #f9fafb;
        }

        .gm-item-un {
            font-size: .68rem;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: #9ca3af;
            margin-top: .1rem;
        }

        .gm-saldo-ok {
            font-weight: 700;
            color: #111827;
        }

        .gm-saldo-bad {
            font-weight: 700;
            color: #dc2626;
        }

        .dark .gm-saldo-ok {
            color: #f9fafb;
        }

        .dark .gm-saldo-bad {
            color: #f87171;
        }

        /* barra inline */
        .gm-bar {
            display: flex;
            align-items: center;
            gap: .5rem;
            min-width: 100px;
        }

        .gm-bar-track {
            flex: 1;
            height: var(--gm-bar-h);
            background: #e5e7eb;
            border-radius: 9999px;
            overflow: hidden;
        }

        .dark .gm-bar-track {
            background: #374151;
        }

        .gm-bar-fill {
            height: 100%;
            border-radius: 9999px;
            transition: width .4s ease;
        }

        .gm-f-normal {
            background: #22c55e;
        }

        .gm-f-baixo {
            background: #f59e0b;
        }

        .gm-f-critico {
            background: #ef4444;
        }

        .gm-f-zerado {
            background: #d1d5db;
        }

        .dark .gm-f-zerado {
            background: #4b5563;
        }

        .gm-bar-pct {
            font-size: .7rem;
            font-weight: 600;
            color: #6b7280;
            width: 2.5rem;
            text-align: right;
            white-space: nowrap;
        }

        .dark .gm-bar-pct {
            color: #9ca3af;
        }

        /* badge nº contratos */
        .gm-nbadge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.6rem;
            height: 1.6rem;
            border-radius: 9999px;
            background: #f3f4f6;
            font-size: .72rem;
            font-weight: 700;
            color: #374151;
        }

        .dark .gm-nbadge {
            background: #1f2937;
            color: #d1d5db;
        }

        /* botão ver */
        .gm-btn-ver {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .35rem .75rem;
            border-radius: var(--gm-radius-sm);
            font-size: .75rem;
            font-weight: 500;
            border: 1px solid var(--primary-200, #c7d2fe);
            background: var(--primary-50, #eef2ff);
            color: var(--primary-700, #4338ca);
            cursor: pointer;
            white-space: nowrap;
            transition: background var(--gm-trans);
        }

        .dark .gm-btn-ver {
            border-color: rgba(99, 102, 241, .4);
            background: rgba(99, 102, 241, .12);
            color: #a5b4fc;
        }

        .gm-btn-ver:hover {
            background: var(--primary-100, #e0e7ff);
        }

        .dark .gm-btn-ver:hover {
            background: rgba(99, 102, 241, .22);
        }

        .gm-btn-ver svg {
            width: .875rem;
            height: .875rem;
        }

        /* empty */
        .gm-empty {
            padding: 3.5rem 1rem;
            text-align: center;
            color: #9ca3af;
        }

        .gm-empty svg {
            width: 2.5rem;
            height: 2.5rem;
            margin: 0 auto .75rem;
            opacity: .35;
            display: block;
        }

        .gm-empty p {
            font-size: .875rem;
        }

        /* ─── SlideOver ──────────────────────────────────────────── */
        .gm-backdrop {
            position: fixed;
            inset: 0;
            z-index: 40;
            background: rgba(3, 7, 18, .6);
            backdrop-filter: blur(2px);
        }

        .gm-so {
            position: fixed;
            inset-y: 0;
            right: 0;
            z-index: 50;
            display: flex;
            flex-direction: column;
            width: 100%;
            max-width: 42rem;
            background: #fff;
            box-shadow: -8px 0 40px rgba(0, 0, 0, .18);
        }

        .dark .gm-so {
            background: #111827;
        }

        @media (max-width:480px) {
            .gm-so {
                max-width: 100%;
            }
        }

        /* header so */
        .gm-so-hd {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            flex-shrink: 0;
        }

        .dark .gm-so-hd {
            border-bottom-color: #374151;
        }

        .gm-so-title {
            font-size: .9375rem;
            font-weight: 700;
            color: #111827;
            line-height: 1.3;
            word-break: break-word;
        }

        .dark .gm-so-title {
            color: #f9fafb;
        }

        .gm-so-sub {
            font-size: .72rem;
            color: #9ca3af;
            margin-top: .25rem;
        }

        .gm-so-close {
            flex-shrink: 0;
            padding: .35rem;
            border-radius: .375rem;
            border: none;
            background: none;
            cursor: pointer;
            color: #6b7280;
            transition: background var(--gm-trans), color var(--gm-trans);
        }

        .gm-so-close:hover {
            background: #f3f4f6;
            color: #111827;
        }

        .dark .gm-so-close:hover {
            background: #1f2937;
            color: #f9fafb;
        }

        .gm-so-close svg {
            width: 1.1rem;
            height: 1.1rem;
        }

        /* resumo so */
        .gm-so-resumo {
            padding: 1rem 1.5rem;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            flex-shrink: 0;
        }

        .dark .gm-so-resumo {
            background: rgba(255, 255, 255, .03);
            border-bottom-color: #374151;
        }

        .gm-so-resumo-title {
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #6b7280;
            margin-bottom: .75rem;
        }

        .dark .gm-so-resumo-title {
            color: #9ca3af;
        }

        .gm-so-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: .6rem .75rem;
        }

        @media (min-width:480px) {
            .gm-so-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        .gm-stat-label {
            font-size: .68rem;
            color: #9ca3af;
            line-height: 1;
            margin-bottom: .2rem;
        }

        .dark .gm-stat-label {
            color: #6b7280;
        }

        .gm-stat-val {
            font-size: .875rem;
            font-weight: 700;
            color: #111827;
        }

        .dark .gm-stat-val {
            color: #f9fafb;
        }

        .gm-stat-val--res {
            color: #d97706;
        }

        .gm-stat-val--ok {
            color: #15803d;
        }

        .gm-stat-val--bad {
            color: #dc2626;
        }

        .dark .gm-stat-val--res {
            color: #fcd34d;
        }

        .dark .gm-stat-val--ok {
            color: #86efac;
        }

        .dark .gm-stat-val--bad {
            color: #fca5a5;
        }

        .gm-so-bar-row {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin-top: .75rem;
        }

        .gm-so-bar-track {
            flex: 1;
            height: 8px;
            background: #e5e7eb;
            border-radius: 9999px;
            overflow: hidden;
        }

        .dark .gm-so-bar-track {
            background: #374151;
        }

        .gm-so-bar-fill {
            height: 100%;
            border-radius: 9999px;
            transition: width .5s ease;
        }

        .gm-so-valor {
            font-size: .72rem;
            color: #6b7280;
            margin-top: .5rem;
        }

        .dark .gm-so-valor {
            color: #9ca3af;
        }

        .gm-so-valor strong {
            color: #374151;
            font-weight: 600;
        }

        .dark .gm-so-valor strong {
            color: #d1d5db;
        }

        /* body so */
        .gm-so-body {
            flex: 1;
            overflow-y: auto;
            padding: 1rem 1.5rem;
            display: flex;
            flex-direction: column;
            gap: .75rem;
            -webkit-overflow-scrolling: touch;
        }

        .gm-so-sect-title {
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #6b7280;
            flex-shrink: 0;
        }

        .dark .gm-so-sect-title {
            color: #9ca3af;
        }

        /* card contrato */
        .gm-cc {
            border-radius: var(--gm-radius);
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            padding: 1rem;
        }

        .dark .gm-cc {
            border-color: #374151;
            background: rgba(255, 255, 255, .03);
        }

        .gm-cc-hd {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: .75rem;
            margin-bottom: .75rem;
        }

        .gm-cc-empresa {
            font-size: .8125rem;
            font-weight: 700;
            color: #111827;
            line-height: 1.3;
            word-break: break-word;
        }

        .dark .gm-cc-empresa {
            color: #f9fafb;
        }

        .gm-cc-num {
            font-size: .7rem;
            color: #9ca3af;
            margin-top: .1rem;
        }

        .gm-cc-badges {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: .3rem;
            flex-shrink: 0;
        }

        .gm-badge {
            display: inline-block;
            padding: .15rem .55rem;
            border-radius: 9999px;
            font-size: .68rem;
            font-weight: 600;
        }

        .gm-badge--ok {
            background: #dcfce7;
            color: #15803d;
        }

        .gm-badge--bad {
            background: #fee2e2;
            color: #b91c1c;
        }

        .dark .gm-badge--ok {
            background: rgba(22, 163, 74, .2);
            color: #86efac;
        }

        .dark .gm-badge--bad {
            background: rgba(185, 28, 28, .2);
            color: #fca5a5;
        }

        .gm-cc-venc {
            font-size: .68rem;
            color: #9ca3af;
        }

        .gm-cc-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .4rem .75rem;
            font-size: .75rem;
            margin-bottom: .75rem;
        }

        .gm-cc-lbl {
            color: #9ca3af;
        }

        .dark .gm-cc-lbl {
            color: #6b7280;
        }

        .gm-cc-val {
            font-weight: 600;
            color: #374151;
            text-align: right;
        }

        .dark .gm-cc-val {
            color: #d1d5db;
        }

        .gm-cc-val--res {
            color: #d97706;
        }

        .dark .gm-cc-val--res {
            color: #fcd34d;
        }

        .gm-cc-ft {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
            margin-top: .5rem;
        }

        .gm-cc-saldo-lbl {
            font-size: .72rem;
            color: #6b7280;
        }

        .dark .gm-cc-saldo-lbl {
            color: #9ca3af;
        }

        .gm-cc-saldo-val {
            font-size: .9375rem;
            font-weight: 800;
            letter-spacing: -.01em;
        }

        .gm-cc-vfin {
            font-size: .7rem;
            color: #6b7280;
            white-space: nowrap;
        }

        .dark .gm-cc-vfin {
            color: #9ca3af;
        }

        .gm-cc-vfin strong {
            color: #374151;
            font-weight: 600;
        }

        .dark .gm-cc-vfin strong {
            color: #d1d5db;
        }

        /* footer so */
        .gm-so-ft {
            flex-shrink: 0;
            padding: 1rem 1.5rem;
            border-top: 1px solid #e5e7eb;
        }

        .dark .gm-so-ft {
            border-top-color: #374151;
        }

        .gm-btn-fechar {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            padding: .625rem 1rem;
            border-radius: var(--gm-radius-sm);
            font-size: .8125rem;
            font-weight: 500;
            border: 1px solid #d1d5db;
            background: #f9fafb;
            color: #374151;
            cursor: pointer;
            transition: background var(--gm-trans);
        }

        .dark .gm-btn-fechar {
            border-color: #374151;
            background: #1f2937;
            color: #d1d5db;
        }

        .gm-btn-fechar:hover {
            background: #f3f4f6;
        }

        .dark .gm-btn-fechar:hover {
            background: #374151;
        }

        .gm-btn-fechar svg {
            width: .875rem;
            height: .875rem;
        }

        [x-cloak] {
            display: none !important;
        }


        /* ─── Modal centralizado ────────────────────────────────── */
        .gm-modal-wrap {
            position: fixed;
            inset: 0;
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            pointer-events: none;
            /* deixa o clique passar para o backdrop */
        }

        .gm-modal {
            pointer-events: all;
            display: flex;
            flex-direction: column;
            width: 100%;
            max-width: 40rem;
            /* 640px — equivale ao modal "lg" do Filament */
            max-height: calc(100dvh - 2rem);
            border-radius: var(--gm-radius);
            border: 1px solid #e5e7eb;
            background: #fff;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .18), 0 4px 16px rgba(0, 0, 0, .08);
            overflow: hidden;
        }

        .dark .gm-modal {
            border-color: #374151;
            background: #1f2937;
            /* mesmo fundo do modal Filament no dark */
            box-shadow: 0 20px 60px rgba(0, 0, 0, .5);
        }

        /* ─── Header ──── */
        .gm-modal-hd {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            flex-shrink: 0;
        }

        .dark .gm-modal-hd {
            border-bottom-color: #374151;
        }

        .gm-modal-title {
            font-size: 1rem;
            font-weight: 600;
            color: #111827;
            line-height: 1.4;
            word-break: break-word;
        }

        .dark .gm-modal-title {
            color: #f9fafb;
        }

        .gm-modal-sub {
            font-size: .75rem;
            color: #6b7280;
            margin-top: .2rem;
        }

        .dark .gm-modal-sub {
            color: #9ca3af;
        }

        .gm-modal-close {
            flex-shrink: 0;
            padding: .35rem;
            border-radius: .375rem;
            border: none;
            background: none;
            cursor: pointer;
            color: #6b7280;
            transition: background var(--gm-trans), color var(--gm-trans);
        }

        .gm-modal-close:hover {
            background: #f3f4f6;
            color: #111827;
        }

        .dark .gm-modal-close:hover {
            background: #374151;
            color: #f9fafb;
        }

        .gm-modal-close svg {
            width: 1.1rem;
            height: 1.1rem;
        }

        /* ─── Resumo ──── */
        .gm-modal-resumo {
            padding: 1rem 1.5rem;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            flex-shrink: 0;
        }

        .dark .gm-modal-resumo {
            background: rgba(255, 255, 255, .03);
            border-bottom-color: #374151;
        }

        /* ─── Body scrollável ──── */
        .gm-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 1rem 1.5rem;
            display: flex;
            flex-direction: column;
            gap: .75rem;
            -webkit-overflow-scrolling: touch;
        }

        /* ─── Footer ──── */
        .gm-modal-ft {
            flex-shrink: 0;
            padding: 1rem 1.5rem;
            border-top: 1px solid #e5e7eb;
            background: #f9fafb;
            /* igual ao footer do modal Filament */
        }

        .dark .gm-modal-ft {
            border-top-color: #374151;
            background: rgba(255, 255, 255, .03);
        }


        /* ─── Cabeçalhos ordenáveis ─── */
        .gm-th-sort {
            cursor: pointer;
            user-select: none;
            white-space: nowrap;
        }

        .gm-th-sort:hover {
            color: #374151;
        }

        .dark .gm-th-sort:hover {
            color: #d1d5db;
        }

        .gm-th-sort-icon {
            display: inline-flex;
            flex-direction: column;
            vertical-align: middle;
            margin-left: .25rem;
            gap: 1px;
            opacity: .35;
            transition: opacity 150ms ease;
        }

        .gm-th-sort:hover .gm-th-sort-icon,
        .gm-th-sort--active .gm-th-sort-icon {
            opacity: 1;
        }

        .gm-th-sort-icon svg {
            width: .625rem;
            height: .625rem;
            display: block;
        }

        .gm-th-sort-icon--asc .icon-up {
            color: var(--primary-600, #4f46e5);
        }

        .gm-th-sort-icon--desc .icon-down {
            color: var(--primary-600, #4f46e5);
        }

        .dark .gm-th-sort-icon--asc .icon-up {
            color: var(--primary-400, #818cf8);
        }

        .dark .gm-th-sort-icon--desc .icon-down {
            color: var(--primary-400, #818cf8);
        }
    </style>

    {{-- ================================================================= --}}
    {{-- CARDS DO TOPO                                                      --}}
    {{-- ================================================================= --}}
    <div class="gm-cards">
        @foreach ($this->cards as $card)
        <div class="gm-card gm-card--{{ $card['cor'] }}">
            <div class="gm-card__icon gm-card__icon--{{ $card['cor'] }}">
                <x-filament::icon :icon="$card['icone']" class="w-5 h-5" />
            </div>
            <div style="min-width:0">
                <p class="gm-card__label">{{ $card['titulo'] }}</p>
                <p class="gm-card__value">{{ $card['valor'] }}</p>
                <p class="gm-card__sub">{{ $card['descricao'] }}</p>
            </div>
        </div>
        @endforeach
    </div>

    {{-- ================================================================= --}}
    {{-- PAINEL: ABAS + TABELA                                             --}}
    {{-- ================================================================= --}}
    <div class="gm-panel">

        {{-- Abas --}}
        <div class="gm-tabs" role="tablist">
            @foreach ($this->abas as $aba)
            <button
                wire:click="mudarAba('{{ $aba['value'] }}')"
                class="gm-tab {{ $abaAtiva === $aba['value'] ? 'gm-tab--active' : '' }}"
                role="tab"
                aria-selected="{{ $abaAtiva === $aba['value'] ? 'true' : 'false' }}">
                {{ $aba['label'] }}
            </button>
            @endforeach
        </div>

        {{-- Tabela --}}
        <div class="gm-table-wrap">
            <table class="gm-table">
                <thead>
                    <tr>
                        {{-- helper: ícone de ordenação --}}
                        @php
                        function thIcon(string $col, string $active, string $dir): string {
                        $isActive = $col === $active;
                        $dirClass = $isActive ? 'gm-th-sort-icon--' . $dir : '';
                        return '
                        <span class="gm-th-sort-icon ' . $dirClass . '">
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
                            class="gm-th-sort {{ $sortCol === 'nome' ? 'gm-th-sort--active' : '' }}">
                            Item{!! thIcon('nome', $sortCol, $sortDir) !!}
                        </th>
                        <th class="gm-c-cat">Categoria</th>
                        <th
                            wire:click="sortBy('total_contratado')"
                            class="gm-c-tot r gm-th-sort {{ $sortCol === 'total_contratado' ? 'gm-th-sort--active' : '' }}">
                            Contratado{!! thIcon('total_contratado', $sortCol, $sortDir) !!}
                        </th>
                        <th
                            wire:click="sortBy('total_utilizado')"
                            class="gm-c-tot r gm-th-sort {{ $sortCol === 'total_utilizado' ? 'gm-th-sort--active' : '' }}">
                            Utilizado{!! thIcon('total_utilizado', $sortCol, $sortDir) !!}
                        </th>
                        <th
                            wire:click="sortBy('total_reservado')"
                            class="gm-c-tot r gm-th-sort {{ $sortCol === 'total_reservado' ? 'gm-th-sort--active' : '' }}">
                            Reservado{!! thIcon('total_reservado', $sortCol, $sortDir) !!}
                        </th>
                        <th
                            wire:click="sortBy('saldo_disponivel')"
                            class="r gm-th-sort {{ $sortCol === 'saldo_disponivel' ? 'gm-th-sort--active' : '' }}">
                            Saldo{!! thIcon('saldo_disponivel', $sortCol, $sortDir) !!}
                        </th>
                        <th
                            wire:click="sortBy('percentual')"
                            class="gm-c-bar gm-th-sort {{ $sortCol === 'percentual' ? 'gm-th-sort--active' : '' }}">
                            Margem{!! thIcon('percentual', $sortCol, $sortDir) !!}
                        </th>
                        <th
                            wire:click="sortBy('qtd_contratos')"
                            class="gm-c-contr c gm-th-sort {{ $sortCol === 'qtd_contratos' ? 'gm-th-sort--active' : '' }}">
                            Contr.{!! thIcon('qtd_contratos', $sortCol, $sortDir) !!}
                        </th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->itensFiltrados as $item)
                    @php
                    $fClass = match($item['status']) {
                    'zerado' => 'gm-f-zerado',
                    'critico' => 'gm-f-critico',
                    'baixo' => 'gm-f-baixo',
                    default => 'gm-f-normal',
                    };
                    $sClass = $item['saldo_disponivel'] <= 0 ? 'gm-saldo-bad' : 'gm-saldo-ok' ;
                        @endphp
                        <tr>
                        <td>
                            <p class="gm-item-nome">{{ $item['nome'] }}</p>
                            <p class="gm-item-un">{{ $item['unidade'] }}</p>
                        </td>
                        <td class="gm-c-cat" style="color:#6b7280;font-size:.75rem">
                            {{ $item['tipo_label'] }}
                        </td>
                        <td class="gm-c-tot r" style="color:#6b7280">
                            {{ number_format($item['total_contratado'], 3, ',', '.') }}
                        </td>
                        <td class="gm-c-tot r" style="color:#6b7280">
                            {{ number_format($item['total_utilizado'], 3, ',', '.') }}
                        </td>
                        <td class="gm-c-tot r" style="color:#d97706">
                            {{ number_format($item['total_reservado'], 3, ',', '.') }}
                        </td>
                        <td class="r">
                            <span class="{{ $sClass }}">
                                {{ number_format($item['saldo_disponivel'], 3, ',', '.') }}
                            </span>
                        </td>
                        <td class="gm-c-bar">
                            <div class="gm-bar">
                                <div class="gm-bar-track">
                                    <div class="gm-bar-fill {{ $fClass }}" style="width:{{ min($item['percentual'], 100) }}%"></div>
                                </div>
                                <span class="gm-bar-pct">{{ $item['percentual'] }}%</span>
                            </div>
                        </td>
                        <td class="gm-c-contr c">
                            <span class="gm-nbadge">{{ $item['qtd_contratos'] }}</span>
                        </td>
                        <td class="c">
                            <button wire:click="abrirSlideOver({{ $item['item_id'] }})" class="gm-btn-ver">
                                <x-filament::icon icon="heroicon-o-eye" />
                                <span class="hidden sm:inline">Contratos</span>
                            </button>
                        </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9">
                                <div class="gm-empty">
                                    <x-filament::icon icon="heroicon-o-inbox" />
                                    <p>Nenhum item com saldo disponível nesta categoria.</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ================================================================= --}}
    {{-- MODAL CENTRALIZADO (substitui o slide-over)                       --}}
    {{-- ================================================================= --}}
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
        class="gm-backdrop"
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
        class="gm-modal-wrap"
        role="dialog"
        aria-modal="true">
        <div class="gm-modal">

            {{-- Header --}}
            <div class="gm-modal-hd">
                <div style="min-width:0">
                    <h2 class="gm-modal-title">{{ $itemSelecionadoNome }}</h2>
                    <p class="gm-modal-sub">
                        Unidade: <strong style="text-transform:uppercase">{{ $itemSelecionadoUnidade }}</strong>
                        &middot;
                        {{ count($contratosDoItem) }} {{ count($contratosDoItem) === 1 ? 'contrato ativo' : 'contratos ativos' }}
                    </p>
                </div>
                <button class="gm-modal-close" wire:click="fecharSlideOver" aria-label="Fechar">
                    <x-filament::icon icon="heroicon-o-x-mark" />
                </button>
            </div>

            {{-- Resumo consolidado --}}
            @php
            $tg = collect($contratosDoItem)->sum('quantidade_total');
            $ug = collect($contratosDoItem)->sum('quantidade_utilizada');
            $rg = collect($contratosDoItem)->sum('quantidade_reservada');
            $sg = collect($contratosDoItem)->sum('saldo_disponivel');
            $vg = collect($contratosDoItem)->sum('valor_total_disponivel');
            $pg = $tg > 0 ? round(($sg / $tg) * 100, 1) : 0;
            $bgc = $pg <= 0 ? 'gm-f-zerado' : ($pg <=10 ? 'gm-f-critico' : ($pg <=30 ? 'gm-f-baixo' : 'gm-f-normal' ));
                $sgc=$sg <=0 ? 'gm-stat-val--bad' : 'gm-stat-val--ok' ;
                @endphp

                <div class="gm-modal-resumo">
                <p class="gm-so-resumo-title">Resumo Consolidado</p>
                <div class="gm-so-grid">
                    <div>
                        <p class="gm-stat-label">Total Contratado</p>
                        <p class="gm-stat-val">{{ number_format($tg, 3, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="gm-stat-label">Utilizado</p>
                        <p class="gm-stat-val">{{ number_format($ug, 3, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="gm-stat-label">Reservado</p>
                        <p class="gm-stat-val gm-stat-val--res">{{ number_format($rg, 3, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="gm-stat-label">Saldo Disponível</p>
                        <p class="gm-stat-val {{ $sgc }}">{{ number_format($sg, 3, ',', '.') }}</p>
                    </div>
                </div>
                <div class="gm-so-bar-row">
                    <div class="gm-so-bar-track">
                        <div class="gm-so-bar-fill {{ $bgc }}" style="width:{{ min($pg, 100) }}%"></div>
                    </div>
                    <span class="gm-bar-pct">{{ $pg }}%</span>
                </div>
                <p class="gm-so-valor">
                    Valor financeiro disponível:
                    <strong>R$ {{ number_format($vg, 2, ',', '.') }}</strong>
                </p>
        </div>

        {{-- Body: lista de contratos --}}
        <div class="gm-modal-body">
            <p class="gm-so-sect-title" style="margin-bottom:.5rem">Detalhes por Contrato</p>

            @forelse ($contratosDoItem as $ct)
            @php
            $pct = $ct['percentual'];
            $fc = $pct <= 0 ? 'gm-f-zerado' : ($pct <=10 ? 'gm-f-critico' : ($pct <=30 ? 'gm-f-baixo' : 'gm-f-normal' ));
                $sc=$ct['saldo_disponivel'] <=0 ? 'gm-saldo-bad' : 'gm-saldo-ok' ;
                @endphp

                <div class="gm-cc">
                <div class="gm-cc-hd">
                    <div style="min-width:0">
                        <p class="gm-cc-empresa">{{ $ct['empresa'] }}</p>
                        <p class="gm-cc-num">{{ $ct['numero_contrato'] }}</p>
                    </div>
                    <div class="gm-cc-badges">
                        <span class="gm-badge {{ $ct['vencido'] ? 'gm-badge--bad' : 'gm-badge--ok' }}">
                            {{ $ct['vencido'] ? 'Vencido' : 'Vigente' }}
                        </span>
                        <span class="gm-cc-venc">Venc: {{ $ct['data_vencimento'] }}</span>
                    </div>
                </div>

                <div class="gm-cc-grid">
                    <span class="gm-cc-lbl">Total contratado</span>
                    <span class="gm-cc-val">{{ number_format($ct['quantidade_total'], 3, ',', '.') }}</span>

                    <span class="gm-cc-lbl">Preço unitário</span>
                    <span class="gm-cc-val">R$ {{ number_format($ct['preco_unitario'], 2, ',', '.') }}</span>

                    <span class="gm-cc-lbl">Utilizado</span>
                    <span class="gm-cc-val">{{ number_format($ct['quantidade_utilizada'], 3, ',', '.') }}</span>

                    <span class="gm-cc-lbl">Reservado</span>
                    <span class="gm-cc-val gm-cc-val--res">{{ number_format($ct['quantidade_reservada'], 3, ',', '.') }}</span>
                </div>

                <div class="gm-bar" style="margin-bottom:.6rem">
                    <div class="gm-bar-track" style="height:7px">
                        <div class="gm-bar-fill {{ $fc }}" style="width:{{ min($pct, 100) }}%"></div>
                    </div>
                    <span class="gm-bar-pct">{{ $pct }}%</span>
                </div>

                <div class="gm-cc-ft">
                    <div>
                        <span class="gm-cc-saldo-lbl">Saldo: </span>
                        <span class="gm-cc-saldo-val {{ $sc }}">
                            {{ number_format($ct['saldo_disponivel'], 3, ',', '.') }}
                        </span>
                    </div>
                    <span class="gm-cc-vfin">
                        Valor: <strong>R$ {{ number_format($ct['valor_total_disponivel'], 2, ',', '.') }}</strong>
                    </span>
                </div>
        </div>
        @empty
        <div class="gm-empty">
            <x-filament::icon icon="heroicon-o-inbox" />
            <p>Nenhum contrato ativo para este item.</p>
        </div>
        @endforelse
    </div>

    {{-- Footer --}}
    <div class="gm-modal-ft">
        <button class="gm-btn-fechar" wire:click="fecharSlideOver">
            <x-filament::icon icon="heroicon-o-x-mark" />
            Fechar
        </button>
    </div>

    </div>
    </div>
    @endif
</x-filament-panels::page>