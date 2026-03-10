<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão Edu | Sistema de Gestão Escolar — Umuarama</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --blue-deep: #033A7A;
            --blue-mid: #074F9B;
            --blue-light: #1A6FD1;
            --blue-pale: #EAF3FF;
            --accent: #F5A623;
            --text-dark: #0B1728;
            --text-mid: #3A5068;
            --text-light: #7A9BB5;
            --white: #FFFFFF;
            --surface: #F7FAFE;
            --border: #D6E6F7;
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--white);
            color: var(--text-dark);
            overflow-x: hidden;
        }

        h1, h2, h3, h4 { font-family: 'Sora', sans-serif; }

        /* ── NAV ── */
        nav {
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
            background: rgba(3, 58, 122, 0.97);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,0.08);
            padding: 0 40px;
            height: 64px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .nav-brand { display: flex; align-items: center; gap: 12px; }
        .nav-brand img { height: 36px; opacity: 0.95; }
        .nav-title { font-family: 'Sora', sans-serif; color: #fff; font-size: 1rem; font-weight: 600; letter-spacing: 0.02em; }
        .nav-links { display: flex; gap: 32px; list-style: none; }
        .nav-links a { color: rgba(255,255,255,0.75); text-decoration: none; font-size: 0.9rem; transition: color 0.2s; }
        .nav-links a:hover { color: #fff; }
        .nav-cta {
            background: var(--accent); color: var(--blue-deep);
            padding: 8px 22px; border-radius: 6px;
            font-family: 'Sora', sans-serif; font-weight: 700; font-size: 0.88rem;
            text-decoration: none; transition: opacity 0.2s;
        }
        .nav-cta:hover { opacity: 0.88; }

        /* ── HERO ── */
        .hero {
            min-height: 100vh;
            background: linear-gradient(160deg, var(--blue-deep) 0%, var(--blue-mid) 55%, #0d6abf 100%);
            display: flex; align-items: center; justify-content: center;
            text-align: center;
            padding: 120px 24px 80px;
            position: relative; overflow: hidden;
        }
        .hero-grid {
            position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.04) 1px, transparent 1px);
            background-size: 60px 60px;
        }
        .hero-glow {
            position: absolute;
            width: 700px; height: 700px;
            background: radial-gradient(circle, rgba(26,111,209,0.35) 0%, transparent 70%);
            top: 50%; left: 50%; transform: translate(-50%, -55%);
            pointer-events: none;
        }
        .hero-content { position: relative; z-index: 2; max-width: 860px; }
        .hero-badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            padding: 6px 16px; border-radius: 100px;
            color: rgba(255,255,255,0.9); font-size: 0.82rem; font-family: 'Sora', sans-serif;
            margin-bottom: 28px;
            animation: fadeUp 0.7s ease both;
        }
        .hero-badge span { width: 6px; height: 6px; background: var(--accent); border-radius: 50%; }
        .hero h1 {
            font-size: clamp(2.4rem, 5vw, 4rem);
            font-weight: 800; color: #fff;
            line-height: 1.15; margin-bottom: 24px;
            animation: fadeUp 0.7s ease 0.1s both;
        }
        .hero h1 em { font-style: normal; color: var(--accent); }
        .hero p {
            font-size: clamp(1rem, 2vw, 1.2rem);
            color: rgba(255,255,255,0.8); line-height: 1.7;
            max-width: 640px; margin: 0 auto 40px;
            animation: fadeUp 0.7s ease 0.2s both;
        }
        .hero-actions {
            display: flex; gap: 16px; justify-content: center; flex-wrap: wrap;
            animation: fadeUp 0.7s ease 0.3s both;
        }
        .btn-primary {
            background: var(--accent); color: var(--blue-deep);
            padding: 14px 36px; border-radius: 8px;
            font-family: 'Sora', sans-serif; font-weight: 700; font-size: 1rem;
            text-decoration: none; transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 20px rgba(245,166,35,0.4);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(245,166,35,0.5); }
        .btn-ghost {
            background: transparent; color: #fff;
            padding: 14px 36px; border-radius: 8px;
            font-family: 'Sora', sans-serif; font-weight: 600; font-size: 1rem;
            text-decoration: none; border: 1.5px solid rgba(255,255,255,0.35);
            transition: background 0.2s, border-color 0.2s;
        }
        .btn-ghost:hover { background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.6); }
        .hero-scroll {
            position: absolute; bottom: 36px; left: 50%; transform: translateX(-50%);
            color: rgba(255,255,255,0.45); font-size: 0.8rem;
            display: flex; flex-direction: column; align-items: center; gap: 8px;
            animation: fadeUp 1s ease 0.8s both;
        }
        .scroll-arrow { width: 20px; height: 20px; border-right: 2px solid rgba(255,255,255,0.4); border-bottom: 2px solid rgba(255,255,255,0.4); transform: rotate(45deg); animation: bounce 1.6s infinite; }

        /* ── STATS BAR ── */
        .stats-bar {
            background: var(--blue-deep);
            padding: 40px 24px;
        }
        .stats-inner {
            max-width: 900px; margin: auto;
            display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 0; text-align: center;
        }
        .stat-item {
            padding: 20px 24px;
            border-right: 1px solid rgba(255,255,255,0.1);
        }
        .stat-item:last-child { border-right: none; }
        .stat-item h3 { font-size: 2.2rem; font-weight: 800; color: var(--accent); font-family: 'Sora', sans-serif; }
        .stat-item p { color: rgba(255,255,255,0.65); font-size: 0.88rem; margin-top: 4px; }

        /* ── SECTION BASE ── */
        .section { padding: 96px 24px; }
        .section-header { text-align: center; margin-bottom: 64px; }
        .section-label {
            display: inline-block;
            font-family: 'Sora', sans-serif; font-weight: 600; font-size: 0.75rem;
            letter-spacing: 0.12em; text-transform: uppercase;
            color: var(--blue-mid); margin-bottom: 12px;
        }
        .section-header h2 {
            font-size: clamp(1.8rem, 3vw, 2.6rem); font-weight: 800;
            color: var(--text-dark); line-height: 1.25; margin-bottom: 16px;
        }
        .section-header p { color: var(--text-mid); font-size: 1.05rem; max-width: 560px; margin: auto; line-height: 1.65; }

        /* ── PROBLEM SECTION ── */
        .problem { background: var(--surface); }
        .problem-grid {
            max-width: 1000px; margin: auto;
            display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
        }
        .problem-card {
            background: #fff; border: 1px solid var(--border);
            border-radius: 16px; padding: 32px 28px;
            position: relative; overflow: hidden;
        }
        .problem-card::before {
            content: '';
            position: absolute; top: 0; left: 0; width: 4px; height: 100%;
            background: linear-gradient(180deg, #e74c3c, #e67e22);
        }
        .problem-icon { font-size: 1.8rem; margin-bottom: 16px; }
        .problem-card h3 { font-size: 1.05rem; font-weight: 700; color: var(--text-dark); margin-bottom: 10px; }
        .problem-card p { color: var(--text-mid); font-size: 0.93rem; line-height: 1.6; }

        /* ── MODULES ── */
        .modules { background: var(--white); }
        .modules-wrapper { max-width: 1160px; margin: auto; }
        .module-block {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 64px; align-items: center;
            padding: 72px 0;
            border-bottom: 1px solid var(--border);
        }
        .module-block:last-child { border-bottom: none; }
        .module-block.reverse { direction: rtl; }
        .module-block.reverse > * { direction: ltr; }

        .module-visual {
            background: var(--blue-pale);
            border-radius: 20px; overflow: hidden;
            aspect-ratio: 4/3;
            display: flex; flex-direction: column;
            position: relative;
        }
        .module-visual-header {
            background: var(--blue-mid);
            padding: 12px 16px;
            display: flex; align-items: center; gap: 8px;
        }
        .dot { width: 10px; height: 10px; border-radius: 50%; }
        .dot.r { background: #ff5f57; }
        .dot.y { background: #ffbd2e; }
        .dot.g { background: #28c840; }
        .module-visual-body { flex: 1; padding: 20px; display: flex; flex-direction: column; gap: 10px; }
        .ui-row {
            background: rgba(7,79,155,0.08);
            border-radius: 8px; height: 14px;
        }
        .ui-row.w30 { width: 30%; }
        .ui-row.w60 { width: 60%; }
        .ui-row.w80 { width: 80%; }
        .ui-row.w100 { width: 100%; }
        .ui-row.h30 { height: 30px; }
        .ui-row.accent { background: rgba(7,79,155,0.18); }
        .ui-card-row { display: flex; gap: 10px; }
        .ui-card {
            flex: 1; background: rgba(7,79,155,0.1);
            border-radius: 8px; padding: 10px;
            display: flex; flex-direction: column; gap: 6px;
        }
        .ui-card-num { font-family: 'Sora', sans-serif; font-weight: 800; color: var(--blue-mid); font-size: 1.3rem; }
        .ui-card-lbl { font-size: 0.65rem; color: var(--text-mid); }
        .ui-badge { display: inline-block; padding: 3px 8px; border-radius: 100px; font-size: 0.65rem; font-weight: 600; }
        .ui-badge.green { background: #d1fae5; color: #065f46; }
        .ui-badge.blue { background: #dbeafe; color: #1e40af; }
        .ui-badge.yellow { background: #fef3c7; color: #92400e; }
        .ui-table-row { display: flex; gap: 8px; align-items: center; padding: 6px 0; border-bottom: 1px solid rgba(7,79,155,0.06); }
        .ui-avatar { width: 24px; height: 24px; border-radius: 50%; background: linear-gradient(135deg, var(--blue-mid), var(--blue-light)); flex-shrink: 0; }

        .module-text {}
        .module-number {
            font-family: 'Sora', sans-serif; font-size: 0.75rem; font-weight: 700;
            letter-spacing: 0.12em; text-transform: uppercase;
            color: var(--blue-light); margin-bottom: 12px;
        }
        .module-icon-wrap {
            width: 56px; height: 56px; border-radius: 14px;
            background: var(--blue-pale); display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem; margin-bottom: 20px;
        }
        .module-text h2 {
            font-size: clamp(1.5rem, 2.5vw, 2rem); font-weight: 800;
            color: var(--text-dark); margin-bottom: 16px; line-height: 1.25;
        }
        .module-text p { color: var(--text-mid); line-height: 1.7; margin-bottom: 24px; font-size: 1rem; }
        .feature-list { list-style: none; display: flex; flex-direction: column; gap: 10px; }
        .feature-list li {
            display: flex; align-items: flex-start; gap: 10px;
            color: var(--text-mid); font-size: 0.93rem;
        }
        .feature-list li::before {
            content: '✓';
            flex-shrink: 0;
            width: 20px; height: 20px; border-radius: 50%;
            background: var(--blue-pale); color: var(--blue-mid);
            font-size: 0.75rem; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            margin-top: 1px;
        }

        /* ── HOW IT WORKS ── */
        .how { background: var(--surface); }
        .steps { max-width: 860px; margin: auto; display: flex; flex-direction: column; gap: 0; }
        .step {
            display: grid; grid-template-columns: 64px 1fr;
            gap: 28px; padding: 40px 0;
            border-bottom: 1px solid var(--border);
        }
        .step:last-child { border-bottom: none; }
        .step-num {
            width: 48px; height: 48px; border-radius: 50%;
            background: var(--blue-mid); color: #fff;
            font-family: 'Sora', sans-serif; font-weight: 800; font-size: 1.1rem;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .step-text h3 { font-size: 1.15rem; font-weight: 700; color: var(--text-dark); margin-bottom: 8px; }
        .step-text p { color: var(--text-mid); line-height: 1.65; font-size: 0.95rem; }

        /* ── CTA FINAL ── */
        .cta-final {
            background: linear-gradient(135deg, var(--blue-deep) 0%, var(--blue-mid) 100%);
            padding: 100px 24px; text-align: center; position: relative; overflow: hidden;
        }
        .cta-final::before {
            content: '';
            position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 48px 48px;
        }
        .cta-final-content { position: relative; z-index: 1; max-width: 680px; margin: auto; }
        .cta-final h2 {
            font-size: clamp(1.8rem, 3vw, 2.6rem); font-weight: 800;
            color: #fff; margin-bottom: 20px;
        }
        .cta-final p { color: rgba(255,255,255,0.78); font-size: 1.05rem; line-height: 1.7; margin-bottom: 36px; }

        /* ── FOOTER ── */
        footer {
            background: #021e42;
            padding: 56px 24px 36px;
        }
        .footer-inner {
            max-width: 1100px; margin: auto;
            display: grid; grid-template-columns: 1.5fr 1fr 1fr;
            gap: 48px; padding-bottom: 40px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .footer-brand img { height: 48px; opacity: 0.85; margin-bottom: 16px; display: block; }
        .footer-brand p { color: rgba(255,255,255,0.5); font-size: 0.88rem; line-height: 1.65; }
        .footer-col h4 { color: rgba(255,255,255,0.9); font-family: 'Sora', sans-serif; font-size: 0.9rem; font-weight: 700; margin-bottom: 16px; }
        .footer-col ul { list-style: none; display: flex; flex-direction: column; gap: 10px; }
        .footer-col ul li a { color: rgba(255,255,255,0.5); text-decoration: none; font-size: 0.88rem; transition: color 0.2s; }
        .footer-col ul li a:hover { color: rgba(255,255,255,0.9); }
        .footer-bottom {
            max-width: 1100px; margin: 28px auto 0;
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;
        }
        .footer-bottom p { color: rgba(255,255,255,0.35); font-size: 0.82rem; }
        .footer-logos { display: flex; align-items: center; gap: 24px; }
        .footer-logos img { height: 36px; opacity: 0.5; transition: opacity 0.2s; }
        .footer-logos img:hover { opacity: 0.8; }

        /* ── ANIMATIONS ── */
        @keyframes fadeUp { from { opacity: 0; transform: translateY(24px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes bounce { 0%, 100% { transform: rotate(45deg) translateY(0); } 50% { transform: rotate(45deg) translateY(6px); } }

        .reveal { opacity: 0; transform: translateY(32px); transition: opacity 0.65s ease, transform 0.65s ease; }
        .reveal.visible { opacity: 1; transform: translateY(0); }

        /* ── RESPONSIVE ── */
        @media(max-width: 900px) {
            .module-block { grid-template-columns: 1fr; gap: 36px; }
            .module-block.reverse { direction: ltr; }
            .footer-inner { grid-template-columns: 1fr 1fr; }
            nav .nav-links { display: none; }
        }
        @media(max-width: 600px) {
            .footer-inner { grid-template-columns: 1fr; }
            .stat-item { border-right: none; border-bottom: 1px solid rgba(255,255,255,0.1); padding: 20px 0; }
            .stat-item:last-child { border-bottom: none; }
            .footer-bottom { justify-content: center; text-align: center; }
        }
    </style>
</head>
<body>

<!-- NAV -->
<nav>
    <div class="nav-brand">
        <img src="/images/brasao-umuarama.png" alt="Brasão de Umuarama">
        <span class="nav-title">Gestão Edu</span>
    </div>
    <ul class="nav-links">
        <li><a href="#modulos">Módulos</a></li>
        <li><a href="#como-funciona">Como Funciona</a></li>
        <li><a href="#problemas">Desafios</a></li>
    </ul>
    <a href="/login" class="nav-cta">Acessar o Sistema</a>
</nav>

<!-- HERO -->
<section class="hero">
    <div class="hero-grid"></div>
    <div class="hero-glow"></div>
    <div class="hero-content">
        <div class="hero-badge"><span></span> Prefeitura Municipal de Umuarama</div>
        <h1>A gestão da educação municipal, <em>totalmente integrada</em></h1>
        <p>
            Gestão Edu centraliza pedagogia, transporte, alimentação, patrimônio e recursos humanos
            em uma única plataforma. Menos papel, mais decisão.
        </p>
        <div class="hero-actions">
            <a href="/login" class="btn-primary">Acessar o Sistema</a>
            <a href="#modulos" class="btn-ghost">Conhecer os módulos</a>
        </div>
    </div>
    <div class="hero-scroll">
        <span>Role para explorar</span>
        <div class="scroll-arrow"></div>
    </div>
</section>

<!-- STATS BAR -->
<div class="stats-bar">
    <div class="stats-inner">
        <div class="stat-item"><h3>5</h3><p>Módulos Integrados</p></div>
        <div class="stat-item"><h3>100%</h3><p>Baseado em Nuvem</p></div>
        <div class="stat-item"><h3>LGPD</h3><p>Dados Protegidos</p></div>
        <div class="stat-item"><h3>24/7</h3><p>Disponibilidade</p></div>
    </div>
</div>

<!-- PROBLEMAS -->
<section id="problemas" class="section problem">
    <div class="section-header reveal">
        <span class="section-label">O Diagnóstico</span>
        <h2>Os desafios de uma gestão fragmentada</h2>
        <p>Sem um sistema integrado, cada setor opera com sua própria lógica — gerando retrabalho, perda de informação e decisões baseadas em dados incompletos.</p>
    </div>
    <div class="problem-grid">
        <div class="problem-card reveal">
            <div class="problem-icon">📂</div>
            <h3>Dados espalhados em planilhas e papel</h3>
            <p>Informações de alunos, funcionários e patrimônio dispersas em arquivos Excel, pastas físicas e e-mails sem rastreabilidade.</p>
        </div>
        <div class="problem-card reveal">
            <div class="problem-icon">⏳</div>
            <h3>Processos manuais e lentos</h3>
            <p>Emissão de declarações, controle de férias e elaboração de cardápios feitos à mão consomem horas que poderiam ser usadas na gestão.</p>
        </div>
        <div class="problem-card reveal">
            <div class="problem-icon">🔗</div>
            <h3>Setores que não se comunicam</h3>
            <p>Transporte não sabe quais alunos estão matriculados. Alimentação não sabe quantas crianças haverá na semana. Cada área opera isolada.</p>
        </div>
        <div class="problem-card reveal">
            <div class="problem-icon">📉</div>
            <h3>Decisões sem embasamento de dados</h3>
            <p>Sem relatórios consolidados em tempo real, gestores tomam decisões baseadas em estimativas — e não em fatos precisos.</p>
        </div>
    </div>
</section>

<!-- MÓDULOS -->
<section id="modulos" class="section modules">
    <div class="section-header reveal">
        <span class="section-label">Funcionalidades</span>
        <h2>Cinco módulos. Uma visão completa.</h2>
        <p>Cada módulo resolve um domínio específico da secretaria, e todos compartilham a mesma base de dados — garantindo consistência e integração real.</p>
    </div>

    <div class="modules-wrapper">

        <!-- PEDAGÓGICO -->
        <div class="module-block reveal">
            <div class="module-visual">
                <div class="module-visual-header">
                    <div class="dot r"></div><div class="dot y"></div><div class="dot g"></div>
                    <span style="color:rgba(255,255,255,0.6);font-size:0.75rem;margin-left:8px;font-family:'Sora',sans-serif;">Módulo Pedagógico</span>
                </div>
                <div class="module-visual-body">
                    <div class="ui-card-row">
                        <div class="ui-card"><div class="ui-card-num">1.243</div><div class="ui-card-lbl">Alunos Ativos</div></div>
                        <div class="ui-card"><div class="ui-card-num">87%</div><div class="ui-card-lbl">Frequência Média</div></div>
                        <div class="ui-card"><div class="ui-card-num">42</div><div class="ui-card-lbl">Turmas</div></div>
                    </div>
                    <div class="ui-row w100 h30 accent" style="border-radius:8px;"></div>
                    <div style="display:flex;flex-direction:column;gap:6px;">
                        <div class="ui-table-row"><div class="ui-avatar"></div><div class="ui-row w60" style="flex:1;height:10px;"></div><div class="ui-badge green">Presente</div></div>
                        <div class="ui-table-row"><div class="ui-avatar"></div><div class="ui-row w60" style="flex:1;height:10px;"></div><div class="ui-badge yellow">Falta</div></div>
                        <div class="ui-table-row"><div class="ui-avatar"></div><div class="ui-row w60" style="flex:1;height:10px;"></div><div class="ui-badge green">Presente</div></div>
                        <div class="ui-table-row"><div class="ui-avatar"></div><div class="ui-row w60" style="flex:1;height:10px;"></div><div class="ui-badge blue">Avaliação</div></div>
                    </div>
                </div>
            </div>
            <div class="module-text">
                <div class="module-number">Módulo 01</div>
                <div class="module-icon-wrap">📋</div>
                <h2>Pedagógico</h2>
                <p>Gestão completa do ciclo escolar — da matrícula ao histórico acadêmico — com visibilidade em tempo real para professores, coordenadores e a secretaria.</p>
                <ul class="feature-list">
                    <li>Gestão de turmas, matrículas e transferências</li>
                    <li>Controle de frequência diária por aluno e turma</li>
                    <li>Registro e acompanhamento de provas e avaliações diagnósticas</li>
                    <li>Planos de intervenção pedagógica individualizados</li>
                    <li>Relatórios de desempenho e histórico evolutivo</li>
                    <li>Alertas automáticos para baixa frequência e rendimento</li>
                </ul>
            </div>
        </div>

        <!-- TRANSPORTE -->
        <div class="module-block reverse reveal">
            <div class="module-visual">
                <div class="module-visual-header">
                    <div class="dot r"></div><div class="dot y"></div><div class="dot g"></div>
                    <span style="color:rgba(255,255,255,0.6);font-size:0.75rem;margin-left:8px;font-family:'Sora',sans-serif;">Módulo Transporte</span>
                </div>
                <div class="module-visual-body">
                    <div class="ui-card-row">
                        <div class="ui-card"><div class="ui-card-num">18</div><div class="ui-card-lbl">Rotas Ativas</div></div>
                        <div class="ui-card"><div class="ui-card-num">412</div><div class="ui-card-lbl">Alunos</div></div>
                    </div>
                    <div style="background:rgba(7,79,155,0.08);border-radius:10px;padding:12px;display:flex;flex-direction:column;gap:8px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <span style="font-size:0.7rem;color:var(--text-mid);font-weight:600;">ROTA 04 — Zona Rural Norte</span>
                            <div class="ui-badge green">Em rota</div>
                        </div>
                        <div class="ui-row w100" style="height:8px;background:rgba(7,79,155,0.12);border-radius:4px;overflow:hidden;">
                            <div style="width:65%;height:100%;background:linear-gradient(90deg,var(--blue-mid),var(--blue-light));border-radius:4px;"></div>
                        </div>
                        <div style="display:flex;justify-content:space-between;">
                            <span style="font-size:0.65rem;color:var(--text-light);">32/38 alunos embarcados</span>
                            <span style="font-size:0.65rem;color:var(--text-light);">Prev. chegada: 07h42</span>
                        </div>
                    </div>
                    <div class="ui-row w100 h30 accent" style="border-radius:8px;"></div>
                    <div style="display:flex;gap:8px;">
                        <div style="flex:1;background:rgba(7,79,155,0.06);border-radius:8px;padding:10px;font-size:0.68rem;color:var(--text-mid);">🪪 Emissão de Carteirinha</div>
                        <div style="flex:1;background:rgba(7,79,155,0.06);border-radius:8px;padding:10px;font-size:0.68rem;color:var(--text-mid);">📋 Critérios de Elegibilidade</div>
                    </div>
                </div>
            </div>
            <div class="module-text">
                <div class="module-number">Módulo 02</div>
                <div class="module-icon-wrap">🚌</div>
                <h2>Transporte Escolar</h2>
                <p>Controle preciso das rotas, veículos e elegibilidade — eliminando o uso irregular do transporte e otimizando a logística de toda a rede municipal.</p>
                <ul class="feature-list">
                    <li>Cadastro e gestão de rotas com horários e pontos de parada</li>
                    <li>Controle de elegibilidade por distância e zona de residência</li>
                    <li>Emissão digital de carteirinhas de transporte</li>
                    <li>Monitoramento de ocupação por rota e veículo</li>
                    <li>Histórico de uso por aluno</li>
                    <li>Alertas de lotação e irregularidades</li>
                </ul>
            </div>
        </div>

        <!-- ALIMENTAÇÃO -->
        <div class="module-block reveal">
            <div class="module-visual">
                <div class="module-visual-header">
                    <div class="dot r"></div><div class="dot y"></div><div class="dot g"></div>
                    <span style="color:rgba(255,255,255,0.6);font-size:0.75rem;margin-left:8px;font-family:'Sora',sans-serif;">Módulo Alimentação</span>
                </div>
                <div class="module-visual-body">
                    <div class="ui-card-row">
                        <div class="ui-card"><div class="ui-card-num">5</div><div class="ui-card-lbl">Dias de Cardápio</div></div>
                        <div class="ui-card"><div class="ui-card-num">847</div><div class="ui-card-lbl">Porções Hoje</div></div>
                    </div>
                    <div style="background:rgba(7,79,155,0.06);border-radius:10px;padding:12px;">
                        <div style="font-size:0.7rem;font-weight:600;color:var(--blue-mid);margin-bottom:8px;">ESTOQUE — COBERTURA ESTIMADA</div>
                        <div style="display:flex;flex-direction:column;gap:6px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <span style="font-size:0.68rem;color:var(--text-mid);">Arroz Integral (50kg)</span>
                                <div class="ui-badge green">18 dias</div>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <span style="font-size:0.68rem;color:var(--text-mid);">Feijão Preto (30kg)</span>
                                <div class="ui-badge yellow">6 dias</div>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <span style="font-size:0.68rem;color:var(--text-mid);">Frango (25kg)</span>
                                <div class="ui-badge green">12 dias</div>
                            </div>
                        </div>
                    </div>
                    <div style="background:rgba(7,79,155,0.06);border-radius:10px;padding:10px;font-size:0.68rem;color:var(--text-mid);">
                        🥦 Macronutrientes: Proteínas 28g · Carboidratos 64g · Gorduras 12g
                    </div>
                </div>
            </div>
            <div class="module-text">
                <div class="module-number">Módulo 03</div>
                <div class="module-icon-wrap">🍽️</div>
                <h2>Alimentação Escolar</h2>
                <p>Do planejamento nutricional ao controle de estoque — o sistema calcula automaticamente a cobertura do estoque com base no cardápio e no número de alunos previstos.</p>
                <ul class="feature-list">
                    <li>Elaboração semanal e mensal de cardápios</li>
                    <li>Controle de estoque de insumos com entradas e saídas</li>
                    <li>Cálculo automático de cobertura do estoque pelo cardápio</li>
                    <li>Monitoramento de macronutrientes por refeição</li>
                    <li>Alertas de insumos abaixo do estoque mínimo</li>
                    <li>Relatórios nutricionais para auditoria e prestação de contas</li>
                </ul>
            </div>
        </div>

        <!-- ADMINISTRATIVO -->
        <div class="module-block reverse reveal">
            <div class="module-visual">
                <div class="module-visual-header">
                    <div class="dot r"></div><div class="dot y"></div><div class="dot g"></div>
                    <span style="color:rgba(255,255,255,0.6);font-size:0.75rem;margin-left:8px;font-family:'Sora',sans-serif;">Módulo Administrativo</span>
                </div>
                <div class="module-visual-body">
                    <div class="ui-card-row">
                        <div class="ui-card"><div class="ui-card-num">34</div><div class="ui-card-lbl">Unidades</div></div>
                        <div class="ui-card"><div class="ui-card-num">7</div><div class="ui-card-lbl">Manutenções</div></div>
                    </div>
                    <div style="background:rgba(7,79,155,0.06);border-radius:10px;padding:12px;display:flex;flex-direction:column;gap:6px;">
                        <div style="font-size:0.7rem;font-weight:600;color:var(--blue-mid);margin-bottom:4px;">ORDENS DE SERVIÇO</div>
                        <div class="ui-table-row"><div class="ui-row w60" style="flex:1;height:10px;"></div><div class="ui-badge yellow">Aguardando</div></div>
                        <div class="ui-table-row"><div class="ui-row w60" style="flex:1;height:10px;"></div><div class="ui-badge blue">Em execução</div></div>
                        <div class="ui-table-row"><div class="ui-row w60" style="flex:1;height:10px;"></div><div class="ui-badge green">Concluído</div></div>
                    </div>
                    <div class="ui-row w100" style="height:10px;"></div>
                    <div class="ui-row w80" style="height:10px;"></div>
                </div>
            </div>
            <div class="module-text">
                <div class="module-number">Módulo 04</div>
                <div class="module-icon-wrap">🏫</div>
                <h2>Administrativo e Patrimônio</h2>
                <p>Gestão centralizada das unidades escolares — do pedido de manutenção ao balanço patrimonial, com rastreabilidade completa de todos os bens e movimentações.</p>
                <ul class="feature-list">
                    <li>Cadastro e controle de bens patrimoniais por unidade</li>
                    <li>Pedidos e acompanhamento de ordens de manutenção</li>
                    <li>Registro de entradas e saídas de materiais e equipamentos</li>
                    <li>Balanço patrimonial consolidado por escola e rede</li>
                    <li>Controle de contratos e fornecedores</li>
                    <li>Relatórios para prestação de contas e auditoria</li>
                </ul>
            </div>
        </div>

        <!-- RH -->
        <div class="module-block reveal">
            <div class="module-visual">
                <div class="module-visual-header">
                    <div class="dot r"></div><div class="dot y"></div><div class="dot g"></div>
                    <span style="color:rgba(255,255,255,0.6);font-size:0.75rem;margin-left:8px;font-family:'Sora',sans-serif;">Módulo Recursos Humanos</span>
                </div>
                <div class="module-visual-body">
                    <div class="ui-card-row">
                        <div class="ui-card"><div class="ui-card-num">318</div><div class="ui-card-lbl">Funcionários</div></div>
                        <div class="ui-card"><div class="ui-card-num">12</div><div class="ui-card-lbl">Em férias</div></div>
                    </div>
                    <div style="background:rgba(7,79,155,0.06);border-radius:10px;padding:12px;display:flex;flex-direction:column;gap:8px;">
                        <div style="font-size:0.7rem;font-weight:600;color:var(--blue-mid);">PENDÊNCIAS DO MÊS</div>
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <span style="font-size:0.68rem;color:var(--text-mid);">Atestados para validar</span>
                            <span style="font-family:'Sora',sans-serif;font-weight:700;color:var(--blue-mid);font-size:0.9rem;">4</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <span style="font-size:0.68rem;color:var(--text-mid);">Férias vencendo em 30 dias</span>
                            <span style="font-family:'Sora',sans-serif;font-weight:700;color:#92400e;font-size:0.9rem;">7</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <span style="font-size:0.68rem;color:var(--text-mid);">Décl. de horas emitidas</span>
                            <span style="font-family:'Sora',sans-serif;font-weight:700;color:#065f46;font-size:0.9rem;">23</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="module-text">
                <div class="module-number">Módulo 05</div>
                <div class="module-icon-wrap">👥</div>
                <h2>Recursos Humanos</h2>
                <p>Controle completo do ciclo de vida do servidor — dos atestados às férias, do controle de ponto às declarações — integrado à lotação e movimentação de pessoal.</p>
                <ul class="feature-list">
                    <li>Gestão de lotação: local de trabalho de cada servidor</li>
                    <li>Emissão e controle de atestados médicos</li>
                    <li>Planejamento e controle de férias e licença-prêmio</li>
                    <li>Emissão de declarações de horas trabalhadas</li>
                    <li>Controle de ponto com registros e relatórios</li>
                    <li>Alertas de vencimentos: férias, licenças e contratos</li>
                </ul>
            </div>
        </div>

    </div>
</section>

<!-- COMO FUNCIONA -->
<section id="como-funciona" class="section how">
    <div class="section-header reveal">
        <span class="section-label">Processo</span>
        <h2>Como o sistema funciona na prática</h2>
        <p>Implementação estruturada para garantir adoção rápida e resultados reais desde as primeiras semanas.</p>
    </div>
    <div class="steps">
        <div class="step reveal">
            <div class="step-num">01</div>
            <div class="step-text">
                <h3>Implantação e migração de dados</h3>
                <p>A equipe técnica realiza a importação dos dados existentes — planilhas, sistemas legados ou registros físicos — garantindo que o histórico da secretaria não seja perdido na transição.</p>
            </div>
        </div>
        <div class="step reveal">
            <div class="step-num">02</div>
            <div class="step-text">
                <h3>Configuração dos perfis de acesso</h3>
                <p>Cada usuário recebe permissões de acordo com sua função: professor, coordenador pedagógico, nutricionista, gestor de RH ou secretário municipal. Ninguém acessa o que não é de sua responsabilidade.</p>
            </div>
        </div>
        <div class="step reveal">
            <div class="step-num">03</div>
            <div class="step-text">
                <h3>Treinamento das equipes</h3>
                <p>Capacitação presencial e por vídeos por módulo, com material de apoio disponível na plataforma. O treinamento é adaptado ao perfil técnico de cada grupo de usuários.</p>
            </div>
        </div>
        <div class="step reveal">
            <div class="step-num">04</div>
            <div class="step-text">
                <h3>Operação com suporte ativo</h3>
                <p>Nos primeiros meses, a equipe técnica acompanha de perto a utilização do sistema, corrigindo fluxos e ajustando configurações conforme a realidade de cada escola e setor.</p>
            </div>
        </div>
        <div class="step reveal">
            <div class="step-num">05</div>
            <div class="step-text">
                <h3>Relatórios e tomada de decisão</h3>
                <p>Com os dados consolidados, a secretaria passa a ter visibilidade real sobre toda a rede — e os gestores conseguem identificar gargalos, planejar melhorias e prestar contas com precisão.</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA FINAL -->
<section class="cta-final">
    <div class="cta-final-content">
        <h2>Acesso exclusivo para profissionais da educação municipal</h2>
        <p>Entre com suas credenciais institucionais para acessar o painel completo do Gestão Edu.</p>
        <a href="/login" class="btn-primary">Acessar o Sistema</a>
    </div>
</section>

<!-- FOOTER -->
<footer>
    <div class="footer-inner">
        <div class="footer-brand">
            <img src="/images/brasao-umuarama.png" alt="Brasão de Umuarama">
            <p>Sistema de Gestão Escolar da Secretaria Municipal de Educação de Umuarama. Desenvolvido para centralizar, integrar e modernizar a gestão da educação pública municipal.</p>
        </div>
        <div class="footer-col">
            <h4>Módulos</h4>
            <ul>
                <li><a href="#modulos">Pedagógico</a></li>
                <li><a href="#modulos">Transporte Escolar</a></li>
                <li><a href="#modulos">Alimentação Escolar</a></li>
                <li><a href="#modulos">Administrativo</a></li>
                <li><a href="#modulos">Recursos Humanos</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4>Secretaria</h4>
            <ul>
                <li><a href="#">Prefeitura de Umuarama</a></li>
                <li><a href="#">Secretaria de Educação</a></li>
                <li><a href="/login">Acessar o Sistema</a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <p>© 2026 · Prefeitura Municipal de Umuarama · Secretaria Municipal de Educação</p>
        <div class="footer-logos">
            <img src="/images/brasao-umuarama.png" alt="Umuarama">
            <img src="/images/abrinq-logo.png" alt="Fundação Abrinq">
        </div>
    </div>
</footer>

<script>
    // Reveal on scroll
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); } });
    }, { threshold: 0.12 });
    document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
</script>
</body>
</html>