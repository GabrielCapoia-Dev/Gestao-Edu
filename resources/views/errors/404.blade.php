{{-- resources/views/errors/404.blade.php --}}
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 — Página não encontrada | Educação Especial Municipal</title>
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
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        .error-code {
            display: inline-block;
            background: linear-gradient(135deg, #074f9b 0%, #053d7a 100%);
            color: #ffffff;
            padding: 12px 24px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(7, 79, 155, 0.2);
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

        .info-card {
            background: linear-gradient(135deg, #f0f7ff 0%, #e8f0f8 100%);
            border-radius: 12px;
            padding: 30px;
            border: 2px solid #d0e3f5;
            margin-bottom: 40px;
            text-align: left;
        }

        .info-card-title {
            font-size: 1.2rem;
            color: #074f9b;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-card-text {
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
            background: rgba(7, 79, 155, 0.1);
            color: #074f9b;
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
            <div class="error-icon">🔍</div>
            <div class="error-code">📍 ERRO 404 - PÁGINA NÃO ENCONTRADA</div>
            
            <h1 class="error-title">Ops! Página não encontrada</h1>
            <p class="error-subtitle">Parece que você se perdeu no caminho...</p>
            
            <p class="error-text">
                A página que você está procurando pode ter sido removida ou o link pode estar incorreto.
                Não se preocupe, vamos ajudá-lo a encontrar o que precisa!
            </p>

            <div class="info-card">
                <div class="info-card-title">
                    <span>💡</span>
                    O que pode ter acontecido?
                </div>
                <div class="info-card-text">
                    A URL pode ter sido digitada incorretamente, a página pode ter sido movida para outro endereço,
                    ou o conteúdo pode ter sido removido do sistema. Verifique o endereço digitado ou use os botões
                    abaixo para navegar até uma área válida do sistema.
                </div>
            </div>

            <div class="tags">
                <span class="tag">🔗 Link quebrado</span>
                <span class="tag">📄 Página removida</span>
                <span class="tag">🔍 URL incorreta</span>
            </div>

            <div class="actions">
                <button class="btn btn-secondary" onclick="history.back()">
                    <span>↩️</span> Voltar à página anterior
                </button>
                <a href="/" class="btn">
                    <span>🏠</span> Ir para a página inicial
                </a>
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