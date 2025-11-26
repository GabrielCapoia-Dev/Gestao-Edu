<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Educação Especial Centralizada - Dashboard</title>
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

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            background: rgba(255, 255, 255, 0.15);
            padding: 10px 20px;
            border-radius: 8px;
            backdrop-filter: blur(10px);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: #ffffff;
            color: #074f9b;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }

        .user-info .user-name {
            color: #ffffff;
        }

        .user-info .user-role {
            color: #d0ddef;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .welcome-section {
            background: linear-gradient(135deg, #074f9b 0%, #053d7a 100%);
            border-radius: 16px;
            padding: 40px;
            margin-bottom: 40px;
            box-shadow: 0 4px 20px rgba(7, 79, 155, 0.2);
        }

        .welcome-title {
            font-size: 2rem;
            margin-bottom: 10px;
            color: #ffffff;
        }

        .welcome-text {
            color: #e0e9f5;
            font-size: 1.1rem;
            line-height: 1.6;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: linear-gradient(135deg, #f0f7ff 0%, #e8f0f8 100%);
            border-radius: 12px;
            padding: 30px;
            border: 2px solid #d0e3f5;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(7, 79, 155, 0.08);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: #074f9b;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(7, 79, 155, 0.15);
            border-color: #074f9b;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            background: rgba(7, 79, 155, 0.1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 15px;
        }

        .stat-label {
            color: #666666;
            font-size: 0.95rem;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #074f9b;
            margin-bottom: 5px;
        }

        .stat-change {
            font-size: 0.85rem;
            color: #0a6fd1;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .stat-change.negative {
            color: #ef4444;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
            margin-bottom: 40px;
        }

        .card {
            background: linear-gradient(135deg, #f0f7ff 0%, #e8f0f8 100%);
            border-radius: 12px;
            padding: 30px;
            border: 2px solid #d0e3f5;
            box-shadow: 0 2px 8px rgba(7, 79, 155, 0.08);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #d0e3f5;
        }

        .card-title {
            font-size: 1.3rem;
            color: #074f9b;
            font-weight: 600;
        }

        .progress-item {
            margin-bottom: 25px;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .progress-label {
            color: #666666;
            font-size: 0.95rem;
        }

        .progress-value {
            color: #074f9b;
            font-weight: 600;
        }

        .progress-bar {
            width: 100%;
            height: 12px;
            background: rgba(255, 255, 255, 0.6);
            border-radius: 6px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #074f9b 0%, #0a6fd1 100%);
            border-radius: 6px;
            transition: width 0.3s ease;
        }

        .activity-item {
            display: flex;
            align-items: start;
            gap: 15px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.6);
            border-radius: 8px;
            margin-bottom: 12px;
            border: 1px solid #d0e3f5;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            background: rgba(7, 79, 155, 0.1);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            color: #333333;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .activity-time {
            color: #999999;
            font-size: 0.85rem;
        }

        .btn {
            background: #074f9b;
            color: #ffffff;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
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
            transform: translateY(-2px);
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 20px;
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .welcome-section {
                padding: 25px;
            }

            .welcome-title {
                font-size: 1.5rem;
            }
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
            <a href="/admin/login" class="login-btn">
                <span>🔐</span>
                Área Administrativa
            </a>
        </div>
    </header>

    <div class="container">
        <div class="welcome-section">
            <h1 class="welcome-title">Bem-vindo ao Educação Especial Centralizada! 👋</h1>
            <p class="welcome-text">
                Sistema de gestão integrada para acompanhamento e desenvolvimento de estudantes com necessidades especiais.
                Aqui você pode acompanhar métricas importantes, visualizar o progresso dos atendimentos e gerenciar as salas de recursos multifuncionais da rede municipal de ensino de Umuarama.
            </p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-label">Total de Alunos Atendidos</div>
                <div class="stat-value">342</div>
                <div class="stat-change">↑ 12% em relação ao ano anterior</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">🏫</div>
                <div class="stat-label">Salas de Recursos Ativas</div>
                <div class="stat-value">28</div>
                <div class="stat-change">↑ 3 novas salas este ano</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">👨‍🏫</div>
                <div class="stat-label">Profissionais Especializados</div>
                <div class="stat-value">45</div>
                <div class="stat-change">100% com formação continuada</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-label">Taxa de Evolução Positiva</div>
                <div class="stat-value">87%</div>
                <div class="stat-change">↑ 5% no último trimestre</div>
            </div>
        </div>

        <div class="content-grid">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Desempenho por Área de Atendimento</h2>
                    <button class="btn btn-secondary">Ver Detalhes</button>
                </div>

                <div class="progress-item">
                    <div class="progress-header">
                        <span class="progress-label">Deficiência Intelectual</span>
                        <span class="progress-value">156 alunos (46%)</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 46%"></div>
                    </div>
                </div>

                <div class="progress-item">
                    <div class="progress-header">
                        <span class="progress-label">Transtorno do Espectro Autista (TEA)</span>
                        <span class="progress-value">92 alunos (27%)</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 27%"></div>
                    </div>
                </div>

                <div class="progress-item">
                    <div class="progress-header">
                        <span class="progress-label">Altas Habilidades/Superdotação</span>
                        <span class="progress-value">48 alunos (14%)</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 14%"></div>
                    </div>
                </div>

                <div class="progress-item">
                    <div class="progress-header">
                        <span class="progress-label">Deficiência Física</span>
                        <span class="progress-value">28 alunos (8%)</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 8%"></div>
                    </div>
                </div>

                <div class="progress-item">
                    <div class="progress-header">
                        <span class="progress-label">Deficiência Visual</span>
                        <span class="progress-value">18 alunos (5%)</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 5%"></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Atividades Recentes</h2>
                </div>

                <div class="activity-item">
                    <div class="activity-icon">📝</div>
                    <div class="activity-content">
                        <div class="activity-title">Novo PDI cadastrado</div>
                        <div class="activity-time">Há 2 horas • Escola Municipal Prof. João Silva</div>
                    </div>
                </div>

                <div class="activity-item">
                    <div class="activity-icon">👤</div>
                    <div class="activity-content">
                        <div class="activity-title">Atendimento realizado</div>
                        <div class="activity-time">Há 4 horas • Maria Santos - TEA</div>
                    </div>
                </div>

                <div class="activity-item">
                    <div class="activity-icon">📊</div>
                    <div class="activity-content">
                        <div class="activity-title">Relatório mensal gerado</div>
                        <div class="activity-time">Ontem • SRM Central</div>
                    </div>
                </div>

                <div class="activity-item">
                    <div class="activity-icon">🎯</div>
                    <div class="activity-content">
                        <div class="activity-title">Meta atingida</div>
                        <div class="activity-time">Há 2 dias • 85% de frequência no mês</div>
                    </div>
                </div>

                <div class="activity-item">
                    <div class="activity-icon">👥</div>
                    <div class="activity-content">
                        <div class="activity-title">Reunião multidisciplinar</div>
                        <div class="activity-time">Há 3 dias • 12 profissionais participaram</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>