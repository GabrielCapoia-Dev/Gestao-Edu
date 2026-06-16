@extends('mobile.layouts.app')

@section('title', 'Relatórios | Gestão Edu Mobile')

@section('content')
    <section class="mobile-page-intro">
        <p class="mobile-page-intro__eyebrow">Relatórios</p>
        <h1 class="mobile-page-intro__title">Consultas desenhadas para o celular.</h1>
        <p class="mobile-page-intro__subtitle">
            Abra um relatório por vez, filtre rápido e leia as informações sem depender do layout do desktop.
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
        <div class="mobile-empty">Seu perfil não possui relatórios liberados no app mobile.</div>
    @endif
@endsection
