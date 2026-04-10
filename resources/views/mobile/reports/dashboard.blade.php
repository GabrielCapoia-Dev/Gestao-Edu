@extends('mobile.layouts.app')

@section('title', 'Dashboard de Relatorios | Gestao Edu Mobile')

@section('content')
    <section class="mobile-page-intro">
        <p class="mobile-page-intro__eyebrow">Dashboard</p>
        <h1 class="mobile-page-intro__title">Panorama geral dos vinculos do sistema.</h1>
        <p class="mobile-page-intro__subtitle">
            Esta visao mobile concentra os indicadores principais e aponta onde a cobertura de professores esta mais pressionada.
        </p>
    </section>

    <section class="mobile-stats-grid">
        <article class="mobile-stat-card"><small>Professores</small><strong>{{ number_format($stats['totalProfessores'], 0, ',', '.') }}</strong></article>
        <article class="mobile-stat-card"><small>Turmas</small><strong>{{ number_format($stats['totalTurmas'], 0, ',', '.') }}</strong></article>
        <article class="mobile-stat-card"><small>Componentes</small><strong>{{ number_format($stats['totalComponentes'], 0, ',', '.') }}</strong></article>
        <article class="mobile-stat-card"><small>Escolas</small><strong>{{ number_format($stats['totalEscolas'], 0, ',', '.') }}</strong></article>
        <article class="mobile-stat-card"><small>Vinculos</small><strong>{{ number_format($stats['vinculos'], 0, ',', '.') }}</strong></article>
        <article class="mobile-stat-card"><small>Com professor</small><strong>{{ number_format($stats['comProfessor'], 0, ',', '.') }}</strong></article>
        <article class="mobile-stat-card"><small>Sem professor</small><strong>{{ number_format($stats['semProfessor'], 0, ',', '.') }}</strong></article>
    </section>

    <section class="mobile-section">
        <div class="mobile-section__header">
            <div>
                <p class="mobile-section__eyebrow">Atalhos</p>
                <h2 class="mobile-section__title">Abrir consultas detalhadas</h2>
            </div>
        </div>

        <div class="mobile-card-grid">
            <a href="{{ route('mobile.reports.professor-by-class') }}" class="mobile-link-card mobile-link-card--violet">
                <span class="mobile-link-card__badge">PT</span>
                <span class="mobile-link-card__content">
                    <strong>Professor por turma</strong>
                    <small>Consulta detalhada por escola, serie, turno e componente.</small>
                </span>
            </a>

            <a href="{{ route('mobile.reports.missing-teachers') }}" class="mobile-link-card mobile-link-card--rose">
                <span class="mobile-link-card__badge">CF</span>
                <span class="mobile-link-card__content">
                    <strong>Componentes faltando</strong>
                    <small>Descubra onde faltam professores e quais turmas estao afetadas.</small>
                </span>
            </a>
        </div>
    </section>

    <section class="mobile-section">
        <div class="mobile-section__header">
            <div>
                <p class="mobile-section__eyebrow">Top 5</p>
                <h2 class="mobile-section__title">Componentes com maior falta de cobertura</h2>
            </div>
        </div>

        <div class="mobile-list">
            @foreach ($topComponentes as $componente)
                <article class="mobile-item-card">
                    <div class="mobile-item-card__header">
                        <div>
                            <h2>{{ $componente->nome }}</h2>
                            <p>Total de vinculos: {{ number_format($componente->total, 0, ',', '.') }}</p>
                        </div>

                        <span class="mobile-status-badge is-warning">
                            {{ number_format($componente->sem_professor, 0, ',', '.') }} sem professor
                        </span>
                    </div>

                    <div class="mobile-meta-list">
                        <span><strong>Com professor:</strong> {{ number_format($componente->com_professor, 0, ',', '.') }}</span>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endsection
