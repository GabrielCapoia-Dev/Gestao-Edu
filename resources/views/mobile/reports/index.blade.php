@extends('mobile.layouts.app')

@section('title', 'Relatorios | Gestao Edu Mobile')

@section('content')
    <section class="mobile-page-intro">
        <p class="mobile-page-intro__eyebrow">Relatorios</p>
        <h1 class="mobile-page-intro__title">Consultas desenhadas para o celular.</h1>
        <p class="mobile-page-intro__subtitle">
            Abra um relatorio por vez, filtre rapido e leia as informacoes sem depender do layout do desktop.
        </p>
    </section>

    @if (count($cards))
        <div class="mobile-card-grid">
            @foreach ($cards as $card)
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
        <div class="mobile-empty">Seu perfil nao possui relatorios liberados no app mobile.</div>
    @endif
@endsection
