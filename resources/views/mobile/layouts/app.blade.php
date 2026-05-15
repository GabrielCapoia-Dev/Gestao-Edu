<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0f4c81">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Gestao Edu">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="description" content="@yield('meta_description', 'Aplicativo mobile do Gestao Edu.')">
    <title>@yield('title', 'Gestao Edu Mobile')</title>

    <link rel="manifest" href="{{ asset('mobile.webmanifest') }}">
    <link rel="icon" href="{{ asset('pwa/icon-192.png') }}" sizes="192x192">
    <link rel="apple-touch-icon" href="{{ asset('pwa/apple-touch-icon.png') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/action-loading.css') }}?v={{ filemtime(public_path('css/action-loading.css')) }}">
    @stack('head')
</head>
<body class="mobile-app-body">
    <div class="mobile-app-shell">
        <header class="mobile-topbar">
            <a href="{{ route('mobile.home') }}" class="mobile-brand">
                <span class="mobile-brand__mark">GE</span>
                <span class="mobile-brand__text">
                    <strong>Gestao Edu</strong>
                    <small>Mobile</small>
                </span>
            </a>

            <div class="mobile-topbar__actions">
                <button type="button" class="mobile-install-button" data-pwa-install hidden>
                    Instalar app
                </button>
            </div>
        </header>

        <main class="mobile-content">
            @yield('content')
        </main>

        <nav class="mobile-bottom-nav">
            <a href="{{ route('mobile.home') }}" class="mobile-bottom-nav__item {{ request()->routeIs('mobile.home', 'mobile.legacy.home') ? 'is-active' : '' }}">
                <span>Inicio</span>
            </a>

            <a href="{{ route('mobile.access.index') }}" class="mobile-bottom-nav__item {{ request()->routeIs('mobile.access.index', 'mobile.users.index', 'mobile.domains.index', 'mobile.roles.index') ? 'is-active' : '' }}">
                <span>Acesso</span>
            </a>

            <a href="{{ route('mobile.reports.index') }}" class="mobile-bottom-nav__item {{ request()->routeIs('mobile.reports.index', 'mobile.reports.dashboard', 'mobile.reports.professor-by-class', 'mobile.reports.missing-teachers') ? 'is-active' : '' }}">
                <span>Relatorios</span>
            </a>

            <form method="POST" action="{{ route('mobile.logout') }}" class="mobile-bottom-nav__form">
                @csrf
                <button type="submit" class="mobile-bottom-nav__item mobile-bottom-nav__button">
                    <span>Sair</span>
                </button>
            </form>
        </nav>
    </div>

    <aside class="mobile-ios-hint" data-ios-install-hint hidden>
        <p>Para instalar no iPhone, toque em Compartilhar e depois em Adicionar a Tela de Inicio.</p>
        <button type="button" data-dismiss-ios-install>Fechar</button>
    </aside>

    <script>
        window.MobilePwaConfig = {
            scope: '/app/',
            serviceWorkerUrl: '{{ asset('mobile-sw.js') }}',
        };
    </script>
    <script src="{{ asset('js/mobile-pwa.js') }}" defer></script>
    <script src="{{ asset('js/app/action-loading.js') }}?v={{ filemtime(public_path('js/app/action-loading.js')) }}" defer></script>
    @stack('scripts')
</body>
</html>
