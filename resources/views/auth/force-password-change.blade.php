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
            --blue-900: #071232;
            --blue-800: #0f2261;
            --blue-700: #17368d;
            --blue-600: #1a6bc7;
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
            background:
                linear-gradient(135deg, rgba(7, 18, 50, .92), rgba(23, 54, 141, .74)),
                url("{{ asset('images/background.webp') }}") center / cover no-repeat fixed;
            color: var(--gray-700);
        }

        .page {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 28px;
        }

        .card {
            width: min(960px, 100%);
            display: grid;
            grid-template-columns: minmax(0, .95fr) minmax(320px, .75fr);
            overflow: hidden;
            border-radius: 10px;
            background: var(--white);
            box-shadow: 0 24px 80px rgba(7, 18, 50, .32);
        }

        .panel {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 42px;
            padding: 42px;
            background: linear-gradient(145deg, var(--blue-900), var(--blue-700));
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

        .brand-title,
        .brand-subtitle {
            display: block;
        }

        .brand-subtitle {
            margin-top: 2px;
            opacity: .78;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .panel h1 {
            margin: 0 0 14px;
            font-size: clamp(32px, 4vw, 54px);
            line-height: 1;
            letter-spacing: 0;
        }

        .panel p {
            margin: 0;
            color: rgba(255, 255, 255, .82);
            font-size: 16px;
            line-height: 1.55;
        }

        .form-wrap {
            padding: 42px;
        }

        .form-wrap h2 {
            margin: 0 0 8px;
            color: var(--blue-900);
            font-size: 24px;
            letter-spacing: 0;
        }

        .intro {
            margin: 0 0 24px;
            color: var(--gray-500);
            font-size: 14px;
            line-height: 1.45;
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

        .field input {
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
            border-radius: 8px;
            background: transparent;
            color: var(--blue-600);
            cursor: pointer;
            transform: translateY(-50%);
            transition: background .18s ease, color .18s ease, box-shadow .18s ease;
        }

        .password-toggle:hover,
        .password-toggle:focus-visible {
            background: var(--gray-100);
            color: var(--blue-700);
            outline: none;
            box-shadow: 0 0 0 3px rgba(26, 107, 199, .12);
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
            color: #b42318;
            font-size: 13px;
            line-height: 1.35;
        }

        .requirements {
            display: grid;
            gap: 7px;
            margin: 0 0 18px;
            padding: 0;
            list-style: none;
            color: var(--gray-500);
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
            background: var(--gray-200);
        }

        .requirements li.valid {
            color: #067647;
        }

        .requirements li.valid::before {
            background: var(--green-500);
        }

        .match {
            min-height: 18px;
            margin: -6px 0 18px;
            color: #b42318;
            font-size: 13px;
        }

        .match.valid {
            color: #067647;
        }

        .submit {
            width: 100%;
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 8px;
            background: var(--blue-600);
            color: var(--white);
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
        }

        .submit:hover {
            background: var(--blue-700);
        }

        .submit:disabled {
            cursor: wait;
            opacity: .72;
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

                <div>
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
