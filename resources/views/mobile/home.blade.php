@extends('mobile.layouts.app')

@section('title', 'Início | Gestão Edu Mobile')

@section('content')
    <section class="mobile-hero">
        <p class="mobile-hero__eyebrow">Workspace mobile</p>
        <h1 class="mobile-hero__title">Um app separado para operar o sistema no celular.</h1>
        <p class="mobile-hero__subtitle">
            Acesso e relatórios ficam em uma navegação própria, sem depender do Filament para construir a interface mobile.
        </p>

        <div class="mobile-chip-list">
            <span class="mobile-chip">Usuário: {{ auth()->user()->name }}</span>
            <span class="mobile-chip">{{ count($accessCards) }} atalhos de acesso</span>
            <span class="mobile-chip">{{ count($reportCards) }} atalhos de relatórios</span>
        </div>
    </section>

    <section class="mobile-section">
        <div class="mobile-section__header">
            <div>
                <p class="mobile-section__eyebrow">Acesso</p>
                <h2 class="mobile-section__title">Usuários, domínios e níveis</h2>
            </div>
        </div>

        @if (count($accessCards))
            <div class="mobile-card-grid">
                @foreach ($accessCards as $card)
                    <a href="{{ $card['url'] }}" class="mobile-link-card mobile-link-card--{{ $card['tone'] }}">
                        <span class="mobile-link-card__badge">{{ $card['badge'] }}</span>
                        <span class="mobile-link-card__content">
                            <strong>{{ $card['title'] }}</strong>
                            <small>{{ $card['description'] }}</small>
                        </span>
                    </a>
                @endforeach
            </div>
        @else
            <div class="mobile-empty">Seu perfil não possui itens de acesso liberados no app mobile.</div>
        @endif
    </section>

    <section class="mobile-section">
        <div class="mobile-section__header">
            <div>
                <p class="mobile-section__eyebrow">Relatórios</p>
                <h2 class="mobile-section__title">Leitura rapida, filtros e consultas</h2>
            </div>
        </div>

        @if (count($reportCards))
            <div class="mobile-card-grid">
                @foreach ($reportCards as $card)
                    <a href="{{ $card['url'] }}" class="mobile-link-card mobile-link-card--{{ $card['tone'] }}">
                        <span class="mobile-link-card__badge">{{ $card['badge'] }}</span>
                        <span class="mobile-link-card__content">
                            <strong>{{ $card['title'] }}</strong>
                            <small>{{ $card['description'] }}</small>
                        </span>
                    </a>
                @endforeach
            </div>
        @else
            <div class="mobile-empty">Nenhum relatório foi liberado para este usuário.</div>
        @endif
    </section>
@endsection
