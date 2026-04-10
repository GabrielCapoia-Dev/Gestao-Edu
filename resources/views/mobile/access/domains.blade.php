@extends('mobile.layouts.app')

@section('title', 'Dominios | Gestao Edu Mobile')

@section('content')
    <section class="mobile-page-intro">
        <p class="mobile-page-intro__eyebrow">Dominios</p>
        <h1 class="mobile-page-intro__title">Quais dominios podem entrar no sistema.</h1>
        <p class="mobile-page-intro__subtitle">
            Uma leitura mais leve para o celular, com foco em status e organizacao por setor.
        </p>
    </section>

    <section class="mobile-stats-grid">
        <article class="mobile-stat-card">
            <small>Total de dominios</small>
            <strong>{{ number_format($stats['total'], 0, ',', '.') }}</strong>
        </article>
        <article class="mobile-stat-card">
            <small>Ativos</small>
            <strong>{{ number_format($stats['active'], 0, ',', '.') }}</strong>
        </article>
        <article class="mobile-stat-card">
            <small>Com setor</small>
            <strong>{{ number_format($stats['withSector'], 0, ',', '.') }}</strong>
        </article>
    </section>

    <form method="GET" class="mobile-filter-form">
        <input type="search" name="search" value="{{ $search }}" class="mobile-input" placeholder="Buscar dominio ou setor">
        <button type="submit" class="mobile-button mobile-button--primary">Filtrar</button>
    </form>

    <section class="mobile-list">
        @forelse ($domains as $domain)
            <article class="mobile-item-card">
                <div class="mobile-item-card__header">
                    <div>
                        <h2>{{ $domain->dominio_email }}</h2>
                        <p>{{ $domain->setor ?: 'Sem setor informado' }}</p>
                    </div>

                    <span class="mobile-status-badge {{ $domain->status ? 'is-success' : 'is-muted' }}">
                        {{ $domain->status ? 'Ativo' : 'Inativo' }}
                    </span>
                </div>
            </article>
        @empty
            <div class="mobile-empty">Nenhum dominio encontrado com os filtros atuais.</div>
        @endforelse
    </section>

    @include('mobile.partials.pagination', ['paginator' => $domains])
@endsection
