<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Background — Gestão Edu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;700;800&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Inputs mais grossos */
        .fi-input {
            padding: 0.875rem 1rem !important;
            font-size: 1rem !important;
            min-height: 48px !important;
        }

        .fi-input-wrp {
            min-height: 48px !important;
        }

        /* Botões mais grossos */
        .fi-btn {
            padding: 0.875rem 1.5rem !important;
            min-height: 48px !important;
            font-size: 0.95rem !important;
            font-weight: 600 !important;
        }

        

        /* ── NAVY GRADIENT BASE ── */
        .bg-base {
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 70% 60% at 20% 30%, rgba(7, 79, 155, 0.55) 0%, transparent 60%),
                radial-gradient(ellipse 55% 50% at 80% 70%, rgba(58, 142, 230, 0.30) 0%, transparent 60%),
                radial-gradient(ellipse 80% 80% at 50% 50%, rgba(7, 40, 100, 0.40) 0%, transparent 70%),
                linear-gradient(160deg, #04122B 0%, #061E45 45%, #0A2E6B 100%);
        }

        /* ── SUBTLE GRID ── */
        .bg-grid {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255, 255, 255, 0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.04) 1px, transparent 1px);
            background-size: 72px 72px;
        }

        /* ── CIRCLE DECORATIONS ── */
        .bg-circles {
            position: absolute;
            inset: 0;
            overflow: hidden;
        }

        .circle {
            position: absolute;
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, 0.07);
        }

        .c1 {
            width: 600px;
            height: 600px;
            top: -200px;
            right: -160px;
        }

        .c2 {
            width: 420px;
            height: 420px;
            top: -80px;
            right: -40px;
            border-color: rgba(255, 255, 255, 0.05);
        }

        .c3 {
            width: 480px;
            height: 480px;
            bottom: -180px;
            left: -140px;
            border-color: rgba(58, 142, 230, 0.10);
        }

        .c4 {
            width: 300px;
            height: 300px;
            bottom: -80px;
            left: -40px;
            border-color: rgba(58, 142, 230, 0.07);
        }

        .c5 {
            width: 700px;
            height: 700px;
            bottom: -350px;
            left: 50%;
            transform: translateX(-50%);
            border-color: rgba(7, 79, 155, 0.08);
        }

        /* ── EMOJI SCATTER ── */
        .emoji-layer {
            position: absolute;
            inset: 0;
            overflow: hidden;
        }

        @keyframes emojiFloat {
            0% {
                transform: translate(-50%, -50%) translateY(0px) rotate(var(--rot0, -3deg)) scale(1);
            }

            25% {
                transform: translate(-50%, -50%) translateY(var(--dy)) rotate(var(--rot1, 3deg)) scale(1.08);
            }

            50% {
                transform: translate(-50%, -50%) translateY(0px) rotate(var(--rot0, -3deg)) scale(1);
            }

            75% {
                transform: translate(-50%, -50%) translateY(calc(var(--dy) * 0.5)) rotate(var(--rot1, 3deg)) scale(0.95);
            }

            100% {
                transform: translate(-50%, -50%) translateY(0px) rotate(var(--rot0, -3deg)) scale(1);
            }
        }

        .emoji-cell {
            position: absolute;
            line-height: 1;
            animation: emojiFloat var(--dur, 6s) ease-in-out var(--delay, 0s) infinite;
            transition: opacity 0.3s ease;
            will-change: transform;
        }


        /* ── WHITE FROST VEIL ── */
        .frost {
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, 0.14);
            /* Slightly denser at the very bottom */
            -webkit-mask-image: linear-gradient(180deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 1) 60%, rgba(255, 255, 255, 0.98) 100%);
        }

        /* ── POST-FROST GLOW (keeps depth after veil) ── */
        .post-glow {
            position: absolute;
            inset: 0;
            pointer-events: none;
            background:
                radial-gradient(ellipse 60% 50% at 18% 25%, rgba(7, 79, 155, 0.18) 0%, transparent 60%),
                radial-gradient(ellipse 45% 40% at 82% 72%, rgba(58, 142, 230, 0.12) 0%, transparent 55%);
        }

        /* ── GOLD TOP BORDER ── */
        .gold-border {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent 0%, #F5A623 20%, #FFB940 50%, #F5A623 80%, transparent 100%);
            z-index: 10;
        }

        .identity {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            z-index: 20;
            white-space: nowrap;

            background: rgba(255, 255, 255, 0.55);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border-radius: 20px;
            padding: 28px 48px 24px;
            border: 1px solid rgba(255, 255, 255, 0.75);
            box-shadow: 0 8px 32px rgba(4, 18, 43, 0.10);
        }

        .wordmark {
            display: flex;
            align-items: baseline;
            justify-content: center;
            gap: 6px;
            margin-bottom: 10px;
        }

        .word-gestao {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 800;
            font-size: 56px;
            color: #04122B;
            letter-spacing: -0.03em;
            line-height: 1;
            text-shadow: 0 2px 20px rgba(4, 18, 43, 0.10);
        }

        .word-edu {
            font-family: 'Instrument Serif', serif;
            font-style: italic;
            font-weight: 400;
            font-size: 56px;
            color: #C8820A;
            letter-spacing: -0.02em;
            line-height: 1;
        }

        .divider-line {
            width: 260px;
            height: 2px;
            background: linear-gradient(90deg, transparent, #F5A623, transparent);
            margin: 0 auto 10px;
            border-radius: 1px;
        }

        .secretaria {
            font-size: 16px;
            font-weight: 700;
            color: #1A2E4A;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .cidade {
            font-size: 13px;
            font-weight: 400;
            color: #3B5475;
            letter-spacing: 0.06em;
        }
    </style>
</head>

<body>

    <div class="bg-base"></div>
    <div class="bg-grid"></div>

    <div class="bg-circles">
        <div class="circle c1"></div>
        <div class="circle c2"></div>
        <div class="circle c3"></div>
        <div class="circle c4"></div>
        <div class="circle c5"></div>
    </div>

    <!-- EMOJI GRID -->
    <div class="emoji-layer" id="emojiGrid"></div>

    <!-- WHITE FROST -->
    <div class="frost"></div>

    <!-- POST-FROST DEPTH -->
    <div class="post-glow"></div>

    <!-- GOLD BORDER -->
    <div class="gold-border"></div>

    <!-- IDENTITY -->
    <div class="identity">
        <div class="wordmark">
            <span class="word-gestao">Gestão</span>
            <span class="word-edu">Edu</span>
        </div>
        <div class="divider-line"></div>
        <div class="secretaria">Secretaria Municipal de Educação</div>
        <div class="cidade">Prefeitura Municipal de Umuarama · PR</div>
    </div>

    <script>
        const emojis = [
            "📋", "✅", "📝", "🎯", "📊", "🗓️", "🏫", "📚",
            "🚌", "🗺️", "🛣️", "🔔", "📍",
            "🍽️", "📦", "🥗", "🥦", "🍎", "🥕",
            "🔧", "🏷️", "💰", "📄", "🔑", "📐",
            "👥", "🏖️", "🕐", "🛡️", "🔒", "📈", "🖥️",
        ];

        const grid = document.getElementById('emojiGrid');
        const count = 80; // quantidade — reduza ou aumente aqui

        // Gera posições sem sobreposição excessiva
        const placed = [];

        function overlaps(x, y, size) {
            return placed.some(p => {
                const dist = Math.sqrt((p.x - x) ** 2 + (p.y - y) ** 2);
                return dist < (p.size + size) * 0.3;
            });
        }

        let attempts = 0;
        let placed_count = 0;

        while (placed_count < count && attempts < 5000) {
            attempts++;
            const size = [28, 32, 36, 40, 46][Math.floor(Math.random() * 5)];
            const x = 3 + Math.random() * 94; // % da largura
            const y = 3 + Math.random() * 94; // % da altura
            // Evita a área central (onde fica o card)
            const cx = Math.abs(x - 50),
                cy = Math.abs(y - 50);
            if (cx < 22 && cy < 16) continue; // zona protegida do card
            if (overlaps(x, y, size / 10)) continue;

            placed.push({
                x,
                y,
                size
            });
            placed_count++;

            const cell = document.createElement('div');
            cell.className = 'emoji-cell';
            cell.textContent = emojis[Math.floor(Math.random() * emojis.length)];

            const dur = (5 + Math.random() * 8).toFixed(1);
            const delay = (Math.random() * 7).toFixed(1);
            const dy = -(4 + Math.random() * 10).toFixed(0);
            const rot0 = ((Math.random() - 0.5) * 12).toFixed(1);
            const rot1 = ((Math.random() - 0.5) * 12).toFixed(1);
            const opacity = (0.50 + Math.random() * 0.45).toFixed(2);

            cell.style.cssText = `
    --dur: ${dur}s; --delay: ${delay}s;
    --dy: ${dy}px; --rot0: ${rot0}deg; --rot1: ${rot1}deg;
    left: ${x}%; top: ${y}%;
    font-size: ${size}px;
    opacity: ${opacity};
    transform: translate(-50%, -50%);
  `;

            grid.appendChild(cell);
        }
    </script>
</body>

</html>