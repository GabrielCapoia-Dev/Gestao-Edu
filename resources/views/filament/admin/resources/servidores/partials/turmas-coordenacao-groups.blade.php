@php
    /** @var array<int, array{escola: string, turmas: array<int, string>}> $grupos */
@endphp

<div class="pessoa-view-groups">
    @forelse ($grupos as $grupo)
        <article class="pessoa-view-group">
            <header class="pessoa-view-group__header">
                <div class="pessoa-view-group__school">
                    <span class="pessoa-view-group__icon">
                        <x-filament::icon icon="heroicon-o-building-library" />
                    </span>
                    <h3>{{ $grupo['escola'] }}</h3>
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
                            <strong>{{ $turma }}</strong>
                        </div>
                    </div>
                @empty
                    <div class="pessoa-view-group__empty">Nenhuma turma vinculada à coordenação.</div>
                @endforelse
            </div>
        </article>
    @empty
        <div class="pessoa-view-groups__empty">
            <x-filament::icon icon="heroicon-o-academic-cap" />
            <strong>Nenhuma turma coordenada</strong>
        </div>
    @endforelse
</div>
