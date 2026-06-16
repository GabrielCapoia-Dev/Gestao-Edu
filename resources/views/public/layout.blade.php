<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Gestão Edu')</title>
    <meta name="description" content="@yield('description', 'Sistema Gestão Edu da Secretaria Municipal de Educação de Umuarama.')">
    <style>
        :root {
            color-scheme: light;
            --primary: #074f9b;
            --primary-800: #053a72;
            --primary-700: #063f7c;
            --primary-100: #dcecff;
            --primary-50: #f0f7ff;
            --sky: #eaf4ff;
            --mint: #dff8ef;
            --green: #11845b;
            --amber: #f4b942;
            --amber-100: #fff4cf;
            --ink: #102033;
            --text: #344054;
            --muted: #667085;
            --line: #d8e4f2;
            --paper: #f8fbff;
            --white: #ffffff;
            font-family: Arial, Helvetica, sans-serif;
        }

        * {
            box-sizing: border-box;
        }

        html {
            min-height: 100%;
            background: var(--paper);
        }

        body {
            min-height: 100vh;
            margin: 0;
            color: var(--text);
            background:
                radial-gradient(circle at 10% 12%, rgba(7, 79, 155, .20), transparent 27%),
                radial-gradient(circle at 66% 8%, rgba(7, 79, 155, .10), transparent 28%),
                radial-gradient(circle at 84% 84%, rgba(244, 185, 66, .16), transparent 30%),
                linear-gradient(135deg, #fafdff 0%, #edf6ff 46%, #ffffff 100%);
            line-height: 1.65;
        }

        a {
            color: var(--primary);
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        .page {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .wrap {
            width: min(1120px, calc(100% - 32px));
            margin: 0 auto;
        }

        .site-header {
            position: sticky;
            top: 0;
            z-index: 20;
            padding: 18px 0;
            background: rgba(248, 251, 255, .82);
            border-bottom: 1px solid rgba(7, 79, 155, .08);
            backdrop-filter: blur(16px);
        }

        .nav {
            min-height: 68px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            padding: 12px 16px;
            border: 1px solid rgba(7, 79, 155, .12);
            border-radius: 18px;
            background: rgba(255, 255, 255, .86);
            box-shadow: 0 20px 54px rgba(7, 79, 155, .08);
        }

        .brand-lockup {
            display: flex;
            align-items: center;
            gap: 13px;
            color: var(--ink);
        }

        .brand-lockup:hover {
            text-decoration: none;
        }

        .brand-mark {
            width: 48px;
            height: 48px;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            border-radius: 14px;
            background: var(--primary);
            color: var(--white);
            font-size: 24px;
            font-weight: 900;
            box-shadow: 0 14px 32px rgba(7, 79, 155, .18);
        }

        .brand-name,
        .brand-subtitle {
            display: block;
            line-height: 1.12;
        }

        .brand-name {
            color: var(--primary);
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 0;
        }

        .brand-subtitle {
            margin-top: 5px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
            font-size: 14px;
            font-weight: 800;
        }

        .nav-links a:not(.button) {
            min-height: 40px;
            display: inline-flex;
            align-items: center;
            padding: 0 12px;
            border-radius: 12px;
            color: var(--primary-700);
        }

        .nav-links a:not(.button):hover,
        .nav-links a.is-active {
            background: var(--primary-50);
            text-decoration: none;
        }

        .button {
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0 18px;
            border: 0;
            border-radius: 12px;
            background: var(--primary);
            color: var(--white);
            font-size: 14px;
            font-weight: 800;
            text-decoration: none;
            box-shadow: 0 16px 34px rgba(7, 79, 155, .20);
            transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
        }

        .button:hover,
        .button:focus-visible {
            transform: translateY(-2px);
            background: var(--primary-700);
            color: var(--white);
            text-decoration: none;
            box-shadow: 0 20px 44px rgba(7, 79, 155, .26);
        }

        .button.secondary {
            border: 1px solid var(--line);
            background: var(--white);
            color: var(--primary);
            box-shadow: 0 14px 28px rgba(7, 79, 155, .08);
        }

        .button.secondary:hover,
        .button.secondary:focus-visible {
            border-color: #b8cce3;
            background: var(--primary-50);
            color: var(--primary-700);
        }

        main {
            flex: 1;
        }

        .public-hero,
        .document-shell {
            position: relative;
            overflow: hidden;
        }

        .public-hero {
            padding: 72px 0 44px;
        }

        .hero-grid {
            position: relative;
            z-index: 2;
            display: grid;
            grid-template-columns: minmax(0, 1.16fr) minmax(320px, .84fr);
            gap: 34px;
            align-items: stretch;
        }

        .hero-copy {
            min-height: 520px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: clamp(28px, 4vw, 52px);
            border: 1px solid rgba(7, 79, 155, .14);
            border-radius: 24px;
            background:
                linear-gradient(118deg, rgba(255, 255, 255, .88) 0%, rgba(247, 251, 255, .78) 46%, rgba(222, 239, 255, .66) 100%),
                linear-gradient(135deg, rgba(7, 79, 155, .12), rgba(255, 255, 255, .28));
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, .80), 0 24px 80px rgba(7, 79, 155, .10);
        }

        .hero-panel {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 22px;
            padding: clamp(24px, 3vw, 34px);
            border: 1px solid rgba(7, 79, 155, .12);
            border-radius: 18px;
            background: rgba(255, 255, 255, .88);
            box-shadow: 0 24px 60px rgba(7, 79, 155, .12);
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin: 0 0 18px;
            color: var(--primary);
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .eyebrow::before {
            content: "";
            width: 34px;
            height: 3px;
            border-radius: 999px;
            background: var(--amber);
        }

        h1,
        h2,
        h3,
        p {
            margin-top: 0;
        }

        h1 {
            max-width: 760px;
            margin-bottom: 22px;
            color: var(--primary);
            font-size: clamp(38px, 5.6vw, 70px);
            line-height: .98;
            letter-spacing: 0;
        }

        h2 {
            margin-bottom: 14px;
            color: var(--primary);
            font-size: clamp(28px, 3vw, 42px);
            line-height: 1.12;
            letter-spacing: 0;
        }

        h3 {
            margin-bottom: 10px;
            color: var(--ink);
            font-size: 18px;
            line-height: 1.25;
        }

        p {
            margin-bottom: 14px;
        }

        .lead {
            max-width: 690px;
            color: var(--primary-700);
            font-size: clamp(15px, 1.4vw, 18px);
            line-height: 1.65;
        }

        .hero-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 18px;
        }

        .status-pill {
            min-height: 34px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
            padding: 0 12px;
            border: 1px solid rgba(17, 132, 91, .18);
            border-radius: 999px;
            background: var(--mint);
            color: var(--green);
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: var(--green);
            box-shadow: 0 0 0 0 rgba(17, 132, 91, .28);
        }

        .metric-list {
            display: grid;
            gap: 14px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .metric-list li {
            padding: 16px;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: #fbfdff;
        }

        .metric-list strong {
            display: block;
            color: var(--primary);
            font-size: 15px;
            line-height: 1.3;
        }

        .metric-list span {
            display: block;
            margin-top: 5px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.45;
        }

        .section {
            padding: 58px 0;
        }

        .section.compact {
            padding-top: 28px;
        }

        .section-header {
            max-width: 760px;
            margin-bottom: 26px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .card {
            min-height: 100%;
            padding: 24px;
            border: 1px solid rgba(7, 79, 155, .12);
            border-radius: 16px;
            background: rgba(255, 255, 255, .90);
            box-shadow: 0 18px 42px rgba(7, 79, 155, .08);
        }

        .card p {
            margin-bottom: 0;
            color: var(--muted);
        }

        .callout {
            padding: clamp(24px, 4vw, 38px);
            border: 1px solid rgba(7, 79, 155, .12);
            border-radius: 18px;
            background:
                linear-gradient(135deg, rgba(255, 255, 255, .92), rgba(240, 247, 255, .92)),
                var(--white);
            box-shadow: 0 24px 60px rgba(7, 79, 155, .10);
        }

        .document-shell {
            padding: 56px 0 66px;
        }

        .document-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 280px;
            gap: 24px;
            align-items: start;
        }

        .document-card,
        .document-side {
            border: 1px solid rgba(7, 79, 155, .12);
            border-radius: 18px;
            background: rgba(255, 255, 255, .90);
            box-shadow: 0 24px 60px rgba(7, 79, 155, .10);
        }

        .document-card {
            padding: clamp(26px, 4vw, 46px);
        }

        .document-side {
            position: sticky;
            top: 122px;
            padding: 22px;
        }

        .document-card h1 {
            max-width: 760px;
            font-size: clamp(34px, 4.8vw, 58px);
        }

        .document-card h2 {
            margin-top: 34px;
            font-size: clamp(22px, 2.4vw, 30px);
        }

        .document-card ul {
            margin: 0 0 18px;
            padding-left: 20px;
        }

        .document-card li {
            margin-bottom: 10px;
        }

        .document-side p {
            margin-bottom: 10px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.5;
        }

        .document-side a {
            font-weight: 800;
        }

        .footer {
            padding: 30px 0;
            background: rgba(5, 58, 114, .96);
            color: #dcecff;
        }

        .footer-grid {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            flex-wrap: wrap;
        }

        .footer-brand {
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
        }

        .footer-brand img {
            max-height: 54px;
            max-width: 220px;
            object-fit: contain;
        }

        .footer-links {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            font-size: 14px;
            font-weight: 800;
        }

        .footer a {
            color: var(--white);
        }

        @media (max-width: 900px) {
            .site-header {
                position: static;
            }

            .nav,
            .footer-grid {
                align-items: flex-start;
                flex-direction: column;
            }

            .nav-links {
                justify-content: flex-start;
            }

            .hero-grid,
            .grid,
            .document-layout {
                grid-template-columns: 1fr;
            }

            .public-hero {
                padding-top: 36px;
            }

            .hero-copy {
                min-height: auto;
            }

            .document-side {
                position: static;
            }
        }

        @media (max-width: 520px) {
            .wrap {
                width: min(100% - 20px, 1120px);
            }

            .nav {
                padding: 12px;
                border-radius: 16px;
            }

            .brand-mark {
                width: 42px;
                height: 42px;
                border-radius: 12px;
                font-size: 21px;
            }

            .brand-name {
                font-size: 18px;
            }

            .brand-subtitle {
                font-size: 11px;
            }

            .nav-links {
                width: 100%;
            }

            .nav-links a:not(.button),
            .nav-links .button {
                width: 100%;
                justify-content: center;
            }

            .hero-copy,
            .hero-panel,
            .card,
            .callout,
            .document-card,
            .document-side {
                border-radius: 16px;
            }

            .section {
                padding: 42px 0;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <header class="site-header">
            <div class="wrap nav">
                <a class="brand-lockup" href="{{ route('public.home', [], false) }}" aria-label="Gestão Edu">
                    <span class="brand-mark" aria-hidden="true">G</span>
                    <span>
                        <span class="brand-name">Gestão Edu</span>
                        <span class="brand-subtitle">Secretaria de Educação</span>
                    </span>
                </a>

                <nav class="nav-links" aria-label="Navegação pública">
                    <a href="{{ route('public.home', [], false) }}" @class(['is-active' => request()->routeIs('public.home')])>Início</a>
                    <a href="{{ route('public.privacy', [], false) }}" @class(['is-active' => request()->routeIs('public.privacy')])>Política de Privacidade</a>
                    <a href="{{ route('public.terms', [], false) }}" @class(['is-active' => request()->routeIs('public.terms')])>Termos de Serviço</a>
                    <a class="button" href="/admin/login">Acessar sistema</a>
                </nav>
            </div>
        </header>

        <main>
            @yield('content')
        </main>

        <footer class="footer">
            <div class="wrap footer-grid">
                <div class="footer-brand">
                    <img src="/images/logo-umuarma-educacao.png" alt="Prefeitura de Umuarama e Secretaria Municipal de Educação">
                    <img src="/images/abrinq-logo.png" alt="Fundação Abrinq">
                </div>
                <div class="footer-links">
                    <a href="{{ route('public.privacy', [], false) }}">Política de Privacidade</a>
                    <a href="{{ route('public.terms', [], false) }}">Termos de Serviço</a>
                    <a href="mailto:automacao@edu.umuarama.pr.gov.br">Contato</a>
                </div>
            </div>
        </footer>
    </div>
</body>
</html>
