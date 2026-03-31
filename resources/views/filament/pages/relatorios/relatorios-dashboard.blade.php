<x-filament-panels::page>
<div class="relatorios-root">

    {{-- HERO --}}
    <div class="rel-hero">
        <div class="rel-hero-grid"></div>
        <div class="rel-hero-glow"></div>
        <div class="rel-hero-inner">
            <div class="rel-eyebrow">
                <span class="pulse-dot"></span>
                Dados atualizados em tempo real
            </div>
            <h1 class="rel-title">
                Central de<br>
                <span class="rel-title-accent">Relatórios</span>
            </h1>
            <p class="rel-subtitle">
                Visualize, filtre e exporte os dados do sistema. Selecione um relatório abaixo para começar.
            </p>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="rel-kpi-grid">
        @can('Listar Relatórios: Professor por Componente e Turma')
        <div class="rel-kpi-card">
            <div class="rel-kpi-icon rel-kpi-icon--blue">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                </svg>
            </div>
            <div class="rel-kpi-body">
                <span class="rel-kpi-label">Professores vinculados</span>
                <span class="rel-kpi-value">—</span>
            </div>
        </div>
        @endcan

        <div class="rel-kpi-card">
            <div class="rel-kpi-icon rel-kpi-icon--amber">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" />
                </svg>
            </div>
            <div class="rel-kpi-body">
                <span class="rel-kpi-label">Turmas cadastradas</span>
                <span class="rel-kpi-value">—</span>
            </div>
        </div>

        <div class="rel-kpi-card">
            <div class="rel-kpi-icon rel-kpi-icon--green">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                </svg>
            </div>
            <div class="rel-kpi-body">
                <span class="rel-kpi-label">Componentes curriculares</span>
                <span class="rel-kpi-value">—</span>
            </div>
        </div>

        <div class="rel-kpi-card">
            <div class="rel-kpi-icon rel-kpi-icon--purple">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                </svg>
            </div>
            <div class="rel-kpi-body">
                <span class="rel-kpi-label">Escolas ativas</span>
                <span class="rel-kpi-value">—</span>
            </div>
        </div>
    </div>

    {{-- RELATÓRIOS --}}
    <div class="rel-section">
        <p class="rel-section-label">Relatórios disponíveis</p>

        <div class="rel-grid">

            @can('Listar Relatórios: Professor por Componente e Turma')
            <a href="{{ route('filament.admin.pages.relatorio-professor-componente-turma') }}" class="rel-card rel-card--blue">
                <div class="rel-card-header">
                    <div class="rel-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>
                    </div>
                    <span class="rel-card-badge">Tabela</span>
                </div>
                <div class="rel-card-body">
                    <h3>Professor por Componente e Turma</h3>
                    <p>Visualize quais professores estão vinculados a cada componente curricular por turma. Permite filtros por escola, série e turno.</p>
                </div>
                <div class="rel-card-footer">
                    <span class="rel-card-tag rel-card-tag--blue">Exportável</span>
                    <span class="rel-card-arrow">→</span>
                </div>
            </a>
            @endcan

            {{-- Placeholder para futuros relatórios --}}
            {{-- @can('Listar Relatórios: Frequência')
            <a href="#" class="rel-card rel-card--green">
                ...
            </a>
            @endcan --}}

        </div>
    </div>

</div>

<style>
    .relatorios-root *,
    .relatorios-root *::before,
    .relatorios-root *::after { box-sizing: border-box; }

    /* ── HERO ─────────────────────────────── */
    .rel-hero {
        position: relative;
        overflow: hidden;
        border-radius: 1.25rem;
        padding: 3rem 2.5rem 2.75rem;
        margin-bottom: 1.75rem;
        background: linear-gradient(135deg, #0c1e3e 0%, #0f2d5e 50%, #0a1f45 100%);
        border: 1px solid rgba(255,255,255,0.08);
    }
    .rel-hero-grid {
        position: absolute; inset: 0;
        background-image:
            linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
        background-size: 40px 40px;
        mask-image: radial-gradient(ellipse at center, black 40%, transparent 80%);
    }
    .rel-hero-glow {
        position: absolute; top: -80px; right: -60px;
        width: 380px; height: 380px; border-radius: 50%;
        background: radial-gradient(circle, rgba(51,102,214,0.28) 0%, transparent 70%);
        pointer-events: none;
    }
    .rel-hero-inner { position: relative; z-index: 1; max-width: 580px; }

    .rel-eyebrow {
        display: inline-flex; align-items: center; gap: 0.5rem;
        color: #86efac;
        font-family: 'Courier New', monospace;
        font-size: 0.72rem; font-weight: 600;
        letter-spacing: 0.12em; text-transform: uppercase;
        margin-bottom: 1.1rem;
    }
    .pulse-dot {
        width: 7px; height: 7px; border-radius: 50%;
        background: #22c55e; flex-shrink: 0;
        animation: pulse-green 2s ease-in-out infinite;
    }
    @keyframes pulse-green {
        0%,100% { box-shadow: 0 0 0 0 rgba(34,197,94,.5); }
        50%      { box-shadow: 0 0 0 6px rgba(34,197,94,0); }
    }
    .rel-title {
        font-size: clamp(1.85rem, 4vw, 2.75rem);
        font-weight: 700; color: #f0f6ff;
        line-height: 1.15; margin: 0 0 0.875rem;
        letter-spacing: -0.02em;
    }
    .rel-title-accent {
        background: linear-gradient(90deg, #60a5fa, #93c5fd, #bfdbfe);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    .rel-subtitle {
        font-size: 0.95rem; color: rgba(255,255,255,0.52);
        line-height: 1.65; margin: 0;
        font-family: system-ui, sans-serif; font-weight: 400;
    }

    /* ── KPIs ─────────────────────────────── */
    .rel-kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 0.875rem;
        margin-bottom: 2rem;
    }
    .rel-kpi-card {
        display: flex; align-items: center; gap: 0.875rem;
        background: white;
        border: 1.5px solid #e2e8f0;
        border-radius: 0.875rem;
        padding: 1rem 1.25rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        transition: box-shadow 0.18s, transform 0.18s;
    }
    .rel-kpi-card:hover {
        box-shadow: 0 6px 18px rgba(0,0,0,0.09);
        transform: translateY(-2px);
    }
    .dark .rel-kpi-card {
        background: rgb(17 24 39);
        border-color: rgba(255,255,255,0.08);
    }
    .rel-kpi-icon {
        width: 42px; height: 42px; border-radius: 0.6rem;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    .rel-kpi-icon svg { width: 20px; height: 20px; }
    .rel-kpi-icon--blue   { background: #dbeafe; color: #2563eb; }
    .rel-kpi-icon--amber  { background: #fef3c7; color: #d97706; }
    .rel-kpi-icon--green  { background: #dcfce7; color: #16a34a; }
    .rel-kpi-icon--purple { background: #ede9fe; color: #7c3aed; }
    .dark .rel-kpi-icon--blue   { background: rgba(37,99,235,.18);   color: #93c5fd; }
    .dark .rel-kpi-icon--amber  { background: rgba(217,119,6,.18);   color: #fcd34d; }
    .dark .rel-kpi-icon--green  { background: rgba(22,163,74,.18);   color: #86efac; }
    .dark .rel-kpi-icon--purple { background: rgba(124,58,237,.18);  color: #c4b5fd; }
    .rel-kpi-body { display: flex; flex-direction: column; gap: 0.15rem; }
    .rel-kpi-label {
        font-size: 0.72rem; font-weight: 600;
        color: #64748b; text-transform: uppercase; letter-spacing: 0.06em;
        font-family: system-ui, sans-serif;
    }
    .dark .rel-kpi-label { color: #94a3b8; }
    .rel-kpi-value {
        font-size: 1.4rem; font-weight: 700;
        color: #0f172a; line-height: 1;
        font-family: system-ui, sans-serif;
    }
    .dark .rel-kpi-value { color: #f1f5f9; }

    /* ── SECTION ──────────────────────────── */
    .rel-section { animation: fadeUp .45s ease-out .1s both; }
    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(14px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .rel-section-label {
        font-family: 'Courier New', monospace;
        font-size: 0.68rem; font-weight: 700;
        letter-spacing: 0.15em; text-transform: uppercase;
        color: #6b7280; margin: 0 0 1rem 0.25rem;
    }
    .dark .rel-section-label { color: #4b5563; }

    /* ── GRID DE CARDS ────────────────────── */
    .rel-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1rem;
    }

    /* ── CARD DE RELATÓRIO ────────────────── */
    .rel-card {
        display: flex; flex-direction: column; gap: 0;
        background: white;
        border: 1.5px solid transparent;
        border-radius: 1rem;
        text-decoration: none;
        overflow: hidden;
        box-shadow: 0 1px 4px rgba(0,0,0,0.07);
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }
    .rel-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 28px rgba(0,0,0,0.11);
    }
    .dark .rel-card {
        background: rgb(17 24 39);
        box-shadow: 0 1px 4px rgba(0,0,0,0.3);
    }

    .rel-card-header {
        display: flex; align-items: center;
        justify-content: space-between;
        padding: 1.1rem 1.25rem 0.75rem;
    }
    .rel-card-icon {
        width: 44px; height: 44px; border-radius: 0.7rem;
        display: flex; align-items: center; justify-content: center;
    }
    .rel-card-icon svg { width: 22px; height: 22px; }
    .rel-card-badge {
        font-size: 0.65rem; font-weight: 700;
        letter-spacing: 0.08em; text-transform: uppercase;
        padding: 3px 9px; border-radius: 9999px;
        border: 1.5px solid currentColor;
        opacity: 0.65;
        font-family: system-ui, sans-serif;
    }

    .rel-card-body {
        padding: 0 1.25rem 1rem; flex: 1;
    }
    .rel-card-body h3 {
        font-family: system-ui, sans-serif;
        font-size: 0.95rem; font-weight: 700;
        color: #111827; margin: 0 0 0.35rem; line-height: 1.3;
    }
    .dark .rel-card-body h3 { color: #f3f4f6; }
    .rel-card-body p {
        font-family: system-ui, sans-serif;
        font-size: 0.8rem; color: #6b7280;
        margin: 0; line-height: 1.55;
    }
    .dark .rel-card-body p { color: #9ca3af; }

    .rel-card-footer {
        display: flex; align-items: center;
        justify-content: space-between;
        padding: 0.75rem 1.25rem;
        border-top: 1px solid #f1f5f9;
        margin-top: auto;
    }
    .dark .rel-card-footer { border-top-color: rgba(255,255,255,0.06); }

    .rel-card-tag {
        font-size: 0.68rem; font-weight: 600;
        padding: 2px 9px; border-radius: 9999px;
        font-family: system-ui, sans-serif;
    }
    .rel-card-tag--blue   { background: #dbeafe; color: #2563eb; }
    .rel-card-tag--green  { background: #dcfce7; color: #16a34a; }
    .rel-card-tag--amber  { background: #fef3c7; color: #d97706; }
    .rel-card-tag--purple { background: #ede9fe; color: #7c3aed; }
    .dark .rel-card-tag--blue   { background: rgba(37,99,235,.18);  color: #93c5fd; }
    .dark .rel-card-tag--green  { background: rgba(22,163,74,.18);  color: #86efac; }
    .dark .rel-card-tag--amber  { background: rgba(217,119,6,.18);  color: #fcd34d; }
    .dark .rel-card-tag--purple { background: rgba(124,58,237,.18); color: #c4b5fd; }

    .rel-card-arrow {
        font-size: 1rem;
        opacity: 0; transform: translateX(-4px);
        transition: opacity .18s, transform .18s;
    }
    .rel-card:hover .rel-card-arrow { opacity: 1; transform: translateX(0); }

    /* ── VARIANTES DE COR DOS CARDS ───────── */
    .rel-card--blue  { --c: #2563eb; --ic: #dbeafe; }
    .rel-card--green { --c: #16a34a; --ic: #dcfce7; }
    .rel-card--amber { --c: #d97706; --ic: #fef3c7; }
    .rel-card--purple{ --c: #7c3aed; --ic: #ede9fe; }
    .rel-card--teal  { --c: #0d9488; --ic: #ccfbf1; }
    .rel-card--rose  { --c: #e11d48; --ic: #ffe4e6; }

    .rel-card:hover        { border-color: var(--c); }
    .rel-card-icon         { background: var(--ic); color: var(--c); }
    .rel-card-badge        { color: var(--c); }
    .rel-card-arrow        { color: var(--c); }

    .dark .rel-card--blue  { --ic: rgba(37,99,235,.18);  }
    .dark .rel-card--green { --ic: rgba(22,163,74,.18);  }
    .dark .rel-card--amber { --ic: rgba(217,119,6,.18);  }
    .dark .rel-card--purple{ --ic: rgba(124,58,237,.18); }
    .dark .rel-card--teal  { --ic: rgba(13,148,136,.18); }
    .dark .rel-card--rose  { --ic: rgba(225,29,72,.18);  }

    /* ── RESPONSIVE ───────────────────────── */
    @media (max-width: 640px) {
        .rel-hero { padding: 2.25rem 1.5rem 2rem; }
        .rel-kpi-grid { grid-template-columns: repeat(2, 1fr); }
        .rel-grid { grid-template-columns: 1fr; }
    }
</style>
</x-filament-panels::page>