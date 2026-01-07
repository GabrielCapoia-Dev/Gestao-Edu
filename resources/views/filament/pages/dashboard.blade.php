<x-filament-panels::page>
    <div class="coming-soon-container">
        <div class="coming-soon-content">
            <!-- Ícone animado -->
            <div class="icon-wrapper">
                <svg class="chart-icon" viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <!-- Background circle -->
                    <circle cx="60" cy="60" r="58" fill="#f0f7ff" stroke="#074F9B" stroke-width="2" />

                    <!-- Grid lines -->
                    <line x1="20" y1="85" x2="100" y2="85" stroke="#d1e3f5" stroke-width="1" opacity="0.5" />
                    <line x1="20" y1="70" x2="100" y2="70" stroke="#d1e3f5" stroke-width="1" opacity="0.5" />
                    <line x1="20" y1="55" x2="100" y2="55" stroke="#d1e3f5" stroke-width="1" opacity="0.5" />
                    <line x1="20" y1="40" x2="100" y2="40" stroke="#d1e3f5" stroke-width="1" opacity="0.5" />

                    <!-- Bar 1 -->
                    <rect class="bar bar-1" x="28" y="55" width="14" height="30" rx="2" fill="#074F9B" />
                    <rect class="bar-glow bar-1" x="28" y="55" width="14" height="30" rx="2" fill="#074F9B" opacity="0.3" />

                    <!-- Bar 2 -->
                    <rect class="bar bar-2" x="48" y="35" width="14" height="50" rx="2" fill="#0a63c4" />
                    <rect class="bar-glow bar-2" x="48" y="35" width="14" height="50" rx="2" fill="#0a63c4" opacity="0.3" />

                    <!-- Bar 3 -->
                    <rect class="bar bar-3" x="68" y="45" width="14" height="40" rx="2" fill="#074F9B" />
                    <rect class="bar-glow bar-3" x="68" y="45" width="14" height="40" rx="2" fill="#074F9B" opacity="0.3" />

                    <!-- Bar 4 -->
                    <rect class="bar bar-4" x="88" y="30" width="14" height="55" rx="2" fill="#0a63c4" />
                    <rect class="bar-glow bar-4" x="88" y="30" width="14" height="55" rx="2" fill="#0a63c4" opacity="0.3" />

                    <!-- Trend line -->
                    <path class="trend-line" d="M 35 65 L 55 40 L 75 50 L 95 35" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none" />

                    <!-- Trend dots -->
                    <circle class="trend-dot dot-1" cx="35" cy="65" r="3" fill="#22c55e" />
                    <circle class="trend-dot dot-2" cx="55" cy="40" r="3" fill="#22c55e" />
                    <circle class="trend-dot dot-3" cx="75" cy="50" r="3" fill="#22c55e" />
                    <circle class="trend-dot dot-4" cx="95" cy="35" r="3" fill="#22c55e" />

                    <!-- Arrow up indicator -->
                    <path class="arrow-up" d="M 105 25 L 110 30 L 105 30 L 105 38 L 103 38 L 103 30 L 98 30 Z" fill="#22c55e" />
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
            height: calc(100vh - 200px);
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f0f7ff 0%, #e6f2ff 100%);
            border-radius: 0.75rem;
            padding: 2rem 1.5rem;
            overflow: hidden;
        }

        .dark .coming-soon-container {
            background: linear-gradient(135deg, rgb(17 24 39) 0%, rgb(31 41 55) 100%);
        }

        .coming-soon-content {
            max-width: 900px;
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
            margin-bottom: 1.5rem;
            animation: float 3s ease-in-out infinite;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        .chart-icon {
            width: 120px;
            height: 120px;
            filter: drop-shadow(0 4px 12px rgba(7, 79, 155, 0.2));
        }

        .dark .chart-icon circle:first-child {
            fill: rgb(31 41 55);
            stroke: #60a5fa;
        }

        .dark .chart-icon line {
            stroke: #374151;
        }

        .dark .chart-icon .bar {
            fill: #3b82f6;
        }

        .dark .chart-icon .bar:nth-child(even) {
            fill: #60a5fa;
        }

        /* Animação das barras crescendo */
        .bar {
            animation: growBar 1.5s ease-out forwards;
            transform-origin: bottom;
        }

        .bar-1 {
            animation-delay: 0.1s;
        }

        .bar-2 {
            animation-delay: 0.2s;
        }

        .bar-3 {
            animation-delay: 0.3s;
        }

        .bar-4 {
            animation-delay: 0.4s;
        }

        @keyframes growBar {
            from {
                transform: scaleY(0);
                opacity: 0;
            }

            to {
                transform: scaleY(1);
                opacity: 1;
            }
        }

        /* Glow das barras */
        .bar-glow {
            animation: glowPulse 2s ease-in-out infinite;
        }

        .bar-glow.bar-1 {
            animation-delay: 0.1s;
        }

        .bar-glow.bar-2 {
            animation-delay: 0.3s;
        }

        .bar-glow.bar-3 {
            animation-delay: 0.5s;
        }

        .bar-glow.bar-4 {
            animation-delay: 0.7s;
        }

        @keyframes glowPulse {

            0%,
            100% {
                opacity: 0.2;
                transform: scale(1);
            }

            50% {
                opacity: 0.4;
                transform: scale(1.05);
            }
        }

        /* Linha de tendência animada */
        .trend-line {
            stroke-dasharray: 200;
            stroke-dashoffset: 200;
            animation: drawLine 2s ease-out 0.5s forwards;
        }

        @keyframes drawLine {
            to {
                stroke-dashoffset: 0;
            }
        }

        /* Pontos da linha de tendência */
        .trend-dot {
            animation: popDot 0.5s ease-out forwards;
            transform-origin: center;
            opacity: 0;
        }

        .dot-1 {
            animation-delay: 0.7s;
        }

        .dot-2 {
            animation-delay: 0.9s;
        }

        .dot-3 {
            animation-delay: 1.1s;
        }

        .dot-4 {
            animation-delay: 1.3s;
        }

        @keyframes popDot {
            0% {
                transform: scale(0);
                opacity: 0;
            }

            50% {
                transform: scale(1.2);
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        /* Seta de crescimento */
        .arrow-up {
            animation: bounceArrow 1s ease-in-out 1.5s infinite;
        }

        @keyframes bounceArrow {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-3px);
            }
        }

        /* Badge */
        .badge {
            display: inline-block;
            background: linear-gradient(135deg, #074F9B 0%, #0a63c4 100%);
            color: white;
            padding: 0.4rem 1.2rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 1rem;
            text-transform: uppercase;
            box-shadow: 0 4px 12px rgba(7, 79, 155, 0.3);
            animation: shimmer 2s ease-in-out infinite;
        }

        .dark .badge {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
        }

        @keyframes shimmer {

            0%,
            100% {
                box-shadow: 0 4px 12px rgba(7, 79, 155, 0.3);
            }

            50% {
                box-shadow: 0 4px 20px rgba(7, 79, 155, 0.5);
            }
        }

        /* Título e descrição */
        .title {
            font-size: 2rem;
            font-weight: 700;
            color: #074F9B;
            margin-bottom: 0.75rem;
            line-height: 1.2;
        }

        .dark .title {
            color: #60a5fa;
        }

        .description {
            font-size: 1rem;
            color: #4b5563;
            line-height: 1.6;
            margin-bottom: 2rem;
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .feature-card {
            background: white;
            padding: 1.5rem 1rem;
            border-radius: 0.75rem;
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
            font-size: 2rem;
            margin-bottom: 0.75rem;
        }

        .feature-card h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.4rem;
        }

        .dark .feature-card h3 {
            color: #f9fafb;
        }

        .feature-card p {
            font-size: 0.85rem;
            color: #6b7280;
            line-height: 1.4;
        }

        .dark .feature-card p {
            color: #9ca3af;
        }

        /* Seção de progresso */
        .progress-section {
            background: white;
            padding: 1.25rem 1.5rem;
            border-radius: 0.75rem;
            margin-bottom: 0;
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