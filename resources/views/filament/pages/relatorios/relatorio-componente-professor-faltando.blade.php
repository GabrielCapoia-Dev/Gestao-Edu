<x-filament-panels::page>
<div class="relatorios-root">

    {{-- KPIs --}}
    <div class="rel-kpi-grid rel-kpi-grid--3 mb-rel">
        <div class="rel-kpi-card rel-kpi-card--highlight">
            <div class="rel-kpi-icon rel-kpi-icon--slate">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/></svg>
            </div>
            <div class="rel-kpi-body">
                <span class="rel-kpi-label">Total de vínculos</span>
                <span class="rel-kpi-value">{{ $this->vinculos }}</span>
                <span class="rel-kpi-sub">turma × componente</span>
            </div>
        </div>
        <div class="rel-kpi-card rel-kpi-card--success">
            <div class="rel-kpi-icon rel-kpi-icon--green">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
            </div>
            <div class="rel-kpi-body">
                <span class="rel-kpi-label">Com professor</span>
                <span class="rel-kpi-value rel-kpi-value--green">{{ $this->comProfessor }}</span>
                @if($this->vinculos > 0)
                <span class="rel-kpi-sub">{{ round(($this->comProfessor / $this->vinculos) * 100) }}% dos vínculos</span>
                @endif
            </div>
        </div>
        <div class="rel-kpi-card rel-kpi-card--danger">
            <div class="rel-kpi-icon rel-kpi-icon--rose">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
            </div>
            <div class="rel-kpi-body">
                <span class="rel-kpi-label">Sem professor</span>
                <span class="rel-kpi-value rel-kpi-value--red">{{ $this->semProfessor }}</span>
                @if($this->vinculos > 0)
                <span class="rel-kpi-sub">{{ round(($this->semProfessor / $this->vinculos) * 100) }}% dos vínculos</span>
                @endif
            </div>
        </div>
    </div>

    {{-- FILTROS --}}
    <div class="rel-filters mb-rel">
        <div class="rel-filter-group">
            <input
                type="text"
                wire:model.live.debounce.400ms="search"
                placeholder="Pesquisar componente..."
                class="rel-filter-input"
            />
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

    {{-- TABELA --}}
    <div class="rel-table-card mb-rel">
        <div class="rel-table-header">
            <div>
                <h3 class="rel-table-title">Componentes com maior falta de docentes</h3>
                <p class="rel-table-sub">
                    {{ $this->totalRegistros }} {{ $this->totalRegistros === 1 ? 'componente encontrado' : 'componentes encontrados' }}
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
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                <p>Nenhum componente encontrado com professor faltando.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- PAGINAÇÃO --}}
        @if($this->totalPaginas > 1)
        <div class="rel-pagination">
            <span class="rel-pagination-info">
                Mostrando {{ (($this->page - 1) * $this->perPage) + 1 }}–{{ min($this->page * $this->perPage, $this->totalRegistros) }}
                de {{ $this->totalRegistros }}
            </span>
            <div class="rel-pagination-btns">
                <button
                    wire:click="irParaPagina({{ $this->page - 1 }})"
                    @if($this->page <= 1) disabled @endif
                    class="rel-page-btn">
                    ←
                </button>
                @for($p = 1; $p <= $this->totalPaginas; $p++)
                    <button
                        wire:click="irParaPagina({{ $p }})"
                        class="rel-page-btn {{ $p === $this->page ? 'rel-page-btn--active' : '' }}">
                        {{ $p }}
                    </button>
                @endfor
                <button
                    wire:click="irParaPagina({{ $this->page + 1 }})"
                    @if($this->page >= $this->totalPaginas) disabled @endif
                    class="rel-page-btn">
                    →
                </button>
            </div>
        </div>
        @endif
    </div>

</div>

<style>
    /* Reutiliza as classes do dashboard — cole aqui o mesmo bloco <style> do relatorios-dashboard.blade.php */

    /* Complementos específicos desta página */
    .rel-filters {
        display: flex;
        flex-wrap: wrap;
        gap: .75rem;
        align-items: center;
    }
    .rel-filter-group { display: flex; flex-direction: column; }
    .rel-filter-input,
    .rel-filter-select {
        height: 38px;
        padding: 0 .75rem;
        border: 1.5px solid #e2e8f0;
        border-radius: .5rem;
        font-size: .85rem;
        color: #374151;
        background: white;
        outline: none;
        transition: border-color .15s;
        min-width: 200px;
    }
    .rel-filter-input:focus,
    .rel-filter-select:focus { border-color: #2563eb; }
    .rel-filter-select--sm { min-width: 140px; }
    .dark .rel-filter-input,
    .dark .rel-filter-select {
        background: rgb(17 24 39);
        border-color: rgba(255,255,255,.1);
        color: #f3f4f6;
    }

    .rel-empty {
        text-align: center;
        padding: 2.5rem 1rem !important;
        color: #9ca3af;
    }
    .rel-empty svg { width: 36px; height: 36px; margin: 0 auto .5rem; display: block; }
    .rel-empty p { margin: 0; font-size: .85rem; }

    .rel-pagination {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: .875rem 1.25rem;
        border-top: 1px solid #f1f5f9;
        flex-wrap: wrap;
        gap: .5rem;
    }
    .dark .rel-pagination { border-top-color: rgba(255,255,255,.06); }
    .rel-pagination-info { font-size: .78rem; color: #6b7280; font-family: system-ui, sans-serif; }
    .rel-pagination-btns { display: flex; gap: .25rem; }
    .rel-page-btn {
        min-width: 32px;
        height: 32px;
        padding: 0 .5rem;
        border: 1.5px solid #e2e8f0;
        border-radius: .4rem;
        background: white;
        font-size: .8rem;
        color: #374151;
        cursor: pointer;
        transition: background .15s, border-color .15s;
    }
    .rel-page-btn:hover:not(:disabled) { background: #f1f5f9; border-color: #cbd5e1; }
    .rel-page-btn:disabled { opacity: .4; cursor: not-allowed; }
    .rel-page-btn--active { background: #2563eb; border-color: #2563eb; color: white; font-weight: 700; }
    .dark .rel-page-btn { background: rgb(17 24 39); border-color: rgba(255,255,255,.1); color: #d1d5db; }
    .dark .rel-page-btn--active { background: #2563eb; border-color: #2563eb; color: white; }

    /* Cole aqui o restante do <style> do relatorios-dashboard para as classes compartilhadas */
</style>
</x-filament-panels::page>