@extends('mobile.layouts.auth')

@section('title', 'Entrar | Gestão Edu Mobile')

@section('content')
    <section class="mobile-auth-card">
        <div class="mobile-hero mobile-hero--auth">
            <p class="mobile-hero__eyebrow">PWA mobile</p>
            <h1 class="mobile-hero__title">Entre no Gestão Edu do celular.</h1>
            <p class="mobile-hero__subtitle">
                A experiencia mobile agora roda separada do painel Filament, com mais liberdade de layout e pronta para instalacao.
            </p>
        </div>

        <div class="mobile-panel">
            <div class="mobile-panel__header">
                <p class="mobile-panel__eyebrow">Acesso</p>
                <h2 class="mobile-panel__title">Use as mesmas credenciais do sistema</h2>
            </div>

            @if ($errors->any())
                <div class="mobile-alert mobile-alert--danger">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('mobile.login.store') }}" class="mobile-form">
                @csrf

                <label class="mobile-field">
                    <span>E-mail</span>
                    <input type="email" name="email" value="{{ old('email') }}" class="mobile-input" autocomplete="username" required>
                </label>

                <label class="mobile-field">
                    <span>Senha</span>
                    <input type="password" name="password" class="mobile-input" autocomplete="current-password" required>
                </label>

                <label class="mobile-checkbox">
                    <input type="checkbox" name="remember" value="1">
                    <span>Manter sessao ativa neste aparelho</span>
                </label>

                <button type="submit" class="mobile-button mobile-button--primary">
                    Entrar
                </button>
            </form>

            <div class="mobile-auth-actions">
                <a href="{{ route('google.redirect', ['redirect_to' => route('mobile.home')]) }}" class="mobile-button mobile-button--ghost">
                    Entrar com Google
                </a>
                <a href="{{ route('mobile.install') }}" class="mobile-button mobile-button--ghost">
                    Como instalar o app
                </a>
                <button type="button" class="mobile-button mobile-button--ghost" data-pwa-install hidden>
                    Instalar app
                </button>
            </div>
        </div>
    </section>
@endsection
