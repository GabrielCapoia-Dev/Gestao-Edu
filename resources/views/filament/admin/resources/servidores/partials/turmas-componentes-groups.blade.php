@php
    /** @var array<int, array{escola: string, turno: string, matriculas: array<int, string>, turmas: array<int, array{nome: string, componentes: array<int, string>}>, cargos?: array<int, string>, portaria?: string, vigencia?: string}> $grupos */
@endphp

<div class="pessoa-view-groups">
    @forelse ($grupos as $grupo)
        <article class="pessoa-view-group">
            <header class="pessoa-view-group__header">
                <div class="pessoa-view-group__school">
                    <span class="pessoa-view-group__icon">
                        <x-filament::icon icon="heroicon-o-building-library" />
                    </span>

                    <div>
                        <h3>{{ $grupo['escola'] }}</h3>

                        <div class="pessoa-view-group__meta">
                            <span class="pessoa-view-group__shift">{{ $grupo['turno'] }}</span>

                            @foreach ($grupo['matriculas'] as $matricula)
                                <span class="pessoa-view-group__registration">Matrícula {{ $matricula }}</span>
                            @endforeach

                            @foreach ($grupo['cargos'] ?? [] as $cargo)
                                <span class="pessoa-view-group__registration">{{ $cargo }}</span>
                            @endforeach

                            @if (filled($grupo['portaria'] ?? null))
                                <span class="pessoa-view-group__registration">Portaria {{ $grupo['portaria'] }}</span>
                            @endif

                            @if (filled($grupo['vigencia'] ?? null))
                                <span class="pessoa-view-group__registration">Desde {{ $grupo['vigencia'] }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                <span class="pessoa-view-group__count">
                    {{ count($grupo['turmas']) }} {{ count($grupo['turmas']) === 1 ? 'turma' : 'turmas' }}
                </span>
            </header>

            <div class="pessoa-view-group__classes">
                @forelse ($grupo['turmas'] as $turma)
                    <div class="pessoa-view-class">
                        <div class="pessoa-view-class__title">
                            <x-filament::icon icon="heroicon-o-user-group" />
                            <strong>{{ $turma['nome'] }}</strong>
                        </div>

                        <div class="pessoa-view-class__components">
                            @foreach ($turma['componentes'] as $componente)
                                <span>{{ $componente }}</span>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="pessoa-view-group__empty">
                        <x-filament::icon icon="heroicon-o-information-circle" />
                        Nenhuma turma vinculada neste grupo.
                    </div>
                @endforelse
            </div>
        </article>
    @empty
        <div class="pessoa-view-groups__empty">
            <x-filament::icon icon="heroicon-o-academic-cap" />
            <strong>Nenhum vínculo ativo cadastrado</strong>
            <span>Esta pessoa ainda não possui escola, matrícula, turma ou cargo gestor ativo.</span>
        </div>
    @endforelse
</div>
