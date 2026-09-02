<nav class="av-tabs-shell av-tabs-shell--turmas" aria-label="Turmas da avaliação">
    <div class="av-tabs-heading">
        <div>
        <span class="av-tabs-kicker">1. Turma</span>
            <strong>Escolha onde deseja trabalhar</strong>
        </div>
        <span>{{ count($tabs) }} {{ count($tabs) === 1 ? 'turma' : 'turmas' }}</span>
    </div>

    <div class="av-tabs-track" role="tablist">
        @foreach ($tabs as $tab)
            @php($progresso = $tab['progresso'])
            <a
                href="{{ $tab['url'] }}"
                wire:navigate
                role="tab"
                aria-selected="{{ (int) $activeId === (int) $tab['id'] ? 'true' : 'false' }}"
                class="av-workspace-tab {{ (int) $activeId === (int) $tab['id'] ? 'is-active' : '' }} {{ $progresso['concluida'] ? 'is-complete' : '' }}">
                <span class="av-workspace-tab__title">
                    <span>{{ $tab['label'] }}</span>
                    <small>{{ $progresso['percentual'] }}%</small>
                </span>
                <small>{{ $tab['meta'] }}</small>
                <span class="av-workspace-tab__progress" aria-label="{{ $progresso['percentual'] }}% concluído">
                    <span style="width: {{ $progresso['percentual'] }}%"></span>
                </span>
            </a>
        @endforeach
    </div>
</nav>
