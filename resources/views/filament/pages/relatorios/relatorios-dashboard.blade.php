<x-filament-panels::page>
    <div class="relatorios-root">

        {{-- KPIs LINHA 1: contagens gerais --}}
        <div class="rel-kpi-grid">
            <div class="rel-kpi-card">
                <div class="rel-kpi-icon rel-kpi-icon--blue">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                    </svg>
                </div>
                <div class="rel-kpi-body">
                    <span class="rel-kpi-label">Professores</span>
                    <span class="rel-kpi-value">{{ $this->totalProfessores }}</span>
                </div>
            </div>

            <div class="rel-kpi-card">
                <div class="rel-kpi-icon rel-kpi-icon--amber">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" />
                    </svg>
                </div>
                <div class="rel-kpi-body">
                    <span class="rel-kpi-label">Turmas</span>
                    <span class="rel-kpi-value">{{ $this->totalTurmas }}</span>
                </div>
            </div>

            <div class="rel-kpi-card">
                <div class="rel-kpi-icon rel-kpi-icon--green">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                    </svg>
                </div>
                <div class="rel-kpi-body">
                    <span class="rel-kpi-label">Componentes</span>
                    <span class="rel-kpi-value">{{ $this->totalComponentes }}</span>
                </div>
            </div>

            <div class="rel-kpi-card">
                <div class="rel-kpi-icon rel-kpi-icon--purple">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                    </svg>
                </div>
                <div class="rel-kpi-body">
                    <span class="rel-kpi-label">Escolas</span>
                    <span class="rel-kpi-value">{{ $this->totalEscolas }}</span>
                </div>
            </div>
        </div>

        {{-- KPIs LINHA 2: vínculos --}}
        <div class="rel-kpi-grid rel-kpi-grid--3 mb-rel">
            <div class="rel-kpi-card rel-kpi-card--highlight">
                <div class="rel-kpi-icon rel-kpi-icon--slate">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                    </svg>
                </div>
                <div class="rel-kpi-body">
                    <span class="rel-kpi-label">Total de vínculos</span>
                    <span class="rel-kpi-value">{{ $this->vinculos }}</span>
                    <span class="rel-kpi-sub">turma × componente</span>
                </div>
            </div>

            <div class="rel-kpi-card rel-kpi-card--success">
                <div class="rel-kpi-icon rel-kpi-icon--green">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <div class="rel-kpi-body">
                    <span class="rel-kpi-label">Com professor</span>
                    <span class="rel-kpi-value rel-kpi-value--green">{{ $this->comProfessor }}</span>
                    @if($this->vinculos > 0)
                    <span class="rel-kpi-sub">{{ round(($this->comProfessor / $this->vinculos) * 100) }}% dos vínculos</span>
                    @endif
                </div>
            </div>

            <div class="rel-kpi-card rel-kpi-card--danger">
                <div class="rel-kpi-icon rel-kpi-icon--rose">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                </div>
                <div class="rel-kpi-body">
                    <span class="rel-kpi-label">Sem professor</span>
                    <span class="rel-kpi-value rel-kpi-value--red">{{ $this->semProfessor }}</span>
                    @if($this->vinculos > 0)
                    <span class="rel-kpi-sub">{{ round(($this->semProfessor / $this->vinculos) * 100) }}% dos vínculos</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- TABELA TOP COMPONENTES --}}
        @if(count($this->topComponentes) > 0)
        <div class="rel-table-card mb-rel">
            <div class="rel-table-header">
                <div>
                    <h3 class="rel-table-title">Componentes com maior falta de docentes</h3>
                    <p class="rel-table-sub">Top 5 componentes curriculares com mais turmas sem professor</p>
                </div>
            </div>
            <div class="rel-table-wrap">
                <table class="rel-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Componente</th>
                            <th>Total</th>
                            <th>Com professor</th>
                            <th>Sem professor</th>
                            <th>Cobertura</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->topComponentes as $i => $comp)
                        @php $pct = $comp->total > 0 ? round(($comp->com_professor / $comp->total) * 100) : 0; @endphp
                        <tr>
                            <td class="rel-table-rank">{{ $i + 1 }}</td>
                            <td class="rel-table-name">{{ $comp->nome }}</td>
                            <td><span class="rel-badge rel-badge--slate">{{ $comp->total }}</span></td>
                            <td><span class="rel-badge rel-badge--green">{{ $comp->com_professor }}</span></td>
                            <td>
                                @if($comp->sem_professor > 0)
                                <span class="rel-badge rel-badge--red">{{ $comp->sem_professor }}</span>
                                @else
                                <span class="rel-badge rel-badge--green">0</span>
                                @endif
                            </td>
                            <td>
                                <div class="rel-progress-wrap">
                                    <div class="rel-progress">
                                        <div class="rel-progress-bar {{ $pct == 100 ? 'rel-progress-bar--full' : ($pct >= 50 ? 'rel-progress-bar--mid' : 'rel-progress-bar--low') }}"
                                            style="width: {{ $pct }}%"></div>
                                    </div>
                                    <span class="rel-progress-label">{{ $pct }}%</span>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

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
                        <div style="display:flex;gap:0.4rem;flex-wrap:wrap">
                            <span class="rel-card-tag rel-card-tag--blue">Exportável</span>
                            @if($this->semProfessor > 0)
                            <span class="rel-card-tag rel-card-tag--red">{{ $this->semProfessor }} sem professor</span>
                            @endif
                        </div>
                        <span class="rel-card-arrow">→</span>
                    </div>
                </a>
                @endcan

                @can('Listar Relatórios: Componentes com Professores Faltando')
                <a href="{{ route('filament.admin.pages.relatorio-componentes-com-professores-faltando') }}" class="rel-card rel-card--amber">
                    <div class="rel-card-header">
                        <div class="rel-card-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.007M10.29 3.86 1.82 18a2.25 2.25 0 0 0 1.94 3.375h16.48A2.25 2.25 0 0 0 22.18 18L13.71 3.86a2.25 2.25 0 0 0-3.42 0Z" />
                            </svg>
                        </div>
                        <span class="rel-card-badge">Análise</span>
                    </div>

                    <div class="rel-card-body">
                        <h3>Componentes com falta de docentes</h3>
                        <p>
                            Identifique os componentes curriculares com maior déficit de professores vinculados,
                            permitindo priorizar alocações e correções.
                        </p>
                    </div>

                    <div class="rel-card-footer">
                        <div style="display:flex;gap:0.4rem;flex-wrap:wrap">
                            <span class="rel-card-tag rel-card-tag--red">Crítico</span>
                        </div>
                        <span class="rel-card-arrow">→</span>
                    </div>
                </a>
                @endcan
            </div>
        </div>

    </div>

    <style>
        .relatorios-root *,
        .relatorios-root *::before,
        .relatorios-root *::after {
            box-sizing: border-box;
        }

        .mb-rel {
            margin-bottom: 1.75rem;
        }

        /* ── HERO ─────────────────────────────── */
        .rel-hero {
            position: relative;
            overflow: hidden;
            border-radius: 1.25rem;
            padding: 3rem 2.5rem 2.75rem;
            margin-bottom: 1.75rem;
            background: linear-gradient(135deg, #0c1e3e 0%, #0f2d5e 50%, #0a1f45 100%);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .rel-hero-grid {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 40px 40px;
            mask-image: radial-gradient(ellipse at center, black 40%, transparent 80%);
        }

        .rel-hero-glow {
            position: absolute;
            top: -80px;
            right: -60px;
            width: 380px;
            height: 380px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(51, 102, 214, .28) 0%, transparent 70%);
            pointer-events: none;
        }

        .rel-hero-inner {
            position: relative;
            z-index: 1;
            max-width: 580px;
        }

        .rel-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            color: #86efac;
            font-family: 'Courier New', monospace;
            font-size: .72rem;
            font-weight: 600;
            letter-spacing: .12em;
            text-transform: uppercase;
            margin-bottom: 1.1rem;
        }

        .pulse-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #22c55e;
            flex-shrink: 0;
            animation: pulse-green 2s ease-in-out infinite;
        }

        @keyframes pulse-green {

            0%,
            100% {
                box-shadow: 0 0 0 0 rgba(34, 197, 94, .5);
            }

            50% {
                box-shadow: 0 0 0 6px rgba(34, 197, 94, 0);
            }
        }

        .rel-title {
            font-size: clamp(1.85rem, 4vw, 2.75rem);
            font-weight: 700;
            color: #f0f6ff;
            line-height: 1.15;
            margin: 0 0 .875rem;
            letter-spacing: -.02em;
        }

        .rel-title-accent {
            background: linear-gradient(90deg, #60a5fa, #93c5fd, #bfdbfe);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .rel-subtitle {
            font-size: .95rem;
            color: rgba(255, 255, 255, .52);
            line-height: 1.65;
            margin: 0;
            font-family: system-ui, sans-serif;
        }

        /* ── KPIs ─────────────────────────────── */
        .rel-kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: .875rem;
            margin-bottom: 1rem;
        }

        .rel-kpi-grid--3 {
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        }

        .rel-kpi-card {
            display: flex;
            align-items: center;
            gap: .875rem;
            background: white;
            border: 1.5px solid #e2e8f0;
            border-radius: .875rem;
            padding: 1rem 1.25rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .06);
            transition: box-shadow .18s, transform .18s;
        }

        .rel-kpi-card:hover {
            box-shadow: 0 6px 18px rgba(0, 0, 0, .09);
            transform: translateY(-2px);
        }

        .dark .rel-kpi-card {
            background: rgb(17 24 39);
            border-color: rgba(255, 255, 255, .08);
        }

        .rel-kpi-card--success {
            border-color: #bbf7d0;
        }

        .dark .rel-kpi-card--success {
            border-color: rgba(22, 163, 74, .3);
        }

        .rel-kpi-card--danger {
            border-color: #fecaca;
        }

        .dark .rel-kpi-card--danger {
            border-color: rgba(220, 38, 38, .3);
        }

        .rel-kpi-icon {
            width: 42px;
            height: 42px;
            border-radius: .6rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .rel-kpi-icon svg {
            width: 20px;
            height: 20px;
        }

        .rel-kpi-icon--blue {
            background: #dbeafe;
            color: #2563eb;
        }

        .rel-kpi-icon--amber {
            background: #fef3c7;
            color: #d97706;
        }

        .rel-kpi-icon--green {
            background: #dcfce7;
            color: #16a34a;
        }

        .rel-kpi-icon--purple {
            background: #ede9fe;
            color: #7c3aed;
        }

        .rel-kpi-icon--slate {
            background: #e2e8f0;
            color: #475569;
        }

        .rel-kpi-icon--rose {
            background: #ffe4e6;
            color: #e11d48;
        }

        .dark .rel-kpi-icon--blue {
            background: rgba(37, 99, 235, .18);
            color: #93c5fd;
        }

        .dark .rel-kpi-icon--amber {
            background: rgba(217, 119, 6, .18);
            color: #fcd34d;
        }

        .dark .rel-kpi-icon--green {
            background: rgba(22, 163, 74, .18);
            color: #86efac;
        }

        .dark .rel-kpi-icon--purple {
            background: rgba(124, 58, 237, .18);
            color: #c4b5fd;
        }

        .dark .rel-kpi-icon--slate {
            background: rgba(71, 85, 105, .18);
            color: #94a3b8;
        }

        .dark .rel-kpi-icon--rose {
            background: rgba(225, 29, 72, .18);
            color: #fda4af;
        }

        .rel-kpi-body {
            display: flex;
            flex-direction: column;
            gap: .1rem;
        }

        .rel-kpi-label {
            font-size: .72rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: .06em;
            font-family: system-ui, sans-serif;
        }

        .dark .rel-kpi-label {
            color: #94a3b8;
        }

        .rel-kpi-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1;
            font-family: system-ui, sans-serif;
        }

        .rel-kpi-value--green {
            color: #16a34a;
        }

        .rel-kpi-value--red {
            color: #dc2626;
        }

        .dark .rel-kpi-value {
            color: #f1f5f9;
        }

        .dark .rel-kpi-value--green {
            color: #86efac;
        }

        .dark .rel-kpi-value--red {
            color: #fca5a5;
        }

        .rel-kpi-sub {
            font-size: .7rem;
            color: #94a3b8;
            font-family: system-ui, sans-serif;
            margin-top: .1rem;
        }

        /* ── TABELA TOP COMPONENTES ───────────── */
        .rel-table-card {
            background: white;
            border: 1.5px solid #e2e8f0;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 1px 4px rgba(0, 0, 0, .06);
        }

        .dark .rel-table-card {
            background: rgb(17 24 39);
            border-color: rgba(255, 255, 255, .08);
        }

        .rel-table-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #f1f5f9;
        }

        .dark .rel-table-header {
            border-bottom-color: rgba(255, 255, 255, .06);
        }

        .rel-table-title {
            font-family: system-ui, sans-serif;
            font-size: .95rem;
            font-weight: 700;
            color: #111827;
            margin: 0 0 .2rem;
        }

        .dark .rel-table-title {
            color: #f3f4f6;
        }

        .rel-table-sub {
            font-family: system-ui, sans-serif;
            font-size: .78rem;
            color: #6b7280;
            margin: 0;
        }

        .rel-table-wrap {
            overflow-x: auto;
        }

        .rel-table {
            width: 100%;
            border-collapse: collapse;
            font-family: system-ui, sans-serif;
        }

        .rel-table thead th {
            background: #f8fafc;
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: #64748b;
            padding: .625rem 1rem;
            text-align: left;
            white-space: nowrap;
            border-bottom: 1px solid #e2e8f0;
        }

        .dark .rel-table thead th {
            background: rgba(255, 255, 255, .03);
            color: #94a3b8;
            border-bottom-color: rgba(255, 255, 255, .06);
        }

        .rel-table tbody tr {
            transition: background .15s;
        }

        .rel-table tbody tr:hover {
            background: #f8fafc;
        }

        .dark .rel-table tbody tr:hover {
            background: rgba(255, 255, 255, .03);
        }

        .rel-table tbody td {
            padding: .75rem 1rem;
            font-size: .85rem;
            color: #374151;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }

        .rel-table tbody tr:last-child td {
            border-bottom: none;
        }

        .dark .rel-table tbody td {
            color: #d1d5db;
            border-bottom-color: rgba(255, 255, 255, .04);
        }

        .rel-table-rank {
            font-weight: 700;
            color: #9ca3af !important;
            font-size: .8rem !important;
            width: 2rem;
        }

        .rel-table-name {
            font-weight: 600 !important;
            color: #111827 !important;
        }

        .dark .rel-table-name {
            color: #f3f4f6 !important;
        }

        /* badges da tabela */
        .rel-badge {
            display: inline-block;
            font-size: .7rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 9999px;
        }

        .rel-badge--slate {
            background: #e2e8f0;
            color: #475569;
        }

        .rel-badge--green {
            background: #dcfce7;
            color: #16a34a;
        }

        .rel-badge--red {
            background: #fee2e2;
            color: #dc2626;
        }

        .dark .rel-badge--slate {
            background: rgba(71, 85, 105, .25);
            color: #94a3b8;
        }

        .dark .rel-badge--green {
            background: rgba(22, 163, 74, .18);
            color: #86efac;
        }

        .dark .rel-badge--red {
            background: rgba(220, 38, 38, .18);
            color: #fca5a5;
        }

        /* barra de progresso */
        .rel-progress-wrap {
            display: flex;
            align-items: center;
            gap: .5rem;
            min-width: 120px;
        }

        .rel-progress {
            flex: 1;
            height: 6px;
            border-radius: 9999px;
            background: #e2e8f0;
            overflow: hidden;
        }

        .dark .rel-progress {
            background: rgba(255, 255, 255, .08);
        }

        .rel-progress-bar {
            height: 100%;
            border-radius: 9999px;
            transition: width .4s ease;
        }

        .rel-progress-bar--full {
            background: #22c55e;
        }

        .rel-progress-bar--mid {
            background: #f59e0b;
        }

        .rel-progress-bar--low {
            background: #ef4444;
        }

        .rel-progress-label {
            font-size: .72rem;
            font-weight: 600;
            color: #64748b;
            white-space: nowrap;
            font-family: 'Courier New', monospace;
        }

        .dark .rel-progress-label {
            color: #94a3b8;
        }

        /* ── SECTION / GRID DE CARDS ──────────── */
        .rel-section {
            animation: fadeUp .45s ease-out .1s both;
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(14px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .rel-section-label {
            font-family: 'Courier New', monospace;
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .15em;
            text-transform: uppercase;
            color: #6b7280;
            margin: 0 0 1rem .25rem;
        }

        .dark .rel-section-label {
            color: #4b5563;
        }

        .rel-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
        }

        .rel-card {
            display: flex;
            flex-direction: column;
            background: white;
            border: 1.5px solid transparent;
            border-radius: 1rem;
            text-decoration: none;
            overflow: hidden;
            box-shadow: 0 1px 4px rgba(0, 0, 0, .07);
            transition: transform .18s, box-shadow .18s, border-color .18s;
        }

        .rel-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 28px rgba(0, 0, 0, .11);
        }

        .dark .rel-card {
            background: rgb(17 24 39);
            box-shadow: 0 1px 4px rgba(0, 0, 0, .3);
        }

        .rel-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.1rem 1.25rem .75rem;
        }

        .rel-card-icon {
            width: 44px;
            height: 44px;
            border-radius: .7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .rel-card-icon svg {
            width: 22px;
            height: 22px;
        }

        .rel-card-badge {
            font-size: .65rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            padding: 3px 9px;
            border-radius: 9999px;
            border: 1.5px solid currentColor;
            opacity: .65;
            font-family: system-ui, sans-serif;
        }

        .rel-card-body {
            padding: 0 1.25rem 1rem;
            flex: 1;
        }

        .rel-card-body h3 {
            font-family: system-ui, sans-serif;
            font-size: .95rem;
            font-weight: 700;
            color: #111827;
            margin: 0 0 .35rem;
            line-height: 1.3;
        }

        .dark .rel-card-body h3 {
            color: #f3f4f6;
        }

        .rel-card-body p {
            font-family: system-ui, sans-serif;
            font-size: .8rem;
            color: #6b7280;
            margin: 0;
            line-height: 1.55;
        }

        .dark .rel-card-body p {
            color: #9ca3af;
        }

        .rel-card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: .75rem 1.25rem;
            border-top: 1px solid #f1f5f9;
            margin-top: auto;
        }

        .dark .rel-card-footer {
            border-top-color: rgba(255, 255, 255, .06);
        }

        .rel-card-tag {
            font-size: .68rem;
            font-weight: 600;
            padding: 2px 9px;
            border-radius: 9999px;
            font-family: system-ui, sans-serif;
        }

        .rel-card-tag--blue {
            background: #dbeafe;
            color: #2563eb;
        }

        .rel-card-tag--red {
            background: #fee2e2;
            color: #dc2626;
        }

        .dark .rel-card-tag--blue {
            background: rgba(37, 99, 235, .18);
            color: #93c5fd;
        }

        .dark .rel-card-tag--red {
            background: rgba(220, 38, 38, .18);
            color: #fca5a5;
        }

        .rel-card-arrow {
            font-size: 1rem;
            opacity: 0;
            transform: translateX(-4px);
            transition: opacity .18s, transform .18s;
        }

        .rel-card:hover .rel-card-arrow {
            opacity: 1;
            transform: translateX(0);
        }

        .rel-card--blue {
            --c: #2563eb;
            --ic: #dbeafe;
        }

        .rel-card--green {
            --c: #16a34a;
            --ic: #dcfce7;
        }

        .rel-card--amber {
            --c: #d97706;
            --ic: #fef3c7;
        }

        .rel-card--purple {
            --c: #7c3aed;
            --ic: #ede9fe;
        }

        .rel-card:hover {
            border-color: var(--c);
        }

        .rel-card-icon {
            background: var(--ic);
            color: var(--c);
        }

        .rel-card-badge {
            color: var(--c);
        }

        .rel-card-arrow {
            color: var(--c);
        }

        .dark .rel-card--blue {
            --ic: rgba(37, 99, 235, .18);
        }

        .dark .rel-card--green {
            --ic: rgba(22, 163, 74, .18);
        }

        .dark .rel-card--amber {
            --ic: rgba(217, 119, 6, .18);
        }

        .dark .rel-card--purple {
            --ic: rgba(124, 58, 237, .18);
        }

        /* ── RESPONSIVE ───────────────────────── */
        @media (max-width: 640px) {
            .rel-hero {
                padding: 2.25rem 1.5rem 2rem;
            }

            .rel-kpi-grid,
            .rel-kpi-grid--3 {
                grid-template-columns: repeat(2, 1fr);
            }

            .rel-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</x-filament-panels::page>
