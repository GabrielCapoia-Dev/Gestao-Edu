<x-filament-panels::page>
    <style>
        .force-password-screen {
            min-height: min(720px, calc(100vh - 48px));
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
            background:
                radial-gradient(circle at top left, rgba(26, 107, 199, 0.14), transparent 34%),
                linear-gradient(135deg, #eef4fb 0%, #f8fafc 52%, #e9f1fb 100%);
        }

        .force-password-card {
            width: min(100%, 460px);
            border: 1px solid #d9e3f1;
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 18px 48px rgba(15, 34, 97, 0.14);
            overflow: hidden;
        }

        .force-password-header {
            padding: 28px 28px 22px;
            border-bottom: 1px solid #e6edf7;
            background: linear-gradient(135deg, #0f5aa6 0%, #1a6bc7 100%);
            color: #ffffff;
        }

        .force-password-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 28px;
            padding: 0 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.16);
            color: #ffffff;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0;
        }

        .force-password-title {
            margin: 14px 0 0;
            color: #ffffff;
            font-size: 26px;
            font-weight: 800;
            line-height: 1.15;
            letter-spacing: 0;
        }

        .force-password-description {
            margin: 10px 0 0;
            color: rgba(255, 255, 255, 0.86);
            font-size: 14px;
            line-height: 1.55;
        }

        .force-password-form {
            padding: 26px 28px 28px;
        }

        .force-password-field + .force-password-field {
            margin-top: 18px;
        }

        .force-password-label {
            display: block;
            margin-bottom: 8px;
            color: #16213a;
            font-size: 14px;
            font-weight: 700;
        }

        .force-password-input {
            display: block;
            width: 100%;
            height: 46px;
            border: 1px solid #c7d3e4;
            border-radius: 8px;
            background: #ffffff;
            color: #101827;
            font-size: 15px;
            outline: none;
            padding: 0 14px;
            transition: border-color 160ms ease, box-shadow 160ms ease;
        }

        .force-password-input:focus {
            border-color: #1a6bc7;
            box-shadow: 0 0 0 4px rgba(26, 107, 199, 0.14);
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
            min-height: 42px;
            min-width: 164px;
            border: 0;
            border-radius: 8px;
            background: #174ea6;
            color: #ffffff;
            cursor: pointer;
            font-size: 14px;
            font-weight: 800;
            padding: 0 18px;
            transition: background 160ms ease, transform 160ms ease, box-shadow 160ms ease;
            box-shadow: 0 10px 22px rgba(23, 78, 166, 0.22);
        }

        .force-password-submit:hover {
            background: #0f3f8a;
            transform: translateY(-1px);
            box-shadow: 0 14px 26px rgba(23, 78, 166, 0.28);
        }

        .force-password-submit:disabled {
            cursor: wait;
            opacity: 0.72;
            transform: none;
            box-shadow: none;
        }

        @media (max-width: 640px) {
            .force-password-screen {
                min-height: calc(100vh - 24px);
                padding: 16px;
            }

            .force-password-header,
            .force-password-form {
                padding-left: 20px;
                padding-right: 20px;
            }

            .force-password-title {
                font-size: 22px;
            }

            .force-password-actions,
            .force-password-submit {
                width: 100%;
            }
        }
    </style>

    <div class="force-password-screen">
        <form wire:submit="salvar" class="force-password-card">
            <div class="force-password-header">
                <span class="force-password-badge">Senha obrigatoria</span>
                <h1 class="force-password-title">Redefina sua senha</h1>
                <p class="force-password-description">
                    Para continuar no sistema, cadastre uma nova senha pessoal.
                </p>
            </div>

            <div class="force-password-form">
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
        </form>
    </div>
</x-filament-panels::page>
