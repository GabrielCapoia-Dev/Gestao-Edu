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
    }
</style>

<section class="force-password-page" aria-labelledby="force-password-title">
    <form wire:submit="salvar" class="force-password-card">
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
                        type="password"
                        wire:model.defer="password"
                        autocomplete="new-password"
                        class="force-password-input"
                    />
                    @error('password')
                        <p class="force-password-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="force-password-field">
                    <label for="password_confirmation" class="force-password-label">Confirmar senha</label>
                    <input
                        id="password_confirmation"
                        type="password"
                        wire:model.defer="password_confirmation"
                        autocomplete="new-password"
                        class="force-password-input"
                    />
                </div>

                <div class="force-password-actions">
                    <button
                        type="submit"
                        class="force-password-submit"
                        wire:loading.attr="disabled"
                    >
                        Salvar nova senha
                    </button>
                </div>
            </div>
        </div>
    </form>
</section>
