@php
    $statePath = $getStatePath();
    $formStatePath = str($statePath)->beforeLast('.');
    $excecoesStatePath = $formStatePath.'.publico_excecoes_ids';
    $grupos = collect();
    $escolasSelecionadas = collect($escolasSelecionadas ?? [])
        ->map(fn ($id): int => (int) $id)
        ->values();

    foreach ($usuarios as $usuario) {
        $escolas = collect($usuario->escolas ?? [])
            ->merge($usuario->escola ? [$usuario->escola] : [])
            ->merge(collect($usuario->servidores ?? [])->flatMap(fn ($servidor) => $servidor->vinculosAtivos ?? [])->pluck('escola'))
            ->merge(collect($usuario->servidores ?? [])->flatMap(fn ($servidor) => $servidor->professores ?? [])->pluck('escola'))
            ->filter()
            ->unique('id')
            ->when($escolasSelecionadas->isNotEmpty(), fn ($escolas) => $escolas->whereIn('id', $escolasSelecionadas->all())->values())
            ->values();

        foreach ($escolas as $escola) {
            $grupos->put($escola->nome, $grupos->get($escola->nome, collect())->push($usuario));
        }
    }

    // Não mascarar inconsistências de vínculo como uma escola válida.
    if ($grupos->isEmpty() && $usuarios->isNotEmpty()) {
        $grupos->put('Escola não identificada', $usuarios);
    }
@endphp

<div class="evento-participantes" x-data="{ busca: '', removidos: @js($excecoes ?? []) }">
    <div class="evento-participantes__header">
        <div>
            <p class="evento-participantes__eyebrow">PARTICIPANTES SELECIONADOS</p>
            <h3>Pessoas convidadas</h3>
            <span>{{ $usuarios->count() }} pessoa(s) encontrada(s) pelos filtros do evento</span>
        </div>
        <div class="evento-participantes__actions">
            <button type="button" @click="$el.closest('.evento-participantes').querySelectorAll('details').forEach((grupo) => grupo.open = true)">Abrir todos</button>
            <button type="button" @click="$el.closest('.evento-participantes').querySelectorAll('details').forEach((grupo) => grupo.open = false)">Fechar todos</button>
            <input x-model.debounce.200ms="busca" type="search" placeholder="Filtrar pessoas na tabela..." aria-label="Filtrar pessoas na tabela">
        </div>
    </div>

    @if ($grupos->isEmpty())
        <div class="evento-participantes__empty">{{ $aguardandoFiltro ?? false ? 'Aplique os filtros para listar as pessoas convidadas.' : 'Nenhuma pessoa encontrada para os filtros informados.' }}</div>
    @else
        <div class="evento-participantes__groups">
            @foreach ($grupos as $escola => $pessoas)
                <details class="evento-participantes__group">
                    <summary class="evento-participantes__group-title">
                        <strong>{{ $escola }}</strong>
                        <span><span>{{ $pessoas->count() }} pessoa(s)</span><b aria-hidden="true">⌄</b></span>
                    </summary>
                    <div class="evento-participantes__table-wrap">
                        <table class="evento-participantes__table">
                            <thead>
                                <tr><th>Nome</th><th>E-mail</th><th>Cargo</th><th></th></tr>
                            </thead>
                            <tbody>
                                @foreach ($pessoas as $pessoa)
                                    @php
                                        $cargos = collect($pessoa->servidores ?? [])
                                            ->flatMap(fn ($servidor) => $servidor->funcoesAtivas ?? [])
                                            ->pluck('nome')->filter()->unique()->implode(', ');
                                        $textoBusca = strtolower(implode(' ', [(string) $pessoa->name, (string) $pessoa->email, $cargos]));
                                    @endphp
                                    <tr x-show="!removidos.includes({{ (int) $pessoa->id }}) && (!busca || @js($textoBusca).includes(busca.toLowerCase()))">
                                        <td>{{ $pessoa->name }}</td>
                                        <td>{{ $pessoa->email ?: 'Não informado' }}</td>
                                        <td>{{ $cargos ?: 'Professor' }}</td>
                                        <td class="evento-participantes__remove-cell">
                                            <button type="button" @click="removidos.push({{ (int) $pessoa->id }}); $wire.set(@js($excecoesStatePath), [...new Set(removidos)])" title="Remover da lista" aria-label="Remover {{ $pessoa->name }}">&times;</button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            @endforeach
        </div>
    @endif
</div>
