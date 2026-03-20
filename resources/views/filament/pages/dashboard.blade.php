<x-filament-panels::page>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
    <style>
        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --ink: #04122B;
            --ink2: #1A2E4A;
            --mid: #3B5475;
            --muted: #7393B3;
            --pale: #B8CDE4;
            --blue: #074F9B;
            --blue2: #1162BB;
            --sky: #3A8EE6;
            --ice: #EBF3FF;
            --ice2: #F4F8FF;
            --gold: #F5A623;
            --gold2: #FFB940;
            --green: #10B981;
            --red: #EF4444;
            --white: #fff;
            --border: rgba(7, 79, 155, 0.12);
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #fff;
            color: var(--ink);
            overflow-x: hidden;
        }

        a {
            text-decoration: none;
        }

        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: #f0f4f8;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--blue);
            border-radius: 3px;
        }

        #cursor-glow {
            position: fixed;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(7, 79, 155, 0.05) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
            transform: translate(-50%, -50%);
            z-index: 0;
            mix-blend-mode: multiply;
            transition: left 0.08s linear, top 0.08s linear;
        }

        /* NAV */
        nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 999;
            height: 68px;
            padding: 0 48px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(4, 18, 43, 0.97);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            box-shadow: 0 1px 0 rgba(255, 255, 255, 0.04);
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .nav-left img {
            height: 60px;
        }

        .nav-wordmark {
            font-weight: 800;
            font-size: 1.1rem;
            color: #fff;
            letter-spacing: -0.01em;
        }

        .nav-wordmark span {
            color: var(--gold);
        }

        .nav-links {
            display: flex;
            gap: 32px;
            list-style: none;
        }

        .nav-links a {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.88rem;
            font-weight: 500;
            transition: color 0.2s;
        }

        .nav-links a:hover {
            color: #fff;
        }

        .nav-right {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .btn-nav-ghost {
            color: rgba(255, 255, 255, 0.75);
            font-size: 0.88rem;
            font-weight: 600;
            padding: 8px 18px;
            border: 1.5px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            transition: all 0.2s;
        }

        .btn-nav-ghost:hover {
            border-color: rgba(255, 255, 255, 0.5);
            color: #fff;
        }

        .btn-nav-solid {
            background: var(--gold);
            color: var(--ink);
            padding: 9px 22px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.88rem;
            transition: opacity 0.2s, transform 0.2s;
            box-shadow: 0 2px 12px rgba(245, 166, 35, 0.35);
        }

        .btn-nav-solid:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        /* HERO */
        .hero {
            min-height: 100vh;
            position: relative;
            background: var(--ink);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            padding: 120px 24px 80px;
        }

        .hero-bg {
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 50% 0%, rgba(7, 79, 155, 0.45) 0%, transparent 60%),
                radial-gradient(ellipse 50% 50% at 80% 80%, rgba(17, 98, 187, 0.2) 0%, transparent 60%),
                radial-gradient(ellipse 40% 40% at 10% 90%, rgba(58, 142, 230, 0.12) 0%, transparent 50%);
        }

        .hero-grid {
            position: absolute;
            inset: 0;
            background-image: linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 72px 72px;
            mask-image: radial-gradient(ellipse 80% 80% at 50% 50%, black 40%, transparent 100%);
        }

        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            animation: orbFloat 8s ease-in-out infinite;
        }

        .orb1 {
            width: 500px;
            height: 500px;
            background: rgba(7, 79, 155, 0.3);
            top: -100px;
            left: -100px;
        }

        .orb2 {
            width: 400px;
            height: 400px;
            background: rgba(58, 142, 230, 0.2);
            bottom: -80px;
            right: -80px;
            animation-delay: -4s;
        }

        .orb3 {
            width: 300px;
            height: 300px;
            background: rgba(245, 166, 35, 0.07);
            top: 40%;
            left: 60%;
            animation-delay: -2s;
        }

        @keyframes orbFloat {

            0%,
            100% {
                transform: translate(0, 0);
            }

            33% {
                transform: translate(30px, -20px);
            }

            66% {
                transform: translate(-20px, 30px);
            }
        }

        .particle {
            position: absolute;
            border-radius: 50%;
            animation: particleDrift linear infinite;
        }

        @keyframes particleDrift {
            0% {
                opacity: 0;
                transform: translateY(0) translateX(0);
            }

            10% {
                opacity: 1;
            }

            90% {
                opacity: 1;
            }

            100% {
                opacity: 0;
                transform: translateY(-100vh) translateX(var(--dx, 20px));
            }
        }

        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 900px;
            text-align: center;
        }

        .hero-pill {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.14);
            padding: 6px 18px 6px 8px;
            border-radius: 100px;
            margin-bottom: 5px;
            animation: fadeUp 0.6s ease both;
        }

        .hero-pill-dot {
            width: 28px;
            height: 28px;
            background: var(--gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
        }

        .hero-pill span {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.82rem;
            font-weight: 500;
        }

        .hero h1 {
            font-size: clamp(2.8rem, 5.5vw, 4.8rem);
            font-weight: 800;
            color: #fff;
            line-height: 1.1;
            letter-spacing: -0.03em;
            margin-bottom: 28px;
            animation: fadeUp 0.6s ease 0.1s both;
        }

        .hero h1 .serif {
            font-family: 'Instrument Serif', serif;
            font-style: italic;
            font-weight: 400;
            color: var(--gold);
        }

        .hero-sub {
            font-size: clamp(1rem, 2vw, 1.2rem);
            color: rgba(255, 255, 255, 0.65);
            line-height: 1.75;
            max-width: 640px;
            margin: 0 auto 22px;
            animation: fadeUp 0.6s ease 0.2s both;
        }

        .hero-actions {
            display: flex;
            gap: 14px;
            justify-content: center;
            flex-wrap: wrap;
            animation: fadeUp 0.6s ease 0.3s both;
            margin-bottom: 80px;
        }

        .btn-hero-primary {
            background: linear-gradient(135deg, var(--gold), var(--gold2));
            color: var(--ink);
            padding: 16px 40px;
            border-radius: 10px;
            font-weight: 800;
            font-size: 1rem;
            box-shadow: 0 8px 32px rgba(245, 166, 35, 0.4);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn-hero-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(245, 166, 35, 0.5);
        }

        .btn-hero-ghost {
            background: rgba(255, 255, 255, 0.06);
            color: rgba(255, 255, 255, 0.9);
            padding: 16px 36px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            border: 1.5px solid rgba(255, 255, 255, 0.15);
            transition: background 0.2s;
        }

        .btn-hero-ghost:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .hero-stats {
            animation: fadeUp 0.6s ease 0.4s both;
            display: flex;
            justify-content: center;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            max-width: 700px;
            margin: 0 auto;
            backdrop-filter: blur(12px);
        }

        .hero-stat {
            flex: 1;
            min-width: 140px;
            padding: 24px 20px;
            text-align: center;
            border-right: 1px solid rgba(255, 255, 255, 0.07);
        }

        .hero-stat:last-child {
            border-right: none;
        }

        .hero-stat-num {
            font-family: 'Instrument Serif', serif;
            font-size: 2.2rem;
            color: var(--gold);
            display: block;
            margin-bottom: 4px;
        }

        .hero-stat-lbl {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.8rem;
        }

        .hero-scroll {
            position: absolute;
            bottom: 32px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            animation: fadeUp 1s ease 1s both;
        }

        .scroll-line {
            width: 1px;
            height: 60px;
            background: linear-gradient(180deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            animation: scrollPulse 2s ease-in-out infinite;
        }

        @keyframes scrollPulse {

            0%,
            100% {
                opacity: 0.3;
            }

            50% {
                opacity: 1;
            }
        }

        .hero-scroll span {
            color: rgba(255, 255, 255, 0.35);
            font-size: 0.72rem;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        /* BAND */
        .band {
            background: var(--blue);
            padding: 0 24px;
        }

        .band-inner {
            max-width: 1200px;
            margin: auto;
            display: flex;
        }

        .band-item {
            flex: 1;
            padding: 36px 28px;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .band-item:last-child {
            border-right: none;
        }

        .band-icon {
            width: 44px;
            height: 44px;
            background: rgba(255, 255, 255, 0.12);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }

        .band-item h3 {
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .band-item p {
            color: rgba(255, 255, 255, 0.65);
            font-size: 0.82rem;
            line-height: 1.5;
        }

        /* SECTION */
        .section {
            padding: 100px 24px;
        }

        .container {
            max-width: 1160px;
            margin: auto;
        }

        .sec-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--blue);
            margin-bottom: 14px;
        }

        .sec-eyebrow::before {
            content: '';
            width: 20px;
            height: 2px;
            background: var(--blue);
            border-radius: 1px;
        }

        .sec-title {
            font-size: clamp(1.9rem, 3.5vw, 2.8rem);
            font-weight: 800;
            color: var(--ink);
            line-height: 1.2;
            letter-spacing: -0.02em;
            margin-bottom: 18px;
        }

        .sec-title .italic {
            font-family: 'Instrument Serif', serif;
            font-weight: 400;
            font-style: italic;
            color: var(--blue);
        }

        .sec-body {
            color: var(--mid);
            font-size: 1.05rem;
            line-height: 1.7;
            max-width: 560px;
        }

        /* PROBLEM */
        .problem {
            background: var(--ice2);
        }

        .problem-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 80px;
            align-items: start;
        }

        .problem-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 28px 24px;
            display: flex;
            gap: 18px;
            align-items: flex-start;
            transition: transform 0.25s, box-shadow 0.25s;
            position: relative;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .problem-card:last-child {
            margin-bottom: 0;
        }

        .problem-card::after {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background: linear-gradient(180deg, #EF4444, #F97316);
            border-radius: 3px 0 0 3px;
        }

        .problem-card:hover {
            transform: translateX(6px);
            box-shadow: 0 8px 32px rgba(7, 79, 155, 0.08);
        }

        .prob-icon {
            width: 40px;
            height: 40px;
            background: #FEF2F2;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .problem-card h3 {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 6px;
        }

        .problem-card p {
            font-size: 0.87rem;
            color: var(--mid);
            line-height: 1.6;
        }

        .solution-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 10px;
            background: var(--ice);
            color: var(--blue);
            font-size: 0.78rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 100px;
        }

        .solution-tag::before {
            content: '✓';
            font-size: 0.7rem;
        }

        .compare-box {
            margin-top: 36px;
            padding: 28px;
            background: #fff;
            border-radius: 16px;
            border: 1px solid var(--border);
        }

        .compare-title {
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--mid);
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 18px;
        }

        .compare-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 8px;
        }

        .compare-row:last-child {
            margin-bottom: 0;
        }

        .compare-cell-bad {
            background: #FEF2F2;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 0.8rem;
            color: #991B1B;
        }

        .compare-cell-good {
            background: #F0FDF4;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 0.8rem;
            color: #166534;
        }

        /* MODULES */
        .modules {
            background: #fff;
        }

        .modules-nav {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 64px;
            border-bottom: 2px solid var(--ice);
            padding-bottom: 0;
        }

        .modnav-btn {
            padding: 12px 22px;
            border-radius: 8px 8px 0 0;
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--mid);
            background: none;
            border: none;
            cursor: pointer;
            transition: color 0.2s, background 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            position: relative;
            bottom: -2px;
            border-bottom: 2px solid transparent;
        }

        .modnav-btn:hover {
            color: var(--blue);
        }

        .modnav-btn.active {
            color: var(--blue);
            border-bottom-color: var(--blue);
            background: var(--ice);
        }

        .modnav-icon {
            font-size: 1.1rem;
        }

        .module-panel {
            display: none;
        }

        .module-panel.active {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 72px;
            align-items: start;
        }

        /* MOCKUP */
        .mockup-shell {
            background: var(--ink);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 24px 80px rgba(4, 18, 43, 0.3), 0 0 0 1px rgba(255, 255, 255, 0.06);
            position: sticky;
            top: 100px;
        }

        .mockup-topbar {
            background: rgba(255, 255, 255, 0.05);
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .mockup-dots {
            display: flex;
            gap: 6px;
        }

        .d {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .d.r {
            background: #FF5F57;
        }

        .d.y {
            background: #FFBD2E;
        }

        .d.g {
            background: #28C840;
        }

        .mockup-title {
            flex: 1;
            text-align: center;
            color: rgba(255, 255, 255, 0.4);
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.05em;
        }

        .mockup-body {
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .mck-layout {
            display: flex;
            gap: 14px;
            min-height: 300px;
        }

        .mck-sidebar {
            width: 44px;
            background: rgba(255, 255, 255, 0.04);
            border-radius: 10px;
            padding: 10px 6px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            align-items: center;
        }

        .mck-icon {
            width: 28px;
            height: 28px;
            border-radius: 7px;
            background: rgba(255, 255, 255, 0.06);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }

        .mck-icon.act {
            background: var(--blue);
        }

        .mck-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .mck-hrow {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .mck-t {
            color: rgba(255, 255, 255, 0.85);
            font-size: 0.8rem;
            font-weight: 700;
        }

        .mck-badge {
            padding: 3px 10px;
            border-radius: 100px;
            font-size: 0.65rem;
            font-weight: 700;
        }

        .mck-badge.g {
            background: rgba(16, 185, 129, 0.2);
            color: #34D399;
        }

        .mck-badge.b {
            background: rgba(58, 142, 230, 0.2);
            color: #7EC8FF;
        }

        .mck-badge.y {
            background: rgba(245, 166, 35, 0.2);
            color: #FFD070;
        }

        .mck-badge.r {
            background: rgba(239, 68, 68, 0.18);
            color: #F87171;
        }

        .mck-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }

        .mck-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 12px 10px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .mck-val {
            color: #fff;
            font-size: 1.3rem;
            font-weight: 800;
            margin-bottom: 2px;
        }

        .mck-lbl {
            color: rgba(255, 255, 255, 0.4);
            font-size: 0.62rem;
        }

        .mck-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
        }

        .mck-row:last-child {
            border-bottom: none;
        }

        .mck-av {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            flex-shrink: 0;
            background: linear-gradient(135deg, var(--blue), var(--sky));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.6rem;
            color: #fff;
            font-weight: 700;
        }

        .mck-line {
            height: 8px;
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.1);
        }

        .mck-prog {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 10px 12px;
        }

        .mck-prog-lbl {
            display: flex;
            justify-content: space-between;
            margin-bottom: 6px;
        }

        .mck-prog-lbl span {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.65rem;
        }

        .mck-prog-lbl strong {
            color: rgba(255, 255, 255, 0.85);
            font-size: 0.65rem;
        }

        .mck-bar {
            height: 6px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 3px;
            overflow: hidden;
        }

        .mck-fill {
            height: 100%;
            border-radius: 3px;
            background: linear-gradient(90deg, var(--blue), var(--sky));
        }

        .mck-fill.gold {
            background: linear-gradient(90deg, var(--gold), var(--gold2));
        }

        .mck-fill.green {
            background: linear-gradient(90deg, #059669, #34D399);
        }

        .mck-tbl {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 10px;
            overflow: hidden;
        }

        .mck-bars {
            display: flex;
            align-items: flex-end;
            gap: 6px;
            height: 60px;
            padding: 0 4px;
        }

        .mck-bc {
            flex: 1;
            border-radius: 4px 4px 0 0;
            background: rgba(7, 79, 155, 0.4);
        }

        .mck-bc.hi {
            background: linear-gradient(180deg, var(--sky), var(--blue));
        }

        .mck-map {
            background: rgba(255, 255, 255, 0.04);
            border-radius: 10px;
            height: 90px;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .mck-map-line {
            position: absolute;
            height: 2px;
            background: linear-gradient(90deg, var(--gold), var(--sky));
            border-radius: 1px;
            opacity: 0.6;
        }

        .mck-map-dot {
            position: absolute;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            border: 2px solid var(--gold);
            background: rgba(245, 166, 35, 0.3);
            animation: mapPulse 2s ease-in-out infinite;
        }

        @keyframes mapPulse {

            0%,
            100% {
                box-shadow: 0 0 0 0 rgba(245, 166, 35, 0.4);
            }

            50% {
                box-shadow: 0 0 0 8px rgba(245, 166, 35, 0);
            }
        }

        /* MODULE INFO */
        .mod-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--ice);
            color: var(--blue);
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 6px 14px;
            border-radius: 100px;
            margin-bottom: 20px;
        }

        .mod-icon-big {
            width: 64px;
            height: 64px;
            border-radius: 18px;
            background: linear-gradient(135deg, var(--blue), var(--sky));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 22px;
            box-shadow: 0 8px 24px rgba(7, 79, 155, 0.3);
        }

        .module-info h2 {
            font-size: clamp(1.6rem, 2.5vw, 2.2rem);
            font-weight: 800;
            color: var(--ink);
            margin-bottom: 16px;
            letter-spacing: -0.02em;
            line-height: 1.2;
        }

        .module-info>p {
            color: var(--mid);
            line-height: 1.75;
            margin-bottom: 32px;
            font-size: 1rem;
        }

        .feat-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 32px;
        }

        .feat-item {
            background: var(--ice2);
            border: 1px solid var(--ice);
            border-radius: 12px;
            padding: 16px 14px;
            transition: border-color 0.2s, transform 0.2s;
        }

        .feat-item:hover {
            border-color: var(--pale);
            transform: translateY(-2px);
        }

        .feat-item-icon {
            font-size: 1.2rem;
            margin-bottom: 8px;
        }

        .feat-item h4 {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 4px;
        }

        .feat-item p {
            font-size: 0.78rem;
            color: var(--mid);
            line-height: 1.5;
        }

        .mod-cta-row {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn-blue {
            background: var(--blue);
            color: #fff;
            padding: 12px 28px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.9rem;
            transition: background 0.2s, transform 0.2s;
            box-shadow: 0 4px 16px rgba(7, 79, 155, 0.3);
        }

        .btn-blue:hover {
            background: var(--blue2);
            transform: translateY(-2px);
        }

        .btn-txt {
            color: var(--blue);
            font-size: 0.88rem;
            font-weight: 600;
            opacity: 0.8;
            transition: opacity 0.2s;
        }

        .btn-txt:hover {
            opacity: 1;
        }

        /* WORKFLOW */
        .workflow {
            background: var(--ink);
            position: relative;
            overflow: hidden;
        }

        .workflow::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse 80% 60% at 50% 0%, rgba(7, 79, 155, 0.3) 0%, transparent 60%);
        }

        .wf-grid {
            position: absolute;
            inset: 0;
            background-image: linear-gradient(rgba(255, 255, 255, 0.02) 1px, transparent 1px), linear-gradient(90deg, rgba(255, 255, 255, 0.02) 1px, transparent 1px);
            background-size: 60px 60px;
        }

        .wf-header {
            text-align: center;
            margin-bottom: 72px;
            position: relative;
            z-index: 1;
        }

        .wf-header .sec-eyebrow {
            color: var(--sky);
        }

        .wf-header .sec-eyebrow::before {
            background: var(--sky);
        }

        .wf-header .sec-title {
            color: #fff;
        }

        .wf-header .sec-body {
            color: rgba(255, 255, 255, 0.6);
            margin: auto;
        }

        .wf-steps {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 0;
            position: relative;
            z-index: 1;
        }

        .wf-steps::before {
            content: '';
            position: absolute;
            top: 40px;
            left: 10%;
            right: 10%;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.15), rgba(255, 255, 255, 0.15), rgba(255, 255, 255, 0.15), transparent);
        }

        .wf-step {
            text-align: center;
            padding: 0 16px;
        }

        .wf-num-wrap {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            position: relative;
            transition: border-color 0.3s, background 0.3s;
        }

        .wf-step:hover .wf-num-wrap {
            border-color: var(--gold);
            background: rgba(245, 166, 35, 0.08);
        }

        .wf-num {
            font-family: 'Instrument Serif', serif;
            font-size: 1.8rem;
            color: rgba(255, 255, 255, 0.3);
            transition: color 0.3s;
        }

        .wf-step:hover .wf-num {
            color: var(--gold);
        }

        .wf-step-ico {
            position: absolute;
            top: -6px;
            right: -6px;
            width: 28px;
            height: 28px;
            background: var(--blue);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            border: 2px solid var(--ink);
        }

        .wf-step h3 {
            color: #fff;
            font-size: 0.92rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .wf-step p {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.8rem;
            line-height: 1.6;
        }

        /* BENEFITS */
        .benefits {
            background: var(--ice2);
        }

        .benefits-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
        }

        .benefit-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 36px 28px;
            transition: transform 0.25s, box-shadow 0.25s, border-color 0.25s;
            position: relative;
            overflow: hidden;
        }

        .benefit-card::before {
            content: '';
            position: absolute;
            top: -60px;
            right: -60px;
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: var(--ice);
            transition: transform 0.4s;
        }

        .benefit-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 60px rgba(7, 79, 155, 0.1);
            border-color: var(--pale);
        }

        .benefit-card:hover::before {
            transform: scale(1.5);
        }

        .benefit-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--blue), var(--sky));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 20px;
            position: relative;
            box-shadow: 0 6px 20px rgba(7, 79, 155, 0.25);
        }

        .benefit-card h3 {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 10px;
        }

        .benefit-card p {
            font-size: 0.88rem;
            color: var(--mid);
            line-height: 1.65;
        }

        .benefit-card.feat {
            background: linear-gradient(135deg, var(--blue), var(--blue2));
            border-color: transparent;
        }

        .benefit-card.feat::before {
            background: rgba(255, 255, 255, 0.05);
        }

        .benefit-card.feat h3 {
            color: #fff;
        }

        .benefit-card.feat p {
            color: rgba(255, 255, 255, 0.7);
        }

        .benefit-card.feat .benefit-icon {
            background: rgba(255, 255, 255, 0.15);
            box-shadow: none;
        }

        /* INTEGRATION */
        .integration {
            background: #fff;
        }

        .int-spokes {
            position: relative;
            height: 280px;
            display: flex;
            align-items: center;
            justify-content: center;
            max-width: 700px;
            margin: 0 auto 56px;
        }

        .int-center {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--blue), var(--sky));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            box-shadow: 0 0 0 14px rgba(7, 79, 155, 0.07), 0 0 0 28px rgba(7, 79, 155, 0.04);
            animation: breathe 3s ease-in-out infinite;
        }

        @keyframes breathe {

            0%,
            100% {
                box-shadow: 0 0 0 14px rgba(7, 79, 155, 0.07), 0 0 0 28px rgba(7, 79, 155, 0.04);
            }

            50% {
                box-shadow: 0 0 0 22px rgba(7, 79, 155, 0.05), 0 0 0 44px rgba(7, 79, 155, 0.02);
            }
        }

        .int-spoke {
            position: absolute;
            width: 110px;
            background: rgba(7, 79, 155, 0.08);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 12px 10px;
            text-align: center;
            transition: transform 0.25s, box-shadow 0.25s, background 0.25s;
        }

        .int-spoke:hover {
            background: var(--ice);
            box-shadow: 0 8px 24px rgba(7, 79, 155, 0.1);
        }

        .int-spoke-icon {
            font-size: 1.3rem;
            margin-bottom: 5px;
        }

        .int-spoke-lbl {
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--blue);
        }

        .int-spoke.s1 {
            transform: translateY(-105px);
        }

        .int-spoke.s2 {
            transform: translate(125px, -55px);
        }

        .int-spoke.s3 {
            transform: translate(125px, 55px);
        }

        .int-spoke.s4 {
            transform: translateY(105px);
        }

        .int-spoke.s5 {
            transform: translate(-125px, 55px);
        }

        .int-spoke.s1:hover {
            transform: translateY(-105px) scale(1.08);
        }

        .int-spoke.s2:hover {
            transform: translate(125px, -55px) scale(1.08);
        }

        .int-spoke.s3:hover {
            transform: translate(125px, 55px) scale(1.08);
        }

        .int-spoke.s4:hover {
            transform: translateY(105px) scale(1.08);
        }

        .int-spoke.s5:hover {
            transform: translate(-125px, 55px) scale(1.08);
        }

        .int-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
        }

        .int-point {
            text-align: center;
            padding: 28px 20px;
            background: var(--ice2);
            border: 1px solid var(--border);
            border-radius: 16px;
        }

        .int-point-icon {
            font-size: 1.6rem;
            margin-bottom: 12px;
        }

        .int-point h3 {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 8px;
        }

        .int-point p {
            font-size: 0.83rem;
            color: var(--mid);
            line-height: 1.6;
        }

        /* SECURITY */
        .security {
            background: var(--ink);
            position: relative;
            overflow: hidden;
        }

        .security::before {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(7, 79, 155, 0.3), transparent 70%);
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            pointer-events: none;
        }

        .security-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 80px;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        .sec-left .sec-eyebrow {
            color: var(--sky);
        }

        .sec-left .sec-eyebrow::before {
            background: var(--sky);
        }

        .sec-left .sec-title {
            color: #fff;
        }

        .sec-left .sec-body {
            color: rgba(255, 255, 255, 0.6);
        }

        .sec-list {
            margin-top: 36px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .sec-item {
            display: flex;
            gap: 14px;
            align-items: flex-start;
            padding: 20px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.07);
            border-radius: 14px;
            transition: border-color 0.2s;
        }

        .sec-item:hover {
            border-color: rgba(58, 142, 230, 0.3);
        }

        .sec-ico {
            width: 36px;
            height: 36px;
            background: rgba(7, 79, 155, 0.3);
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .sec-item h4 {
            color: #fff;
            font-size: 0.9rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .sec-item p {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.82rem;
            line-height: 1.6;
        }

        .shield-box {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            padding: 40px;
            text-align: center;
        }

        .shield-ico {
            font-size: 5rem;
            display: block;
            margin-bottom: 24px;
            animation: shieldPulse 3s ease-in-out infinite;
        }

        @keyframes shieldPulse {

            0%,
            100% {
                filter: drop-shadow(0 0 0 rgba(58, 142, 230, 0));
            }

            50% {
                filter: drop-shadow(0 0 20px rgba(58, 142, 230, 0.5));
            }
        }

        .shield-box h3 {
            color: #fff;
            font-size: 1.3rem;
            font-weight: 800;
            margin-bottom: 12px;
        }

        .shield-box p {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.88rem;
            line-height: 1.7;
            margin-bottom: 28px;
        }

        .shield-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
        }

        .shield-tag {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.75rem;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 100px;
        }

        /* QUOTE */
        .quote-section {
            background: var(--ice2);
            text-align: center;
            padding: 80px 24px;
        }

        .quote-inner {
            max-width: 720px;
            margin: auto;
        }

        .quote-mark {
            font-family: 'Instrument Serif', serif;
            font-size: 6rem;
            line-height: 0.8;
            color: var(--pale);
            margin-bottom: 8px;
        }

        blockquote {
            font-family: 'Instrument Serif', serif;
            font-size: clamp(1.3rem, 2.5vw, 1.8rem);
            color: var(--ink);
            line-height: 1.5;
            font-style: italic;
            margin-bottom: 28px;
        }

        .quote-author {
            color: var(--mid);
            font-size: 0.88rem;
            font-weight: 600;
        }

        .quote-author strong {
            color: var(--blue);
        }

        /* CTA FINAL */
        .cta-final {
            background: linear-gradient(135deg, var(--ink) 0%, var(--blue) 60%, var(--sky) 100%);
            padding: 120px 24px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .cta-final::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 60px 60px;
        }

        .cta-orb {
            position: absolute;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.06), transparent 70%);
            border-radius: 50%;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            pointer-events: none;
        }

        .cta-inner {
            position: relative;
            z-index: 1;
            max-width: 700px;
            margin: auto;
        }

        .cta-inner h2 {
            font-size: clamp(2rem, 4vw, 3.2rem);
            font-weight: 800;
            color: #fff;
            line-height: 1.15;
            letter-spacing: -0.02em;
            margin-bottom: 20px;
        }

        .cta-inner h2 .italic {
            font-family: 'Instrument Serif', serif;
            font-weight: 400;
            font-style: italic;
        }

        .cta-inner p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.05rem;
            line-height: 1.7;
            margin-bottom: 40px;
        }

        .cta-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 44px;
        }

        .cta-card {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 16px;
            padding: 20px 16px;
            transition: background 0.2s;
        }

        .cta-card:hover {
            background: rgba(255, 255, 255, 0.12);
        }

        .cta-card-icon {
            font-size: 1.6rem;
            margin-bottom: 8px;
        }

        .cta-card h4 {
            color: #fff;
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .cta-card p {
            color: rgba(255, 255, 255, 0.55);
            font-size: 0.78rem;
        }

        .btn-cta {
            background: #fff;
            color: var(--blue);
            padding: 16px 44px;
            border-radius: 10px;
            font-weight: 800;
            font-size: 1rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            transition: transform 0.2s, box-shadow 0.2s;
            display: inline-block;
        }

        .btn-cta:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
        }

        /* FOOTER */
        footer {
            background: #020D1F;
            padding: 64px 24px 32px;
        }

        .footer-top {
            max-width: 1160px;
            margin: auto;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 48px;
            padding-bottom: 48px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }

        .footer-brand img {
            height: 44px;
            opacity: 0.8;
            margin-bottom: 16px;
            display: block;
        }

        .footer-brand p {
            color: rgba(255, 255, 255, 0.4);
            font-size: 0.85rem;
            line-height: 1.7;
            margin-bottom: 20px;
        }

        .footer-contact p {
            color: rgba(255, 255, 255, 0.4);
            font-size: 0.82rem;
            margin-bottom: 5px;
        }

        .footer-contact p strong {
            color: rgba(255, 255, 255, 0.7);
        }

        .footer-col-ttl {
            color: rgba(255, 255, 255, 0.75);
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 20px;
        }

        .footer-links {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .footer-links a {
            color: rgba(255, 255, 255, 0.4);
            font-size: 0.85rem;
            transition: color 0.2s;
        }

        .footer-links a:hover {
            color: rgba(255, 255, 255, 0.85);
        }

        .footer-bottom {
            max-width: 1160px;
            margin: 32px auto 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .footer-bottom p {
            color: rgba(255, 255, 255, 0.25);
            font-size: 0.8rem;
        }

        .footer-logos {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .footer-logos img {
            height: 90px;
            opacity: 0.9;
            transition: opacity 0.2s;
        }

        /* ANIMATIONS */
        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(28px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .reveal {
            opacity: 0;
            transform: translateY(36px);
            transition: opacity 0.7s ease, transform 0.7s ease;
        }

        .reveal.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .d1 {
            transition-delay: 0.1s;
        }

        .d2 {
            transition-delay: 0.2s;
        }

        .d3 {
            transition-delay: 0.3s;
        }

        .d4 {
            transition-delay: 0.4s;
        }

        /* RESPONSIVE */
        @media(max-width:960px) {
            nav {
                padding: 0 20px;
            }

            .nav-links {
                display: none;
            }

            .module-panel.active {
                grid-template-columns: 1fr;
            }

            .mockup-shell {
                position: static;
            }

            .problem-layout {
                grid-template-columns: 1fr;
            }

            .wf-steps {
                grid-template-columns: 1fr 1fr;
            }

            .wf-steps::before {
                display: none;
            }

            .benefits-grid {
                grid-template-columns: 1fr 1fr;
            }

            .security-grid {
                grid-template-columns: 1fr;
            }

            .cta-cards {
                grid-template-columns: 1fr 1fr;
            }

            .footer-top {
                grid-template-columns: 1fr 1fr;
            }

            .int-grid {
                grid-template-columns: 1fr 1fr;
            }

            .band-inner {
                flex-direction: column;
            }

            .band-item {
                border-right: none;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            }
        }

        @media(max-width:640px) {
            .hero h1 {
                font-size: 2.2rem;
            }

            .hero-stats {
                flex-direction: column;
            }

            .hero-stat {
                border-right: none;
                border-bottom: 1px solid rgba(255, 255, 255, 0.07);
            }

            .feat-grid {
                grid-template-columns: 1fr;
            }

            .benefits-grid {
                grid-template-columns: 1fr;
            }

            .wf-steps {
                grid-template-columns: 1fr;
            }

            .cta-cards {
                grid-template-columns: 1fr;
            }

            .footer-top {
                grid-template-columns: 1fr;
            }

            .int-grid {
                grid-template-columns: 1fr;
            }

            .int-spokes {
                display: none;
            }

            .footer-bottom {
                justify-content: center;
                text-align: center;
            }
        }
    </style>
    </head>

    <body>

        <div id="cursor-glow"></div>

        <!-- NAV -->
        <nav>
            <div class="nav-left">
                <img src="/images/brasao-umuarama.png" alt="Brasão de Umuarama">
                <span class="nav-wordmark">Gestão<span>Edu</span></span>
            </div>
            <ul class="nav-links">
                <li><a href="#problemas">Diagnóstico</a></li>
                <li><a href="#modulos">Módulos</a></li>
                <li><a href="#integracao">Integração</a></li>
                <li><a href="#seguranca">Segurança</a></li>
                <li><a href="#como-funciona">Implantação</a></li>
            </ul>
            <div class="nav-right">
                <a href="#modulos" class="btn-nav-ghost">Saiba mais</a>
                <a href="/admin/login" class="btn-nav-solid">Acessar o Sistema</a>
            </div>
        </nav>

        <!-- HERO -->
        <section class="hero">
            <div class="hero-bg"></div>
            <div class="hero-grid"></div>
            <div class="orb orb1"></div>
            <div class="orb orb2"></div>
            <div class="orb orb3"></div>
            <div id="particles"></div>

            <div class="hero-content">
                <div class="hero-pill">
                    <div class="hero-pill-dot">🏛️</div>
                    <span>Prefeitura Municipal de Umuarama · Secretaria de Educação</span>
                </div>
                <h1>Gestão educacional<br><span class="serif">inteligente e integrada</span><br>em um só lugar</h1>
                <p class="hero-sub">O Gestão Edu centraliza pedagogia, transporte, alimentação, patrimônio e recursos humanos numa única plataforma — eliminando retrabalho e dando à secretaria visibilidade real sobre toda a rede municipal.</p>
                <div class="hero-actions">
                    <a href="/admin/login" class="btn-hero-primary">Acessar o Sistema →</a>
                    <a href="#modulos" class="btn-hero-ghost">Explorar os módulos</a>
                </div>
                <div class="hero-stats">
                    <div class="hero-stat"><span class="hero-stat-num">5</span><span class="hero-stat-lbl">Módulos Integrados</span></div>
                    <div class="hero-stat"><span class="hero-stat-num">100%</span><span class="hero-stat-lbl">Baseado em Nuvem</span></div>
                    <div class="hero-stat"><span class="hero-stat-num">LGPD</span><span class="hero-stat-lbl">Conformidade</span></div>
                    <div class="hero-stat"><span class="hero-stat-num">24/7</span><span class="hero-stat-lbl">Disponibilidade</span></div>
                </div>
            </div>

        </section>

        <!-- BAND -->
        <div class="band">
            <div class="band-inner">
                <div class="band-item">
                    <div class="band-icon">🎯</div>
                    <div>
                        <h3>Dados em tempo real</h3>
                        <p>Visibilidade sobre a rede a qualquer momento, de qualquer dispositivo.</p>
                    </div>
                </div>
                <div class="band-item">
                    <div class="band-icon">🔗</div>
                    <div>
                        <h3>Integração entre setores</h3>
                        <p>Pedagogia, RH, transporte e alimentação falam a mesma língua.</p>
                    </div>
                </div>
                <div class="band-item">
                    <div class="band-icon">📊</div>
                    <div>
                        <h3>Relatórios automáticos</h3>
                        <p>Indicadores gerados automaticamente, sem consolidação manual.</p>
                    </div>
                </div>
                <div class="band-item">
                    <div class="band-icon">🔒</div>
                    <div>
                        <h3>Segurança e LGPD</h3>
                        <p>Criptografia, backups automáticos e controle de acesso rigoroso.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- PROBLEMAS -->
        <section id="problemas" class="section problem">
            <div class="container">
                <div class="problem-layout">
                    <div class="reveal">
                        <div class="sec-eyebrow">O Diagnóstico</div>
                        <h2 class="sec-title">Uma secretaria que gerencia centenas de vidas <span class="italic">merece ferramentas à altura</span></h2>
                        <p class="sec-body">Sem um sistema integrado, cada setor opera com sua própria lógica — gerando ilhas de informação, retrabalho constante e decisões baseadas em dados desatualizados.</p>
                        <div class="compare-box">
                            <div class="compare-title">Antes × Depois do Gestão Edu</div>
                            <div class="compare-row">
                                <div class="compare-cell-bad">❌ Dados em planilhas isoladas</div>
                                <div class="compare-cell-good">✅ Base única centralizada</div>
                            </div>
                            <div class="compare-row">
                                <div class="compare-cell-bad">❌ Relatórios manuais e demorados</div>
                                <div class="compare-cell-good">✅ Relatórios automáticos em tempo real</div>
                            </div>
                            <div class="compare-row">
                                <div class="compare-cell-bad">❌ Setores sem comunicação</div>
                                <div class="compare-cell-good">✅ Integração total entre módulos</div>
                            </div>
                            <div class="compare-row">
                                <div class="compare-cell-bad">❌ Decisões sem embasamento</div>
                                <div class="compare-cell-good">✅ Indicadores para gestão estratégica</div>
                            </div>
                            <div class="compare-row">
                                <div class="compare-cell-bad">❌ Prestação de contas manual</div>
                                <div class="compare-cell-good">✅ Relatórios prontos para auditoria</div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="problem-card reveal">
                            <div class="prob-icon">📂</div>
                            <div>
                                <h3>Dados fragmentados em planilhas e papel</h3>
                                <p>Informações de alunos, funcionários e patrimônio dispersas em arquivos Excel e pastas físicas — sem rastreabilidade nem histórico confiável.</p>
                                <div class="solution-tag">Gestão Edu unifica tudo em uma base central</div>
                            </div>
                        </div>
                        <div class="problem-card reveal d1">
                            <div class="prob-icon">⏳</div>
                            <div>
                                <h3>Processos manuais que consomem tempo estratégico</h3>
                                <p>Emissão de declarações, cálculo de estoque, controle de férias — feitos à mão, consumindo horas que poderiam ser usadas em gestão e planejamento.</p>
                                <div class="solution-tag">Automação de processos e documentos repetitivos</div>
                            </div>
                        </div>
                        <div class="problem-card reveal d2">
                            <div class="prob-icon">🔗</div>
                            <div>
                                <h3>Setores que não se comunicam entre si</h3>
                                <p>Transporte não sabe quais alunos estão ativos. Alimentação não sabe a frequência da semana. Cada área opera em silo, gerando inconsistências.</p>
                                <div class="solution-tag">Dados compartilhados entre todos os módulos</div>
                            </div>
                        </div>
                        <div class="problem-card reveal d3">
                            <div class="prob-icon">📉</div>
                            <div>
                                <h3>Prestação de contas lenta e sujeita a erros</h3>
                                <p>Consolidar dados de diversas fontes para relatórios e auditorias exige horas de trabalho manual com alto risco de inconsistências.</p>
                                <div class="solution-tag">Relatórios automáticos para órgãos fiscalizadores</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- MÓDULOS -->
        <section id="modulos" class="section modules">
            <div class="container">
                <div style="text-align:center;margin-bottom:48px;" class="reveal">
                    <div class="sec-eyebrow">Funcionalidades</div>
                    <h2 class="sec-title">Cinco módulos. <span class="italic">Uma visão completa.</span></h2>
                    <p class="sec-body" style="margin:0 auto;">Cada módulo resolve um domínio específico e compartilha a mesma base de dados — garantindo consistência e integração real entre todas as áreas.</p>
                </div>

                <div class="modules-nav">
                    <button class="modnav-btn active" onclick="switchMod('pedagogico',this)"><span class="modnav-icon">📋</span> Pedagógico</button>
                    <button class="modnav-btn" onclick="switchMod('transporte',this)"><span class="modnav-icon">🚌</span> Transporte</button>
                    <button class="modnav-btn" onclick="switchMod('alimentacao',this)"><span class="modnav-icon">🍽️</span> Alimentação</button>
                    <button class="modnav-btn" onclick="switchMod('administrativo',this)"><span class="modnav-icon">🏫</span> Administrativo</button>
                    <button class="modnav-btn" onclick="switchMod('rh',this)"><span class="modnav-icon">👥</span> Recursos Humanos</button>
                </div>

                <!-- PEDAGÓGICO -->
                <div id="mod-pedagogico" class="module-panel active">
                    <div class="mockup-shell">
                        <div class="mockup-topbar">
                            <div class="mockup-dots">
                                <div class="d r"></div>
                                <div class="d y"></div>
                                <div class="d g"></div>
                            </div>
                            <div class="mockup-title">Gestão Edu · Módulo Pedagógico</div>
                        </div>
                        <div class="mockup-body">
                            <div class="mck-layout">
                                <div class="mck-sidebar">
                                    <div class="mck-icon act">📋</div>
                                    <div class="mck-icon">👥</div>
                                    <div class="mck-icon">📊</div>
                                    <div class="mck-icon">✅</div>
                                    <div class="mck-icon">📅</div>
                                </div>
                                <div class="mck-main">
                                    <div class="mck-hrow"><span class="mck-t">Turma 4º Ano B — 2026</span><span class="mck-badge g">Ativo</span></div>
                                    <div class="mck-cards">
                                        <div class="mck-card">
                                            <div class="mck-val">28</div>
                                            <div class="mck-lbl">Alunos</div>
                                        </div>
                                        <div class="mck-card">
                                            <div class="mck-val">92%</div>
                                            <div class="mck-lbl">Frequência</div>
                                        </div>
                                        <div class="mck-card">
                                            <div class="mck-val">7.4</div>
                                            <div class="mck-lbl">Média geral</div>
                                        </div>
                                    </div>
                                    <div class="mck-prog">
                                        <div class="mck-prog-lbl"><span>Conteúdo programático</span><strong>68% concluído</strong></div>
                                        <div class="mck-bar">
                                            <div class="mck-fill" style="width:68%"></div>
                                        </div>
                                    </div>
                                    <div class="mck-tbl">
                                        <div class="mck-row">
                                            <div class="mck-av">AL</div>
                                            <div class="mck-line" style="flex:1"></div>
                                            <div class="mck-badge g">Presente</div>
                                            <div style="color:rgba(255,255,255,0.4);font-size:0.63rem;margin-left:6px;">8.2</div>
                                        </div>
                                        <div class="mck-row">
                                            <div class="mck-av">BM</div>
                                            <div class="mck-line" style="flex:1"></div>
                                            <div class="mck-badge y">Falta</div>
                                            <div style="color:rgba(255,255,255,0.4);font-size:0.63rem;margin-left:6px;">6.1</div>
                                        </div>
                                        <div class="mck-row">
                                            <div class="mck-av">CF</div>
                                            <div class="mck-line" style="flex:1"></div>
                                            <div class="mck-badge g">Presente</div>
                                            <div style="color:rgba(255,255,255,0.4);font-size:0.63rem;margin-left:6px;">9.0</div>
                                        </div>
                                        <div class="mck-row">
                                            <div class="mck-av">DL</div>
                                            <div class="mck-line" style="flex:1"></div>
                                            <div class="mck-badge r">Intervenção</div>
                                            <div style="color:rgba(255,255,255,0.4);font-size:0.63rem;margin-left:6px;">4.3</div>
                                        </div>
                                    </div>
                                    <div style="display:flex;gap:8px;">
                                        <div class="mck-bars">
                                            <div class="mck-bc" style="height:40%"></div>
                                            <div class="mck-bc" style="height:65%"></div>
                                            <div class="mck-bc hi" style="height:80%"></div>
                                            <div class="mck-bc" style="height:55%"></div>
                                            <div class="mck-bc hi" style="height:90%"></div>
                                            <div class="mck-bc" style="height:70%"></div>
                                        </div>
                                        <div style="flex:1;background:rgba(255,255,255,0.04);border-radius:8px;padding:10px;display:flex;flex-direction:column;gap:6px;">
                                            <div style="font-size:0.63rem;color:rgba(255,255,255,0.4);font-weight:600;">PRÓXIMAS PROVAS</div>
                                            <div style="font-size:0.7rem;color:rgba(255,255,255,0.7);">📝 Mat. — 15 Jun</div>
                                            <div style="font-size:0.7rem;color:rgba(255,255,255,0.7);">📝 Port. — 18 Jun</div>
                                            <div style="font-size:0.7rem;color:rgba(255,255,255,0.7);">📝 Ciênc. — 22 Jun</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="module-info">
                        <div class="mod-tag">Módulo 01 — Pedagógico</div>
                        <div class="mod-icon-big">📋</div>
                        <h2>Gestão Pedagógica Completa</h2>
                        <p>Acompanhe a vida escolar de cada aluno em tempo real — da matrícula ao histórico acadêmico. Professores, coordenadores e a secretaria têm visibilidade integrada sobre frequência, desempenho, intervenções e o cumprimento do currículo em toda a rede.</p>
                        <div class="feat-grid">
                            <div class="feat-item">
                                <div class="feat-item-icon">🏫</div>
                                <h4>Turmas e matrículas</h4>
                                <p>Gestão de matrículas, transferências, reclassificações e histórico escolar completo.</p>
                            </div>
                            <div class="feat-item">
                                <div class="feat-item-icon">✅</div>
                                <h4>Frequência diária</h4>
                                <p>Registro por turma com alertas automáticos para baixa frequência e risco de reprovação.</p>
                            </div>
                            <div class="feat-item">
                                <div class="feat-item-icon">📝</div>
                                <h4>Avaliações e notas</h4>
                                <p>Provas, diagnósticos, recuperação e cálculo automático de médias por disciplina.</p>
                            </div>
                            <div class="feat-item">
                                <div class="feat-item-icon">🎯</div>
                                <h4>Planos de intervenção</h4>
                                <p>Identificação de alunos em risco e registro de intervenções pedagógicas individualizadas.</p>
                            </div>
                            <div class="feat-item">
                                <div class="feat-item-icon">📊</div>
                                <h4>Relatórios de desempenho</h4>
                                <p>Indicadores por aluno, turma, escola e rede para tomada de decisão fundamentada.</p>
                            </div>
                            <div class="feat-item">
                                <div class="feat-item-icon">🗓️</div>
                                <h4>Calendário escolar</h4>
                                <p>Planejamento integrado com eventos, avaliações e datas para toda a rede municipal.</p>
                            </div>
                        </div>
                        <div class="mod-cta-row"><a href="/admin/login" class="btn-blue">Acessar o módulo</a><a href="#como-funciona" class="btn-txt">Como funciona a implantação →</a></div>
                    </div>
                </div>

                <!-- TRANSPORTE -->
                <div id="mod-transporte" class="module-panel">
                    <div class="mockup-shell">
                        <div class="mockup-topbar">
                            <div class="mockup-dots">
                                <div class="d r"></div>
                                <div class="d y"></div>
                                <div class="d g"></div>
                            </div>
                            <div class="mockup-title">Gestão Edu · Módulo Transporte</div>
                        </div>
                        <div class="mockup-body">
                            <div class="mck-layout">
                                <div class="mck-sidebar">
                                    <div class="mck-icon act">🚌</div>
                                    <div class="mck-icon">🗺️</div>
                                    <div class="mck-icon">👥</div>
                                    <div class="mck-icon">🪪</div>
                                </div>
                                <div class="mck-main">
                                    <div class="mck-hrow"><span class="mck-t">Rotas — Turno Manhã</span><span class="mck-badge g">18 ativas</span></div>
                                    <div class="mck-cards">
                                        <div class="mck-card">
                                            <div class="mck-val">412</div>
                                            <div class="mck-lbl">Alunos elegíveis</div>
                                        </div>
                                        <div class="mck-card">
                                            <div class="mck-val">18</div>
                                            <div class="mck-lbl">Rotas</div>
                                        </div>
                                        <div class="mck-card">
                                            <div class="mck-val">96%</div>
                                            <div class="mck-lbl">Ocupação</div>
                                        </div>
                                    </div>
                                    <div class="mck-map">
                                        <div class="mck-map-line" style="width:70%;top:40%;left:15%;transform:rotate(-8deg);"></div>
                                        <div class="mck-map-line" style="width:50%;top:55%;left:30%;transform:rotate(5deg);"></div>
                                        <div class="mck-map-dot" style="top:35%;left:20%;"></div>
                                        <div class="mck-map-dot" style="top:50%;left:55%;animation-delay:0.5s;"></div>
                                        <div class="mck-map-dot" style="top:60%;left:75%;animation-delay:1s;"></div>
                                        <div style="position:absolute;inset:0;background:repeating-linear-gradient(0deg,rgba(255,255,255,0.015) 0px,rgba(255,255,255,0.015) 1px,transparent 1px,transparent 18px),repeating-linear-gradient(90deg,rgba(255,255,255,0.015) 0px,rgba(255,255,255,0.015) 1px,transparent 1px,transparent 18px);"></div>
                                        <div style="position:absolute;top:8px;left:10px;font-size:0.62rem;color:rgba(255,255,255,0.35);font-weight:600;">MAPA DE ROTAS</div>
                                    </div>
                                    <div class="mck-tbl">
                                        <div class="mck-row">
                                            <div style="width:8px;height:8px;border-radius:50%;background:#34D399;flex-shrink:0;"></div>
                                            <div class="mck-line" style="flex:1"></div>
                                            <div style="color:rgba(255,255,255,0.4);font-size:0.63rem;">32/38</div>
                                            <div class="mck-badge g">Em rota</div>
                                        </div>
                                        <div class="mck-row">
                                            <div style="width:8px;height:8px;border-radius:50%;background:#FBBF24;flex-shrink:0;"></div>
                                            <div class="mck-line" style="flex:1"></div>
                                            <div style="color:rgba(255,255,255,0.4);font-size:0.63rem;">24/30</div>
                                            <div class="mck-badge y">Aguardando</div>
                                        </div>
                                        <div class="mck-row">
                                            <div style="width:8px;height:8px;border-radius:50%;background:#60A5FA;flex-shrink:0;"></div>
                                            <div class="mck-line" style="flex:1"></div>
                                            <div style="color:rgba(255,255,255,0.4);font-size:0.63rem;">28/28</div>
                                            <div class="mck-badge b">Concluído</div>
                                        </div>
                                    </div>
                                    <div style="background:rgba(255,255,255,0.04);border-radius:8px;padding:10px 12px;display:flex;justify-content:space-between;align-items:center;">
                                        <span style="font-size:0.7rem;color:rgba(255,255,255,0.6);">🪪 Nova carteirinha: João P. Silva</span>
                                        <div class="mck-badge b">Emitir</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="module-info">
                        <div class="mod-tag">Módulo 02 — Transporte</div>
                        <div class="mod-icon-big">🚌</div>
                        <h2>Transporte Escolar Inteligente</h2>
                        <p>Controle total das rotas, veículos e elegibilidade — eliminando o uso irregular do transporte, otimizando a logística e garantindo que cada aluno com direito ao serviço seja atendido com segurança e rastreabilidade.</p>
                        <div class="feat-grid">
                            <div class="feat-item">
                                <div class="feat-item-icon">🗺️</div>
                                <h4>Gestão de rotas</h4>
                                <p>Cadastro de rotas com paradas, horários, distâncias e veículos designados a cada trecho.</p>
                            </div>
                            <div class="feat-item">
                                <div class="feat-item-icon">✅</div>
                                <h4>Controle de elegibilidade</h4>
                                <p>Critérios automáticos por distância e zona rural para definir o direito ao transporte gratuito.</p>
                            </div>
                            <div class="feat-item">
                                <div class="feat-item-icon">🪪</div>
                                <h4>Emissão de carteirinhas</h4>
                                <p>Geração digital de carteirinhas com foto, dados do aluno e QR code para validação.</p>
                            </div>
                            <div class="feat-item">
                                <div class="feat-item-icon">📊</div>
                                <h4>Ocupação por rota</h4>
                                <p>Monitoramento de lotação por veículo para redistribuição e otimização da frota.</p>
                            </div>
                            <div class="feat-item">
                                <div class="feat-item-icon">📋</div>
                                <h4>Histórico de uso</h4>
                                <p>Registro de embarques por aluno, com histórico auditável para prestação de contas.</p>
                            </div>
                            <div class="feat-item">
                                <div class="feat-item-icon">🔔</div>
                                <h4>Alertas e irregularidades</h4>
                                <p>Detecção de alunos usando o transporte sem elegibilidade ou fora da rota designada.</p>
                            </div>
                        </div>
                        <div class="mod-cta-row"><a href="/admin/login" class="btn-blue">Acessar o módulo</a><a href="#como-funciona" class="btn-txt">Como funciona a implantação →</a></div>
                    </div>
                </div>

                <!-- ALIMENTAÇÃO -->
                <div id="mod-alimentacao" class="module-panel">
                    <div class="mockup-shell">
                        <div class="mockup-topbar">
                            <div class="mockup-dots">
                                <div class="d r"></div>
                                <div class="d y"></div>
                                <div class="d g"></div>
                            </div>
                            <div class="mockup-title">Gestão Edu · Alimentação Escolar</div>
                        </div>
                        <div class="mockup-body">
                            <div class="mck-layout">
                                <div class="mck-sidebar">
                                    <div class="mck-icon act">🍽️</div>
                                    <div class="mck-icon">📦</div>
                                    <div class="mck-icon">🥗</div>
                                    <div class="mck-icon">📊</div>
                                </div>
                                <div class="mck-main">
                                    <div class="mck-hrow"><span class="mck-t">Semana 23 — Jun 2026</span><span class="mck-badge b">847 porções/dia</span></div>
                                    <div class="mck-cards">
                                        <div class="mck-card">
                                            <div class="mck-val">5</div>
                                            <div class="mck-lbl">Dias planejados</div>
                                        </div>
                                        <div class="mck-card">
                                            <div class="mck-val">3</div>
                                            <div class="mck-lbl">Alertas</div>
                                        </div>
                                        <div class="mck-card">
                                            <div class="mck-val">98%</div>
                                            <div class="mck-lbl">Meta nutricional</div>
                                        </div>
                                    </div>
                                    <div style="background:rgba(255,255,255,0.04);border-radius:10px;padding:12px;display:flex;flex-direction:column;gap:8px;">
                                        <div style="font-size:0.63rem;font-weight:700;color:rgba(255,255,255,0.4);margin-bottom:4px;letter-spacing:0.05em;">COBERTURA DO ESTOQUE</div>
                                        <div>
                                            <div class="mck-prog-lbl"><span>Arroz integral (50kg)</span><strong style="color:#34D399;">18 dias</strong></div>
                                            <div class="mck-bar">
                                                <div class="mck-fill green" style="width:85%"></div>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="mck-prog-lbl"><span>Feijão preto (12kg)</span><strong style="color:#FBBF24;">4 dias ⚠️</strong></div>
                                            <div class="mck-bar">
                                                <div class="mck-fill gold" style="width:22%"></div>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="mck-prog-lbl"><span>Frango congelado (25kg)</span><strong style="color:#34D399;">12 dias</strong></div>
                                            <div class="mck-bar">
                                                <div class="mck-fill" style="width:60%"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="background:rgba(255,255,255,0.04);border-radius:10px;padding:12px;">
                                        <div style="font-size:0.63rem;font-weight:700;color:rgba(255,255,255,0.4);margin-bottom:8px;">MACRONUTRIENTES / PORÇÃO</div>
                                        <div style="display:flex;gap:16px;">
                                            <div style="text-align:center;">
                                                <div style="color:#fff;font-size:1rem;font-weight:800;">28g</div>
                                                <div style="font-size:0.6rem;color:rgba(255,255,255,0.4);">Proteínas</div>
                                            </div>
                                            <div style="text-align:center;">
                                                <div style="color:#fff;font-size:1rem;font-weight:800;">64g</div>
                                                <div style="font-size:0.6rem;color:rgba(255,255,255,0.4);">Carboidratos</div>
                                            </div>
                                            <div style="text-align:center;">
                                                <div style="color:#fff;font-size:1rem;font-weight:800;">12g</div>
                                                <div style="font-size:0.6rem;color:rgba(255,255,255,0.4);">Gorduras</div>
                                            </div>
                                            <div style="text-align:center;">
                                                <div style="color:#fff;font-size:1rem;font-weight:800;">480</div>
                                                <div style="font-size:0.6rem;color:rgba(255,255,255,0.4);">Kcal</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="module-info">
                        <div class="mod-tag">Módulo 03 — Alimentação</div>
                        <div class="mod-icon-big">🍽️</div>
                        <h2>Alimentação Escolar Nutritiva</h2>
                        <p>Do planejamento nutricional ao controle de estoque — o sistema calcula automaticamente por quantos dias o estoque atual sustenta o cardápio planejado, com base no número real de alunos atendidos por escola e turno.</p>
                        <div class="feat-grid">
                            <div class="feat-item">
                                <div class="feat-item-icon">🥗</div>
                                <h4>Elaboração de cardápios</h4>
                                <p>Planejamento semanal e mensal de cardápios por escola, turno e faixa etária.</p>
                            </div>
                            <div class="feat-item">
                                <div class="feat-item-icon">📦</div>
                                <h4>Controle de estoque</h4>
                                <p>Entradas, saídas e saldo por insumo com alertas de estoque mínimo configuráveis.</p>
                            </div>
                            <div class="feat-item">
                                <div class="feat-item-icon">🧮</div>
                                <h4>Cobertura automática</h4>
                                <p>Cálculo automático de quantos dias o estoque sustenta o cardápio e a demanda da rede.</p>
                            </div>
                            <div class="feat-item">
                                <div class="feat-item-icon">🥦</div>
                                <h4>Controle nutricional</h4>
                                <p>Monitoramento de macronutrientes e calorias com adequação às diretrizes do PNAE.</p>
                            </div>
                            <div class="feat-item">
                                <div class="feat-item-icon">📊</div>
                                <h4>Relatórios para FNDE</h4>
                                <p>Relatórios nutricionais automáticos para o FNDE, Conselho de Alimentação Escolar e auditorias.</p>
                            </div>
                            <div class="feat-item">
                                <div class="feat-item-icon">🔔</div>
                                <h4>Alertas de abastecimento</h4>
                                <p>Notificações quando insumos atingem o nível mínimo por escola ou rede.</p>
                            </div>
                        </div>
                        <div class="mod-cta-row"><a href="/admin/login" class="btn-blue">Acessar o módulo</a><a href="#como-funciona" class="btn-txt">Como funciona a implantação →</a></div>
                    </div>
                </div>

                <!-- ADMINISTRATIVO -->
                <div id="mod-administrativo" class="module-panel">
                    <div class="mockup-shell">
                        <div class="mockup-topbar">
                            <div class="mockup-dots">
                                <div class="d r"></div>
                                <div class="d y"></div>
                                <div class="d g"></div>
                            </div>
                            <div class="mockup-title">Gestão Edu · Módulo Administrativo</div>
                        </div>
                        <div class="mockup-body">
                            <div class="mck-layout">
                                <div class="mck-sidebar">
                                    <div class="mck-icon act">🏫</div>
                                    <div class="mck-icon">🔧</div>
                                    <div class="mck-icon">📦</div>
                                    <div class="mck-icon">📊</div>
                                </div>
                                <div class="mck-main">
                                    <div class="mck-hrow"><span class="mck-t">Patrimônio e Manutenção</span><span class="mck-badge b">34 unidades</span></div>
                                    <div class="mck-cards">
                                        <div class="mck-card">
                                            <div class="mck-val">2.847</div>
                                            <div class="mck-lbl">Itens patrimoniais</div>
                                        </div>
                                        <div class="mck-card">
                                            <div class="mck-val">7</div>
                                            <div class="mck-lbl">OS abertas</div>
                                        </div>
                                        <div class="mck-card">
                                            <div class="mck-val">R$1.2M</div>
                                            <div class="mck-lbl">Patrimônio</div>
                                        </div>
                                    </div>
                                    <div style="background:rgba(255,255,255,0.04);border-radius:10px;padding:12px;display:flex;flex-direction:column;gap:6px;">
                                        <div style="font-size:0.63rem;font-weight:700;color:rgba(255,255,255,0.4);margin-bottom:4px;">ORDENS DE SERVIÇO</div>
                                        <div class="mck-row">
                                            <div style="font-size:0.7rem;color:rgba(255,255,255,0.7);flex:1;">E.M. Monteiro Lobato — Telhado</div>
                                            <div class="mck-badge y">Aguardando</div>
                                        </div>
                                        <div class="mck-row">
                                            <div style="font-size:0.7rem;color:rgba(255,255,255,0.7);flex:1;">E.M. Castro Alves — Elétrica</div>
                                            <div class="mck-badge b">Em execução</div>
                                        </div>
                                        <div class="mck-row">
                                            <div style="font-size:0.7rem;color:rgba(255,255,255,0.7);flex:1;">CMEI Girassol — Pintura</div>
                                            <div class="mck-badge g">Concluído</div>
                                        </div>
                                    </div>
                                    <div style="background:rgba(255,255,255,0.04);border-radius:10px;padding:12px;">
                                        <div style="font-size:0.63rem;font-weight:700;color:rgba(255,255,255,0.4);margin-bottom:8px;">MOVIMENTAÇÃO PATRIMONIAL</div>
                                        <div class="mck-bars">
                                            <div class="mck-bc" style="height:60%"></div>
                                            <div class="mck-bc hi" style="height:85%"></div>
                                            <div class="mck-bc" style="height:40%"></div>
                                            <div class="mck-bc" style="height:70%"></div>
                                            <div class="mck-bc hi" style="height:95%"></div>
                                            <div class="mck-bc" style="height:55%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="module-info">
                        <div class="mod-tag">Módulo 04 — Administrativo</div>
                        <div class="mod-icon-big">🏫</div>
                        <h2>Gestão Administrativa e Patrimonial</h2>
                        <p>Controle total das unidades escolares — do pedido de manutenção ao balanço patrimonial, com rastreabilidade completa de todos os bens e movimentações de cada escola da rede municipal.</p>
                        <div class="feat-grid">
                            <div class="feat-item">
                                <div class="feat-item-icon">🏷️</div>
                                <h4>Inventário patrimonial</h4>
                                <p>Cadastro de bens por unidade com tombamento, valor, estado de conservação e localização.</p>
                            </div>
                            <div class="feat-item">
                                <div class="feat-item-icon">🔧</div>
                                <h4>Ordens de manutenção</h4>
                                <p>Abertura, acompanhamento e encerramento de OS com histórico por unidade escolar.</p>
                            </div>
                            <div class="feat-item">
                                <div class="feat-item-icon">📦</div>
                                <h4>Controle de materiais</h4>
                                <p>Registro de entradas e saídas de materiais e equipamentos entre unidades.</p>
                            </div>
                            <div class="feat-item">
                                <div class="feat-item-icon">💰</div>
                                <h4>Balanço patrimonial</h4>
                                <p>Consolidação automática do patrimônio por escola e rede, com depreciação e atualização.</p>
                            </div>
                            <div class="feat-item">
                                <div class="feat-item-icon">📄</div>
                                <h4>Contratos e fornecedores</h4>
                                <p>Gestão de contratos de serviços com alertas de vencimento e histórico por fornecedor.</p>
                            </div>
                            <div class="feat-item">
                                <div class="feat-item-icon">📊</div>
                                <h4>Relatórios para TCE</h4>
                                <p>Documentação completa para prestação de contas ao Tribunal de Contas e órgãos fiscalizadores.</p>
                            </div>
                        </div>
                        <div class="mod-cta-row"><a href="/admin/login" class="btn-blue">Acessar o módulo</a><a href="#como-funciona" class="btn-txt">Como funciona a implantação →</a></div>
                    </div>
                </div>

                <!-- RH -->
                <div id="mod-rh" class="module-panel">
                    <div class="mockup-shell">
                        <div class="mockup-topbar">
                            <div class="mockup-dots">
                                <div class="d r"></div>
                                <div class="d y"></div>
                                <div class="d g"></div>
                            </div>
                            <div class="mockup-title">Gestão Edu · Recursos Humanos</div>
                        </div>
                        <div class="mockup-body">
                            <div class="mck-layout">
                                <div class="mck-sidebar">
                                    <div class="mck-icon act">👥</div>
                                    <div class="mck-icon">📅</div>
                                    <div class="mck-icon">🕐</div>
                                    <div class="mck-icon">📄</div>
                                </div>
                                <div class="mck-main">
                                    <div class="mck-hrow"><span class="mck-t">Painel RH — Jun 2026</span><span class="mck-badge g">318 servidores</span></div>
                                    <div class="mck-cards">
                                        <div class="mck-card">
                                            <div class="mck-val">318</div>
                                            <div class="mck-lbl">Servidores</div>
                                        </div>
                                        <div class="mck-card">
                                            <div class="mck-val">12</div>
                                            <div class="mck-lbl">Em férias</div>
                                        </div>
                                        <div class="mck-card">
                                            <div class="mck-val">4</div>
                                            <div class="mck-lbl">Atestados</div>
                                        </div>
                                    </div>
                                    <div style="background:rgba(255,255,255,0.04);border-radius:10px;padding:12px;display:flex;flex-direction:column;gap:8px;">
                                        <div style="font-size:0.63rem;font-weight:700;color:rgba(255,255,255,0.4);">PENDÊNCIAS DO MÊS</div>
                                        <div style="display:flex;justify-content:space-between;align-items:center;"><span style="font-size:0.73rem;color:rgba(255,255,255,0.7);">🏖️ Férias vencendo em 30 dias</span><span class="mck-badge y">7</span></div>
                                        <div style="display:flex;justify-content:space-between;align-items:center;"><span style="font-size:0.73rem;color:rgba(255,255,255,0.7);">📋 Atestados para validar</span><span class="mck-badge b">4</span></div>
                                        <div style="display:flex;justify-content:space-between;align-items:center;"><span style="font-size:0.73rem;color:rgba(255,255,255,0.7);">🏆 Licença-prêmio disponível</span><span class="mck-badge g">23</span></div>
                                    </div>
                                    <div class="mck-tbl">
                                        <div class="mck-row">
                                            <div class="mck-av">MS</div>
                                            <div style="flex:1;font-size:0.68rem;color:rgba(255,255,255,0.7);">Maria S. · Prof. Mat. · E.M. Lobato</div>
                                            <div class="mck-badge b">Lotado</div>
                                        </div>
                                        <div class="mck-row">
                                            <div class="mck-av">JP</div>
                                            <div style="flex:1;font-size:0.68rem;color:rgba(255,255,255,0.7);">João P. · Coord. · Secretaria</div>
                                            <div class="mck-badge y">Férias</div>
                                        </div>
                                        <div class="mck-row">
                                            <div class="mck-av">AF</div>
                                            <div style="flex:1;font-size:0.68rem;color:rgba(255,255,255,0.7);">Ana F. · Aux. Ed. · CMEI Girassol</div>
                                            <div class="mck-badge g">Ativo</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="module-info">
                        <div class="mod-tag">Módulo 05 — Recursos Humanos</div>
                        <div class="mod-icon-big">👥</div>
                        <h2>Gestão de Pessoas da Educação</h2>
                        <p>Controle completo do ciclo de vida do servidor — dos atestados às férias, do controle de ponto às declarações — com visibilidade total sobre lotação e movimentação de pessoal em toda a rede.</p>
                        <div class="feat-grid">
                            <div class="feat-item">
                                <div class="feat-item-icon">📍</div>
                                <h4>Controle de lotação</h4>
                                <p>Registro atualizado do local de trabalho de cada servidor com histórico de movimentações.</p>
                            </div>
                            <div class="feat-item">
                                <div class="feat-item-icon">📋</div>
                                <h4>Gestão de atestados</h4>
                                <p>Emissão, recebimento e controle de atestados médicos com integração ao controle de ponto.</p>
                            </div>
                            <div class="feat-item">
                                <div class="feat-item-icon">🏖️</div>
                                <h4>Férias e licença-prêmio</h4>
                                <p>Planejamento, controle e alertas de vencimento de férias e licenças de toda a equipe.</p>
                            </div>
                            <div class="feat-item">
                                <div class="feat-item-icon">🕐</div>
                                <h4>Controle de ponto</h4>
                                <p>Registro de frequência com relatórios de horas por servidor, turno e unidade escolar.</p>
                            </div>
                            <div class="feat-item">
                                <div class="feat-item-icon">📄</div>
                                <h4>Declarações automáticas</h4>
                                <p>Geração automática de declarações de horas, vínculos e função com assinatura digital.</p>
                            </div>
                            <div class="feat-item">
                                <div class="feat-item-icon">🔔</div>
                                <h4>Alertas de gestão</h4>
                                <p>Notificações para férias vencendo, atestados pendentes e contratos temporários a renovar.</p>
                            </div>
                        </div>
                        <div class="mod-cta-row"><a href="/admin/login" class="btn-blue">Acessar o módulo</a><a href="#como-funciona" class="btn-txt">Como funciona a implantação →</a></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- COMO FUNCIONA -->
        <section id="como-funciona" class="section workflow">
            <div class="wf-grid"></div>
            <div class="container">
                <div class="wf-header">
                    <div class="sec-eyebrow">Implantação</div>
                    <h2 class="sec-title">Como o Gestão Edu é <span class="italic">implantado na prática</span></h2>
                    <p class="sec-body">Um processo estruturado para garantir adoção rápida e resultados reais desde as primeiras semanas de uso.</p>
                </div>
                <div class="wf-steps">
                    <div class="wf-step reveal">
                        <div class="wf-num-wrap"><span class="wf-num">01</span>
                            <div class="wf-step-ico">📂</div>
                        </div>
                        <h3>Migração de dados</h3>
                        <p>Importação do histórico existente — planilhas, sistemas legados ou registros físicos — nenhum dado é perdido na transição.</p>
                    </div>
                    <div class="wf-step reveal d1">
                        <div class="wf-num-wrap"><span class="wf-num">02</span>
                            <div class="wf-step-ico">🔑</div>
                        </div>
                        <h3>Configuração de perfis</h3>
                        <p>Cada usuário recebe acesso de acordo com sua função: professor, coordenador, nutricionista, gestor de RH ou secretário.</p>
                    </div>
                    <div class="wf-step reveal d2">
                        <div class="wf-num-wrap"><span class="wf-num">03</span>
                            <div class="wf-step-ico">🎓</div>
                        </div>
                        <h3>Treinamento das equipes</h3>
                        <p>Capacitação presencial por módulo, com material de apoio na plataforma e vídeos de consulta rápida para cada perfil.</p>
                    </div>
                    <div class="wf-step reveal d3">
                        <div class="wf-num-wrap"><span class="wf-num">04</span>
                            <div class="wf-step-ico">🛠️</div>
                        </div>
                        <h3>Operação assistida</h3>
                        <p>Acompanhamento técnico nos primeiros meses para ajustar fluxos, resolver dúvidas e garantir adoção completa.</p>
                    </div>
                    <div class="wf-step reveal d4">
                        <div class="wf-num-wrap"><span class="wf-num">05</span>
                            <div class="wf-step-ico">📊</div>
                        </div>
                        <h3>Gestão estratégica</h3>
                        <p>Com dados consolidados, a secretaria passa a tomar decisões baseadas em dados reais e prestar contas com precisão.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- BENEFÍCIOS -->
        <section class="section benefits">
            <div class="container">
                <div style="text-align:center;margin-bottom:56px;" class="reveal">
                    <div class="sec-eyebrow">Por que o Gestão Edu</div>
                    <h2 class="sec-title">Benefícios que toda a rede sente</h2>
                </div>
                <div class="benefits-grid">
                    <div class="benefit-card reveal">
                        <div class="benefit-icon">🎯</div>
                        <h3>Centralização de Dados</h3>
                        <p>Todas as informações — pedagógicas, patrimoniais, de pessoal e de alimentação — reunidas em uma única base confiável e acessível.</p>
                    </div>
                    <div class="benefit-card feat reveal d1">
                        <div class="benefit-icon">🔗</div>
                        <h3>Integração Real entre Setores</h3>
                        <p>O RH sabe quais professores estão de atestado. A alimentação conhece a frequência prevista. O transporte vê os alunos matriculados. Tudo conectado.</p>
                    </div>
                    <div class="benefit-card reveal d2">
                        <div class="benefit-icon">⚡</div>
                        <h3>Eficiência Administrativa</h3>
                        <p>Redução de retrabalho, padronização de processos e automação de tarefas repetitivas que consomem horas da equipe diariamente.</p>
                    </div>
                    <div class="benefit-card reveal d1">
                        <div class="benefit-icon">🔍</div>
                        <h3>Transparência Total</h3>
                        <p>Histórico auditável de todas as ações — quem fez o quê, quando e em qual unidade — para prestação de contas sem margem para erros.</p>
                    </div>
                    <div class="benefit-card reveal d2">
                        <div class="benefit-icon">📱</div>
                        <h3>Acesso Responsivo</h3>
                        <p>Interface adaptada para computadores, tablets e smartphones — gestores, diretores e professores acessam de qualquer lugar.</p>
                    </div>
                    <div class="benefit-card reveal d3">
                        <div class="benefit-icon">📊</div>
                        <h3>Decisões Baseadas em Dados</h3>
                        <p>Indicadores consolidados em tempo real para que a secretaria passe de uma gestão reativa para uma gestão verdadeiramente estratégica.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- INTEGRAÇÃO -->
        <section id="integracao" class="section integration">
            <div class="container">
                <div style="text-align:center;margin-bottom:64px;" class="reveal">
                    <div class="sec-eyebrow">Integração</div>
                    <h2 class="sec-title">Um ecossistema, <span class="italic">não uma coleção de ferramentas</span></h2>
                    <p class="sec-body" style="margin:0 auto;">Os cinco módulos compartilham a mesma base de dados. A matrícula alimenta o transporte. A frequência alimenta a alimentação. O RH alimenta tudo. Essa é a diferença de um sistema realmente integrado.</p>
                </div>
                <div class="int-spokes reveal">
                    <div class="int-center">🏛️</div>
                    <div class="int-spoke s1">
                        <div class="int-spoke-icon">📋</div>
                        <div class="int-spoke-lbl">Pedagógico</div>
                    </div>
                    <div class="int-spoke s2">
                        <div class="int-spoke-icon">🚌</div>
                        <div class="int-spoke-lbl">Transporte</div>
                    </div>
                    <div class="int-spoke s3">
                        <div class="int-spoke-icon">🍽️</div>
                        <div class="int-spoke-lbl">Alimentação</div>
                    </div>
                    <div class="int-spoke s4">
                        <div class="int-spoke-icon">🏫</div>
                        <div class="int-spoke-lbl">Administrativo</div>
                    </div>
                    <div class="int-spoke s5">
                        <div class="int-spoke-icon">👥</div>
                        <div class="int-spoke-lbl">R. Humanos</div>
                    </div>
                </div>
                <div class="int-grid">
                    <div class="int-point reveal">
                        <div class="int-point-icon">🔄</div>
                        <h3>Dados compartilhados</h3>
                        <p>Aluno matriculado no módulo pedagógico já aparece automaticamente no transporte e na alimentação.</p>
                    </div>
                    <div class="int-point reveal d1">
                        <div class="int-point-icon">📊</div>
                        <h3>Relatórios consolidados</h3>
                        <p>Visão gerencial da secretaria com dados de todos os módulos em uma única tela de indicadores.</p>
                    </div>
                    <div class="int-point reveal d2">
                        <div class="int-point-icon">🔔</div>
                        <h3>Alertas cruzados</h3>
                        <p>Queda de frequência no pedagógico gera alerta no RH. Sistema que pensa junto com o gestor.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- SEGURANÇA -->
        <section id="seguranca" class="section security">
            <div class="container security-grid">
                <div class="sec-left reveal">
                    <div class="sec-eyebrow">Segurança e Privacidade</div>
                    <h2 class="sec-title">Dados públicos exigem <span class="italic">proteção de nível profissional</span></h2>
                    <p class="sec-body">O Gestão Edu foi projetado com a LGPD como princípio de design — protegendo as informações de alunos, funcionários e a própria secretaria com rigor técnico e conformidade legal.</p>
                    <div class="sec-list">
                        <div class="sec-item">
                            <div class="sec-ico">🔒</div>
                            <div>
                                <h4>Criptografia em trânsito e em repouso</h4>
                                <p>Comunicações protegidas por TLS 1.3 e dados armazenados cifrados — impossibilitando acesso não autorizado mesmo em caso de vazamento de infraestrutura.</p>
                            </div>
                        </div>
                        <div class="sec-item">
                            <div class="sec-ico">👤</div>
                            <div>
                                <h4>Controle de acesso por perfil (RBAC)</h4>
                                <p>Cada usuário vê apenas o que sua função permite. Um professor não acessa dados de RH; um motorista não vê notas de alunos.</p>
                            </div>
                        </div>
                        <div class="sec-item">
                            <div class="sec-ico">🗄️</div>
                            <div>
                                <h4>Backups automáticos e recuperação garantida</h4>
                                <p>Cópias automáticas diárias com retenção de 30 dias. Recuperação de dados em caso de incidentes sem perda de histórico.</p>
                            </div>
                        </div>
                        <div class="sec-item">
                            <div class="sec-ico">📋</div>
                            <div>
                                <h4>Log de auditoria imutável</h4>
                                <p>Registro completo de toda ação no sistema — quem, o quê, quando e de qual dispositivo — para fins de auditoria e conformidade legal.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="reveal d2">
                    <div class="shield-box">
                        <span class="shield-ico">🛡️</span>
                        <h3>Conformidade com a LGPD</h3>
                        <p>Controle de consentimento, minimização de dados e direito ao esquecimento incorporados na arquitetura — não adicionados depois como correção.</p>
                        <div class="shield-tags">
                            <div class="shield-tag">🔒 TLS 1.3</div>
                            <div class="shield-tag">🛡️ LGPD</div>
                            <div class="shield-tag">🗄️ Backup Diário</div>
                            <div class="shield-tag">📋 Auditoria</div>
                            <div class="shield-tag">👤 RBAC</div>
                            <div class="shield-tag">☁️ Cloud</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- QUOTE -->
        <section class="quote-section">
            <div class="quote-inner reveal">
                <div class="quote-mark">"</div>
                <blockquote>Uma secretaria de educação eficiente não se constrói com mais papel — constrói-se com informação certa, na hora certa, para quem precisa tomar a decisão.</blockquote>
                <div class="quote-author">Princípio da <strong>Gestão Pública Orientada por Dados</strong></div>
            </div>
        </section>

        <!-- CTA FINAL -->
        <section class="cta-final">
            <div class="cta-orb"></div>
            <div class="cta-inner">
                <h2>Pronto para transformar a gestão da <span class="italic">educação de Umuarama?</span></h2>
                <p>Entre com suas credenciais institucionais e acesse o painel completo da Secretaria Municipal de Educação.</p>
                <div class="cta-cards">
                    <div class="cta-card">
                        <div class="cta-card-icon">📋</div>
                        <h4>Gestão Pedagógica</h4>
                        <p>Alunos, turmas, notas e frequência</p>
                    </div>
                    <div class="cta-card">
                        <div class="cta-card-icon">🚌</div>
                        <h4>Transporte e Alimentação</h4>
                        <p>Rotas, cardápios e estoque integrados</p>
                    </div>
                    <div class="cta-card">
                        <div class="cta-card-icon">👥</div>
                        <h4>RH e Patrimônio</h4>
                        <p>Servidores, bens e manutenções</p>
                    </div>
                </div>
                <a href="/admin/login" class="btn-cta">Acessar o Sistema →</a>
            </div>
        </section>

        <!-- FOOTER -->
        <footer>
            <div class="footer-top">
                <div class="footer-brand">
                    <img src="/images/brasao-umuarama.png" alt="Brasão de Umuarama">
                    <p>Sistema de Gestão Escolar da Secretaria Municipal de Educação de Umuarama — desenvolvido para centralizar, integrar e modernizar a gestão da educação pública municipal.</p>
                    <div class="footer-contact">
                        <p><strong>Secretaria Municipal de Educação</strong></p>
                        <p>Prefeitura Municipal de Umuarama — PR</p>
                    </div>
                </div>
                <div>
                    <div class="footer-col-ttl">Módulos</div>
                    <ul class="footer-links">
                        <li><a href="#modulos">Módulo Pedagógico</a></li>
                        <li><a href="#modulos">Transporte Escolar</a></li>
                        <li><a href="#modulos">Alimentação Escolar</a></li>
                        <li><a href="#modulos">Administrativo</a></li>
                        <li><a href="#modulos">Recursos Humanos</a></li>
                    </ul>
                </div>
                <div>
                    <div class="footer-col-ttl">Navegação</div>
                    <ul class="footer-links">
                        <li><a href="#problemas">Diagnóstico</a></li>
                        <li><a href="#integracao">Integração</a></li>
                        <li><a href="#seguranca">Segurança</a></li>
                        <li><a href="#como-funciona">Implantação</a></li>
                        <li><a href="/admin/login">Acessar o Sistema</a></li>
                    </ul>
                </div>
                <div>
                    <div class="footer-col-ttl">Conformidade</div>
                    <ul class="footer-links">
                        <li><a href="#">Política de Privacidade</a></li>
                        <li><a href="#">Termos de Uso</a></li>
                        <li><a href="#">LGPD</a></li>
                        <li><a href="#">Suporte Técnico</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>© 2026 · Prefeitura Municipal de Umuarama · Secretaria Municipal de Educação · Gestão Edu</p>
                <div class="footer-logos">
                    <img src="/images/brasao-umuarama.png" alt="Umuarama">
                    <img src="/images/abrinq-logo.png" alt="Fundação Abrinq">
                </div>
            </div>
        </footer>

        <script>
            // Cursor glow
            const glow = document.getElementById('cursor-glow');
            document.addEventListener('mousemove', e => {
                glow.style.left = e.clientX + 'px';
                glow.style.top = e.clientY + 'px';
            });

            // Reveal on scroll
            const obs = new IntersectionObserver(entries => {
                entries.forEach(e => {
                    if (e.isIntersecting) e.target.classList.add('visible');
                });
            }, {
                threshold: 0.1
            });
            document.querySelectorAll('.reveal').forEach(el => obs.observe(el));

            // Module tabs
            function switchMod(id, btn) {
                document.querySelectorAll('.module-panel').forEach(p => p.classList.remove('active'));
                document.querySelectorAll('.modnav-btn').forEach(b => b.classList.remove('active'));
                document.getElementById('mod-' + id).classList.add('active');
                btn.classList.add('active');
            }

            // Particles
            const pc = document.getElementById('particles');
            for (let i = 0; i < 30; i++) {
                const p = document.createElement('div');
                p.className = 'particle';
                p.style.cssText = `left:${Math.random()*100}%;top:${100+Math.random()*100}%;--dx:${(Math.random()-.5)*60}px;animation-duration:${6+Math.random()*10}s;animation-delay:${Math.random()*10}s;opacity:${0.2+Math.random()*0.4};width:${1+Math.random()*2}px;height:${1+Math.random()*2}px;background:rgba(255,255,255,0.5);`;
                pc.appendChild(p);
            }

            // Counter animation
            setTimeout(() => {
                document.querySelectorAll('.hero-stat-num').forEach(el => {
                    const text = el.textContent;
                    const num = parseFloat(text.replace(/[^0-9.]/g, ''));
                    if (isNaN(num)) return;
                    const suffix = text.replace(/[0-9.]/g, '');
                    let start = 0;
                    const step = num / 40;
                    const t = setInterval(() => {
                        start += step;
                        if (start >= num) {
                            el.textContent = text;
                            clearInterval(t);
                            return;
                        }
                        el.textContent = (Number.isInteger(num) ? Math.floor(start) : start.toFixed(1)) + suffix;
                    }, 40);
                });
            }, 800);
        </script>

</x-filament-panels::page>