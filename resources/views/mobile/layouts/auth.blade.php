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
    <meta name="description" content="@yield('meta_description', 'Acesso mobile do Gestao Edu.')">
    <title>@yield('title', 'Entrar | Gestao Edu Mobile')</title>

    <link rel="manifest" href="{{ asset('mobile.webmanifest') }}">
    <link rel="icon" href="{{ asset('pwa/icon-192.png') }}" sizes="192x192">
    <link rel="apple-touch-icon" href="{{ asset('pwa/apple-touch-icon.png') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-app.css') }}">
    @stack('head')
</head>
<body class="mobile-auth-body">
    <main class="mobile-auth-shell">
        @yield('content')
    </main>

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
    @stack('scripts')
</body>
</html>
