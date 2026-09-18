@php
    $grupos = $usuarios->groupBy(fn ($usuario) => $usuario->escola?->nome ?? 'Sem escola');
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
        <div class="evento-participantes__empty">Nenhuma pessoa encontrada para os filtros informados.</div>
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
                                            <button type="button" @click="removidos.push({{ (int) $pessoa->id }}); $wire.set('mountedActionsData.0.publico_excecoes_ids', [...new Set(removidos)])" title="Remover da lista" aria-label="Remover {{ $pessoa->name }}">&times;</button>
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

<style>
    .evento-participantes { border: 1px solid #d7e2f0; border-radius: .8rem; background: #fff; overflow: hidden; }
    .evento-participantes__header { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: 1rem 1.1rem; border-bottom: 1px solid #e4ebf3; }
    .evento-participantes__eyebrow { margin: 0 0 .2rem; color: #1d5fb8; font-size: .68rem; font-weight: 700; letter-spacing: .05em; }
    .evento-participantes h3 { margin: 0; color: #15233b; font-size: 1rem; }
    .evento-participantes__header span, .evento-participantes__group-title span { color: #64748b; font-size: .75rem; }
    .evento-participantes__header input { width: min(20rem, 45%); border: 1px solid #cbd8e8; border-radius: .55rem; padding: .55rem .7rem; font-size: .8rem; }
    .evento-participantes__actions { display: flex; align-items: center; justify-content: flex-end; gap: .4rem; flex-wrap: wrap; }
    .evento-participantes__actions button { border: 1px solid #cbd8e8; border-radius: .45rem; background: #f7faff; color: #1d4d91; padding: .45rem .6rem; font-size: .72rem; font-weight: 600; cursor: pointer; }
    .evento-participantes__actions button:hover { border-color: #1d5fb8; background: #edf4ff; }
    .evento-participantes__groups { display: grid; gap: .75rem; padding: .8rem; }
    .evento-participantes__group { border: 1px solid #e1e9f3; border-radius: .65rem; overflow: hidden; }
    .evento-participantes__group > summary { list-style: none; cursor: pointer; }
    .evento-participantes__group > summary::-webkit-details-marker { display: none; }
    .evento-participantes__group > summary > span { display: inline-flex; align-items: center; gap: .65rem; }
    .evento-participantes__group > summary b { color: #1d5fb8; font-size: 1rem; transition: transform .15s ease; }
    .evento-participantes__group:not([open]) > summary b { transform: rotate(-90deg); }
    .evento-participantes__group-title { display: flex; justify-content: space-between; padding: .7rem .85rem; background: #f5f8fc; color: #173b73; font-size: .82rem; }
    .evento-participantes__table-wrap { overflow-x: auto; }
    .evento-participantes__table { width: 100%; border-collapse: collapse; font-size: .78rem; }
    .evento-participantes__table th, .evento-participantes__table td { padding: .6rem .85rem; border-top: 1px solid #edf1f6; text-align: left; }
    .evento-participantes__table th { color: #64748b; font-size: .68rem; text-transform: uppercase; letter-spacing: .03em; }
    .evento-participantes__remove-cell { width: 2.5rem; text-align: center !important; }
    .evento-participantes__remove-cell button { border: 0; background: transparent; color: #dc2626; cursor: pointer; font-size: 1.25rem; line-height: 1; }
    .evento-participantes__empty { padding: 1rem; color: #64748b; font-size: .8rem; }
    @media (max-width: 640px) { .evento-participantes__header { align-items: stretch; flex-direction: column; } .evento-participantes__actions { justify-content: flex-start; } .evento-participantes__header input { width: 100%; } }
</style>
