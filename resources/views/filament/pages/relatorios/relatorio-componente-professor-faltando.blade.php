<x-filament-panels::page>
    <div class="relatorios-root">

        {{-- KPIs --}}
        <div class="rel-kpi-grid rel-kpi-grid--5 mb-rel">
            <div class="rel-kpi-card">
                <div class="rel-kpi-icon rel-kpi-icon--slate">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                    </svg>
                </div>
                <div class="rel-kpi-body">
                    <span class="rel-kpi-label">Total de vínculos</span>
                    <span class="rel-kpi-value">{{ $this->vinculos }}</span>
                    <span class="rel-kpi-sub">turma × componente</span>
                </div>
            </div>
            <div class="rel-kpi-card">
                <div class="rel-kpi-icon rel-kpi-icon--green">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <div class="rel-kpi-body">
                    <span class="rel-kpi-label">Componentes com professor</span>
                    <span class="rel-kpi-value rel-kpi-value--green">{{ $this->comProfessor }}</span>
                    @if($this->vinculos > 0)
                    <span class="rel-kpi-sub">{{ round(($this->comProfessor / $this->vinculos) * 100) }}% dos vínculos</span>
                    @endif
                </div>
            </div>
            <div class="rel-kpi-card">
                <div class="rel-kpi-icon rel-kpi-icon--rose">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                </div>
                <div class="rel-kpi-body">
                    <span class="rel-kpi-label">Componentes sem professor</span>
                    <span class="rel-kpi-value rel-kpi-value--red">{{ $this->semProfessor }}</span>
                    @if($this->vinculos > 0)
                    <span class="rel-kpi-sub">{{ round(($this->semProfessor / $this->vinculos) * 100) }}% dos vínculos</span>
                    @endif
                </div>
            </div>
            <div class="rel-kpi-card">
                <div class="rel-kpi-icon rel-kpi-icon--rose">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 3.741-1.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" />
                    </svg>
                </div>
                <div class="rel-kpi-body">
                    <span class="rel-kpi-label">Turmas sem professor</span>
                    <span class="rel-kpi-value rel-kpi-value--red">{{ $this->totalTurmasFaltando }}</span>
                    @if($this->totalTurmas > 0)
                    <span class="rel-kpi-sub">de {{ $this->totalTurmas }} turmas</span>
                    @endif
                </div>
            </div>
            <div class="rel-kpi-card">
                <div class="rel-kpi-icon rel-kpi-icon--slate">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                    </svg>
                </div>
                <div class="rel-kpi-body">
                    <span class="rel-kpi-label">Total de professores</span>
                    <span class="rel-kpi-value">{{ $this->totalProfessores }}</span>
                    <span class="rel-kpi-sub">cadastrados</span>
                </div>
            </div>
        </div>

        {{-- FILTROS --}}
        <div class="rel-filters mb-rel">
            <div class="rel-filter-group">
                <input type="text" wire:model.live.debounce.400ms="search" placeholder="Pesquisar componente..." class="rel-filter-input" />
            </div>
            <div class="rel-filter-group">
                <select wire:model.live="escola_id" class="rel-filter-select">
                    <option value="">Todas as escolas</option>
                    @foreach($this->escolas as $escola)
                    <option value="{{ $escola->id }}">{{ $escola->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="rel-filter-group">
                <select wire:model.live="serie_id" class="rel-filter-select">
                    <option value="">Todas as séries</option>
                    @foreach($this->series as $serie)
                    <option value="{{ $serie->id }}">{{ $serie->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="rel-filter-group">
                <select wire:model.live="perPage" class="rel-filter-select rel-filter-select--sm">
                    <option value="5">5 por página</option>
                    <option value="10">10 por página</option>
                    <option value="25">25 por página</option>
                    <option value="50">50 por página</option>
                </select>
            </div>
        </div>

        {{-- TABELA: COMPONENTES --}}
        <div class="rel-table-card mb-rel">
            <div class="rel-table-header">
                <div>
                    <h3 class="rel-table-title">Componentes com maior falta de docentes</h3>
                    <p class="rel-table-sub">
                        {{ $this->totalRegistros }} {{ $this->totalRegistros === 1 ? 'componente encontrado' : 'componentes encontrados' }}
                        @if($this->situacao === 'sem_professor')
                        <span class="rel-badge-inline rel-badge-inline--red">Filtro: sem professor</span>
                        @elseif($this->situacao === 'com_professor')
                        <span class="rel-badge-inline rel-badge-inline--green">Filtro: com professor</span>
                        @endif
                    </p>
                </div>
            </div>
            <div class="rel-table-wrap">
                <table class="rel-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Componente</th>
                            <th>Total</th>
                            <th>Com professor</th>
                            <th>Sem professor</th>
                            <th>Cobertura</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->registros as $i => $comp)
                        @php
                        $comp = (object) $comp;
                        $pct = $comp->total > 0 ? round(($comp->com_professor / $comp->total) * 100) : 0;
                        $rank = (($this->page - 1) * $this->perPage) + $i + 1;
                        @endphp
                        <tr>
                            <td class="rel-table-rank">{{ $rank }}</td>
                            <td class="rel-table-name">{{ $comp->componente_nome }}</td>
                            <td><span class="rel-badge rel-badge--slate">{{ $comp->total }}</span></td>
                            <td><span class="rel-badge rel-badge--green">{{ $comp->com_professor }}</span></td>
                            <td>
                                @if($comp->sem_professor > 0)
                                <span class="rel-badge rel-badge--red">{{ $comp->sem_professor }}</span>
                                @else
                                <span class="rel-badge rel-badge--green">0</span>
                                @endif
                            </td>
                            <td>
                                <div class="rel-progress-wrap">
                                    <div class="rel-progress">
                                        <div class="rel-progress-bar {{ $pct == 100 ? 'rel-progress-bar--full' : ($pct >= 50 ? 'rel-progress-bar--mid' : 'rel-progress-bar--low') }}"
                                            style="width: {{ $pct }}%"></div>
                                    </div>
                                    <span class="rel-progress-label">{{ $pct }}%</span>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="rel-empty">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                <p>Nenhum componente encontrado com os filtros aplicados.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($this->totalPaginas > 1)
            <div class="rel-pagination">
                <span class="rel-pagination-info">
                    Mostrando {{ (($this->page - 1) * $this->perPage) + 1 }}–{{ min($this->page * $this->perPage, $this->totalRegistros) }}
                    de {{ $this->totalRegistros }}
                </span>
                <div class="rel-pagination-btns">
                    <button wire:click="irParaPagina({{ $this->page - 1 }})" @if($this->page <= 1) disabled @endif class="rel-page-btn">←</button>
                    @for($p = 1; $p <= $this->totalPaginas; $p++)
                        <button wire:click="irParaPagina({{ $p }})" class="rel-page-btn {{ $p === $this->page ? 'rel-page-btn--active' : '' }}">{{ $p }}</button>
                        @endfor
                        <button wire:click="irParaPagina({{ $this->page + 1 }})" @if($this->page >= $this->totalPaginas) disabled @endif class="rel-page-btn">→</button>
                </div>
            </div>
            @endif
        </div>

        {{-- TABELA: TURMAS COM PROFESSOR FALTANDO --}}
        <div class="rel-table-card mb-rel">
            <div class="rel-table-header">
                <div>
                    <h3 class="rel-table-title">Turmas com componentes sem professor</h3>
                    <p class="rel-table-sub">
                        {{ $this->totalTurmasFaltando }} {{ $this->totalTurmasFaltando === 1 ? 'turma encontrada' : 'turmas encontradas' }}
                    </p>
                </div>
                <div class="rel-filter-group">
                    <select wire:model.live="perPageTurmas" class="rel-filter-select rel-filter-select--sm">
                        <option value="5">5 por página</option>
                        <option value="10">10 por página</option>
                        <option value="25">25 por página</option>
                        <option value="50">50 por página</option>
                    </select>
                </div>
            </div>
            <div class="rel-table-wrap">
                <table class="rel-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Escola</th>
                            <th>Série</th>
                            <th>Turma</th>
                            <th>Componentes sem professor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->turmas as $i => $turma)
                        @php $turma = (object) $turma; @endphp
                        <tr>
                            <td class="rel-table-rank">{{ (($this->pageTurmas - 1) * $this->perPageTurmas) + $i + 1 }}</td>
                            <td class="rel-table-muted">{{ $turma->escola_nome }}</td>
                            <td class="rel-table-muted">{{ $turma->serie_nome }}</td>
                            <td class="rel-table-name">{{ $turma->turma_nome }}</td>
                            <td>
                                <span class="rel-badge rel-badge--red">{{ $turma->componentes_sem_professor }}</span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="rel-empty">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                <p>Nenhuma turma com professor faltando encontrada.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($this->totalPaginasTurmas > 1)
            <div class="rel-pagination">
                <span class="rel-pagination-info">
                    Mostrando {{ (($this->pageTurmas - 1) * $this->perPageTurmas) + 1 }}–{{ min($this->pageTurmas * $this->perPageTurmas, $this->totalTurmasFaltando) }}
                    de {{ $this->totalTurmasFaltando }}
                </span>
                <div class="rel-pagination-btns">
                    <button wire:click="irParaPaginaTurmas({{ $this->pageTurmas - 1 }})" @if($this->pageTurmas <= 1) disabled @endif class="rel-page-btn">←</button>
                    @for($p = 1; $p <= $this->totalPaginasTurmas; $p++)
                        <button wire:click="irParaPaginaTurmas({{ $p }})" class="rel-page-btn {{ $p === $this->pageTurmas ? 'rel-page-btn--active' : '' }}">{{ $p }}</button>
                        @endfor
                        <button wire:click="irParaPaginaTurmas({{ $this->pageTurmas + 1 }})" @if($this->pageTurmas >= $this->totalPaginasTurmas) disabled @endif class="rel-page-btn">→</button>
                </div>
            </div>
            @endif
        </div>

    </div>

    <style>
        .rel-kpi-grid--5 {
            grid-template-columns: repeat(5, 1fr);
        }

        @media (max-width: 1024px) {
            .rel-kpi-grid--5 {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .rel-kpi-grid--5 {
                grid-template-columns: 1fr;
            }
        }

        
        .rel-kpi-grid {
            display: grid;
            gap: 1rem;
        }

        .rel-kpi-grid--3 {
            grid-template-columns: repeat(3, 1fr);
        }

        .rel-kpi-card {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1rem 1.25rem;
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / .04);
        }

        .rel-kpi-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .rel-kpi-icon svg {
            width: 18px;
            height: 18px;
        }

        .rel-kpi-icon--slate {
            background: #f1f5f9;
            color: #64748b;
        }

        .rel-kpi-icon--green {
            background: #dcfce7;
            color: #16a34a;
        }

        .rel-kpi-icon--rose {
            background: #fee2e2;
            color: #dc2626;
        }

        .rel-kpi-body {
            display: flex;
            flex-direction: column;
            gap: 1px;
        }

        .rel-kpi-label {
            font-size: .7rem;
            font-weight: 500;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .rel-kpi-value {
            font-size: 1.6rem;
            font-weight: 700;
            color: #111827;
            line-height: 1.15;
        }

        .rel-kpi-value--green {
            color: #16a34a;
        }

        .rel-kpi-value--red {
            color: #dc2626;
        }

        .rel-kpi-sub {
            font-size: .71rem;
            color: #9ca3af;
        }

        .dark .rel-kpi-card {
            background: rgb(17 24 39);
            border-color: rgba(255, 255, 255, .07);
            box-shadow: none;
        }

        .dark .rel-kpi-icon--slate {
            background: rgba(255, 255, 255, .07);
        }

        .dark .rel-kpi-icon--green {
            background: rgba(22, 163, 74, .15);
        }

        .dark .rel-kpi-icon--rose {
            background: rgba(220, 38, 38, .15);
        }

        .dark .rel-kpi-value {
            color: #f9fafb;
        }

        .dark .rel-kpi-label {
            color: #9ca3af;
        }

        .rel-filters {
            display: flex;
            flex-wrap: wrap;
            gap: .6rem;
            align-items: center;
        }

        .rel-filter-group {
            display: flex;
        }

        .rel-filter-input,
        .rel-filter-select {
            height: 36px;
            padding: 0 .7rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: .82rem;
            color: #374151;
            background: white;
            outline: none;
            min-width: 190px;
            transition: border-color .15s, box-shadow .15s;
        }

        .rel-filter-input:focus,
        .rel-filter-select:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 2px rgb(99 102 241 / .12);
        }

        .rel-filter-select--sm {
            min-width: 130px;
        }

        .dark .rel-filter-input,
        .dark .rel-filter-select {
            background: rgb(17 24 39);
            border-color: rgba(255, 255, 255, .1);
            color: #f3f4f6;
        }

        .rel-badge-inline {
            display: inline-flex;
            align-items: center;
            padding: .1rem .5rem;
            border-radius: 999px;
            font-size: .68rem;
            font-weight: 600;
            margin-left: .4rem;
        }

        .rel-badge-inline--red {
            background: #fee2e2;
            color: #b91c1c;
        }

        .rel-badge-inline--green {
            background: #dcfce7;
            color: #15803d;
        }

        .rel-table-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / .04);
        }

        .rel-table-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.25rem .7rem;
            border-bottom: 1px solid #f1f5f9;
            gap: 1rem;
        }

        .rel-table-title {
            margin: 0;
            font-size: .9rem;
            font-weight: 600;
            color: #111827;
        }

        .rel-table-sub {
            margin: .2rem 0 0;
            font-size: .76rem;
            color: #6b7280;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: .25rem;
        }

        .dark .rel-table-card {
            background: rgb(17 24 39);
            border-color: rgba(255, 255, 255, .07);
            box-shadow: none;
        }

        .dark .rel-table-header {
            border-bottom-color: rgba(255, 255, 255, .06);
        }

        .dark .rel-table-title {
            color: #f9fafb;
        }

        .rel-table-wrap {
            overflow-x: auto;
        }

        .rel-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .83rem;
        }

        .rel-table thead th {
            padding: .6rem 1rem;
            text-align: left;
            font-size: .7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .06em;
            background: #1e3a5f;
            color: white;
            white-space: nowrap;
        }

        .rel-table tbody td {
            padding: .65rem 1rem;
            border-bottom: 1px solid #f3f4f6;
            color: #374151;
            vertical-align: middle;
        }

        .rel-table tbody tr:last-child td {
            border-bottom: none;
        }

        .rel-table tbody tr:hover {
            background: #f9fafb;
        }

        .dark .rel-table thead th {
            background: #1e3a5f;
            color: #e2e8f0;
        }

        .dark .rel-table tbody td {
            color: #d1d5db;
            border-bottom-color: rgba(255, 255, 255, .04);
        }

        .dark .rel-table tbody tr:hover {
            background: rgba(255, 255, 255, .02);
        }

        .rel-table-rank {
            font-size: .75rem;
            color: #9ca3af;
            font-weight: 500;
            width: 36px;
        }

        .rel-table-name {
            font-weight: 500;
            color: #111827;
        }

        .rel-table-muted {
            color: #6b7280;
            font-size: .81rem;
        }

        .dark .rel-table-name {
            color: #f9fafb;
        }

        .dark .rel-table-muted {
            color: #9ca3af;
        }

        .rel-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: .15rem .55rem;
            border-radius: 999px;
            font-size: .73rem;
            font-weight: 600;
        }

        .rel-badge--slate {
            background: #f1f5f9;
            color: #475569;
        }

        .rel-badge--green {
            background: #dcfce7;
            color: #15803d;
        }

        .rel-badge--red {
            background: #fee2e2;
            color: #b91c1c;
        }

        .rel-progress-wrap {
            display: flex;
            align-items: center;
            gap: .5rem;
            min-width: 110px;
        }

        .rel-progress {
            flex: 1;
            height: 5px;
            background: #e5e7eb;
            border-radius: 99px;
            overflow: hidden;
        }

        .rel-progress-bar {
            height: 100%;
            border-radius: 99px;
            transition: width .3s;
        }

        .rel-progress-bar--full {
            background: #22c55e;
        }

        .rel-progress-bar--mid {
            background: #f59e0b;
        }

        .rel-progress-bar--low {
            background: #ef4444;
        }

        .rel-progress-label {
            font-size: .73rem;
            font-weight: 600;
            color: #374151;
            white-space: nowrap;
            min-width: 34px;
            text-align: right;
        }

        .dark .rel-progress {
            background: rgba(255, 255, 255, .1);
        }

        .dark .rel-progress-label {
            color: #d1d5db;
        }

        .rel-empty {
            text-align: center;
            padding: 2.5rem 1rem !important;
            color: #9ca3af;
        }

        .rel-empty svg {
            width: 32px;
            height: 32px;
            margin: 0 auto .5rem;
            display: block;
        }

        .rel-empty p {
            margin: 0;
            font-size: .82rem;
        }

        .rel-pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: .75rem 1.25rem;
            border-top: 1px solid #f3f4f6;
            flex-wrap: wrap;
            gap: .5rem;
        }

        .dark .rel-pagination {
            border-top-color: rgba(255, 255, 255, .05);
        }

        .rel-pagination-info {
            font-size: .76rem;
            color: #6b7280;
        }

        .rel-pagination-btns {
            display: flex;
            gap: .2rem;
        }

        .rel-page-btn {
            min-width: 30px;
            height: 30px;
            padding: 0 .45rem;
            border: 1px solid #e5e7eb;
            border-radius: 5px;
            background: white;
            font-size: .78rem;
            color: #374151;
            cursor: pointer;
            transition: background .12s, border-color .12s;
        }

        .rel-page-btn:hover:not(:disabled) {
            background: #f3f4f6;
            border-color: #d1d5db;
        }

        .rel-page-btn:disabled {
            opacity: .35;
            cursor: not-allowed;
        }

        .rel-page-btn--active {
            background: #1e3a5f;
            border-color: #1e3a5f;
            color: white;
            font-weight: 600;
        }

        .dark .rel-page-btn {
            background: rgb(17 24 39);
            border-color: rgba(255, 255, 255, .1);
            color: #d1d5db;
        }

        .dark .rel-page-btn--active {
            background: #1e3a5f;
            border-color: #1e3a5f;
            color: white;
        }

        .mb-rel {
            margin-bottom: 1.1rem;
        }

        @media (max-width: 768px) {
            .rel-kpi-grid--3 {
                grid-template-columns: 1fr;
            }

            .rel-filters {
                flex-direction: column;
                align-items: stretch;
            }

            .rel-filter-input,
            .rel-filter-select {
                min-width: unset;
                width: 100%;
            }
        }
    </style>
</x-filament-panels::page>