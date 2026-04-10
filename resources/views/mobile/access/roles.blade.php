@extends('mobile.layouts.app')

@section('title', 'Niveis de Acesso | Gestao Edu Mobile')

@section('content')
    <section class="mobile-page-intro">
        <p class="mobile-page-intro__eyebrow">Niveis</p>
        <h1 class="mobile-page-intro__title">Catalogo de perfis e permissoes.</h1>
        <p class="mobile-page-intro__subtitle">
            A tela mobile prioriza leitura e entendimento rapido da estrutura de acesso.
        </p>
    </section>

    <section class="mobile-stats-grid">
        <article class="mobile-stat-card">
            <small>Niveis cadastrados</small>
            <strong>{{ number_format($stats['roles'], 0, ',', '.') }}</strong>
        </article>
        <article class="mobile-stat-card">
            <small>Com permissoes</small>
            <strong>{{ number_format($stats['withPermissions'], 0, ',', '.') }}</strong>
        </article>
        <article class="mobile-stat-card">
            <small>Catalogo de permissoes</small>
            <strong>{{ number_format($stats['permissions'], 0, ',', '.') }}</strong>
        </article>
        <article class="mobile-stat-card">
            <small>Atualizados hoje</small>
            <strong>{{ number_format($stats['updatedToday'], 0, ',', '.') }}</strong>
        </article>
    </section>

    <form method="GET" class="mobile-filter-form">
        <input type="search" name="search" value="{{ $search }}" class="mobile-input" placeholder="Buscar nivel de acesso">
        <button type="submit" class="mobile-button mobile-button--primary">Filtrar</button>
    </form>

    <section class="mobile-list">
        @forelse ($roles as $role)
            <article class="mobile-item-card">
                <div class="mobile-item-card__header">
                    <div>
                        <h2>{{ $role->name }}</h2>
                        <p>{{ $role->updated_at?->format('d/m/Y H:i') }}</p>
                    </div>

                    <span class="mobile-status-badge is-info">
                        {{ $role->permissions_count }} permissoes
                    </span>
                </div>

                <div class="mobile-meta-list">
                    <span><strong>Usuarios com este nivel:</strong> {{ $role->users_count }}</span>
                </div>
            </article>
        @empty
            <div class="mobile-empty">Nenhum nivel encontrado com os filtros atuais.</div>
        @endforelse
    </section>

    @include('mobile.partials.pagination', ['paginator' => $roles])
@endsection
