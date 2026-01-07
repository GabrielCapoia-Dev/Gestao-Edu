<x-filament-panels::page>
    <div class="coming-soon-container">
        <div class="coming-soon-content">
            <!-- Ícone animado -->
            <div class="icon-wrapper">
                <svg class="chart-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M3 13h4v8H3v-8zm6-8h4v16h-4V5zm6 4h4v12h-4V9z" fill="currentColor" opacity="0.3"/>
                    <path d="M7 13v8M13 5v16M19 9v12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <circle class="pulse-dot" cx="7" cy="13" r="2" fill="#074F9B"/>
                    <circle class="pulse-dot" cx="13" cy="5" r="2" fill="#074F9B"/>
                    <circle class="pulse-dot" cx="19" cy="9" r="2" fill="#074F9B"/>
                </svg>
            </div>

            <!-- Conteúdo -->
            <div class="badge">Em Desenvolvimento</div>
            <h2 class="title">Dashboard em Breve</h2>
            <p class="description">
                Estamos preparando visualizações interativas e relatórios detalhados para você acompanhar os dados do SRM de forma intuitiva e eficiente.
            </p>

            <!-- Features do que está vindo -->
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <h3>Relatórios Visuais</h3>
                    <p>Gráficos e indicadores em tempo real</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📈</div>
                    <h3>Análises Detalhadas</h3>
                    <p>Acompanhamento de evolução e métricas</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🎯</div>
                    <h3>Insights Personalizados</h3>
                    <p>Dados segmentados por necessidade</p>
                </div>
            </div>

            <!-- Status de desenvolvimento -->
            <div class="progress-section">
                <div class="progress-label">
                    <span>Progresso do Desenvolvimento</span>
                    <span class="progress-percentage">75%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .coming-soon-container {
            position: relative;
            width: 100%;
            min-height: calc(100vh - 180px);
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f0f7ff 0%, #e6f2ff 100%);
            border-radius: 0.75rem;
            padding: 3rem 1.5rem;
        }

        .dark .coming-soon-container {
            background: linear-gradient(135deg, rgb(17 24 39) 0%, rgb(31 41 55) 100%);
        }

        .coming-soon-content {
            max-width: 800px;
            width: 100%;
            text-align: center;
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Ícone animado */
        .icon-wrapper {
            margin-bottom: 2rem;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-10px);
            }
        }

        .chart-icon {
            width: 120px;
            height: 120px;
            color: #074F9B;
            filter: drop-shadow(0 4px 12px rgba(7, 79, 155, 0.2));
        }

        .dark .chart-icon {
            color: #60a5fa;
        }

        .pulse-dot {
            animation: pulse 2s ease-in-out infinite;
        }

        .pulse-dot:nth-child(2) {
            animation-delay: 0.3s;
        }

        .pulse-dot:nth-child(3) {
            animation-delay: 0.6s;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
                transform: scale(1);
            }
            50% {
                opacity: 0.5;
                transform: scale(1.2);
            }
        }

        /* Badge */
        .badge {
            display: inline-block;
            background: linear-gradient(135deg, #074F9B 0%, #0a63c4 100%);
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 1.5rem;
            text-transform: uppercase;
            box-shadow: 0 4px 12px rgba(7, 79, 155, 0.3);
            animation: shimmer 2s ease-in-out infinite;
        }

        .dark .badge {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
        }

        @keyframes shimmer {
            0%, 100% {
                box-shadow: 0 4px 12px rgba(7, 79, 155, 0.3);
            }
            50% {
                box-shadow: 0 4px 20px rgba(7, 79, 155, 0.5);
            }
        }

        /* Título e descrição */
        .title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #074F9B;
            margin-bottom: 1rem;
            line-height: 1.2;
        }

        .dark .title {
            color: #60a5fa;
        }

        .description {
            font-size: 1.125rem;
            color: #4b5563;
            line-height: 1.7;
            margin-bottom: 3rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .dark .description {
            color: #d1d5db;
        }

        /* Grid de features */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .feature-card {
            background: white;
            padding: 2rem 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .dark .feature-card {
            background: rgb(31 41 55);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(7, 79, 155, 0.15);
            border-color: #074F9B;
        }

        .dark .feature-card:hover {
            border-color: #3b82f6;
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.2);
        }

        .feature-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .feature-card h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .dark .feature-card h3 {
            color: #f9fafb;
        }

        .feature-card p {
            font-size: 0.875rem;
            color: #6b7280;
            line-height: 1.5;
        }

        .dark .feature-card p {
            color: #9ca3af;
        }

        /* Seção de progresso */
        .progress-section {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: 1rem;
            margin-bottom: 2.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .dark .progress-section {
            background: rgb(31 41 55);
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
        }

        .dark .progress-label {
            color: #e5e7eb;
        }

        .progress-percentage {
            color: #074F9B;
            font-size: 1rem;
        }

        .dark .progress-percentage {
            color: #60a5fa;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e5e7eb;
            border-radius: 50px;
            overflow: hidden;
        }

        .dark .progress-bar {
            background: rgb(55 65 81);
        }

        .progress-fill {
            width: 75%;
            height: 100%;
            background: linear-gradient(90deg, #074F9B 0%, #0a63c4 100%);
            border-radius: 50px;
            animation: progressAnimation 2s ease-out;
        }

        .dark .progress-fill {
            background: linear-gradient(90deg, #1e40af 0%, #3b82f6 100%);
        }

        @keyframes progressAnimation {
            from {
                width: 0;
            }
            to {
                width: 75%;
            }
        }

        /* Call to action */
        .cta-section {
            margin-top: 2rem;
        }

        .cta-text {
            color: #6b7280;
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
        }

        .dark .cta-text {
            color: #9ca3af;
        }

        .btn-primary {
            display: inline-block;
            background: linear-gradient(135deg, #074F9B 0%, #0a63c4 100%);
            color: white;
            text-decoration: none;
            padding: 0.875rem 2.5rem;
            border-radius: 0.75rem;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(7, 79, 155, 0.3);
        }

        .dark .btn-primary {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #063d7a 0%, #0851a0 100%);
            box-shadow: 0 6px 16px rgba(7, 79, 155, 0.4);
            transform: translateY(-2px);
        }

        .dark .btn-primary:hover {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
        }

        /* Responsividade */
        @media (max-width: 768px) {
            .coming-soon-container {
                min-height: calc(100vh - 140px);
                padding: 2rem 1rem;
            }

            .title {
                font-size: 2rem;
            }

            .description {
                font-size: 1rem;
                margin-bottom: 2rem;
            }

            .chart-icon {
                width: 80px;
                height: 80px;
            }

            .features-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
                margin-bottom: 2rem;
            }

            .feature-card {
                padding: 1.5rem 1rem;
            }

            .progress-section {
                padding: 1.25rem 1.5rem;
            }

            .btn-primary {
                width: 100%;
                padding: 1rem 2rem;
            }
        }

        @media (max-width: 480px) {
            .title {
                font-size: 1.75rem;
            }

            .badge {
                font-size: 0.75rem;
                padding: 0.4rem 1.2rem;
            }

            .feature-icon {
                font-size: 2rem;
            }

            .feature-card h3 {
                font-size: 1rem;
            }
        }
    </style>
</x-filament-panels::page>