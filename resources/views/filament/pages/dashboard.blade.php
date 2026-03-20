<x-filament-panels::page>

    <div class="welcome-root">

        {{-- HERO --}}
        <div class="hero">
            <div class="hero-bg-grid"></div>
            <div class="hero-bg-glow"></div>

            <div class="hero-inner">
                <div class="hero-eyebrow">
                    <span class="pulse-dot"></span>
                    Sistema Ativo
                </div>

                <h1 class="hero-title">
                    Bem-vindo ao<br>
                    <span class="hero-title-accent">Gestão Edu</span>
                </h1>

                <p class="hero-subtitle">
                    Central de gestão escolar. Acesse rapidamente os módulos do sistema abaixo.
                </p>
            </div>
        </div>

        {{-- NAVIGATION CARDS --}}
        <div class="nav-section">
            <p class="nav-label">Acesso Rápido</p>

            <div class="nav-grid">

                @can('Listar Pedidos')
                <a href="{{ route('filament.admin.resources.pedidos.index') }}" class="nav-card nav-card--blue">
                    <div class="nav-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0 1 18 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3 1.5 1.5 3-3.75" />
                        </svg>
                    </div>
                    <div class="nav-card-body">
                        <h3>Pedidos</h3>
                        <p>Gerencie solicitações de manutenção</p>
                    </div>
                    <div class="nav-card-arrow">→</div>
                </a>
                @endcan

                @can('Listar Escolas')
                <a href="{{ route('filament.admin.resources.escolas.index') }}" class="nav-card nav-card--teal">
                    <div class="nav-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                        </svg>
                    </div>
                    <div class="nav-card-body">
                        <h3>Escolas</h3>
                        <p>Cadastro de unidades escolares</p>
                    </div>
                    <div class="nav-card-arrow">→</div>
                </a>
                @endcan

                @can('Visualizar Feedback de Pedidos')
                <a href="{{ route('filament.admin.pages.feedback-pedidos') }}" class="nav-card nav-card--rose">
                    <div class="nav-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                        </svg>
                    </div>
                    <div class="nav-card-body">
                        <h3>Feedbacks</h3>
                        <p>Avaliações dos pedidos concluídos</p>
                    </div>
                    <div class="nav-card-arrow">→</div>
                </a>
                @endcan

                @can('Listar Usuários')
                <a href="{{ route('filament.admin.resources.usuarios.index') }}" class="nav-card nav-card--slate">
                    <div class="nav-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                    </div>
                    <div class="nav-card-body">
                        <h3>Usuários</h3>
                        <p>Controle de acesso e permissões</p>
                    </div>
                    <div class="nav-card-arrow">→</div>
                </a>
                @endcan

                @can('Listar Empresa Contratada')
                <a href="{{ route('filament.admin.resources.empresas-contratadas.index') }}" class="nav-card nav-card--orange">
                    <div class="nav-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z" />
                        </svg>
                    </div>
                    <div class="nav-card-body">
                        <h3>Empresas</h3>
                        <p>Empresas contratadas e parceiros</p>
                    </div>
                    <div class="nav-card-arrow">→</div>
                </a>
                @endcan

                @can('Listar Níveis de Acesso')
                <a href="{{ route('filament.admin.resources.niveis-de-acesso.index') }}" class="nav-card nav-card--purple">
                    <div class="nav-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                        </svg>
                    </div>
                    <div class="nav-card-body">
                        <h3>Níveis de Acesso</h3>
                        <p>Roles e permissões do sistema</p>
                    </div>
                    <div class="nav-card-arrow">→</div>
                </a>
                @endcan

                @can('Listar Dominios de Email')
                <a href="{{ route('filament.admin.resources.dominio-emails.index') }}" class="nav-card nav-card--amber">
                    <div class="nav-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                        </svg>
                    </div>
                    <div class="nav-card-body">
                        <h3>Domínios de E-mail</h3>
                        <p>Domínios permitidos para acesso</p>
                    </div>
                    <div class="nav-card-arrow">→</div>
                </a>
                @endcan

                {{-- ════════════════════════════════════════════════════
                     EM DESENVOLVIMENTO — descomentar quando prontos
                     ════════════════════════════════════════════════════ --}}

                {{--
                @can('Listar Alunos')
                <a href="{{ route('filament.admin.grupo-alunos.resources.alunos.index') }}" class="nav-card nav-card--green">
                    <div class="nav-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 3.741-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" />
                        </svg>
                    </div>
                    <div class="nav-card-body">
                        <h3>Alunos</h3>
                        <p>Cadastro e acompanhamento escolar</p>
                    </div>
                    <div class="nav-card-arrow">→</div>
                </a>
                @endcan

                @can('Listar Turmas')
                <a href="{{ route('filament.admin.resources.turmas.index') }}" class="nav-card nav-card--amber">
                    <div class="nav-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                        </svg>
                    </div>
                    <div class="nav-card-body">
                        <h3>Turmas</h3>
                        <p>Organização de turmas e séries</p>
                    </div>
                    <div class="nav-card-arrow">→</div>
                </a>
                @endcan

                @can('Listar Professores')
                <a href="{{ route('filament.admin.resources.professores.index') }}" class="nav-card nav-card--purple">
                    <div class="nav-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>
                    </div>
                    <div class="nav-card-body">
                        <h3>Professores</h3>
                        <p>Gestão do corpo docente</p>
                    </div>
                    <div class="nav-card-arrow">→</div>
                </a>
                @endcan
                --}}

            </div>
        </div>

    </div>

    <style>
        /* ─── RESET LOCAL ─────────────────────────────────────── */
        .welcome-root *,
        .welcome-root *::before,
        .welcome-root *::after {
            box-sizing: border-box;
        }

        .welcome-root {
            font-family: 'Georgia', 'Times New Roman', serif;
        }

        /* ─── HERO ────────────────────────────────────────────── */
        .hero {
            position: relative;
            overflow: hidden;
            border-radius: 1.25rem;
            padding: 3.5rem 2.5rem 3rem;
            margin-bottom: 2rem;
            background: linear-gradient(135deg, #0c1e3e 0%, #0f2d5e 50%, #0a1f45 100%);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .hero-bg-grid {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 40px 40px;
            mask-image: radial-gradient(ellipse at center, black 40%, transparent 80%);
        }

        .hero-bg-glow {
            position: absolute;
            top: -80px;
            right: -60px;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(14, 99, 196, 0.25) 0%, transparent 70%);
            pointer-events: none;
        }

        .hero-inner {
            position: relative;
            z-index: 1;
            max-width: 640px;
        }

        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #86efac;
            font-family: 'Courier New', monospace;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            margin-bottom: 1.25rem;
        }

        .pulse-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #22c55e;
            animation: pulse-green 2s ease-in-out infinite;
            flex-shrink: 0;
        }

        @keyframes pulse-green {
            0%, 100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.5); }
            50%       { box-shadow: 0 0 0 6px rgba(34, 197, 94, 0); }
        }

        .hero-title {
            font-size: clamp(2rem, 5vw, 3rem);
            font-weight: 700;
            color: #f0f6ff;
            line-height: 1.15;
            margin: 0 0 1rem;
            letter-spacing: -0.02em;
        }

        .hero-title-accent {
            background: linear-gradient(90deg, #60a5fa, #93c5fd, #bfdbfe);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-subtitle {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.55);
            line-height: 1.65;
            margin: 0;
            font-family: system-ui, sans-serif;
            font-weight: 400;
        }

        /* ─── NAV SECTION ─────────────────────────────────────── */
        .nav-section {
            animation: fadeUp 0.5s ease-out 0.15s both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .nav-label {
            font-family: 'Courier New', monospace;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: #6b7280;
            margin: 0 0 1rem 0.25rem;
        }

        .dark .nav-label { color: #4b5563; }

        .nav-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 0.875rem;
        }

        /* ─── NAV CARD ────────────────────────────────────────── */
        .nav-card {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.1rem 1.25rem;
            border-radius: 0.875rem;
            text-decoration: none;
            border: 1.5px solid transparent;
            background: white;
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.07), 0 1px 2px rgba(0, 0, 0, 0.04);
        }

        .dark .nav-card {
            background: rgb(17 24 39);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
        }

        .nav-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.1);
        }

        .nav-card-icon {
            flex-shrink: 0;
            width: 42px;
            height: 42px;
            border-radius: 0.625rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .nav-card-icon svg {
            width: 20px;
            height: 20px;
        }

        .nav-card-body {
            flex: 1;
            min-width: 0;
        }

        .nav-card-body h3 {
            font-family: system-ui, sans-serif;
            font-size: 0.925rem;
            font-weight: 600;
            color: #111827;
            margin: 0 0 0.2rem;
            line-height: 1.3;
        }

        .dark .nav-card-body h3 { color: #f3f4f6; }

        .nav-card-body p {
            font-family: system-ui, sans-serif;
            font-size: 0.78rem;
            color: #6b7280;
            margin: 0;
            line-height: 1.4;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dark .nav-card-body p { color: #9ca3af; }

        .nav-card-arrow {
            font-size: 1rem;
            opacity: 0;
            transform: translateX(-4px);
            transition: opacity 0.18s ease, transform 0.18s ease;
            flex-shrink: 0;
        }

        .nav-card:hover .nav-card-arrow {
            opacity: 1;
            transform: translateX(0);
        }

        /* ─── COLOR VARIANTS ──────────────────────────────────── */
        .nav-card--blue   { --c: #2563eb; --ic: #dbeafe; }
        .nav-card--green  { --c: #16a34a; --ic: #dcfce7; }
        .nav-card--amber  { --c: #d97706; --ic: #fef3c7; }
        .nav-card--purple { --c: #7c3aed; --ic: #ede9fe; }
        .nav-card--teal   { --c: #0d9488; --ic: #ccfbf1; }
        .nav-card--rose   { --c: #e11d48; --ic: #ffe4e6; }
        .nav-card--slate  { --c: #475569; --ic: #e2e8f0; }
        .nav-card--orange { --c: #ea580c; --ic: #fed7aa; }

        .nav-card:hover   { border-color: var(--c); }
        .nav-card-icon    { background: var(--ic); color: var(--c); }
        .nav-card-arrow   { color: var(--c); }

        .dark .nav-card--blue   { --ic: rgba(37, 99, 235, 0.18); }
        .dark .nav-card--green  { --ic: rgba(22, 163, 74, 0.18); }
        .dark .nav-card--amber  { --ic: rgba(217, 119, 6, 0.18); }
        .dark .nav-card--purple { --ic: rgba(124, 58, 237, 0.18); }
        .dark .nav-card--teal   { --ic: rgba(13, 148, 136, 0.18); }
        .dark .nav-card--rose   { --ic: rgba(225, 29, 72, 0.18); }
        .dark .nav-card--slate  { --ic: rgba(71, 85, 105, 0.18); }
        .dark .nav-card--orange { --ic: rgba(234, 88, 12, 0.18); }

        /* ─── RESPONSIVE ──────────────────────────────────────── */
        @media (max-width: 640px) {
            .hero { padding: 2.5rem 1.5rem 2rem; }
            .nav-grid { grid-template-columns: 1fr; }
        }
    </style>

</x-filament-panels::page>