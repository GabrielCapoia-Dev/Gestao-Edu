<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Redefinir senha - Gestao Edu</title>
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

        body {
            min-height: 100vh;
            margin: 0;
            background:
                radial-gradient(circle at 12% 10%, rgba(7, 79, 155, .20), transparent 28%),
                radial-gradient(circle at 86% 82%, rgba(244, 185, 66, .15), transparent 30%),
                radial-gradient(circle at 70% 12%, rgba(7, 79, 155, .10), transparent 30%),
                linear-gradient(135deg, #fbfdff 0%, #edf6ff 48%, #ffffff 100%);
            color: var(--text);
        }

        .page {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: clamp(20px, 4vw, 46px);
        }

        .card {
            width: min(1080px, 100%);
            display: grid;
            grid-template-columns: minmax(340px, .95fr) minmax(340px, .82fr);
            overflow: hidden;
            border: 1px solid rgba(7, 79, 155, .14);
            border-radius: 22px;
            background: rgba(255, 255, 255, .88);
            box-shadow: 0 28px 82px rgba(7, 79, 155, .16);
            animation: cardIn .48s .08s ease both;
        }

        .panel {
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 42px;
            min-height: 620px;
            padding: clamp(34px, 5vw, 52px);
            overflow: hidden;
            border-right: 1px solid rgba(7, 79, 155, .10);
            background:
                linear-gradient(145deg, rgba(255, 255, 255, .92), rgba(239, 247, 255, .78)),
                linear-gradient(135deg, rgba(7, 79, 155, .10), rgba(255, 255, 255, .26));
            color: var(--ink);
        }

        .panel::before {
            content: "";
            position: absolute;
            right: -78px;
            top: 96px;
            width: 260px;
            height: 260px;
            border-radius: 999px;
            border: 24px solid rgba(7, 79, 155, .09);
            background: radial-gradient(circle, rgba(255, 255, 255, .74) 0 43%, rgba(7, 79, 155, .04) 44% 100%);
            pointer-events: none;
        }

        .panel::after {
            content: "";
            position: absolute;
            left: -92px;
            bottom: -132px;
            width: 330px;
            height: 330px;
            border-radius: 42% 58% 36% 64%;
            background: linear-gradient(145deg, rgba(7, 79, 155, .14), rgba(7, 79, 155, .03));
            border: 1px solid rgba(7, 79, 155, .10);
            pointer-events: none;
        }

        .brand {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 14px;
            font-weight: 800;
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

        .brand-title,
        .brand-subtitle {
            display: block;
            line-height: 1.12;
        }

        .brand-title {
            color: var(--ink);
            font-size: 20px;
            font-weight: 800;
        }

        .brand-subtitle {
            margin-top: 5px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        .panel-content {
            position: relative;
            z-index: 1;
            max-width: 500px;
        }

        .panel h1 {
            margin: 0 0 14px;
            color: var(--ink);
            font-size: clamp(38px, 5vw, 64px);
            line-height: .98;
            letter-spacing: 0;
        }

        .panel p {
            margin: 0;
            color: var(--muted);
            font-size: clamp(15px, 1.4vw, 18px);
            line-height: 1.65;
        }

        .form-wrap {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: clamp(34px, 5vw, 52px);
            background: var(--white);
        }

        .form-wrap h2 {
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

        .field {
            margin-bottom: 16px;
        }

        .field label {
            display: block;
            margin-bottom: 7px;
            color: var(--ink);
            font-size: 13px;
            font-weight: 800;
        }

        .field input {
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

        .field input:hover {
            background: var(--white);
            border-color: #b8cce3;
        }

        .field input:focus {
            background: var(--white);
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(7, 79, 155, .12);
        }

        .password-field {
            position: relative;
        }

        .password-field input {
            padding-right: 46px;
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 7px;
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            padding: 0;
            border: 0;
            border-radius: 10px;
            background: transparent;
            color: var(--primary);
            cursor: pointer;
            transform: translateY(-50%);
            transition: background .18s ease, color .18s ease, box-shadow .18s ease;
        }

        .password-toggle:hover,
        .password-toggle:focus-visible {
            background: var(--primary-50);
            color: var(--primary-700);
            outline: none;
            box-shadow: 0 0 0 3px rgba(7, 79, 155, .10);
        }

        .password-toggle svg {
            width: 18px;
            height: 18px;
            fill: none;
            stroke: currentColor;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-width: 2;
        }

        .password-toggle .eye-off,
        .password-toggle.is-visible .eye {
            display: none;
        }

        .password-toggle.is-visible .eye-off {
            display: block;
        }

        .error {
            margin: 7px 0 0;
            color: var(--danger);
            font-size: 13px;
            line-height: 1.35;
        }

        .requirements {
            display: grid;
            gap: 7px;
            margin: 0 0 18px;
            padding: 0;
            list-style: none;
            color: var(--muted);
            font-size: 13px;
        }

        .requirements li {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .requirements li::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: var(--line);
        }

        .requirements li.valid {
            color: var(--success);
        }

        .requirements li.valid::before {
            background: var(--green);
        }

        .match {
            min-height: 18px;
            margin: -6px 0 18px;
            color: var(--danger);
            font-size: 13px;
        }

        .match.valid {
            color: var(--success);
        }

        .submit {
            width: 100%;
            min-height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 12px;
            background: var(--primary);
            color: var(--white);
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 16px 34px rgba(7, 79, 155, .24);
            transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
        }

        .submit:hover,
        .submit:focus-visible {
            transform: translateY(-2px);
            background: var(--primary-700);
            box-shadow: 0 20px 44px rgba(7, 79, 155, .30);
        }

        .submit:disabled {
            cursor: wait;
            opacity: .72;
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

        @media (max-width: 820px) {
            .page {
                padding: 16px;
            }

            .card {
                grid-template-columns: 1fr;
            }

            .panel,
            .form-wrap {
                padding: 28px;
            }

            .panel {
                min-height: auto;
                border-right: 0;
                border-bottom: 1px solid rgba(7, 79, 155, .10);
            }

            .panel::before {
                right: -120px;
                top: -52px;
            }

            .panel h1 {
                font-size: 36px;
            }
        }

        @media (max-width: 480px) {
            .page {
                padding: 14px;
            }

            .card {
                border-radius: 16px;
            }

            .panel,
            .form-wrap {
                padding: 24px;
            }

            .form-wrap h2 {
                font-size: 26px;
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
    <main class="page">
        <form class="card" method="POST" action="{{ route('auth.force-password.update') }}" id="force-password-form">
            @csrf

            <section class="panel" aria-labelledby="force-password-title">
                <div class="brand">
                    <span class="brand-mark" aria-hidden="true">G</span>
                    <span>
                        <span class="brand-title">Gestao Edu</span>
                        <span class="brand-subtitle">Senha obrigatoria</span>
                    </span>
                </div>

                <div class="panel-content">
                    <h1 id="force-password-title">Redefina sua senha</h1>
                    <p>Para continuar no sistema, cadastre uma senha pessoal usando letras, numeros e simbolos.</p>
                </div>
            </section>

            <section class="form-wrap" aria-label="Formulario de redefinicao de senha">
                <h2>Criar nova senha</h2>
                <p class="intro">Depois de salvar, voce voltara para o login e entrara com a nova senha.</p>

                <div class="field">
                    <label for="password">Nova senha</label>
                    <div class="password-field">
                        <input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="new-password"
                            required
                        >
                        <button class="password-toggle" type="button" data-password-toggle="password" aria-label="Mostrar senha" aria-pressed="false">
                            <svg class="eye" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg class="eye-off" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M3 3l18 18"></path>
                                <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"></path>
                                <path d="M7.1 7.1C4.2 8.8 2.5 12 2.5 12s3.5 6 9.5 6c1.5 0 2.9-.4 4.1-1"></path>
                                <path d="M12 6c6 0 9.5 6 9.5 6a15.2 15.2 0 0 1-2.6 3.2"></path>
                            </svg>
                        </button>
                    </div>
                    @error('password')
                        <p class="error">{{ $message }}</p>
                    @enderror
                </div>

                <ul class="requirements" aria-label="Requisitos da senha">
                    <li data-rule="min">Minimo de 8 caracteres</li>
                    <li data-rule="upper">Letra maiuscula</li>
                    <li data-rule="lower">Letra minuscula</li>
                    <li data-rule="number">Numero</li>
                    <li data-rule="symbol">Caractere especial</li>
                </ul>

                <div class="field">
                    <label for="password_confirmation">Confirmar senha</label>
                    <div class="password-field">
                        <input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            required
                        >
                        <button class="password-toggle" type="button" data-password-toggle="password_confirmation" aria-label="Mostrar senha" aria-pressed="false">
                            <svg class="eye" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg class="eye-off" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M3 3l18 18"></path>
                                <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"></path>
                                <path d="M7.1 7.1C4.2 8.8 2.5 12 2.5 12s3.5 6 9.5 6c1.5 0 2.9-.4 4.1-1"></path>
                                <path d="M12 6c6 0 9.5 6 9.5 6a15.2 15.2 0 0 1-2.6 3.2"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <p class="match" id="password-match" aria-live="polite"></p>

                <button class="submit" type="submit" id="submit-button">Salvar nova senha</button>
            </section>
        </form>
    </main>

    <script>
        (function () {
            var form = document.getElementById('force-password-form');
            var password = document.getElementById('password');
            var confirmation = document.getElementById('password_confirmation');
            var match = document.getElementById('password-match');
            var submit = document.getElementById('submit-button');
            var rules = {
                min: function (value) { return value.length >= 8; },
                upper: function (value) { return /[A-Z]/.test(value); },
                lower: function (value) { return /[a-z]/.test(value); },
                number: function (value) { return /[0-9]/.test(value); },
                symbol: function (value) { return /[^A-Za-z0-9]/.test(value); }
            };

            document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var input = document.getElementById(button.getAttribute('data-password-toggle'));
                    if (! input) return;

                    var visible = input.type === 'password';
                    input.type = visible ? 'text' : 'password';
                    button.classList.toggle('is-visible', visible);
                    button.setAttribute('aria-pressed', visible ? 'true' : 'false');
                    button.setAttribute('aria-label', visible ? 'Ocultar senha' : 'Mostrar senha');
                });
            });

            function refresh() {
                var value = password.value || '';

                Object.keys(rules).forEach(function (rule) {
                    var item = document.querySelector('[data-rule="' + rule + '"]');
                    if (! item) return;
                    item.classList.toggle('valid', rules[rule](value));
                });

                if (! confirmation.value) {
                    match.textContent = '';
                    match.className = 'match';
                    return;
                }

                if (value === confirmation.value) {
                    match.textContent = 'As senhas estao iguais.';
                    match.className = 'match valid';
                } else {
                    match.textContent = 'As senhas devem ser iguais.';
                    match.className = 'match';
                }
            }

            password.addEventListener('input', refresh);
            confirmation.addEventListener('input', refresh);
            form.addEventListener('submit', function () {
                submit.disabled = true;
                submit.textContent = 'Salvando...';
            });
        })();
    </script>
</body>
</html>
