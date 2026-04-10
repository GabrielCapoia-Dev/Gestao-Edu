@extends('mobile.layouts.app')

@section('title', 'Usuarios | Gestao Edu Mobile')

@section('content')
    <section class="mobile-page-intro">
        <p class="mobile-page-intro__eyebrow">Usuarios</p>
        <h1 class="mobile-page-intro__title">Visao resumida dos acessos ativos.</h1>
        <p class="mobile-page-intro__subtitle">
            Consulte o status de liberacao, escola, setor e niveis sem depender das telas administrativas do desktop.
        </p>
    </section>

    <section class="mobile-stats-grid">
        <article class="mobile-stat-card">
            <small>Usuarios visiveis</small>
            <strong>{{ number_format($stats['total'], 0, ',', '.') }}</strong>
        </article>
        <article class="mobile-stat-card">
            <small>Acessos liberados</small>
            <strong>{{ number_format($stats['approved'], 0, ',', '.') }}</strong>
        </article>
        <article class="mobile-stat-card">
            <small>Multiplos niveis</small>
            <strong>{{ number_format($stats['multiRole'], 0, ',', '.') }}</strong>
        </article>
        <article class="mobile-stat-card">
            <small>Permissoes extras</small>
            <strong>{{ number_format($stats['directPermissions'], 0, ',', '.') }}</strong>
        </article>
    </section>

    <form method="GET" class="mobile-filter-form">
        <input type="search" name="search" value="{{ $search }}" class="mobile-input" placeholder="Buscar por nome ou e-mail">
        <button type="submit" class="mobile-button mobile-button--primary">Filtrar</button>
    </form>

    <section class="mobile-list">
        @forelse ($users as $user)
            <article class="mobile-item-card">
                <div class="mobile-item-card__header">
                    <div>
                        <h2>{{ $user->name }}</h2>
                        <p>{{ $user->email }}</p>
                    </div>

                    <span class="mobile-status-badge {{ $user->email_approved ? 'is-success' : 'is-warning' }}">
                        {{ $user->email_approved ? 'Liberado' : 'Pendente' }}
                    </span>
                </div>

                <div class="mobile-meta-list">
                    <span><strong>Escola:</strong> {{ $user->escola?->nome ?? 'Nao vinculada' }}</span>
                    <span><strong>Setor:</strong> {{ $user->setor?->nome ?? 'Nao vinculado' }}</span>
                </div>

                <div class="mobile-tags">
                    @forelse ($user->roles as $role)
                        <span class="mobile-tag">{{ $role->name }}</span>
                    @empty
                        <span class="mobile-tag mobile-tag--muted">Sem nivel vinculado</span>
                    @endforelse
                </div>
            </article>
        @empty
            <div class="mobile-empty">Nenhum usuario encontrado com os filtros atuais.</div>
        @endforelse
    </section>

    @include('mobile.partials.pagination', ['paginator' => $users])
@endsection
