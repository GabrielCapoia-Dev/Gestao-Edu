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
            --primary: #074f9b;
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
            --danger: #b42318;
            --success: #067647;
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
                radial-gradient(circle at 12% 14%, rgba(7, 79, 155, .10), transparent 26%),
                radial-gradient(circle at 84% 78%, rgba(244, 185, 66, .18), transparent 28%),
                linear-gradient(135deg, #ffffff 0%, #f4f9ff 48%, #eef6ff 100%);
        }

        button,
        input {
            font: inherit;
        }

        .auth-shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: minmax(420px, 1fr) minmax(360px, 480px);
        }

        .brand-panel {
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 100vh;
            padding: clamp(30px, 5vw, 58px);
            overflow: hidden;
        }

        .brand-panel::before {
            content: "";
            position: absolute;
            inset: 28px;
            border: 1px solid rgba(7, 79, 155, .10);
            border-radius: 24px;
            background: rgba(255, 255, 255, .42);
            pointer-events: none;
        }

        .brand-panel::after {
            content: "";
            position: absolute;
            top: 18%;
            right: 7%;
            width: 190px;
            height: 190px;
            border-radius: 999px;
            border: 28px solid rgba(7, 79, 155, .08);
            animation: floatSeal 9s ease-in-out infinite;
            pointer-events: none;
        }

        .brand-header,
        .hero,
        .module-grid {
            position: relative;
            z-index: 1;
        }

        .brand-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            animation: fadeUp .5s ease both;
        }

        .brand-lockup,
        .mobile-brand {
            display: flex;
            align-items: center;
            gap: 13px;
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
            color: var(--ink);
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

        .status-pill {
            min-height: 34px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
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
            animation: pulse 2.4s ease-out infinite;
        }

        .hero {
            max-width: 700px;
            padding: clamp(42px, 8vw, 90px) 0 34px;
            animation: fadeUp .58s .08s ease both;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
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

        .hero h1 {
            max-width: 660px;
            margin: 0;
            color: var(--ink);
            font-size: clamp(40px, 6.5vw, 76px);
            line-height: .98;
            letter-spacing: 0;
        }

        .hero p {
            max-width: 560px;
            margin: 22px 0 0;
            color: var(--muted);
            font-size: clamp(15px, 1.4vw, 18px);
            line-height: 1.65;
        }

        .module-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(170px, 240px));
            gap: 14px;
            animation: fadeUp .62s .14s ease both;
        }

        .module {
            min-height: 104px;
            padding: 16px;
            border: 1px solid rgba(7, 79, 155, .12);
            border-radius: 16px;
            background: rgba(255, 255, 255, .76);
            box-shadow: 0 16px 38px rgba(7, 79, 155, .08);
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .module:hover {
            transform: translateY(-3px);
            box-shadow: 0 22px 44px rgba(7, 79, 155, .12);
        }

        .module-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .module-dot {
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            border-radius: 11px;
            background: var(--primary-50);
            color: var(--primary);
            font-size: 18px;
            font-weight: 900;
        }

        .module small {
            color: var(--muted);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .module strong {
            display: block;
            margin-top: 18px;
            color: var(--ink);
            font-size: 16px;
        }

        .auth-panel {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(24px, 5vw, 52px);
            background: rgba(255, 255, 255, .74);
            border-left: 1px solid rgba(7, 79, 155, .10);
            backdrop-filter: blur(14px);
        }

        .login-card {
            width: min(100%, 386px);
            padding: 30px;
            border: 1px solid rgba(7, 79, 155, .12);
            border-radius: 18px;
            background: var(--white);
            box-shadow: 0 24px 60px rgba(7, 79, 155, .12);
            animation: cardIn .48s .1s ease both;
        }

        .mobile-brand {
            display: none;
            margin-bottom: 24px;
        }

        .login-title {
            margin: 0 0 8px;
            color: var(--primary);
            font-size: 30px;
            line-height: 1.12;
            letter-spacing: 0;
        }

        .intro {
            margin: 0 0 26px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.55;
        }

        .alert {
            margin-bottom: 18px;
            padding: 12px 14px;
            border: 1px solid #abefc6;
            border-radius: 12px;
            background: #ecfdf3;
            color: var(--success);
            font-size: 13px;
            line-height: 1.4;
        }

        .field {
            margin-bottom: 17px;
        }

        .field label {
            display: block;
            margin-bottom: 7px;
            color: var(--ink);
            font-size: 13px;
            font-weight: 800;
        }

        .field input[type="email"],
        .field input[type="password"] {
            width: 100%;
            min-height: 48px;
            padding: 12px 14px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #fbfdff;
            color: var(--ink);
            font-size: 15px;
            outline: none;
            transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
        }

        .field input::placeholder {
            color: #98a2b3;
        }

        .field input:hover {
            background: var(--white);
            border-color: #b8cce3;
        }

        .field input:focus {
            background: var(--white);
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(7, 79, 155, .12);
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
            accent-color: var(--primary);
        }

        .submit,
        .google {
            width: 100%;
            min-height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
            transition: transform .18s ease, box-shadow .18s ease, background .18s ease, border-color .18s ease;
        }

        .submit {
            border: 0;
            background: var(--primary);
            color: var(--white);
            box-shadow: 0 16px 34px rgba(7, 79, 155, .24);
        }

        .submit:hover,
        .submit:focus-visible {
            transform: translateY(-2px);
            background: var(--primary-700);
            box-shadow: 0 20px 44px rgba(7, 79, 155, .30);
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 14px;
            margin: 24px 0;
            color: var(--muted);
            font-size: 12px;
            font-weight: 800;
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
            background: var(--white);
            color: var(--primary);
        }

        .google:hover,
        .google:focus-visible {
            transform: translateY(-2px);
            border-color: #b8cce3;
            background: var(--primary-50);
            box-shadow: 0 14px 28px rgba(7, 79, 155, .10);
        }

        .footnote {
            margin: 22px 0 0;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.45;
            text-align: center;
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

        @keyframes cardIn {
            from {
                opacity: 0;
                transform: translateY(12px) scale(.99);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(17, 132, 91, .28);
            }
            70% {
                box-shadow: 0 0 0 9px rgba(17, 132, 91, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(17, 132, 91, 0);
            }
        }

        @keyframes floatSeal {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(12px);
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
                align-items: flex-start;
                min-height: 100vh;
                padding: 34px 20px;
                border-left: 0;
                background: transparent;
            }

            .login-card {
                max-width: 430px;
                margin: 0 auto;
                padding: 26px;
            }

            .mobile-brand {
                display: flex;
            }
        }

        @media (max-width: 480px) {
            .auth-panel {
                padding: 22px 14px;
            }

            .login-card {
                padding: 22px;
                border-radius: 16px;
            }

            .login-title {
                font-size: 26px;
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
                        <span class="brand-subtitle">Secretaria de Educacao</span>
                    </span>
                </div>

                <span class="status-pill">
                    <span class="status-dot" aria-hidden="true"></span>
                    Sistema ativo
                </span>
            </div>

            <div class="hero">
                <span class="eyebrow">Prefeitura Municipal de Umuarama</span>
                <h1>Gestao escolar simples, clara e conectada.</h1>
                <p>Um painel para apoiar a rotina da Secretaria de Educacao com acesso rapido a escolas, avaliacoes, merenda, pedidos e relatorios.</p>
            </div>

            <div class="module-grid" aria-hidden="true">

            </div>
        </section>

        <section class="auth-panel" aria-labelledby="login-title">
            <div class="login-card">
                <div class="mobile-brand">
                    <span class="brand-mark" aria-hidden="true">G</span>
                    <span>
                        <span class="brand-name">Gestao Edu</span>
                        <span class="brand-subtitle">Secretaria de Educacao</span>
                    </span>
                </div>

                <h2 class="login-title" id="login-title">Acessar o sistema</h2>
                <p class="intro">Entre com seu email institucional para continuar.</p>

                @if (session('status'))
                    <div class="alert">{{ session('status') }}</div>
                @endif

                <form method="POST" action="{{ $loginAction }}" autocomplete="on">
                    @csrf

                    <div class="field">
                        <label for="email">Email</label>
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
                        @error('email')
                            <p class="error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label for="password">Senha</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="current-password"
                            placeholder="Digite sua senha"
                            required
                        >
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
