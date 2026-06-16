@extends('mobile.layouts.app')

@section('title', 'Acesso | Gestão Edu Mobile')

@section('content')
    <section class="mobile-page-intro">
        <p class="mobile-page-intro__eyebrow">Acesso</p>
        <h1 class="mobile-page-intro__title">Módulos de controle de permissão e usuários.</h1>
        <p class="mobile-page-intro__subtitle">
            Está área foi separada para você navegar no celular com menos densidade e mais clareza visual.
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
        <div class="mobile-empty">Seu perfil não possui acesso aos módulos de controle.</div>
    @endif
@endsection
