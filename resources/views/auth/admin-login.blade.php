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
            --blue-900: #071232;
            --blue-800: #0f2261;
            --blue-700: #17368d;
            --blue-600: #1a6bc7;
            --cyan-500: #16a3c7;
            --green-500: #21a67a;
            --gray-50: #f6f8fb;
            --gray-100: #eef2f7;
            --gray-200: #dde5f0;
            --gray-500: #667085;
            --gray-700: #344054;
            --white: #ffffff;
            font-family: Arial, Helvetica, sans-serif;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            background: var(--gray-50);
            color: var(--gray-700);
        }

        .auth-page {
            min-height: 100vh;
            display: grid;
            grid-template-columns: minmax(0, 1fr) 420px;
        }

        .auth-media {
            position: relative;
            min-height: 100vh;
            overflow: hidden;
            background:
                linear-gradient(135deg, rgba(7, 18, 50, .92), rgba(23, 54, 141, .72)),
                url("{{ asset('images/background.webp') }}") center / cover no-repeat;
        }

        .auth-media::after {
            content: "";
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 28% 22%, rgba(255, 255, 255, .18), transparent 28%);
        }

        .auth-media-content {
            position: relative;
            z-index: 1;
            display: flex;
            min-height: 100%;
            flex-direction: column;
            justify-content: space-between;
            padding: 48px;
            color: var(--white);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 14px;
            font-weight: 700;
        }

        .brand-mark {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            border-radius: 8px;
            background: rgba(255, 255, 255, .16);
            border: 1px solid rgba(255, 255, 255, .32);
            font-size: 22px;
        }

        .brand-title {
            display: block;
            font-size: 18px;
        }

        .brand-subtitle {
            display: block;
            margin-top: 2px;
            opacity: .78;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .media-copy {
            max-width: 680px;
        }

        .media-copy h1 {
            margin: 0 0 16px;
            font-size: clamp(34px, 5vw, 64px);
            line-height: 1;
            letter-spacing: 0;
        }

        .media-copy p {
            max-width: 560px;
            margin: 0;
            color: rgba(255, 255, 255, .82);
            font-size: 17px;
            line-height: 1.55;
        }

        .auth-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 32px;
            background: var(--white);
        }

        .login-card {
            width: 100%;
            max-width: 360px;
        }

        .login-card h2 {
            margin: 0 0 8px;
            color: var(--blue-900);
            font-size: 26px;
            letter-spacing: 0;
        }

        .login-card .intro {
            margin: 0 0 26px;
            color: var(--gray-500);
            font-size: 14px;
            line-height: 1.45;
        }

        .alert {
            margin-bottom: 18px;
            padding: 12px 14px;
            border-radius: 8px;
            background: #ecfdf3;
            border: 1px solid #abefc6;
            color: #067647;
            font-size: 13px;
        }

        .field {
            margin-bottom: 16px;
        }

        .field label {
            display: block;
            margin-bottom: 7px;
            color: var(--blue-900);
            font-size: 13px;
            font-weight: 700;
        }

        .field input[type="email"],
        .field input[type="password"] {
            width: 100%;
            min-height: 44px;
            padding: 10px 12px;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            background: var(--white);
            color: var(--blue-900);
            font-size: 15px;
            outline: none;
        }

        .field input:focus {
            border-color: var(--blue-600);
            box-shadow: 0 0 0 3px rgba(26, 107, 199, .14);
        }

        .error {
            margin: 7px 0 0;
            color: #b42318;
            font-size: 13px;
            line-height: 1.35;
        }

        .options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin: 6px 0 22px;
            color: var(--gray-500);
            font-size: 13px;
        }

        .remember {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .remember input {
            width: 16px;
            height: 16px;
            margin: 0;
            accent-color: var(--blue-600);
        }

        .submit,
        .google {
            width: 100%;
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .submit {
            border: 0;
            background: var(--blue-600);
            color: var(--white);
        }

        .submit:hover {
            background: var(--blue-700);
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 22px 0;
            color: var(--gray-500);
            font-size: 12px;
        }

        .divider::before,
        .divider::after {
            content: "";
            height: 1px;
            flex: 1;
            background: var(--gray-200);
        }

        .google {
            border: 1px solid var(--gray-200);
            background: var(--white);
            color: var(--gray-700);
        }

        .google:hover {
            background: var(--gray-100);
        }

        .footnote {
            margin: 20px 0 0;
            color: var(--gray-500);
            font-size: 12px;
            line-height: 1.45;
            text-align: center;
        }

        @media (max-width: 900px) {
            .auth-page {
                grid-template-columns: 1fr;
            }

            .auth-media {
                min-height: 260px;
            }

            .auth-media-content {
                min-height: 260px;
                padding: 28px;
            }

            .media-copy h1 {
                font-size: 34px;
            }

            .auth-panel {
                min-height: auto;
                padding: 32px 20px;
            }
        }
    </style>
</head>
<body>
    <main class="auth-page">
        <section class="auth-media" aria-label="Gestao Edu">
            <div class="auth-media-content">
                <div class="brand">
                    <span class="brand-mark" aria-hidden="true">G</span>
                    <span>
                        <span class="brand-title">Gestao Edu</span>
                        <span class="brand-subtitle">Secretaria Municipal de Educacao</span>
                    </span>
                </div>

                <div class="media-copy">
                    <h1>Sistema ativo</h1>
                    <p>Ambiente administrativo para gestao escolar, avaliacoes, estoque, pedidos e relatorios.</p>
                </div>
            </div>
        </section>

        <section class="auth-panel" aria-labelledby="login-title">
            <div class="login-card">
                <h2 id="login-title">Entrar</h2>
                <p class="intro">Use suas credenciais institucionais para acessar o painel.</p>

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
