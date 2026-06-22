@extends('mobile.layouts.app')

@section('title', 'Componentes sem Professor | Gestão Edu Mobile')

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
        <p class="mobile-page-intro__eyebrow">Cobertura</p>
        <h1 class="mobile-page-intro__title">Onde faltam professores hoje.</h1>
        <p class="mobile-page-intro__subtitle">
            Dois painéis na mesma tela: componentes com lacuna e turmas afetadas pela falta de cobertura.
        </p>
    </section>

    <section class="mobile-stats-grid">
        <article class="mobile-stat-card"><small>Vínculos</small><strong>{{ number_format($stats['vinculos'], 0, ',', '.') }}</strong></article>
        <article class="mobile-stat-card"><small>Com professor</small><strong>{{ number_format($stats['comProfessor'], 0, ',', '.') }}</strong></article>
        <article class="mobile-stat-card"><small>Sem professor</small><strong>{{ number_format($stats['semProfessor'], 0, ',', '.') }}</strong></article>
        <article class="mobile-stat-card"><small>Turmas com falta</small><strong>{{ number_format($stats['totalTurmasFaltando'], 0, ',', '.') }}</strong></article>
    </section>

    <form method="GET" class="mobile-filter-stack">
        <input type="search" name="search" value="{{ $filters['search'] }}" class="mobile-input" placeholder="Buscar componente, turma, escola ou série">

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

            <select name="situacao" class="mobile-select">
                <option value="">Todos com falta</option>
                <option value="sem_professor" @selected($filters['situacao'] === 'sem_professor')>Sem nenhum professor</option>
                <option value="com_professor" @selected($filters['situacao'] === 'com_professor')>Cobertura parcial</option>
            </select>
        </div>

        <button type="submit" class="mobile-button mobile-button--primary">Aplicar filtros</button>
    </form>

    <section class="mobile-section">
        <div class="mobile-section__header">
            <div>
                <p class="mobile-section__eyebrow">Componentes</p>
                <h2 class="mobile-section__title">Onde a falta aparece com mais frequência</h2>
            </div>
        </div>

        <div class="mobile-list">
            @forelse ($componentes as $item)
                <article class="mobile-item-card">
                    <div class="mobile-item-card__header">
                        <div>
                            <h2>{{ $item->componente_nome }}</h2>
                            <p>Total de vínculos: {{ number_format($item->total, 0, ',', '.') }}</p>
                        </div>

                        <span class="mobile-status-badge is-warning">
                            {{ number_format($item->sem_professor, 0, ',', '.') }} sem professor
                        </span>
                    </div>

                    <div class="mobile-meta-list">
                        <span><strong>Com professor:</strong> {{ number_format($item->com_professor, 0, ',', '.') }}</span>
                        <span><strong>Cobertura:</strong> {{ number_format((float) $item->cobertura_percentual, 2, ',', '.') }}%</span>
                    </div>
                </article>
            @empty
                <div class="mobile-empty">Nenhum componente encontrado com os filtros atuais.</div>
            @endforelse
        </div>

        @include('mobile.partials.pagination', ['paginator' => $componentes])
    </section>

    <section class="mobile-section">
        <div class="mobile-section__header">
            <div>
                <p class="mobile-section__eyebrow">Turmas</p>
                <h2 class="mobile-section__title">Quais turmas foram impactadas</h2>
            </div>
        </div>

        <div class="mobile-list">
            @forelse ($turmas as $turma)
                <article class="mobile-item-card">
                    <div class="mobile-item-card__header">
                        <div>
                            <h2>{{ $turma->turma_nome }}</h2>
                            <p>{{ $turma->escola_nome }} • {{ $turma->serie_nome }}</p>
                        </div>

                        <span class="mobile-status-badge is-warning">
                            {{ number_format($turma->componentes_sem_professor, 0, ',', '.') }} faltando
                        </span>
                    </div>

                    <div class="mobile-meta-list">
                        <span><strong>Turno:</strong> {{ $turnoLabel($turma->turma_turno) }}</span>
                    </div>
                </article>
            @empty
                <div class="mobile-empty">Nenhuma turma encontrada com os filtros atuais.</div>
            @endforelse
        </div>

        @include('mobile.partials.pagination', ['paginator' => $turmas])
    </section>
@endsection
