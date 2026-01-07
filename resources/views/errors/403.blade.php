{{-- resources/views/errors/403.blade.php --}}
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 — Acesso negado | Educação Especial Municipal</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #ffffff;
            color: #333333;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .header {
            background: #074f9b;
            border-bottom: 3px solid #053d7a;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(7, 79, 155, 0.15);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .logo {
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .header-title {
            display: flex;
            flex-direction: column;
        }

        .system-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: #ffffff;
        }

        .system-desc {
            font-size: 0.9rem;
            color: #e0e9f5;
        }

        .login-btn {
            background: #0b6acfff;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.3);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 60px 20px;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .error-content {
            text-align: center;
            max-width: 800px;
        }

        .error-icon {
            font-size: 8rem;
            margin-bottom: 20px;
            animation: shake 2s ease-in-out infinite;
        }

        @keyframes shake {
            0%, 100% { transform: rotate(0deg); }
            25% { transform: rotate(-5deg); }
            75% { transform: rotate(5deg); }
        }

        .error-code {
            display: inline-block;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: #ffffff;
            padding: 12px 24px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }

        .error-title {
            font-size: 3rem;
            font-weight: 700;
            color: #074f9b;
            margin-bottom: 20px;
            line-height: 1.2;
        }

        .error-subtitle {
            font-size: 1.5rem;
            color: #666666;
            margin-bottom: 30px;
        }

        .error-text {
            color: #666666;
            font-size: 1.1rem;
            line-height: 1.8;
            margin-bottom: 40px;
        }

        .alert-box {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            border-left: 4px solid #ef4444;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 40px;
            text-align: left;
        }

        .alert-box-title {
            font-size: 1.2rem;
            color: #dc2626;
            font-weight: 600;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-box-text {
            color: #666666;
            line-height: 1.6;
        }

        .tags {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: center;
            margin-bottom: 40px;
        }

        .tag {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            background: #074f9b;
            color: #ffffff;
            border: none;
            padding: 14px 28px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn:hover {
            background: #053d7a;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(7, 79, 155, 0.3);
        }

        .btn-secondary {
            background: transparent;
            border: 2px solid #074f9b;
            color: #074f9b;
        }

        .btn-secondary:hover {
            background: #e8f0f8;
        }

        .help-card {
            background: linear-gradient(135deg, #f0f7ff 0%, #e8f0f8 100%);
            border-radius: 12px;
            padding: 30px;
            border: 2px solid #d0e3f5;
            margin-top: 40px;
        }

        .help-card-title {
            font-size: 1.3rem;
            color: #074f9b;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            justify-content: center;
        }

        .help-card-text {
            color: #666666;
            line-height: 1.6;
        }

        .footer {
            background: #f0f7ff;
            border-top: 2px solid #d0e3f5;
            padding: 30px 40px;
            text-align: center;
            color: #666666;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 20px;
                padding: 20px;
            }

            .error-icon {
                font-size: 5rem;
            }

            .error-title {
                font-size: 2rem;
            }

            .error-subtitle {
                font-size: 1.2rem;
            }

            .actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <header class="header">
        <div class="header-left">
            <div class="logo">
                <img src="{{ asset('images/logo-educação-especial.png') }}" alt="Educação Especial">
            </div>
            <div class="header-title">
                <div class="system-name">Educação Especial Centralizada</div>
                <div class="system-desc">Atendimento Especializado e Inclusivo</div>
            </div>
        </div>
        <div>
            @auth
                <a href="/admin" class="login-btn">
                    <span>🏠</span>
                    Painel Administrativo
                </a>
            @else
                <a href="/admin/login" class="login-btn">
                    <span>🔐</span>
                    Área Administrativa
                </a>
            @endauth
        </div>
    </header>

    <div class="container">
        <div class="error-content">
            <div class="error-icon">🔒</div>
            <div class="error-code">⚠️ ERRO 403 - ACESSO NEGADO</div>
            
            <h1 class="error-title">Acesso Negado</h1>
            <p class="error-subtitle">Você não tem permissão para acessar esta área</p>
            
            <p class="error-text">
                Infelizmente, você não possui as permissões necessárias para acessar esta página.
                Esta é uma área restrita do Sistema de Educação Especial.
            </p>

            <div class="alert-box">
                <div class="alert-box-title">
                    <span>🔐</span>
                    Por que estou vendo esta mensagem?
                </div>
                <div class="alert-box-text">
                    Seu usuário não possui as permissões adequadas ou você tentou acessar uma área administrativa.
                    Se você acredita que deveria ter acesso, entre em contato com o administrador do sistema para
                    solicitar a revisão de suas permissões. Certifique-se de que seu usuário está vinculado ao setor
                    ou escola corretos.
                </div>
            </div>

            <div class="tags">
                <span class="tag">🚫 Área restrita</span>
                <span class="tag">🔐 Sem permissão</span>
                <span class="tag">👤 Acesso limitado</span>
            </div>

            <div class="actions">
                <button class="btn btn-secondary" onclick="history.back()">
                    <span>↩️</span> Voltar à página anterior
                </button>
                @auth
                    <a href="/admin" class="btn">
                        <span>🏠</span> Ir para o painel principal
                    </a>
                @else
                    <a href="/admin/login" class="btn">
                        <span>🔐</span> Fazer login no sistema
                    </a>
                @endauth
            </div>

            <div class="help-card">
                <div class="help-card-title">
                    <span>🆘</span>
                    Precisa de ajuda?
                </div>
                <div class="help-card-text">
                    Se você acredita que deveria ter acesso a esta área, entre em contato com o administrador do sistema.
                    Informe qual página você tentou acessar e qual é sua função no sistema para que possamos revisar
                    suas permissões adequadamente.
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        <small>&copy; {{ date('Y') }} Prefeitura Municipal de Umuarama — Sistema de Educação Especial</small>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const backButton = document.querySelector('.btn-secondary');
            if (backButton && window.history.length <= 1) {
                backButton.style.display = 'none';
            }
        });
    </script>
</body>

</html>