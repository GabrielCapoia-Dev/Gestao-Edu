<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Gestao Edu')</title>
    <meta name="description" content="@yield('description', 'Sistema Gestao Edu da Secretaria Municipal de Educacao de Umuarama.')">
    <style>
        :root {
            --blue: #0757a6;
            --blue-dark: #07305f;
            --gold: #f2a51f;
            --ink: #172033;
            --muted: #5b6b82;
            --line: #dbe5f0;
            --soft: #f5f8fc;
            --white: #ffffff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: var(--ink);
            background: var(--soft);
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.6;
        }

        a {
            color: var(--blue);
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

        .topbar {
            background: var(--white);
            border-bottom: 1px solid var(--line);
        }

        .wrap {
            width: min(1120px, calc(100% - 32px));
            margin: 0 auto;
        }

        .nav {
            min-height: 72px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--ink);
            font-weight: 800;
        }

        .brand img {
            width: 180px;
            max-width: 42vw;
            height: auto;
            object-fit: contain;
        }

        .links {
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
            font-size: 0.95rem;
            font-weight: 700;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 0 18px;
            border-radius: 8px;
            background: var(--blue);
            color: var(--white);
            font-weight: 800;
        }

        .button:hover {
            text-decoration: none;
            background: var(--blue-dark);
        }

        main {
            flex: 1;
        }

        .hero {
            color: var(--white);
            background: linear-gradient(135deg, var(--blue-dark), var(--blue));
            padding: 78px 0 72px;
        }

        .hero-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.25fr) minmax(280px, 0.75fr);
            gap: 36px;
            align-items: center;
        }

        .eyebrow {
            margin: 0 0 12px;
            color: #ffcf6e;
            font-size: 0.82rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        h1 {
            margin: 0 0 18px;
            font-size: clamp(2.2rem, 6vw, 4.2rem);
            line-height: 1.05;
        }

        h2 {
            margin: 0 0 14px;
            color: var(--blue-dark);
            font-size: clamp(1.55rem, 3vw, 2.25rem);
            line-height: 1.2;
        }

        h3 {
            margin: 0 0 8px;
            color: var(--blue-dark);
            font-size: 1.05rem;
        }

        p {
            margin: 0 0 14px;
        }

        .lead {
            max-width: 720px;
            color: rgba(255, 255, 255, 0.88);
            font-size: 1.12rem;
        }

        .hero-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 28px;
        }

        .button.secondary {
            background: var(--white);
            color: var(--blue-dark);
        }

        .button.secondary:hover {
            background: #eef5ff;
        }

        .hero-panel {
            padding: 26px;
            border: 1px solid rgba(255, 255, 255, 0.22);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(8px);
        }

        .hero-panel strong {
            display: block;
            margin-bottom: 6px;
            color: #ffcf6e;
            font-size: 1.7rem;
        }

        .section {
            padding: 56px 0;
            background: var(--white);
        }

        .section.alt {
            background: var(--soft);
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .card {
            height: 100%;
            padding: 24px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--white);
        }

        .document {
            max-width: 860px;
            margin: 0 auto;
            padding: 56px 0;
        }

        .document-card {
            padding: 36px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--white);
        }

        .document ul {
            padding-left: 20px;
        }

        .document li {
            margin-bottom: 10px;
        }

        .footer {
            padding: 30px 0;
            color: #d6e2f0;
            background: var(--blue-dark);
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
            height: 54px;
            max-width: 220px;
            object-fit: contain;
        }

        .footer a {
            color: #ffffff;
            font-weight: 700;
        }

        @media (max-width: 760px) {
            .nav {
                align-items: flex-start;
                flex-direction: column;
                padding: 16px 0;
            }

            .links {
                align-items: flex-start;
                flex-direction: column;
                gap: 10px;
            }

            .hero-grid,
            .grid {
                grid-template-columns: 1fr;
            }

            .hero {
                padding: 48px 0;
            }

            .document-card {
                padding: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <header class="topbar">
            <div class="wrap nav">
                <a class="brand" href="{{ route('public.home', [], false) }}" aria-label="Gestao Edu">
                    <img src="/images/logo-educacao.png" alt="Secretaria Municipal de Educacao de Umuarama">
                </a>

                <nav class="links" aria-label="Navegacao publica">
                    <a href="{{ route('public.home', [], false) }}">Inicio</a>
                    <a href="{{ route('public.privacy', [], false) }}">Politica de Privacidade</a>
                    <a href="{{ route('public.terms', [], false) }}">Termos de Servico</a>
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
                    <img src="/images/logo-umuarma-educacao.png" alt="Prefeitura de Umuarama e Secretaria Municipal de Educacao">
                    <img src="/images/abrinq-logo.png" alt="Fundacao Abrinq">
                </div>
                <div class="links">
                    <a href="{{ route('public.privacy', [], false) }}">Politica de Privacidade</a>
                    <a href="{{ route('public.terms', [], false) }}">Termos de Servico</a>
                    <a href="mailto:automacao@edu.umuarama.pr.gov.br">Contato</a>
                </div>
            </div>
        </footer>
    </div>
</body>
</html>
