@extends('mobile.layouts.app')

@section('title', 'Professor por Turma | Gestão Edu Mobile')

@section('content')
    @php
        $turnoLabel = static fn (?string $turno): string => match ($turno) {
            'manha' => 'Manhã',
            'tarde' => 'Tarde',
            'noite' => 'Noite',
            'integral' => 'Integral',
            default => $turno ?: 'Não informado',
        };
    @endphp

    <section class="mobile-page-intro">
        <p class="mobile-page-intro__eyebrow">Professor por turma</p>
        <h1 class="mobile-page-intro__title">Consulta detalhada por escola, série e componente.</h1>
        <p class="mobile-page-intro__subtitle">
            A estrutura em cards facilita a leitura rápida no celular e mantém os filtros essenciais sempre visíveis.
        </p>
    </section>

    <section class="mobile-stats-grid">
        <article class="mobile-stat-card"><small>Registros</small><strong>{{ number_format($stats['total'], 0, ',', '.') }}</strong></article>
        <article class="mobile-stat-card"><small>Com professor</small><strong>{{ number_format($stats['comProfessor'], 0, ',', '.') }}</strong></article>
        <article class="mobile-stat-card"><small>Sem professor</small><strong>{{ number_format($stats['semProfessor'], 0, ',', '.') }}</strong></article>
    </section>

    <form method="GET" class="mobile-filter-stack">
        <input type="search" name="search" value="{{ $filters['search'] }}" class="mobile-input" placeholder="Buscar escola, série, turma, componente ou professor">

        <div class="mobile-filter-grid">
            <select name="escola_id" class="mobile-select">
                <option value="">Todas as escolas</option>
                @foreach ($escolas as $escola)
                    <option value="{{ $escola->id }}" @selected((string) $filters['escola_id'] === (string) $escola->id)>{{ $escola->nome }}</option>
                @endforeach
            </select>

            <select name="serie_id" class="mobile-select">
                <option value="">Todas as séries</option>
                @foreach ($series as $serie)
                    <option value="{{ $serie->id }}" @selected((string) $filters['serie_id'] === (string) $serie->id)>{{ $serie->nome }}</option>
                @endforeach
            </select>

            <select name="turno" class="mobile-select">
                <option value="">Todos os turnos</option>
                <option value="manha" @selected($filters['turno'] === 'manha')>Manhã</option>
                <option value="tarde" @selected($filters['turno'] === 'tarde')>Tarde</option>
                <option value="noite" @selected($filters['turno'] === 'noite')>Noite</option>
                <option value="integral" @selected($filters['turno'] === 'integral')>Integral</option>
            </select>
        </div>

        <button type="submit" class="mobile-button mobile-button--primary">Aplicar filtros</button>
    </form>

    <section class="mobile-list">
        @forelse ($records as $record)
            <article class="mobile-item-card">
                <div class="mobile-item-card__header">
                    <div>
                        <h2>{{ $record->componente_nome }}</h2>
                        <p>{{ $record->escola_nome }} • {{ $record->serie_nome }}</p>
                    </div>

                    <span class="mobile-status-badge {{ $record->professor_nome === 'Sem professor' ? 'is-warning' : 'is-success' }}">
                        {{ $record->professor_nome }}
                    </span>
                </div>

                <div class="mobile-meta-list">
                    <span><strong>Turma:</strong> {{ $record->turma_nome }}</span>
                    <span><strong>Turno:</strong> {{ $turnoLabel($record->turno) }}</span>
                    <span><strong>Matrícula:</strong> {{ $record->professor_matricula }}</span>
                    <span><strong>E-mail:</strong> {{ $record->professor_email }}</span>
                </div>
            </article>
        @empty
            <div class="mobile-empty">Nenhum registro encontrado com os filtros atuais.</div>
        @endforelse
    </section>

    @include('mobile.partials.pagination', ['paginator' => $records])
@endsection
