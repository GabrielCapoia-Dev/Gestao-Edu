<style>
    .force-password-page,
    .force-password-page * {
        box-sizing: border-box;
    }

    .force-password-page {
        min-height: calc(100vh - 72px);
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 32px;
        background:
            linear-gradient(180deg, rgba(245, 248, 252, 0.95) 0%, rgba(237, 243, 250, 0.95) 100%),
            #f3f6fb;
        color: #101827;
        font-family: var(--font-family), Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    .force-password-card {
        width: min(920px, 100%);
        min-height: 520px;
        display: grid;
        grid-template-columns: minmax(280px, 0.9fr) minmax(360px, 1.1fr);
        border: 1px solid #d8e2ef;
        border-radius: 8px;
        background: #ffffff;
        overflow: hidden;
        box-shadow: 0 20px 55px rgba(15, 34, 97, 0.12);
    }

    .force-password-panel {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        padding: 36px;
        background:
            linear-gradient(150deg, rgba(255, 255, 255, 0.12) 0%, rgba(255, 255, 255, 0) 48%),
            linear-gradient(135deg, #0f2261 0%, #1557a6 55%, #1a6bc7 100%);
        color: #ffffff;
    }

    .force-password-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
    }

    .force-password-brand-mark {
        width: 42px;
        height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.14);
        border: 1px solid rgba(255, 255, 255, 0.22);
        font-size: 20px;
        font-weight: 800;
    }

    .force-password-brand-text {
        display: block;
        color: rgba(255, 255, 255, 0.82);
        font-size: 13px;
        line-height: 1.3;
    }

    .force-password-brand-name {
        display: block;
        margin-top: 2px;
        color: #ffffff;
        font-size: 18px;
        font-weight: 800;
        line-height: 1.2;
    }

    .force-password-panel-copy {
        margin-top: 56px;
    }

    .force-password-kicker {
        display: inline-flex;
        align-items: center;
        min-height: 30px;
        padding: 0 12px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.14);
        color: #ffffff;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: 0;
    }

    .force-password-title {
        max-width: 320px;
        margin: 18px 0 0;
        color: #ffffff;
        font-size: 32px;
        font-weight: 800;
        line-height: 1.12;
        letter-spacing: 0;
    }

    .force-password-description {
        max-width: 330px;
        margin: 14px 0 0;
        color: rgba(255, 255, 255, 0.82);
        font-size: 15px;
        line-height: 1.6;
    }

    .force-password-panel-note {
        margin: 36px 0 0;
        color: rgba(255, 255, 255, 0.68);
        font-size: 13px;
        line-height: 1.5;
    }

    .force-password-form-wrap {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 42px;
        background: #ffffff;
    }

    .force-password-form {
        width: min(100%, 390px);
    }

    .force-password-form-heading {
        margin: 0;
        color: #0f2261;
        font-size: 22px;
        font-weight: 800;
        line-height: 1.25;
        letter-spacing: 0;
    }

    .force-password-form-text {
        margin: 8px 0 24px;
        color: #526173;
        font-size: 14px;
        line-height: 1.55;
    }

    .force-password-field + .force-password-field {
        margin-top: 18px;
    }

    .force-password-label {
        display: block;
        margin-bottom: 8px;
        color: #1f2a3d;
        font-size: 14px;
        font-weight: 700;
        line-height: 1.3;
    }

    .force-password-input {
        display: block;
        width: 100%;
        min-width: 0;
        height: 46px;
        border: 1px solid #c8d5e6;
        border-radius: 8px;
        background: #ffffff;
        color: #101827;
        font-size: 15px;
        line-height: 46px;
        outline: none;
        padding: 0 14px;
        transition: border-color 160ms ease, box-shadow 160ms ease, background 160ms ease;
    }

    .force-password-input:focus {
        border-color: #1a6bc7;
        background: #fbfdff;
        box-shadow: 0 0 0 4px rgba(26, 107, 199, 0.13);
    }

    .force-password-error {
        margin: 8px 0 0;
        color: #b42318;
        font-size: 13px;
        line-height: 1.45;
    }

    .force-password-requirements {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px 12px;
        margin: 14px 0 0;
        padding: 0;
        list-style: none;
    }

    .force-password-requirement {
        display: flex;
        align-items: center;
        gap: 8px;
        min-width: 0;
        min-height: 28px;
        color: #526173;
        font-size: 13px;
        line-height: 1.25;
        transition: color 160ms ease;
    }

    .force-password-requirement-icon {
        width: 18px;
        height: 18px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        border: 1px solid #c8d5e6;
        border-radius: 999px;
        background: #ffffff;
        transition: border-color 160ms ease, background 160ms ease;
    }

    .force-password-requirement-icon::after {
        content: "";
        width: 6px;
        height: 6px;
        border-radius: 999px;
        background: #9aa9bb;
        transition: background 160ms ease, transform 160ms ease;
    }

    .force-password-requirement.is-valid {
        color: #176b3a;
    }

    .force-password-requirement.is-valid .force-password-requirement-icon {
        border-color: #9fd8b5;
        background: #edf9f2;
    }

    .force-password-requirement.is-valid .force-password-requirement-icon::after {
        background: #176b3a;
        transform: scale(1.2);
    }

    .force-password-match-message {
        margin: 8px 0 0;
        color: #b42318;
        font-size: 13px;
        line-height: 1.45;
    }

    .force-password-match-message.is-valid {
        color: #176b3a;
    }

    .force-password-actions {
        display: flex;
        justify-content: flex-end;
        margin-top: 24px;
    }

    .force-password-submit {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: auto;
        min-width: 178px;
        min-height: 44px;
        border: 0;
        border-radius: 8px;
        background: #1a6bc7;
        color: #ffffff;
        cursor: pointer;
        font-size: 14px;
        font-weight: 800;
        letter-spacing: 0;
        padding: 0 18px;
        box-shadow: 0 12px 24px rgba(26, 107, 199, 0.22);
        transition: background 160ms ease, box-shadow 160ms ease, transform 160ms ease;
    }

    .force-password-submit:hover {
        background: #1557a6;
        box-shadow: 0 14px 28px rgba(26, 107, 199, 0.27);
        transform: translateY(-1px);
    }

    .force-password-submit:focus {
        outline: none;
        box-shadow: 0 0 0 4px rgba(26, 107, 199, 0.18);
    }

    .force-password-submit:disabled {
        cursor: wait;
        opacity: 0.72;
        transform: none;
        box-shadow: none;
    }

    .force-password-submit-content {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .force-password-submit-spinner,
    .force-password-success-spinner {
        width: 16px;
        height: 16px;
        display: inline-block;
        border: 2px solid rgba(255, 255, 255, 0.45);
        border-top-color: #ffffff;
        border-radius: 999px;
        animation: force-password-spin 700ms linear infinite;
    }

    .force-password-success {
        position: fixed;
        inset: 0;
        z-index: 80;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
        background: rgba(8, 17, 36, 0.38);
        backdrop-filter: blur(2px);
    }

    .force-password-success-card {
        width: min(360px, 100%);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        border: 1px solid #d8e2ef;
        border-radius: 8px;
        background: #ffffff;
        color: #101827;
        padding: 28px;
        text-align: center;
        box-shadow: 0 24px 60px rgba(8, 17, 36, 0.22);
    }

    .force-password-success-card strong {
        color: #0f2261;
        font-size: 18px;
        line-height: 1.25;
    }

    .force-password-success-card span:last-child {
        color: #526173;
        font-size: 14px;
        line-height: 1.45;
    }

    .force-password-success-spinner {
        width: 30px;
        height: 30px;
        border-color: rgba(26, 107, 199, 0.22);
        border-top-color: #1a6bc7;
    }

    [x-cloak] {
        display: none !important;
    }

    @keyframes force-password-spin {
        to {
            transform: rotate(360deg);
        }
    }

    @media (max-width: 900px) {
        .force-password-page {
            min-height: calc(100vh - 40px);
            padding: 22px;
        }

        .force-password-card {
            width: min(620px, 100%);
            min-height: 0;
            grid-template-columns: 1fr;
        }

        .force-password-panel {
            min-height: 250px;
            padding: 28px;
        }

        .force-password-panel-copy {
            margin-top: 40px;
        }

        .force-password-title {
            max-width: none;
            font-size: 28px;
        }

        .force-password-description {
            max-width: none;
        }

        .force-password-panel-note {
            display: none;
        }

        .force-password-form-wrap {
            padding: 30px 28px;
        }
    }

    @media (max-width: 520px) {
        .force-password-page {
            min-height: calc(100vh - 20px);
            align-items: stretch;
            padding: 12px;
        }

        .force-password-card {
            width: 100%;
        }

        .force-password-panel,
        .force-password-form-wrap {
            padding: 22px;
        }

        .force-password-brand-mark {
            width: 38px;
            height: 38px;
        }

        .force-password-panel-copy {
            margin-top: 32px;
        }

        .force-password-title {
            font-size: 24px;
        }

        .force-password-form-heading {
            font-size: 20px;
        }

        .force-password-actions,
        .force-password-submit {
            width: 100%;
        }

        .force-password-requirements {
            grid-template-columns: 1fr;
        }
    }
</style>

<section
    class="force-password-page"
    aria-labelledby="force-password-title"
    x-data="{
        password: '',
        confirmation: '',
        submitting: false,
        hasMin() { return this.password.length >= 8 },
        hasUpper() { return /[A-Z]/.test(this.password) },
        hasLower() { return /[a-z]/.test(this.password) },
        hasNumber() { return /[0-9]/.test(this.password) },
        hasSymbol() { return /[^A-Za-z0-9]/.test(this.password) },
        passwordsMatch() {
            return this.password.length > 0
                && this.confirmation.length > 0
                && this.password === this.confirmation
        },
        showMismatch() {
            return this.confirmation.length > 0 && this.password !== this.confirmation
        },
    }"
>
    <form method="POST" action="{{ route('auth.force-password.update') }}" x-on:submit="submitting = true" class="force-password-card">
        @csrf

        <div class="force-password-panel">
            <div class="force-password-brand">
                <span class="force-password-brand-mark">G</span>
                <span>
                    <span class="force-password-brand-text">Sistema Integrado Municipal</span>
                    <span class="force-password-brand-name">Gestao Edu</span>
                </span>
            </div>

            <div class="force-password-panel-copy">
                <span class="force-password-kicker">Senha obrigatoria</span>
                <h1 id="force-password-title" class="force-password-title">Redefina sua senha</h1>
                <p class="force-password-description">
                    Para continuar no sistema, cadastre uma nova senha pessoal.
                </p>
            </div>

            <p class="force-password-panel-note">
                Essa etapa protege sua conta apos a aplicacao da senha padrao.
            </p>
        </div>

        <div class="force-password-form-wrap">
            <div class="force-password-form">
                <h2 class="force-password-form-heading">Crie sua nova senha</h2>
                <p class="force-password-form-text">
                    Use letras maiusculas e minusculas, numeros e simbolos.
                </p>

                <div class="force-password-field">
                    <label for="password" class="force-password-label">Nova senha</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        x-model="password"
                        x-bind:readonly="submitting"
                        autocomplete="new-password"
                        required
                        class="force-password-input"
                    />
                    @error('password')
                        <p class="force-password-error">{{ $message }}</p>
                    @enderror

                    <ul class="force-password-requirements" aria-label="Requisitos da senha" aria-live="polite">
                        <li class="force-password-requirement" x-bind:class="{ 'is-valid': hasMin() }">
                            <span class="force-password-requirement-icon" aria-hidden="true"></span>
                            Minimo de 8 caracteres
                        </li>
                        <li class="force-password-requirement" x-bind:class="{ 'is-valid': hasUpper() }">
                            <span class="force-password-requirement-icon" aria-hidden="true"></span>
                            Letras maiusculas
                        </li>
                        <li class="force-password-requirement" x-bind:class="{ 'is-valid': hasLower() }">
                            <span class="force-password-requirement-icon" aria-hidden="true"></span>
                            Letras minusculas
                        </li>
                        <li class="force-password-requirement" x-bind:class="{ 'is-valid': hasNumber() }">
                            <span class="force-password-requirement-icon" aria-hidden="true"></span>
                            Numeros
                        </li>
                        <li class="force-password-requirement" x-bind:class="{ 'is-valid': hasSymbol() }">
                            <span class="force-password-requirement-icon" aria-hidden="true"></span>
                            Caracteres especiais
                        </li>
                    </ul>
                </div>

                <div class="force-password-field">
                    <label for="password_confirmation" class="force-password-label">Confirmar senha</label>
                    <input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        x-model="confirmation"
                        x-bind:readonly="submitting"
                        autocomplete="new-password"
                        required
                        class="force-password-input"
                    />
                    <p class="force-password-match-message" x-cloak x-show="showMismatch()">
                        As senhas devem ser iguais.
                    </p>
                    <p class="force-password-match-message is-valid" x-cloak x-show="passwordsMatch()">
                        As senhas estao iguais.
                    </p>
                </div>

                <div class="force-password-actions">
                    <button
                        type="submit"
                        class="force-password-submit"
                        x-bind:disabled="submitting"
                    >
                        <span x-show="! submitting">Salvar nova senha</span>
                        <span class="force-password-submit-content" x-cloak x-show="submitting">
                            <span class="force-password-submit-spinner" aria-hidden="true"></span>
                            Redefinindo...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </form>

    <div
        class="force-password-success"
        x-cloak
        x-show="submitting"
        x-transition.opacity
        role="status"
        aria-live="assertive"
    >
        <div class="force-password-success-card">
            <span class="force-password-success-spinner" aria-hidden="true"></span>
            <strong>Redefinindo senha</strong>
            <span>Voltando para a tela de login...</span>
        </div>
    </div>
</section>
