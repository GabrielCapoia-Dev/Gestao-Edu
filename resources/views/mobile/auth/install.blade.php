@extends('mobile.layouts.auth')

@section('title', 'Instalar | Gestao Edu Mobile')
@section('meta_description', 'Instale o Gestao Edu Mobile no celular por um link publico e compartilhavel.')

@section('content')
    <section class="mobile-auth-card">
        <div class="mobile-hero mobile-hero--auth">
            <p class="mobile-hero__eyebrow">Instalacao mobile</p>
            <h1 class="mobile-hero__title">Instale o Gestao Edu no celular por um link direto.</h1>
            <p class="mobile-hero__subtitle">
                Esta pagina foi feita para abrir no celular e instalar o app web sem depender do painel do navegador.
                Voce tambem pode compartilhar o link por WhatsApp para outra pessoa instalar no proprio aparelho.
            </p>
        </div>

        <div class="mobile-panel">
            <div class="mobile-panel__header">
                <p class="mobile-panel__eyebrow">Link publico</p>
                <h2 class="mobile-panel__title">Use este link para instalar ou compartilhar</h2>
            </div>

            <div class="mobile-share-box">
                <span class="mobile-share-box__label">Link de instalacao</span>
                <code class="mobile-share-box__link">{{ $shareUrl }}</code>
            </div>

            <div class="mobile-install-actions">
                <button type="button" class="mobile-button mobile-button--primary" data-pwa-install hidden>
                    Instalar neste aparelho
                </button>

                <a href="{{ $entryUrl }}" class="mobile-button mobile-button--ghost">
                    {{ $entryLabel }}
                </a>

                <button
                    type="button"
                    class="mobile-button mobile-button--ghost"
                    data-copy-install-link
                    data-copy-text="{{ $shareUrl }}"
                >
                    Copiar link
                </button>

                <a href="{{ $whatsAppUrl }}" class="mobile-button mobile-button--ghost" target="_blank" rel="noopener">
                    Compartilhar no WhatsApp
                </a>
            </div>

            <div class="mobile-alert mobile-alert--success" data-copy-feedback hidden>
                Link copiado. Agora voce pode colar no WhatsApp ou em qualquer mensagem.
            </div>
        </div>

        <div class="mobile-panel">
            <div class="mobile-panel__header">
                <p class="mobile-panel__eyebrow">Como instalar</p>
                <h2 class="mobile-panel__title">O app nao baixa um APK. Ele instala pelo navegador.</h2>
            </div>

            <div class="mobile-install-guide">
                <div class="mobile-install-guide__item">
                    <strong>Android</strong>
                    <p>Abra este link no Chrome ou Edge e toque em "Instalar neste aparelho". Se o botao nao aparecer, use o menu do navegador e escolha "Instalar app" ou "Adicionar a tela inicial".</p>
                </div>

                <div class="mobile-install-guide__item">
                    <strong>iPhone</strong>
                    <p>Abra este link no Safari. Depois toque em Compartilhar e escolha "Adicionar a Tela de Inicio".</p>
                </div>

                <div class="mobile-install-guide__item">
                    <strong>Depois da instalacao</strong>
                    <p>O app vai aparecer como icone proprio no celular e abrir em tela cheia, separado do navegador tradicional.</p>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        (function () {
            var copyButton = document.querySelector('[data-copy-install-link]');
            var feedback = document.querySelector('[data-copy-feedback]');

            if (!copyButton || !feedback || !navigator.clipboard) {
                return;
            }

            copyButton.addEventListener('click', function () {
                var text = copyButton.getAttribute('data-copy-text') || '';

                navigator.clipboard.writeText(text).then(function () {
                    feedback.hidden = false;

                    window.setTimeout(function () {
                        feedback.hidden = true;
                    }, 2800);
                }).catch(function () {
                    return null;
                });
            });
        })();
    </script>
@endpush
