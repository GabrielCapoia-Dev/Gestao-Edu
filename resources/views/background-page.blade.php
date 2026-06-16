@once
    @push('styles')
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;700;800&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
    @endpush
@endonce

@once
    <style>
        .bg-base {
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 70% 60% at 20% 30%, rgba(7, 79, 155, 0.55) 0%, transparent 60%),
                radial-gradient(ellipse 55% 50% at 80% 70%, rgba(58, 142, 230, 0.30) 0%, transparent 60%),
                radial-gradient(ellipse 80% 80% at 50% 50%, rgba(7, 40, 100, 0.40) 0%, transparent 70%),
                linear-gradient(160deg, #04122b 0%, #061e45 45%, #0a2e6b 100%);
        }

        .bg-grid {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255, 255, 255, 0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.04) 1px, transparent 1px);
            background-size: 72px 72px;
        }

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

        .c1 { width: 600px; height: 600px; top: -200px; right: -160px; }
        .c2 { width: 420px; height: 420px; top: -80px; right: -40px; border-color: rgba(255, 255, 255, 0.05); }
        .c3 { width: 480px; height: 480px; bottom: -180px; left: -140px; border-color: rgba(58, 142, 230, 0.10); }
        .c4 { width: 300px; height: 300px; bottom: -80px; left: -40px; border-color: rgba(58, 142, 230, 0.07); }
        .c5 { width: 700px; height: 700px; bottom: -350px; left: 50%; transform: translateX(-50%); border-color: rgba(7, 79, 155, 0.08); }

        .emoji-layer {
            position: absolute;
            inset: 0;
            overflow: hidden;
            pointer-events: none;
        }

        @keyframes emoji-float {
            0%, 100% { transform: translate(-50%, -50%) translateY(0) rotate(var(--rot0, -3deg)) scale(1); }
            25% { transform: translate(-50%, -50%) translateY(var(--dy)) rotate(var(--rot1, 3deg)) scale(1.08); }
            50% { transform: translate(-50%, -50%) translateY(0) rotate(var(--rot0, -3deg)) scale(1); }
            75% { transform: translate(-50%, -50%) translateY(calc(var(--dy) * 0.5)) rotate(var(--rot1, 3deg)) scale(0.95); }
        }

        .emoji-cell {
            position: absolute;
            line-height: 1;
            will-change: transform;
            animation: emoji-float var(--dur, 6s) ease-in-out var(--delay, 0s) infinite;
        }

        .frost {
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, 0.14);
            -webkit-mask-image: linear-gradient(180deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 1) 60%, rgba(255, 255, 255, 0.98) 100%);
        }

        .post-glow {
            position: absolute;
            inset: 0;
            pointer-events: none;
            background:
                radial-gradient(ellipse 60% 50% at 18% 25%, rgba(7, 79, 155, 0.18) 0%, transparent 60%),
                radial-gradient(ellipse 45% 40% at 82% 72%, rgba(58, 142, 230, 0.12) 0%, transparent 55%);
        }

        .gold-border {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            z-index: 10;
            background: linear-gradient(90deg, transparent 0%, #f5a623 20%, #ffb940 50%, #f5a623 80%, transparent 100%);
        }

        .identity {
            position: absolute;
            top: 50%;
            left: 50%;
            z-index: 20;
            text-align: center;
            transform: translate(-50%, -50%);
            white-space: nowrap;
            background: rgba(255, 255, 255, 0.55);
            border: 1px solid rgba(255, 255, 255, 0.75);
            border-radius: 20px;
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            box-shadow: 0 8px 32px rgba(4, 18, 43, 0.10);
            padding: 28px 48px 24px;
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
            letter-spacing: -0.03em;
            line-height: 1;
            color: #04122b;
        }

        .word-edu {
            font-family: 'Instrument Serif', serif;
            font-style: italic;
            font-weight: 400;
            font-size: 56px;
            letter-spacing: -0.02em;
            line-height: 1;
            color: #f5a623;
        }

        .divider-line {
            width: 260px;
            height: 2px;
            margin: 0 auto 10px;
            border-radius: 1px;
            background: linear-gradient(90deg, transparent, #f5a623, transparent);
        }

        .secretaria {
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #1a2e4a;
            margin-bottom: 4px;
        }

        .cidade {
            font-size: 13px;
            font-weight: 400;
            letter-spacing: 0.06em;
            color: #3b5475;
        }
    </style>
@endonce

<div class="bg-base"></div>
<div class="bg-grid"></div>

<div class="bg-circles">
    <div class="circle c1"></div>
    <div class="circle c2"></div>
    <div class="circle c3"></div>
    <div class="circle c4"></div>
    <div class="circle c5"></div>
</div>

<div class="emoji-layer" id="emojiGrid"></div>

<div class="frost"></div>
<div class="post-glow"></div>
<div class="gold-border"></div>

<div class="identity">
    <div class="wordmark">
        <span class="word-gestao">Gestao</span>
        <span class="word-edu">Edu</span>
    </div>
    <div class="divider-line"></div>
    <div class="secretaria">Secretaria Municipal de Educação</div>
    <div class="cidade">Prefeitura Municipal de Umuarama - PR</div>
</div>

@once
    <script>
        (() => {
            const grid = document.getElementById('emojiGrid');

            if (!grid || grid.dataset.initialized === '1') {
                return;
            }

            grid.dataset.initialized = '1';

            const icons = [
                "\u{1F4CB}", "\u{2705}", "\u{1F4DD}", "\u{1F3AF}", "\u{1F4CA}", "\u{1F5D3}\u{FE0F}", "\u{1F3EB}",
                "\u{1F4DA}", "\u{1F68C}", "\u{1F5FA}\u{FE0F}", "\u{1F6E3}\u{FE0F}", "\u{1F514}", "\u{1F4CD}",
                "\u{1F37D}\u{FE0F}", "\u{1F4E6}", "\u{1F957}", "\u{1F966}", "\u{1F34E}", "\u{1F955}",
                "\u{1F527}", "\u{1F3F7}\u{FE0F}", "\u{1F4B0}", "\u{1F4C4}", "\u{1F511}", "\u{1F4D0}",
                "\u{1F465}", "\u{1F3D6}\u{FE0F}", "\u{1F550}", "\u{1F6E1}\u{FE0F}", "\u{1F512}", "\u{1F4C8}", "\u{1F5A5}\u{FE0F}",
            ];

            const count = 80;
            const placed = [];

            const overlaps = (x, y, size) => placed.some((p) => {
                const dist = Math.sqrt((p.x - x) ** 2 + (p.y - y) ** 2);
                return dist < (p.size + size) * 0.3;
            });

            let attempts = 0;
            let placedCount = 0;

            while (placedCount < count && attempts < 5000) {
                attempts++;

                const size = [28, 32, 36, 40, 46][Math.floor(Math.random() * 5)];
                const x = 3 + Math.random() * 94;
                const y = 3 + Math.random() * 94;

                const centerX = Math.abs(x - 50);
                const centerY = Math.abs(y - 50);

                if (centerX < 22 && centerY < 16) {
                    continue;
                }

                if (overlaps(x, y, size / 10)) {
                    continue;
                }

                placed.push({ x, y, size });
                placedCount++;

                const cell = document.createElement('div');
                cell.className = 'emoji-cell';
                cell.textContent = icons[Math.floor(Math.random() * icons.length)];

                const dur = (5 + Math.random() * 8).toFixed(1);
                const delay = (Math.random() * 7).toFixed(1);
                const dy = -(4 + Math.random() * 10).toFixed(0);
                const rot0 = ((Math.random() - 0.5) * 12).toFixed(1);
                const rot1 = ((Math.random() - 0.5) * 12).toFixed(1);
                const opacity = (0.50 + Math.random() * 0.45).toFixed(2);

                cell.style.cssText = `
                    --dur: ${dur}s;
                    --delay: ${delay}s;
                    --dy: ${dy}px;
                    --rot0: ${rot0}deg;
                    --rot1: ${rot1}deg;
                    left: ${x}%;
                    top: ${y}%;
                    font-size: ${size}px;
                    opacity: ${opacity};
                    transform: translate(-50%, -50%);
                `;

                grid.appendChild(cell);
            }
        })();
    </script>
@endonce
