<div>
    @if ($aberto)
        <div
            class="evento-detalhes-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="evento-detalhes-titulo"
            x-data
            x-init="$nextTick(() => $refs.fechar?.focus())"
            x-on:keydown.escape.window="$wire.fechar()"
        >
            <button type="button" class="evento-detalhes-modal__backdrop" aria-label="Fechar detalhes" wire:click="fechar"></button>

            <section class="evento-detalhes-modal__panel" x-on:click.stop>
                <header class="evento-detalhes-modal__header">
                    <div class="evento-detalhes-modal__identity">
                        <span class="evento-detalhes-modal__icon" aria-hidden="true">{{ mb_substr($resumo['titulo'] ?? 'E', 0, 1) }}</span>
                        <div>
                            <span>AGENDA ESCOLAR</span>
                            <h2 id="evento-detalhes-titulo">{{ $resumo['titulo'] ?? 'Detalhes do evento' }}</h2>
                            <p>{{ $resumo['local'] ?: ($resumo['endereco_mapa'] ?? 'Local não informado') }}</p>
                        </div>
                    </div>
                    @if ($resumo !== [])
                        <div class="evento-detalhes-modal__date">
                            <strong>{{ $resumo['data'] }}</strong>
                            <span>{{ $resumo['inicio'] }}–{{ $resumo['fim'] }}</span>
                        </div>
                    @endif
                    <button x-ref="fechar" type="button" class="evento-detalhes-modal__close" wire:click="fechar" aria-label="Fechar">×</button>
                </header>

                <nav class="evento-detalhes-modal__tabs" role="tablist" aria-label="Detalhes do evento">
                    @foreach (['resumo' => 'Resumo', 'participantes' => 'Participantes', 'escolas' => 'Escolas e turmas', 'alunos' => 'Alunos e transporte'] as $chave => $rotulo)
                        <button type="button" role="tab" @class(['is-active' => $aba === $chave]) aria-selected="{{ $aba === $chave ? 'true' : 'false' }}" wire:click="selecionarAba('{{ $chave }}')">
                            {{ $rotulo }}
                        </button>
                    @endforeach
                </nav>

                <main class="evento-detalhes-modal__body">
                    <div wire:loading.flex class="evento-detalhes-modal__loading"><span></span> Carregando informações...</div>

                    @if ($erro)
                        <div class="evento-detalhes-modal__error" role="alert">{{ $erro }}</div>
                    @elseif ($resumo !== [])
                        <div wire:loading.remove>
                            @if ($aba === 'resumo')
                                <div class="evento-detalhes-modal__badges">
                                    <span class="status-{{ $resumo['status'] }}">{{ $resumo['status_label'] }}</span>
                                    @if ($resumo['possui_transporte'])<span class="is-transport">Transporte solicitado</span>@endif
                                </div>
                                <p class="evento-detalhes-modal__description">{{ $resumo['descricao'] ?: 'Este evento não possui descrição.' }}</p>
                                <section class="evento-detalhes-modal__metrics">
                                    @foreach (['escolas' => 'Escolas', 'turmas' => 'Turmas', 'participantes' => 'Participantes', 'alunos' => 'Alunos'] as $campo => $rotulo)
                                        <article><strong>{{ number_format($resumo['totais'][$campo] ?? 0, 0, ',', '.') }}</strong><span>{{ $rotulo }}</span></article>
                                    @endforeach
                                </section>
                                <section class="evento-detalhes-modal__card">
                                    <h3>Informações gerais</h3>
                                    <dl class="evento-detalhes-modal__summary">
                                        <div><dt>Categoria</dt><dd>{{ $resumo['categoria'] }}</dd></div>
                                        <div><dt>Local</dt><dd>{{ $resumo['local'] ?: 'Não informado' }}</dd></div>
                                        @if ($resumo['endereco_mapa'])<div class="evento-detalhes-modal__summary--full"><dt>Endereço</dt><dd>{{ $resumo['endereco_mapa'] }}</dd></div>@endif
                                    </dl>
                                </section>
                                @if ($resumo['latitude'] !== null && $resumo['longitude'] !== null)
                                    <section class="evento-detalhes-modal__card">
                                        <h3>Localização</h3>
                                        @if ($resumo['endereco_mapa'])<p>{{ $resumo['endereco_mapa'] }}</p>@endif
                                        <div class="evento-detalhes-modal__map" x-data="eventoDetailMap({ latitude: @js($resumo['latitude']), longitude: @js($resumo['longitude']), query: @js($resumo['endereco_mapa'] ?: $resumo['local']) })" x-init="init()" data-evento-detail-map></div>
                                    </section>
                                @else
                                    <section class="evento-detalhes-modal__card">
                                        <h3>Localização</h3>
                                        <p class="evento-detalhes-modal__empty">Localização não definida.</p>
                                    </section>
                                @endif
                            @elseif ($aba === 'participantes')
                                <form class="evento-detalhes-modal__search" wire:submit="pesquisarParticipantes">
                                    <input type="search" wire:model="buscaParticipante" placeholder="Buscar participante pelo nome" aria-label="Buscar participante">
                                    <button type="submit">Buscar</button>
                                </form>
                                @if (($participantes['items'] ?? []) === [])
                                    <div class="evento-detalhes-modal__empty">Nenhum participante encontrado.</div>
                                @else
                                    <section class="evento-detalhes-modal__card evento-detalhes-modal__table-card">
                                        <div class="evento-detalhes-modal__table-toolbar"><span>{{ $participantes['total'] }} participante(s)</span><label>Por página <select wire:change="alterarPorPaginaParticipantes($event.target.value)">@foreach ([5, 10, 25, 50] as $limite)<option value="{{ $limite }}" @selected($porPaginaParticipantes === $limite)>{{ $limite }}</option>@endforeach</select></label></div>
                                        <div class="evento-detalhes-modal__table-wrap"><table><thead><tr><th>Escola</th><th>Nome</th><th>Cargo</th><th>Turno</th></tr></thead><tbody>@foreach ($participantes['items'] as $pessoa)<tr><td>{{ $pessoa['escola'] }}</td><td>{{ $pessoa['nome'] }}</td><td>{{ $pessoa['cargo'] }}</td><td>{{ $pessoa['turno'] }}</td></tr>@endforeach</tbody></table></div>
                                        @if (($participantes['ultima_pagina'] ?? 1) > 1)<div class="evento-detalhes-modal__pagination"><button type="button" wire:click="paginaParticipantes({{ max(1, $participantes['pagina'] - 1) }})" @disabled($participantes['pagina'] <= 1)>Anterior</button><span>Página {{ $participantes['pagina'] }} de {{ $participantes['ultima_pagina'] }}</span><button type="button" wire:click="paginaParticipantes({{ min($participantes['ultima_pagina'], $participantes['pagina'] + 1) }})" @disabled($participantes['pagina'] >= $participantes['ultima_pagina'])>Próxima</button></div>@endif
                                    </section>
                                @endif
                            @elseif ($aba === 'escolas')
                                @if (($escolas['items'] ?? []) === [])
                                    <div class="evento-detalhes-modal__empty">Nenhuma escola vinculada ao seu escopo.</div>
                                @else
                                    <section class="evento-detalhes-modal__card evento-detalhes-modal__table-card">
                                        <div class="evento-detalhes-modal__table-toolbar"><span>{{ $escolas['total'] }} turma(s)</span><label>Por página <select wire:change="alterarPorPaginaEscolas($event.target.value)">@foreach ([5, 10, 25, 50] as $limite)<option value="{{ $limite }}" @selected($porPaginaEscolas === $limite)>{{ $limite }}</option>@endforeach</select></label></div>
                                        <div class="evento-detalhes-modal__table-wrap"><table><thead><tr><th>Escola</th><th>Série</th><th>Turma</th><th>Turno</th><th class="is-number">Quantidade de alunos</th></tr></thead><tbody>@foreach ($escolas['items'] as $turma)<tr><td>{{ $turma['escola'] }}</td><td>{{ $turma['serie'] }}</td><td>{{ $turma['turma'] }}</td><td>{{ $turma['turno'] }}</td><td class="is-number">{{ $turma['quantidade_alunos'] === null ? '—' : number_format($turma['quantidade_alunos'], 0, ',', '.') }}</td></tr>@endforeach</tbody></table></div>
                                        @if (($escolas['ultima_pagina'] ?? 1) > 1)<div class="evento-detalhes-modal__pagination"><button type="button" wire:click="paginaEscolas({{ max(1, $escolas['pagina'] - 1) }})" @disabled($escolas['pagina'] <= 1)>Anterior</button><span>Página {{ $escolas['pagina'] }} de {{ $escolas['ultima_pagina'] }}</span><button type="button" wire:click="paginaEscolas({{ min($escolas['ultima_pagina'], $escolas['pagina'] + 1) }})" @disabled($escolas['pagina'] >= $escolas['ultima_pagina'])>Próxima</button></div>@endif
                                    </section>
                                @endif
                            @elseif ($aba === 'alunos')
                                <section class="evento-detalhes-modal__card">
                                    <h3>Situação do transporte</h3>
                                    <p><b>{{ $alunos['transporte']['status'] ?? $resumo['status_label'] }}</b></p>
                                    @forelse ($alunos['transporte']['alocacoes'] ?? [] as $alocacao)
                                        <p class="evento-detalhes-modal__transport-info">{{ $alocacao['veiculo'] }} · {{ $alocacao['motorista'] }}@if ($alocacao['turmas']) · {{ $alocacao['turmas'] }}@endif</p>
                                    @empty
                                        <p class="evento-detalhes-modal__empty">Nenhum veículo foi alocado até o momento.</p>
                                    @endforelse
                                </section>
                                <form class="evento-detalhes-modal__search" wire:submit="pesquisarAlunos">
                                    <input type="search" wire:model="buscaAluno" placeholder="Buscar aluno por nome ou CGM" aria-label="Buscar aluno">
                                    <button type="submit">Buscar</button>
                                </form>
                                @forelse (collect($alunos['items'] ?? [])->groupBy('escola') as $escola => $lista)
                                    <section class="evento-detalhes-modal__card evento-detalhes-modal__group">
                                        <header><h3>{{ $escola }}</h3><span>{{ $lista->count() }} aluno(s)</span></header>
                                        @foreach ($lista->groupBy(fn ($aluno) => ($aluno['serie'] ?: 'Sem série').' · '.($aluno['turma'] ?: 'Sem turma')) as $turma => $grupo)
                                            <details><summary>{{ $turma }} <span>{{ $grupo->count() }}</span></summary>
                                                <ul>@foreach ($grupo as $aluno)<li><strong>{{ $aluno['nome'] }}</strong><small>CGM {{ $aluno['cgm'] ?: 'não informado' }} · {{ ucfirst($aluno['turno'] ?? 'turno não informado') }}</small></li>@endforeach</ul>
                                            </details>
                                        @endforeach
                                    </section>
                                @empty
                                    <div class="evento-detalhes-modal__empty">Nenhum aluno encontrado para o transporte.</div>
                                @endforelse
                                @if (count($alunos['items'] ?? []) < ($alunos['total'] ?? 0))<button class="evento-detalhes-modal__more" wire:click="maisAlunos">Carregar mais</button>@endif
                            @endif
                        </div>
                    @endif
                </main>

                <footer class="evento-detalhes-modal__footer"><button type="button" wire:click="fechar">Fechar</button></footer>
            </section>
        </div>
    @endif
</div>
