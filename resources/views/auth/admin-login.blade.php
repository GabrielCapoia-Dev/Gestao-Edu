<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Login - Gestão Edu</title>
    <style>
        :root {
            color-scheme: light;
            --primary: #074f9b;
            --primary-800: #053a72;
            --primary-700: #063f7c;
            --primary-100: #dcecff;
            --primary-50: #f0f7ff;
            --sky: #eaf4ff;
            --mint: #dff8ef;
            --green: #11845b;
            --amber: #f4b942;
            --amber-100: #fff4cf;
            --ink: #102033;
            --text: #344054;
            --muted: #667085;
            --line: #d8e4f2;
            --paper: #f8fbff;
            --white: #ffffff;
            --danger: #b42318;
            --success: #067647;
            font-family: Arial, Helvetica, sans-serif;
        }

        * {
            box-sizing: border-box;
        }

        html {
            height: 100%;
            min-height: 100%;
            background: var(--paper);
            overflow: hidden;
        }

        body {
            height: 100vh;
            height: 100svh;
            min-height: 100vh;
            margin: 0;
            overflow: hidden;
            color: var(--text);
            background:
                radial-gradient(circle at 10% 12%, rgba(7, 79, 155, .22), transparent 27%),
                radial-gradient(circle at 66% 8%, rgba(7, 79, 155, .10), transparent 28%),
                radial-gradient(circle at 84% 84%, rgba(244, 185, 66, .16), transparent 30%),
                linear-gradient(135deg, #fafdff 0%, #edf6ff 46%, #ffffff 100%);
        }

        button,
        input {
            font: inherit;
        }

        .auth-shell {
            height: 100vh;
            height: 100svh;
            min-height: 0;
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(360px, 480px);
            overflow: hidden;
        }

        .brand-panel {
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            gap: clamp(46px, 10vh, 112px);
            height: 100vh;
            height: 100svh;
            min-height: 0;
            padding: clamp(24px, 4vw, 52px);
            overflow: hidden;
        }

        .brand-panel::before {
            content: "";
            position: absolute;
            inset: 28px;
            z-index: 0;
            border: 1px solid rgba(7, 79, 155, .14);
            border-radius: 24px;
            background:
                linear-gradient(118deg, rgba(255, 255, 255, .88) 0%, rgba(247, 251, 255, .78) 46%, rgba(222, 239, 255, .66) 100%),
                linear-gradient(135deg, rgba(7, 79, 155, .12), rgba(255, 255, 255, .28));
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, .80), 0 24px 80px rgba(7, 79, 155, .10);
            pointer-events: none;
        }

        .brand-header,
        .hero {
            position: relative;
            z-index: 2;
        }

        .icon-sprite {
            position: absolute;
            width: 0;
            height: 0;
            overflow: hidden;
        }

        .edu-icons {
            position: absolute;
            inset: 28px;
            z-index: 1;
            overflow: hidden;
            pointer-events: none;
        }

        .edu-icon {
            --size: 64px;
            --alpha: .24;
            --rot: 0deg;
            position: absolute;
            width: var(--size);
            height: var(--size);
            color: rgba(7, 79, 155, var(--alpha));
            transform: rotate(var(--rot));
            filter: drop-shadow(0 18px 28px rgba(7, 79, 155, .08));
        }

        .edu-icon svg {
            width: 100%;
            height: 100%;
            fill: none;
            stroke: currentColor;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-width: 1.8;
        }

        .icon-book {
            --size: clamp(62px, 6.4vw, 100px);
            --alpha: .32;
            --rot: -10deg;
            top: 10%;
            right: 28%;
        }

        .icon-calendar {
            --size: clamp(50px, 5.4vw, 82px);
            --alpha: .36;
            --rot: -6deg;
            right: 8%;
            top: 13%;
        }

        .icon-check {
            --size: clamp(52px, 5.8vw, 86px);
            --alpha: .34;
            --rot: 0deg;
            right: 17%;
            bottom: 27%;
        }

        .icon-key {
            --size: clamp(44px, 4.8vw, 72px);
            --alpha: .24;
            --rot: 22deg;
            right: 34%;
            bottom: 12%;
        }

        .icon-pencil {
            --size: clamp(40px, 4.2vw, 62px);
            --alpha: .23;
            --rot: 11deg;
            right: 42%;
            top: 8%;
        }

        .icon-books {
            --size: clamp(56px, 6vw, 90px);
            --alpha: .28;
            --rot: 8deg;
            right: 5%;
            bottom: 6%;
        }

        .icon-book-alt {
            --size: clamp(42px, 4.6vw, 70px);
            --alpha: .17;
            --rot: 12deg;
            right: 45%;
            bottom: 20%;
        }

        .icon-calendar-alt {
            --size: clamp(38px, 4.2vw, 62px);
            --alpha: .22;
            --rot: 8deg;
            right: 25%;
            bottom: 8%;
        }

        .icon-check-alt {
            --size: clamp(34px, 3.8vw, 56px);
            --alpha: .31;
            --rot: -8deg;
            right: 31%;
            bottom: 38%;
        }

        .icon-key-alt {
            --size: clamp(34px, 3.8vw, 54px);
            --alpha: .19;
            --rot: -18deg;
            right: 21%;
            top: 24%;
        }

        .icon-pencil-alt {
            --size: clamp(34px, 3.4vw, 50px);
            --alpha: .28;
            --rot: -13deg;
            right: 36%;
            top: 28%;
        }

        .icon-books-alt {
            --size: clamp(42px, 4.5vw, 64px);
            --alpha: .23;
            --rot: -7deg;
            right: 10%;
            top: 35%;
        }

        .icon-book-top {
            --size: clamp(36px, 3.7vw, 58px);
            --alpha: .27;
            --rot: 8deg;
            right: 18%;
            top: 7%;
        }

        .icon-calendar-top {
            --size: clamp(34px, 3.4vw, 52px);
            --alpha: .18;
            --rot: 10deg;
            right: 4%;
            top: 28%;
        }

        .icon-check-top {
            --size: clamp(38px, 3.8vw, 60px);
            --alpha: .24;
            --rot: -7deg;
            right: 51%;
            top: 18%;
        }

        .icon-key-top {
            --size: clamp(30px, 3vw, 46px);
            --alpha: .34;
            --rot: 18deg;
            right: 34%;
            top: 20%;
        }

        .brand-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            animation: fadeUp .5s ease both;
        }

        .brand-lockup,
        .mobile-brand {
            display: flex;
            align-items: center;
            gap: 13px;
        }

        .brand-mark {
            width: 48px;
            height: 48px;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            border-radius: 14px;
            background: var(--primary);
            color: var(--white);
            font-size: 24px;
            font-weight: 900;
            box-shadow: 0 14px 32px rgba(7, 79, 155, .18);
        }

        .brand-name,
        .brand-subtitle {
            display: block;
            line-height: 1.12;
        }

        .brand-name {
            color: var(--primary);
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 0;
        }

        .brand-subtitle {
            margin-top: 5px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .status-pill {
            min-height: 34px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0 12px;
            border: 1px solid rgba(17, 132, 91, .18);
            border-radius: 999px;
            background: var(--mint);
            color: var(--green);
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: var(--green);
            box-shadow: 0 0 0 0 rgba(17, 132, 91, .28);
            animation: pulse 2.4s ease-out infinite;
        }

        .hero {
            max-width: 640px;
            padding: 0;
            animation: fadeUp .58s .08s ease both;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
            color: var(--primary);
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .eyebrow::before {
            content: "";
            width: 34px;
            height: 3px;
            border-radius: 999px;
            background: var(--amber);
        }

        .hero h1 {
            max-width: 660px;
            margin: 0;
            color: var(--primary);
            font-size: clamp(38px, 5.6vw, 70px);
            line-height: .98;
            letter-spacing: 0;
        }

        .hero p {
            max-width: 560px;
            margin: 22px 0 0;
            color: var(--primary-700);
            font-size: clamp(15px, 1.4vw, 18px);
            line-height: 1.65;
        }

        .auth-panel {
            height: 100vh;
            height: 100svh;
            min-height: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(16px, 4vh, 40px) clamp(20px, 5vw, 52px);
            overflow-y: auto;
            background: linear-gradient(180deg, rgba(255, 255, 255, .90), rgba(247, 251, 255, .86));
            border-left: 1px solid rgba(7, 79, 155, .10);
            backdrop-filter: blur(14px);
        }

        .login-card {
            width: min(100%, 386px);
            max-height: calc(100svh - 32px);
            overflow-y: auto;
            overscroll-behavior: contain;
            padding: clamp(22px, 3vh, 30px);
            border: 1px solid rgba(7, 79, 155, .12);
            border-radius: 18px;
            background: var(--white);
            box-shadow: 0 24px 60px rgba(7, 79, 155, .12);
            animation: cardIn .48s .1s ease both;
        }

        .mobile-brand {
            display: none;
            margin-bottom: 24px;
        }

        .login-title {
            margin: 0 0 8px;
            color: var(--primary);
            font-size: 30px;
            line-height: 1.12;
            letter-spacing: 0;
        }

        .intro {
            margin: 0 0 26px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.55;
        }

        .login-notices {
            display: grid;
            gap: 10px;
            margin-bottom: 18px;
        }

        .login-notice {
            display: grid;
            grid-template-columns: 22px minmax(0, 1fr);
            gap: 10px;
            padding: 12px 14px;
            border: 1px solid var(--notice-border);
            border-radius: 12px;
            background: var(--notice-background);
            color: var(--notice-color);
            font-size: 13px;
            line-height: 1.4;
        }

        .login-notice-success {
            --notice-border: #abefc6;
            --notice-background: #ecfdf3;
            --notice-color: #067647;
        }

        .login-notice-warning {
            --notice-border: #fedf89;
            --notice-background: #fffaeb;
            --notice-color: #93370d;
        }

        .login-notice-danger {
            --notice-border: #fecdca;
            --notice-background: #fef3f2;
            --notice-color: #b42318;
        }

        .login-notice-info {
            --notice-border: #b2ddff;
            --notice-background: #eff8ff;
            --notice-color: #175cd3;
        }

        .login-notice-icon {
            width: 22px;
            height: 22px;
            display: grid;
            place-items: center;
            border: 1px solid currentColor;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 900;
            line-height: 1;
        }

        .login-notice-title {
            display: block;
            margin-bottom: 2px;
            color: inherit;
            font-weight: 800;
        }

        .login-notice-message {
            margin: 0;
        }

        .field {
            margin-bottom: 17px;
        }

        .field label {
            display: block;
            margin-bottom: 7px;
            color: var(--ink);
            font-size: 13px;
            font-weight: 800;
        }

        .field input[type="email"],
        .field input[type="password"],
        .field input[type="text"] {
            width: 100%;
            min-height: 48px;
            padding: 12px 14px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #fbfdff;
            color: var(--ink);
            font-size: 15px;
            outline: none;
            transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
        }

        .field input::placeholder {
            color: #98a2b3;
        }

        .field input:hover {
            background: var(--white);
            border-color: #b8cce3;
        }

        .field input:focus {
            background: var(--white);
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(7, 79, 155, .12);
        }

        .password-field {
            position: relative;
        }

        .password-field input {
            padding-right: 48px;
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 8px;
            width: 36px;
            height: 36px;
            display: grid;
            place-items: center;
            padding: 0;
            border: 0;
            border-radius: 10px;
            background: transparent;
            color: var(--primary);
            cursor: pointer;
            transform: translateY(-50%);
            transition: background .18s ease, color .18s ease, box-shadow .18s ease;
        }

        .password-toggle:hover,
        .password-toggle:focus-visible {
            background: var(--primary-50);
            color: var(--primary-700);
            outline: none;
            box-shadow: 0 0 0 3px rgba(7, 79, 155, .10);
        }

        .password-toggle svg {
            width: 19px;
            height: 19px;
            fill: none;
            stroke: currentColor;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-width: 2;
        }

        .password-toggle .eye-off,
        .password-toggle.is-visible .eye {
            display: none;
        }

        .password-toggle.is-visible .eye-off {
            display: block;
        }

        .error {
            margin: 7px 0 0;
            color: var(--danger);
            font-size: 13px;
            line-height: 1.35;
        }

        .options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin: 2px 0 24px;
            color: var(--muted);
            font-size: 13px;
        }

        .remember {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            user-select: none;
        }

        .remember input {
            width: 17px;
            height: 17px;
            margin: 0;
            accent-color: var(--primary);
        }

        .submit,
        .google {
            width: 100%;
            min-height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
            transition: transform .18s ease, box-shadow .18s ease, background .18s ease, border-color .18s ease;
        }

        .submit {
            border: 0;
            background: var(--primary);
            color: var(--white);
            box-shadow: 0 16px 34px rgba(7, 79, 155, .24);
        }

        .submit:hover,
        .submit:focus-visible {
            transform: translateY(-2px);
            background: var(--primary-700);
            box-shadow: 0 20px 44px rgba(7, 79, 155, .30);
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 14px;
            margin: 24px 0;
            color: var(--muted);
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .divider::before,
        .divider::after {
            content: "";
            height: 1px;
            flex: 1;
            background: var(--line);
        }

        .google {
            border: 1px solid var(--line);
            background: var(--white);
            color: var(--primary);
        }

        .google:hover,
        .google:focus-visible {
            transform: translateY(-2px);
            border-color: #b8cce3;
            background: var(--primary-50);
            box-shadow: 0 14px 28px rgba(7, 79, 155, .10);
        }

        .footnote {
            margin: 22px 0 0;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.45;
            text-align: center;
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(14px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes cardIn {
            from {
                opacity: 0;
                transform: translateY(12px) scale(.99);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(17, 132, 91, .28);
            }
            70% {
                box-shadow: 0 0 0 9px rgba(17, 132, 91, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(17, 132, 91, 0);
            }
        }

        @media (max-width: 980px) {
            .auth-shell {
                grid-template-columns: 1fr;
            }

            .brand-panel {
                display: none;
            }

            .auth-panel {
                align-items: center;
                height: 100vh;
                height: 100svh;
                min-height: 0;
                padding: 18px 20px;
                border-left: 0;
                background: transparent;
            }

            .login-card {
                max-width: 430px;
                margin: 0 auto;
                padding: 26px;
            }

            .mobile-brand {
                display: flex;
            }
        }

        @media (max-width: 1220px) {
            .edu-icon {
                opacity: .62;
            }

            .hero {
                max-width: 590px;
            }
        }

        @media (max-width: 480px) {
            .auth-panel {
                padding: 10px;
            }

            .login-card {
                padding: 18px;
                border-radius: 16px;
            }

            .mobile-brand {
                margin-bottom: 16px;
            }

            .brand-mark {
                width: 42px;
                height: 42px;
                border-radius: 12px;
                font-size: 21px;
            }

            .brand-name {
                font-size: 18px;
            }

            .brand-subtitle {
                margin-top: 3px;
                font-size: 11px;
            }

            .login-title {
                font-size: 25px;
            }

            .intro {
                margin-bottom: 14px;
                font-size: 13px;
            }

            .field {
                margin-bottom: 11px;
            }

            .field label {
                margin-bottom: 6px;
            }

            .options {
                margin-bottom: 14px;
            }

            .divider {
                margin: 16px 0;
            }

            .footnote {
                margin-top: 14px;
            }

            .submit,
            .google,
            .field input[type="email"],
            .field input[type="password"],
            .field input[type="text"] {
                min-height: 43px;
            }
        }

        @media (max-width: 360px), (max-height: 600px) {
            .login-card {
                padding: 14px;
                border-radius: 14px;
            }

            .brand-mark {
                width: 38px;
                height: 38px;
                border-radius: 11px;
                font-size: 20px;
            }

            .mobile-brand {
                margin-bottom: 12px;
            }

            .login-title {
                font-size: 23px;
            }

            .intro {
                margin-bottom: 10px;
            }

            .field {
                margin-bottom: 9px;
            }

            .field input[type="email"],
            .field input[type="password"],
            .field input[type="text"],
            .submit,
            .google {
                min-height: 40px;
            }

            .options,
            .divider,
            .footnote {
                margin-top: 10px;
                margin-bottom: 10px;
            }
        }

        @media (max-height: 520px) {
            .mobile-brand {
                margin-bottom: 8px;
            }

            .intro,
            .footnote {
                display: none;
            }

            .login-title {
                margin-bottom: 10px;
                font-size: 21px;
            }

            .field label {
                margin-bottom: 4px;
                font-size: 12px;
            }

            .field {
                margin-bottom: 7px;
            }

            .options {
                margin: 8px 0 10px;
            }

            .divider {
                margin: 10px 0;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
                scroll-behavior: auto !important;
                transition-duration: .01ms !important;
            }
        }
    </style>
</head>
<body>
    <svg class="icon-sprite" aria-hidden="true" focusable="false">
        <symbol id="edu-icon-book" viewBox="0 0 64 64">
            <path d="M14 17c7-3 13-2 18 2v34c-5-4-11-5-18-2V17z"></path>
            <path d="M50 17c-7-3-13-2-18 2v34c5-4 11-5 18-2V17z"></path>
            <path d="M32 19v34"></path>
        </symbol>
        <symbol id="edu-icon-calendar" viewBox="0 0 64 64">
            <rect x="13" y="16" width="38" height="36" rx="6"></rect>
            <path d="M22 12v8M42 12v8M13 26h38"></path>
            <path d="M22 35h6M36 35h6M22 44h6"></path>
        </symbol>
        <symbol id="edu-icon-check" viewBox="0 0 64 64">
            <rect x="14" y="14" width="36" height="36" rx="7"></rect>
            <path d="M23 33l7 7 13-16"></path>
        </symbol>
        <symbol id="edu-icon-key" viewBox="0 0 64 64">
            <circle cx="24" cy="32" r="9"></circle>
            <path d="M33 32h19M43 32v7M50 32v5"></path>
        </symbol>
        <symbol id="edu-icon-pencil" viewBox="0 0 64 64">
            <path d="M16 48l-3 9 9-3 30-30-6-6-30 30z"></path>
            <path d="M41 23l6 6"></path>
            <path d="M46 18l3-3a4 4 0 0 1 6 6l-3 3"></path>
        </symbol>
        <symbol id="edu-icon-books" viewBox="0 0 64 64">
            <path d="M18 16h12v38H18zM34 20h12v34H34z"></path>
            <path d="M21 25h6M37 29h6M21 47h6M37 47h6"></path>
        </symbol>
    </svg>

    <main class="auth-shell">
        <section class="brand-panel" aria-label="Gestão Edu">
            <div class="edu-icons" aria-hidden="true">
                <span class="edu-icon icon-book"><svg><use href="#edu-icon-book"></use></svg></span>
                <span class="edu-icon icon-calendar"><svg><use href="#edu-icon-calendar"></use></svg></span>
                <span class="edu-icon icon-check"><svg><use href="#edu-icon-check"></use></svg></span>
                <span class="edu-icon icon-key"><svg><use href="#edu-icon-key"></use></svg></span>
                <span class="edu-icon icon-pencil"><svg><use href="#edu-icon-pencil"></use></svg></span>
                <span class="edu-icon icon-books"><svg><use href="#edu-icon-books"></use></svg></span>
                <span class="edu-icon icon-book-alt"><svg><use href="#edu-icon-book"></use></svg></span>
                <span class="edu-icon icon-calendar-alt"><svg><use href="#edu-icon-calendar"></use></svg></span>
                <span class="edu-icon icon-check-alt"><svg><use href="#edu-icon-check"></use></svg></span>
                <span class="edu-icon icon-key-alt"><svg><use href="#edu-icon-key"></use></svg></span>
                <span class="edu-icon icon-pencil-alt"><svg><use href="#edu-icon-pencil"></use></svg></span>
                <span class="edu-icon icon-books-alt"><svg><use href="#edu-icon-books"></use></svg></span>
                <span class="edu-icon icon-book-top"><svg><use href="#edu-icon-book"></use></svg></span>
                <span class="edu-icon icon-calendar-top"><svg><use href="#edu-icon-calendar"></use></svg></span>
                <span class="edu-icon icon-check-top"><svg><use href="#edu-icon-check"></use></svg></span>
                <span class="edu-icon icon-key-top"><svg><use href="#edu-icon-key"></use></svg></span>
            </div>

            <div class="brand-header">
                <div class="brand-lockup">
                    <span class="brand-mark" aria-hidden="true">G</span>
                    <span>
                        <span class="brand-name">Gestão Edu</span>
                        <span class="brand-subtitle">Secretaria de Educação</span>
                    </span>
                </div>

                <span class="status-pill">
                    <span class="status-dot" aria-hidden="true"></span>
                    Sistema ativo
                </span>
            </div>

            <div class="hero">
                <span class="eyebrow">Prefeitura Municipal de Umuarama</span>
                <h1>Gestão escolar simples, clara e conectada.</h1>
                <p>Um painel para apoiar a rotina da Secretaria de Educação com acesso rápido a escolas, avaliações, merenda, pedidos e relatórios.</p>
            </div>

        </section>

        <section class="auth-panel" aria-labelledby="login-title">
            <div class="login-card">
                <div class="mobile-brand">
                    <span class="brand-mark" aria-hidden="true">G</span>
                    <span>
                        <span class="brand-name">Gestão Edu</span>
                        <span class="brand-subtitle">Secretaria de Educação</span>
                    </span>
                </div>

                <h2 class="login-title" id="login-title">Acessar o sistema</h2>
                <p class="intro">Entre com seu e-mail institucional para continuar.</p>

                @if ($loginNotices !== [])
                    <div class="login-notices" role="status" aria-live="polite" aria-atomic="false">
                        @foreach ($loginNotices as $notice)
                            <div class="login-notice login-notice-{{ $notice['type'] }}">
                                <span class="login-notice-icon" aria-hidden="true">
                                    {{ match ($notice['type']) {
                                        'success' => '+',
                                        'warning' => '!',
                                        'danger' => 'x',
                                        default => 'i',
                                    } }}
                                </span>
                                <div>
                                    <strong class="login-notice-title">{{ $notice['title'] }}</strong>
                                    @if ($notice['message'] !== '')
                                        <p class="login-notice-message">{{ $notice['message'] }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ $loginAction }}" autocomplete="on">
                    @csrf

                    <div class="field">
                        <label for="email">E-mail</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            autocomplete="username"
                            placeholder="seu.email@edu.umuarama.pr.gov.br"
                            required
                            autofocus
                        >
                        @error('email')
                            <p class="error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label for="password">Senha</label>
                        <div class="password-field">
                            <input
                                id="password"
                                name="password"
                                type="password"
                                autocomplete="current-password"
                                placeholder="Digite sua senha"
                                required
                            >
                            <button class="password-toggle" type="button" data-password-toggle="password" aria-label="Mostrar senha" aria-pressed="false">
                                <svg class="eye" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <svg class="eye-off" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M3 3l18 18"></path>
                                    <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"></path>
                                    <path d="M7.1 7.1C4.2 8.8 2.5 12 2.5 12s3.5 6 9.5 6c1.5 0 2.9-.4 4.1-1"></path>
                                    <path d="M12 6c6 0 9.5 6 9.5 6a15.2 15.2 0 0 1-2.6 3.2"></path>
                                </svg>
                            </button>
                        </div>
                        @error('password')
                            <p class="error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="options">
                        <label class="remember" for="remember">
                            <input id="remember" name="remember" type="checkbox" value="1">
                            Manter conectado
                        </label>
                    </div>

                    <button class="submit" type="submit">Entrar no sistema</button>
                </form>

                <div class="divider">ou</div>

                <a class="google" href="{{ $googleLoginUrl }}">Entrar com Google</a>

                <p class="footnote">Prefeitura Municipal de Umuarama - PR</p>
            </div>
        </section>
    </main>
    <script>
        (function () {
            document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var input = document.getElementById(button.getAttribute('data-password-toggle'));
                    if (! input) return;

                    var visible = input.type === 'password';
                    input.type = visible ? 'text' : 'password';
                    button.classList.toggle('is-visible', visible);
                    button.setAttribute('aria-pressed', visible ? 'true' : 'false');
                    button.setAttribute('aria-label', visible ? 'Ocultar senha' : 'Mostrar senha');
                });
            });
        })();
    </script>
</body>
</html>
