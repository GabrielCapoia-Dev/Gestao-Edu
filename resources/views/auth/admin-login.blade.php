<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Login - Gestao Edu</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #101828;
            --ink-2: #1d2939;
            --blue: #2563eb;
            --teal: #0f766e;
            --teal-2: #14b8a6;
            --amber: #f59e0b;
            --rose: #be123c;
            --paper: #f8fafc;
            --panel: #ffffff;
            --line: #d9e2ee;
            --muted: #667085;
            --muted-2: #98a2b3;
            --danger: #b42318;
            --success: #067647;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
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
            background:
                linear-gradient(90deg, rgba(37, 99, 235, .08) 1px, transparent 1px),
                linear-gradient(180deg, rgba(15, 118, 110, .07) 1px, transparent 1px),
                #f7f9fc;
            background-size: 52px 52px;
            color: var(--ink-2);
        }

        button,
        input {
            font: inherit;
        }

        .auth-shell {
            position: relative;
            min-height: 100vh;
            display: grid;
            grid-template-columns: minmax(420px, 1fr) minmax(360px, 520px);
            overflow: hidden;
        }

        .brand-panel {
            position: relative;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: clamp(32px, 5vw, 64px);
            overflow: hidden;
            background:
                linear-gradient(135deg, rgba(20, 184, 166, .18), transparent 36%),
                linear-gradient(215deg, rgba(245, 158, 11, .16), transparent 34%),
                var(--ink);
            color: #ffffff;
            isolation: isolate;
        }

        .brand-panel::before {
            content: "";
            position: absolute;
            inset: -20%;
            z-index: -2;
            background:
                repeating-linear-gradient(90deg, rgba(255, 255, 255, .055) 0 1px, transparent 1px 64px),
                repeating-linear-gradient(0deg, rgba(255, 255, 255, .045) 0 1px, transparent 1px 64px);
            transform: rotate(-8deg);
            animation: gridDrift 18s linear infinite;
        }

        .brand-panel::after {
            content: "";
            position: absolute;
            inset: 0;
            z-index: -1;
            background:
                linear-gradient(115deg, transparent 0 32%, rgba(255, 255, 255, .08) 44%, transparent 58%),
                linear-gradient(0deg, rgba(16, 24, 40, .42), transparent 45%);
            animation: lightSweep 8s ease-in-out infinite;
        }

        .brand-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            animation: fadeUp .6s ease both;
        }

        .brand-lockup {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        .brand-mark {
            width: 48px;
            height: 48px;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            border-radius: 12px;
            background: #ffffff;
            color: var(--ink);
            font-size: 24px;
            font-weight: 900;
            box-shadow: 0 14px 38px rgba(0, 0, 0, .25);
        }

        .brand-name,
        .brand-kicker {
            display: block;
            line-height: 1.1;
        }

        .brand-name {
            font-size: 19px;
            font-weight: 800;
            letter-spacing: 0;
        }

        .brand-kicker {
            margin-top: 5px;
            color: rgba(255, 255, 255, .68);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .status-pill {
            min-height: 34px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0 12px;
            border: 1px solid rgba(255, 255, 255, .18);
            border-radius: 999px;
            background: rgba(255, 255, 255, .08);
            color: rgba(255, 255, 255, .82);
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: var(--teal-2);
            box-shadow: 0 0 0 0 rgba(20, 184, 166, .55);
            animation: pulse 2.4s ease-out infinite;
        }

        .brand-main {
            max-width: 720px;
            padding: 72px 0;
            animation: fadeUp .7s .08s ease both;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 22px;
            color: rgba(255, 255, 255, .72);
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .eyebrow::before {
            content: "";
            width: 34px;
            height: 2px;
            border-radius: 99px;
            background: var(--amber);
        }

        .brand-main h1 {
            max-width: 680px;
            margin: 0;
            font-size: clamp(42px, 7vw, 86px);
            line-height: .94;
            letter-spacing: 0;
        }

        .brand-main p {
            max-width: 540px;
            margin: 24px 0 0;
            color: rgba(255, 255, 255, .74);
            font-size: clamp(15px, 1.5vw, 18px);
            line-height: 1.65;
        }

        .signal-board {
            width: min(560px, 100%);
            display: grid;
            gap: 12px;
            animation: fadeUp .7s .16s ease both;
        }

        .signal-row {
            display: grid;
            grid-template-columns: 88px 1fr 48px;
            align-items: center;
            gap: 12px;
            padding: 13px 14px;
            border: 1px solid rgba(255, 255, 255, .13);
            border-radius: 10px;
            background: rgba(255, 255, 255, .075);
            backdrop-filter: blur(10px);
        }

        .signal-label,
        .signal-value {
            color: rgba(255, 255, 255, .78);
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .signal-value {
            color: rgba(255, 255, 255, .92);
            text-align: right;
        }

        .signal-track {
            position: relative;
            height: 8px;
            overflow: hidden;
            border-radius: 99px;
            background: rgba(255, 255, 255, .12);
        }

        .signal-track span {
            position: absolute;
            inset: 0 auto 0 0;
            width: var(--width);
            border-radius: inherit;
            background: linear-gradient(90deg, var(--teal-2), var(--amber));
            transform-origin: left center;
            animation: scaleIn .9s ease both;
        }

        .auth-panel {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(24px, 5vw, 56px);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, .92), rgba(248, 250, 252, .98));
        }

        .login-card {
            width: min(100%, 390px);
            animation: cardIn .58s .12s cubic-bezier(.2, .8, .2, 1) both;
        }

        .mobile-brand {
            display: none;
            align-items: center;
            gap: 12px;
            margin-bottom: 28px;
        }

        .mobile-brand .brand-mark {
            width: 42px;
            height: 42px;
            color: #ffffff;
            background: var(--ink);
            box-shadow: none;
        }

        .login-title {
            margin: 0 0 8px;
            color: var(--ink);
            font-size: 31px;
            line-height: 1.1;
            letter-spacing: 0;
        }

        .intro {
            margin: 0 0 28px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.55;
        }

        .alert {
            margin-bottom: 18px;
            padding: 12px 14px;
            border: 1px solid #abefc6;
            border-radius: 10px;
            background: #ecfdf3;
            color: var(--success);
            font-size: 13px;
            line-height: 1.4;
        }

        .field {
            position: relative;
            margin-bottom: 18px;
        }

        .field label {
            display: block;
            margin-bottom: 7px;
            color: var(--ink);
            font-size: 13px;
            font-weight: 800;
        }

        .input-wrap {
            position: relative;
        }

        .field input[type="email"],
        .field input[type="password"] {
            width: 100%;
            min-height: 48px;
            padding: 12px 14px;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: rgba(255, 255, 255, .92);
            color: var(--ink);
            font-size: 15px;
            outline: none;
            transition:
                border-color .18s ease,
                box-shadow .18s ease,
                transform .18s ease,
                background .18s ease;
        }

        .field input::placeholder {
            color: var(--muted-2);
        }

        .field input:hover {
            border-color: #bdc8d7;
            background: #ffffff;
        }

        .field input:focus {
            border-color: var(--blue);
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, .12);
            transform: translateY(-1px);
        }

        .error {
            margin: 7px 0 0;
            color: var(--danger);
            font-size: 13px;
            line-height: 1.35;
        }

        .options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin: 2px 0 24px;
            color: var(--muted);
            font-size: 13px;
        }

        .remember {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            user-select: none;
        }

        .remember input {
            width: 17px;
            height: 17px;
            margin: 0;
            accent-color: var(--teal);
        }

        .submit,
        .google {
            width: 100%;
            min-height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
            transition:
                transform .18s ease,
                box-shadow .18s ease,
                border-color .18s ease,
                background .18s ease;
        }

        .submit {
            position: relative;
            overflow: hidden;
            border: 0;
            background: var(--ink);
            color: #ffffff;
            box-shadow: 0 18px 38px rgba(16, 24, 40, .22);
        }

        .submit::after {
            content: "";
            position: absolute;
            top: 0;
            bottom: 0;
            left: -42%;
            width: 34%;
            transform: skewX(-18deg);
            background: rgba(255, 255, 255, .18);
            transition: left .42s ease;
        }

        .submit:hover,
        .submit:focus-visible {
            transform: translateY(-2px);
            background: #172033;
            box-shadow: 0 22px 48px rgba(16, 24, 40, .28);
        }

        .submit:hover::after,
        .submit:focus-visible::after {
            left: 112%;
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 14px;
            margin: 24px 0;
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .divider::before,
        .divider::after {
            content: "";
            height: 1px;
            flex: 1;
            background: var(--line);
        }

        .google {
            border: 1px solid var(--line);
            background: #ffffff;
            color: var(--ink-2);
        }

        .google:hover,
        .google:focus-visible {
            transform: translateY(-2px);
            border-color: #b7c3d3;
            background: #fbfdff;
            box-shadow: 0 14px 32px rgba(16, 24, 40, .10);
        }

        .footnote {
            margin: 24px 0 0;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.45;
            text-align: center;
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(18px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes cardIn {
            from {
                opacity: 0;
                transform: translateY(16px) scale(.985);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(20, 184, 166, .55);
            }
            70% {
                box-shadow: 0 0 0 9px rgba(20, 184, 166, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(20, 184, 166, 0);
            }
        }

        @keyframes scaleIn {
            from {
                transform: scaleX(.2);
            }
            to {
                transform: scaleX(1);
            }
        }

        @keyframes gridDrift {
            from {
                transform: translate3d(0, 0, 0) rotate(-8deg);
            }
            to {
                transform: translate3d(64px, 64px, 0) rotate(-8deg);
            }
        }

        @keyframes lightSweep {
            0%, 100% {
                opacity: .54;
                transform: translateX(-4%);
            }
            50% {
                opacity: .9;
                transform: translateX(4%);
            }
        }

        @media (max-width: 980px) {
            .auth-shell {
                grid-template-columns: 1fr;
            }

            .brand-panel {
                display: none;
            }

            .auth-panel {
                min-height: 100vh;
                align-items: flex-start;
                padding: 42px 22px;
            }

            .mobile-brand {
                display: flex;
            }
        }

        @media (max-width: 480px) {
            body {
                background-size: 40px 40px;
            }

            .auth-panel {
                padding: 28px 18px;
            }

            .login-card {
                width: 100%;
            }

            .login-title {
                font-size: 27px;
            }

            .submit,
            .google,
            .field input[type="email"],
            .field input[type="password"] {
                min-height: 46px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
                scroll-behavior: auto !important;
                transition-duration: .01ms !important;
            }
        }
    </style>
</head>
<body>
    <main class="auth-shell">
        <section class="brand-panel" aria-label="Gestao Edu">
            <div class="brand-header">
                <div class="brand-lockup">
                    <span class="brand-mark" aria-hidden="true">G</span>
                    <span>
                        <span class="brand-name">Gestao Edu</span>
                        <span class="brand-kicker">Umuarama - PR</span>
                    </span>
                </div>

                <span class="status-pill">
                    <span class="status-dot" aria-hidden="true"></span>
                    Acesso seguro
                </span>
            </div>

            <div class="brand-main">
                <span class="eyebrow">Secretaria Municipal de Educacao</span>
                <h1>Central de gestao escolar</h1>
                <p>Entre para acompanhar rotinas administrativas, pedagogicas e operacionais em um unico painel.</p>
            </div>

            <div class="signal-board" aria-hidden="true">
                <div class="signal-row">
                    <span class="signal-label">Painel</span>
                    <span class="signal-track"><span style="--width: 88%"></span></span>
                    <span class="signal-value">Online</span>
                </div>
                <div class="signal-row">
                    <span class="signal-label">Dados</span>
                    <span class="signal-track"><span style="--width: 74%"></span></span>
                    <span class="signal-value">Ativo</span>
                </div>
                <div class="signal-row">
                    <span class="signal-label">Sessao</span>
                    <span class="signal-track"><span style="--width: 62%"></span></span>
                    <span class="signal-value">Web</span>
                </div>
            </div>
        </section>

        <section class="auth-panel" aria-labelledby="login-title">
            <div class="login-card">
                <div class="mobile-brand">
                    <span class="brand-mark" aria-hidden="true">G</span>
                    <span>
                        <span class="brand-name" style="color: var(--ink)">Gestao Edu</span>
                        <span class="brand-kicker" style="color: var(--muted)">Umuarama - PR</span>
                    </span>
                </div>

                <h2 class="login-title" id="login-title">Bem-vindo de volta</h2>
                <p class="intro">Acesse o painel com seu email institucional.</p>

                @if (session('status'))
                    <div class="alert">{{ session('status') }}</div>
                @endif

                <form method="POST" action="{{ $loginAction }}" autocomplete="on">
                    @csrf

                    <div class="field">
                        <label for="email">Email</label>
                        <div class="input-wrap">
                            <input
                                id="email"
                                name="email"
                                type="email"
                                value="{{ old('email') }}"
                                autocomplete="username"
                                placeholder="seu.email@edu.umuarama.pr.gov.br"
                                required
                                autofocus
                            >
                        </div>
                        @error('email')
                            <p class="error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label for="password">Senha</label>
                        <div class="input-wrap">
                            <input
                                id="password"
                                name="password"
                                type="password"
                                autocomplete="current-password"
                                placeholder="Digite sua senha"
                                required
                            >
                        </div>
                        @error('password')
                            <p class="error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="options">
                        <label class="remember" for="remember">
                            <input id="remember" name="remember" type="checkbox" value="1">
                            Manter conectado
                        </label>
                    </div>

                    <button class="submit" type="submit">Entrar no Sistema</button>
                </form>

                <div class="divider">ou</div>

                <a class="google" href="{{ $googleLoginUrl }}">Entrar com Google</a>

                <p class="footnote">Prefeitura Municipal de Umuarama - PR</p>
            </div>
        </section>
    </main>
</body>
</html>
