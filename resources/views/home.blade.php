<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema SRM - Sala de Recursos Multifuncionais</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f0f7ff 0%, #e6f2ff 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 10px 40px rgba(7, 79, 155, 0.1);
            max-width: 600px;
            width: 100%;
            padding: 60px 50px;
            text-align: center;
            border: 1px solid rgba(7, 79, 155, 0.08);
        }

        .logo-wrapper {
            margin-bottom: 35px;
        }

        .logo {
            max-width: 120px;
            width: 100%;
            height: auto;
            filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.08));
        }

        .badge {
            display: inline-block;
            background: linear-gradient(135deg, #074F9B 0%, #0a63c4 100%);
            color: white;
            padding: 8px 24px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 20px;
            text-transform: uppercase;
            box-shadow: 0 4px 12px rgba(7, 79, 155, 0.2);
        }

        h1 {
            color: #074F9B;
            font-size: 2.5rem;
            margin-bottom: 15px;
            font-weight: 700;
            line-height: 1.2;
        }

        .subtitle {
            color: #0a63c4;
            font-size: 1.3rem;
            margin-bottom: 25px;
            font-weight: 500;
        }

        .description {
            color: #4a5568;
            font-size: 1.05rem;
            margin-bottom: 35px;
            line-height: 1.8;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        .features {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
            margin-bottom: 40px;
            text-align: left;
        }

        .feature-item {
            background: #f8fbff;
            padding: 18px 20px;
            border-radius: 12px;
            border-left: 4px solid #074F9B;
            transition: all 0.3s ease;
        }

        .feature-item:hover {
            background: #f0f7ff;
            transform: translateX(5px);
        }

        .feature-icon {
            display: inline-block;
            width: 24px;
            height: 24px;
            background: #074F9B;
            border-radius: 50%;
            margin-right: 12px;
            vertical-align: middle;
            position: relative;
        }

        .feature-icon::after {
            content: '✓';
            color: white;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 14px;
            font-weight: bold;
        }

        .feature-text {
            display: inline-block;
            vertical-align: middle;
            color: #2d3748;
            font-size: 0.95rem;
            font-weight: 500;
            max-width: calc(100% - 40px);
        }

        .btn-access {
            display: inline-block;
            background: linear-gradient(135deg, #074F9B 0%, #0a63c4 100%);
            color: white;
            text-decoration: none;
            padding: 18px 60px;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(7, 79, 155, 0.3);
            border: none;
            cursor: pointer;
        }

        .btn-access:hover {
            background: linear-gradient(135deg, #063d7a 0%, #0851a0 100%);
            box-shadow: 0 8px 25px rgba(7, 79, 155, 0.4);
            transform: translateY(-2px);
        }

        .btn-access:active {
            transform: translateY(0);
        }

        .footer {
            margin-top: 50px;
            padding-top: 30px;
            border-top: 1px solid #e2e8f0;
        }

        .footer-text {
            color: #718096;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .footer-subtext {
            color: #a0aec0;
            font-size: 0.85rem;
            margin-top: 8px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 45px 35px;
            }

            h1 {
                font-size: 2rem;
            }

            .subtitle {
                font-size: 1.1rem;
            }

            .description {
                font-size: 1rem;
            }

            .btn-access {
                padding: 16px 50px;
                font-size: 1rem;
            }

            .logo {
                max-width: 100px;
            }

            .badge {
                font-size: 0.8rem;
                padding: 7px 20px;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 35px 25px;
                border-radius: 20px;
            }

            h1 {
                font-size: 1.7rem;
            }

            .subtitle {
                font-size: 1rem;
            }

            .description {
                font-size: 0.95rem;
            }

            .logo {
                max-width: 90px;
            }

            .btn-access {
                padding: 15px 40px;
                width: 100%;
            }

            .feature-item {
                padding: 15px 18px;
            }

            .feature-text {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo-wrapper">
            <img src="/images/brasao-umuarama.png" alt="Brasão Umuarama" class="logo">
        </div>
        
        <div class="badge">SRM-Gestão</div>
        <h1>Bem-vindo ao Sistema SRM</h1>
        <p class="subtitle">Sala de Recursos Multifuncionais</p>
        <p class="description">
            Plataforma completa para gestão e acompanhamento pedagógico de estudantes atendidos pela educação especial, promovendo inclusão e desenvolvimento personalizado.
        </p>
        
        <div class="features">
            <div class="feature-item">
                <span class="feature-icon"></span>
                <span class="feature-text">Acompanhamento individualizado de estudantes</span>
            </div>
            <div class="feature-item">
                <span class="feature-icon"></span>
                <span class="feature-text">Registro e análise de dados pedagógicos</span>
            </div>
            <div class="feature-item">
                <span class="feature-icon"></span>
                <span class="feature-text">Gestão integrada de recursos multifuncionais</span>
            </div>
        </div>
        
        <a href="/admin/login" class="btn-access">
            Acessar o Sistema
        </a>
        
        <div class="footer">
            <div class="footer-text">Prefeitura Municipal de Umuarama</div>
            <div class="footer-subtext">Secretaria de Educação</div>
        </div>
    </div>
</body>
</html>