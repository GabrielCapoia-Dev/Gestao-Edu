<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão Escolar | SigmaEdu - Umuarama</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #ffffff;
            color: #1a1a1a;
            overflow-x: hidden;
        }

        /* Header Hero Section */
        .hero {
            background: linear-gradient(135deg, #074F9B 0%, #0d5db8 50%, #1a6fd1 100%);
            padding: 20px;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.1)"/></svg>');
            opacity: 0.3;
        }

        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 900px;
            margin: auto;
        }

        .logo-container {
            animation: fadeInDown 0.8s ease;
        }

        .logo-container img {
            max-width: 360px;
        }

        .hero h1 {
            font-size: 3.2rem;
            font-weight: 800;
            margin-bottom: 25px;
            line-height: 1.2;
            animation: fadeInUp 0.8s ease 0.2s both;
        }

        .hero p {
            font-size: 1.3rem;
            opacity: 0.95;
            line-height: 1.6;
            margin-bottom: 40px;
            animation: fadeInUp 0.8s ease 0.4s both;
        }

        .cta-button {
            display: inline-block;
            background: white;
            color: #074F9B;
            padding: 18px 50px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1.1rem;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            animation: fadeInUp 0.8s ease 0.6s both;
        }

        .cta-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
            background: #f0f9ff;
        }

        /* Stats Section */
        .stats {
            background: #f8fafc;
            padding: 60px 20px;
            border-top: 1px solid #e2e8f0;
        }

        .stats-container {
            max-width: 1100px;
            margin: auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 40px;
            text-align: center;
        }

        .stat-item h3 {
            font-size: 2.5rem;
            color: #074F9B;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .stat-item p {
            color: #64748b;
            font-size: 1rem;
        }

        /* Features Section */
        .section {
            padding: 90px 20px;
            max-width: 1200px;
            margin: auto;
        }

        .section-header {
            text-align: center;
            margin-bottom: 70px;
        }

        .section-header h2 {
            font-size: 2.5rem;
            color: #0f172a;
            margin-bottom: 15px;
            font-weight: 800;
        }

        .section-header p {
            font-size: 1.1rem;
            color: #64748b;
            max-width: 600px;
            margin: auto;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 35px;
        }

        .feature-card {
            background: white;
            padding: 40px 35px;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #074F9B, #1a6fd1);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 35px rgba(7, 79, 155, 0.15);
        }

        .feature-card:hover::before {
            transform: scaleX(1);
        }

        .feature-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #074F9B, #1a6fd1);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 25px;
            font-size: 1.8rem;
        }

        .feature-card h3 {
            margin-bottom: 15px;
            color: #0f172a;
            font-size: 1.4rem;
            font-weight: 700;
        }

        .feature-card p {
            font-size: 1rem;
            line-height: 1.7;
            color: #475569;
        }

        /* Benefits Section */
        .benefits {
            padding: 60px 40px;
            border-radius: 24px;
            background: linear-gradient(135deg, #f8fafc 0%, #e0f2fe 100%);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        /* CTA Section */
        .cta-section {
            background: linear-gradient(-225deg, #f8fafc 0%, #e0f2fe 100%);
            color: #074F9B;
            text-align: center;
            padding: 90px 20px;
            position: relative;
            overflow: hidden;
            margin-top: 60px;
        }

        .cta-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 500px;
            height: 500px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
        }

        .cta-content {
            position: relative;
            z-index: 1;
            max-width: 700px;
            margin: auto;
        }

        .cta-section h2 {
            font-size: 2.5rem;
            margin-bottom: 20px;
            font-weight: 800;
        }

        .cta-section p {
            font-size: 1.2rem;
            margin-bottom: 35px;
            opacity: 0.95;
        }

        /* Footer */
        footer {
            padding: 50px 20px;
            background: #074F9B;
            color: #94a3b8;
        }

        .footer-container {
            max-width: 1200px;
            margin: auto;
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 30px;
        }

        .footer-left {
            text-align: left;
        }

        .footer-center {
            text-align: center;
        }

        .footer-right {
            text-align: right;
        }

        .footer-logo-img {
            max-width: 300px;
            opacity: 0.8;
            transition: opacity 0.3s ease;
        }

        .footer-logo-img:hover {
            opacity: 1;
        }

        footer p {
            margin-bottom: 8px;
        }

        @media(max-width: 768px) {
            .footer-container {
                grid-template-columns: 1fr;
                gap: 30px;
            }

            .footer-left,
            .footer-right {
                text-align: center;
            }
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
        @media(max-width: 768px) {
            .hero h1 {
                font-size: 2.2rem;
            }

            .hero p {
                font-size: 1.1rem;
            }

            .section-header h2 {
                font-size: 2rem;
            }

            .cta-section h2 {
                font-size: 2rem;
            }

            .stat-item h3 {
                font-size: 2rem;
            }
        }

        .sistema-button {
            display: inline-block;
            padding: 12px 24px;
            background: #ffffff;
            color: #074F9B;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 600;
            transition: background 0.3s ease;
        }

        .sistema-button:hover {
            background: linear-gradient(100deg, #ffffff, #cccccc);
        }
    </style>
</head>

<body>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <div class="logo-container">
                <img src="{{ asset('images/brasao-umuarama.png') }}" alt="Brasão Município de Umuarama">
            </div>
            <h1>Gestão Secretaria de Educação</h1>
            <p>
                Plataforma completa para gestão pedagógica, administrativa, logistica e de recursos humanos,
                garantindo acompanhamento integrado e excelência na gestão escolar.
            </p>
            <a href="/administrativo/login" class="sistema-button">Administrativo</a>
            <a href="/especial/login" class="sistema-button">Educação Especial</a>
            <a href="/admin/login" class="sistema-button">Painel Admin</a>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats">
        <div class="stats-container">
            <div class="stat-item">
                <h3>100%</h3>
                <p>Digital</p>
            </div>
            <div class="stat-item">
                <h3>Seguro</h3>
                <p>Dados Protegidos</p>
            </div>
            <div class="stat-item">
                <h3>Integrado</h3>
                <p>Sistema Único</p>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="section">
        <div class="section-header">
            <h2>Funcionalidades Principais</h2>
            <p>Tudo que você precisa para uma gestão escolar eficiente e integrada</p>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">📋</div>
                <h3>Acompanhamento Individual</h3>
                <p>
                    Registro completo do desenvolvimento dos estudantes com planos
                    personalizados, metas definidas e histórico evolutivo detalhado.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">🏫</div>
                <h3>Gestão de Atendimento</h3>
                <p>
                    Organização eficiente de turmas, horários, profissionais e recursos
                    de todos os setores da educação municipal.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">📊</div>
                <h3>Relatórios Gerenciais</h3>
                <p>
                    Indicadores pedagógicos estratégicos e relatórios personalizados
                    para apoio à tomada de decisão da Secretaria de Educação.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">✅</div>
                <h3>Registro de Avaliações</h3>
                <p>
                    Controle completo de avaliações diagnósticas, planos de intervenção
                    e acompanhamento contínuo de metas educacionais.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">👥</div>
                <h3>Perfis Personalizados</h3>
                <p>
                    Diferentes níveis de acesso para professores, coordenadores,
                    gestores e equipe técnica da Secretaria.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">🔔</div>
                <h3>Notificações Inteligentes</h3>
                <p>
                    Alertas automáticos para prazos, avaliações pendentes e
                    atualizações importantes no sistema.
                </p>
            </div>
        </div>
    </section>

    <!-- Benefits Section -->
    <section class="section benefits">
        <div class="section-header">
            <h2>Benefícios para sua Escola</h2>
            <p>Transforme a gestão da educação com tecnologia e eficiência</p>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">🎯</div>
                <h3>Centralização de Dados</h3>
                <p>
                    Todas as informações pedagógicas, documentos e históricos
                    reunidos em um único sistema seguro e acessível.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">🔍</div>
                <h3>Transparência Total</h3>
                <p>
                    Histórico completo de atendimentos, intervenções e evolução
                    dos estudantes com rastreabilidade total.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">⚡</div>
                <h3>Eficiência Administrativa</h3>
                <p>
                    Redução significativa de retrabalho, padronização de processos
                    e otimização do tempo dos profissionais.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">🔒</div>
                <h3>Segurança e Privacidade</h3>
                <p>
                    Proteção de dados sensíveis conforme LGPD, com backups
                    automáticos e controle de acesso rigoroso.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">📱</div>
                <h3>Acesso Responsivo</h3>
                <p>
                    Interface adaptável para computadores, tablets e smartphones,
                    permitindo trabalho em qualquer lugar.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">💡</div>
                <h3>Suporte Dedicado</h3>
                <p>
                    Equipe técnica disponível para treinamento, suporte e
                    atualização contínua da plataforma.
                </p>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="cta-content">
            <h2>Acesso Restrito aos Profissionais da Educação</h2>
            <p>
                Entre com suas credenciais para acessar o sistema completo de
                gestão escolar integrada de Umuarama.
            </p>
            <a href="/admin/login" class="cta-button">Fazer Login no Sistema</a>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="footer-container">
            <div class="footer-left">
                <img src="/images/brasao-umuarama.png" alt="Brasão Município de Umuarama" class="footer-logo-img">
            </div>

            <div class="footer-center">
                <p style="font-weight: 600; color: #cbd5e1; font-size: 1.1rem;">Prefeitura Municipal de Umuarama</p>
                <p>Secretaria Municipal de Educação</p>
                <p style="margin-top: 20px; font-size: 0.9rem;">Sistema de Gestão Escolar © 2026</p>
            </div>

            <div class="footer-right">
                <img src="/images/abrinq-logo.png" alt="Fundação Abrinq" class="footer-logo-img">
            </div>
        </div>
    </footer>

</body>

</html>