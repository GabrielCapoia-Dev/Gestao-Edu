@php
    $grupos = $usuarios->groupBy(fn ($usuario) => $usuario->escola?->nome ?? 'Sem escola');
@endphp

<div class="evento-participantes" x-data="{ busca: '' }">
    <div class="evento-participantes__header">
        <div>
            <p class="evento-participantes__eyebrow">PARTICIPANTES SELECIONADOS</p>
            <h3>Pessoas convidadas</h3>
            <span>{{ $usuarios->count() }} pessoa(s) encontrada(s) pelos filtros do evento</span>
        </div>
        <input x-model.debounce.200ms="busca" type="search" placeholder="Filtrar pessoas na tabela..." aria-label="Filtrar pessoas na tabela">
    </div>

    @if ($grupos->isEmpty())
        <div class="evento-participantes__empty">Nenhuma pessoa encontrada para os filtros informados.</div>
    @else
        <div class="evento-participantes__groups">
            @foreach ($grupos as $escola => $pessoas)
                <section class="evento-participantes__group">
                    <div class="evento-participantes__group-title">
                        <strong>{{ $escola }}</strong>
                        <span>{{ $pessoas->count() }} pessoa(s)</span>
                    </div>
                    <div class="evento-participantes__table-wrap">
                        <table class="evento-participantes__table">
                            <thead>
                                <tr><th>Nome</th><th>E-mail</th><th>Cargo</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($pessoas as $pessoa)
                                    @php
                                        $cargos = $pessoa->servidores?->flatMap->funcoesAtivas->pluck('nome')->filter()->unique()->implode(', ');
                                        $textoBusca = mb_strtolower(implode(' ', [$pessoa->name, $pessoa->email, $cargos]));
                                    @endphp
                                    <tr x-show="!busca || @js($textoBusca).includes(busca.toLowerCase())">
                                        <td>{{ $pessoa->name }}</td>
                                        <td>{{ $pessoa->email ?: 'Não informado' }}</td>
                                        <td>{{ $cargos ?: 'Professor' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
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
    .evento-participantes__groups { display: grid; gap: .75rem; padding: .8rem; }
    .evento-participantes__group { border: 1px solid #e1e9f3; border-radius: .65rem; overflow: hidden; }
    .evento-participantes__group-title { display: flex; justify-content: space-between; padding: .7rem .85rem; background: #f5f8fc; color: #173b73; font-size: .82rem; }
    .evento-participantes__table-wrap { overflow-x: auto; }
    .evento-participantes__table { width: 100%; border-collapse: collapse; font-size: .78rem; }
    .evento-participantes__table th, .evento-participantes__table td { padding: .6rem .85rem; border-top: 1px solid #edf1f6; text-align: left; }
    .evento-participantes__table th { color: #64748b; font-size: .68rem; text-transform: uppercase; letter-spacing: .03em; }
    .evento-participantes__empty { padding: 1rem; color: #64748b; font-size: .8rem; }
    @media (max-width: 640px) { .evento-participantes__header { align-items: stretch; flex-direction: column; } .evento-participantes__header input { width: 100%; } }
</style>
