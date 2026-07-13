@php
    /** @var array<int, array{escola: string, turno: string, matriculas: array<int, string>, turmas: array<int, array{nome: string, componentes: array<int, string>}>}> $grupos */
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
                        Nenhuma turma ou componente vinculado nesta lotação.
                    </div>
                @endforelse
            </div>
        </article>
    @empty
        <div class="pessoa-view-groups__empty">
            <x-filament::icon icon="heroicon-o-academic-cap" />
            <strong>Nenhuma lotação cadastrada</strong>
            <span>Esta pessoa ainda não possui escola, matrícula ou turno vinculados.</span>
        </div>
    @endforelse
</div>
