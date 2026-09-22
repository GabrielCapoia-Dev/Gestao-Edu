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
                            <p>{{ $resumo['local'] ?? 'Local não informado' }}</p>
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
                    @foreach (['resumo' => 'Resumo', 'participantes' => 'Participantes', 'escolas' => 'Escolas e turmas', 'alunos' => 'Alunos e transporte', 'historico' => 'Histórico'] as $chave => $rotulo)
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
                        @if (($resumo['snapshot_legado'] ?? false) && $aba !== 'resumo')
                            <div class="evento-detalhes-modal__legacy">Dados reconstruídos do cadastro atual. Eventos anteriores podem não refletir exatamente a seleção original.</div>
                        @endif

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
                                        <div><dt>Criado por</dt><dd>{{ $resumo['criado_por'] }}</dd></div>
                                        <div><dt>Criado em</dt><dd>{{ $resumo['criado_em'] }}</dd></div>
                                        <div><dt>Categoria</dt><dd>{{ $resumo['categoria'] }}</dd></div>
                                        <div><dt>Local</dt><dd>{{ $resumo['local'] ?: 'Não informado' }}</dd></div>
                                    </dl>
                                </section>
                                @if ($resumo['local'])
                                    <section class="evento-detalhes-modal__card">
                                        <h3>Localização</h3>
                                        <p data-evento-detail-address>{{ $resumo['local'] }}</p>
                                        <div class="evento-detalhes-modal__map" x-data="eventoDetailMap({ latitude: @js($resumo['latitude']), longitude: @js($resumo['longitude']), query: @js($resumo['local']) })" x-init="init()" data-evento-detail-map></div>
                                    </section>
                                @endif
                            @elseif ($aba === 'participantes')
                                <form class="evento-detalhes-modal__search" wire:submit="pesquisarParticipantes">
                                    <input type="search" wire:model="buscaParticipante" placeholder="Buscar participante pelo nome" aria-label="Buscar participante">
                                    <button type="submit">Buscar</button>
                                </form>
                                @forelse (collect($participantes['items'] ?? [])->groupBy('escola') as $escola => $pessoas)
                                    <section class="evento-detalhes-modal__card evento-detalhes-modal__group">
                                        <header><h3>{{ $escola }}</h3><span>{{ $pessoas->count() }} pessoa(s)</span></header>
                                        @foreach ($pessoas->groupBy('cargo') as $cargo => $grupo)
                                            <details><summary>{{ $cargo }} <span>{{ $grupo->count() }}</span></summary>
                                                <ul>@foreach ($grupo as $pessoa)<li><strong>{{ $pessoa['nome'] }}</strong><small>{{ $pessoa['email'] }}</small></li>@endforeach</ul>
                                            </details>
                                        @endforeach
                                    </section>
                                @empty
                                    <div class="evento-detalhes-modal__empty">Nenhum participante encontrado.</div>
                                @endforelse
                                @if (count($participantes['items'] ?? []) < ($participantes['total'] ?? 0))<button class="evento-detalhes-modal__more" wire:click="maisParticipantes">Carregar mais</button>@endif
                            @elseif ($aba === 'escolas')
                                @forelse ($escolas as $escola)
                                    <details class="evento-detalhes-modal__card evento-detalhes-modal__school">
                                        <summary><span><strong>{{ $escola['nome'] }}</strong><small>{{ $escola['horario'] }}</small></span><span>{{ count($escola['turmas']) }} turma(s)</span></summary>
                                        @if ($escola['precisa_transporte'])<p class="evento-detalhes-modal__transport-info">Transporte para {{ number_format($escola['estimativa'], 0, ',', '.') }} aluno(s)</p>@endif
                                        @if ($escola['series'] !== [])<p><b>Séries:</b> {{ implode(', ', $escola['series']) }}</p>@endif
                                        <div class="evento-detalhes-modal__class-grid">@foreach ($escola['turmas'] as $turma)<span><b>{{ $turma['serie'] }} · {{ $turma['nome'] }}</b><small>{{ ucfirst($turma['turno'] ?? 'Turno não informado') }}</small></span>@endforeach</div>
                                    </details>
                                @empty
                                    <div class="evento-detalhes-modal__empty">Nenhuma escola vinculada ao seu escopo.</div>
                                @endforelse
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
                            @elseif ($aba === 'historico')
                                <ol class="evento-detalhes-modal__timeline">
                                    @forelse ($historico as $item)<li><i></i><div><strong>{{ $item['acao'] }}</strong><span>{{ $item['usuario'] }} · {{ $item['data'] }}</span>@if ($item['motivo'])<p>{{ $item['motivo'] }}</p>@endif</div></li>@empty<li class="evento-detalhes-modal__empty">Ainda não há movimentações registradas.</li>@endforelse
                                </ol>
                            @endif
                        </div>
                    @endif
                </main>

                <footer class="evento-detalhes-modal__footer"><button type="button" wire:click="fechar">Fechar</button></footer>
            </section>
        </div>
    @endif
</div>
