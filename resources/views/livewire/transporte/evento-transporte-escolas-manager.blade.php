<div class="gi-school-transport-manager">
    <div class="gi-vehicle-relation-toolbar">
        <div>
            <strong>Transporte do evento</strong>
            <span>{{ $relacaoVeiculos->count() }} veículo(s) atribuído(s)</span>
        </div>
        <button
            type="button"
            wire:click="alternarRelacaoVeiculos"
            aria-expanded="{{ $mostrarRelacaoVeiculos ? 'true' : 'false' }}"
        >
            {{ $mostrarRelacaoVeiculos ? 'Ocultar relação' : 'Relação de veículos' }}
        </button>
    </div>

    @if ($mostrarRelacaoVeiculos)
        <section class="gi-vehicle-relation" aria-label="Relação de veículos do evento">
            @forelse ($relacaoVeiculos as $rota)
                @php
                    $alocacaoRota = $rota['alocacao'];
                @endphp
                <article class="gi-vehicle-relation__item">
                    <header>
                        <div>
                            <strong>{{ $alocacaoRota->veiculo?->identificacao ?: $alocacaoRota->veiculo?->placa }}</strong>
                            <span>{{ $alocacaoRota->veiculo?->placa }} · {{ $alocacaoRota->motoristaNomeExibicao() }}</span>
                        </div>
                    </header>

                    <div class="gi-vehicle-relation__schools">
                        @foreach ($rota['escolas'] as $escolaRota)
                            <div>
                                <strong>{{ $escolaRota['nome'] }}</strong>
                                <span>{{ implode(', ', $escolaRota['turmas']) }}</span>
                                <small>{{ $escolaRota['alunos'] }} aluno(s)</small>
                            </div>
                        @endforeach
                    </div>
                </article>
            @empty
                <p class="gi-event-detail__empty">Nenhum veículo foi atribuído ao evento.</p>
            @endforelse
        </section>
    @endif

    @forelse ($escolas as $item)
        @php
            $agendamento = $item['agendamento'];
            $agendamentoId = (int) $agendamento->getKey();
        @endphp

        <details @class([
            'gi-school-transport-card',
            'has-assignment' => $item['alocacoes']->isNotEmpty(),
        ]) wire:key="transporte-escola-{{ $agendamentoId }}">
            <summary class="gi-school-transport-card__header">
                <div>
                    <strong>{{ $agendamento->escola?->nome ?? 'Escola não informada' }}</strong>
                    <span>
                        {{ $agendamento->precisa_transporte
                            ? number_format((int) $agendamento->quantidade_estimada_transporte, 0, ',', '.').' aluno(s)'
                            : 'Transporte não solicitado' }}
                    </span>
                </div>
                <div class="gi-school-transport-card__status">
                    @if ($item['alocacoes']->isNotEmpty())
                        <b>Atribuído</b>
                    @endif
                    <small>
                        {{ $item['turmas']->count() }} turma(s)
                        <span aria-hidden="true">·</span>
                        <span class="gi-school-transport-card__action-label">Gerenciar</span>
                    </small>
                </div>
            </summary>

            <div class="gi-school-transport-card__body">

            @foreach ($item['alocacoes'] as $alocacao)
                <div class="gi-school-transport-card__allocation">
                    <div class="gi-school-transport-card__allocation-heading">
                        <div>
                            <small>Veículo atribuído</small>
                            <strong>{{ $alocacao->veiculo?->identificacao ?: $alocacao->veiculo?->placa }}</strong>
                        </div>
                    </div>
                    <p><b>Motorista:</b> {{ $alocacao->motoristaNomeExibicao() }}</p>
                    <p><b>Turmas:</b> {{ $alocacao->turmas->map(fn ($turma) => trim(($turma->serie?->nome ? $turma->serie->nome.' ' : '').$turma->nome))->join(', ') }}</p>

                    @if ($podeGerenciar)
                        <button
                            type="button"
                            class="gi-school-transport-card__remove"
                            wire:click="remover({{ $alocacao->getKey() }}, {{ $agendamentoId }})"
                            wire:confirm="Remover as turmas desta escola do veículo?"
                        >
                            Remover desta escola
                        </button>
                    @endif
                </div>
            @endforeach

            @if (! $podeGerenciar && $item['turmas']->isNotEmpty())
                <div class="gi-school-transport-card__classes" aria-label="Turmas participantes">
                    @foreach ($item['turmas'] as $turma)
                        <span>
                            {{ trim(($turma->serie?->nome ? $turma->serie->nome.' ' : '').$turma->nome) }}
                            <small>{{ (int) $turma->estudantes_transporte_count }}</small>
                        </span>
                    @endforeach
                </div>
            @endif

            @if ($item['disponiveis']->isNotEmpty())
                @if ($podeGerenciar)
                    <div class="gi-school-transport-card__form">
                        <div class="gi-school-transport-card__resources">
                            <label class="gi-school-transport-card__field">
                                <span>Veículo</span>
                                <select
                                    wire:model="veiculosSelecionados.{{ $agendamentoId }}"
                                    wire:key="veiculo-escola-{{ $agendamentoId }}"
                                >
                                    <option value="">Selecione</option>
                                    @foreach ($item['veiculos'] as $veiculo)
                                        <option value="{{ $veiculo->getKey() }}">
                                            {{ $veiculo->identificacao ? $veiculo->identificacao.' — ' : '' }}{{ $veiculo->placa }}{{ $veiculo->ja_alocado ? ' · já atribuído ao evento' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="gi-school-transport-card__field">
                                <span>Motorista</span>
                                <select
                                    wire:model="motoristasSelecionados.{{ $agendamentoId }}"
                                    wire:key="motorista-escola-{{ $agendamentoId }}"
                                >
                                    <option value="">Selecione</option>
                                    @foreach ($motoristas as $motoristaId => $motorista)
                                        <option value="{{ $motoristaId }}">{{ $motorista }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        <fieldset>
                            <legend>Turmas escolhidas para ir ao evento</legend>
                            <div class="gi-school-transport-card__checks">
                                @foreach ($item['disponiveis'] as $turma)
                                    <label>
                                        <input
                                            type="checkbox"
                                            value="{{ $turma->getKey() }}"
                                            wire:key="turma-escola-{{ $agendamentoId }}-{{ $turma->getKey() }}"
                                            wire:change="alternarTurma({{ $agendamentoId }}, {{ $turma->getKey() }}, $event.target.checked)"
                                            @checked(in_array((int) $turma->getKey(), array_map('intval', $turmasSelecionadas[$agendamentoId] ?? []), true))
                                        >
                                        <span>{{ trim(($turma->serie?->nome ? $turma->serie->nome.' ' : '').$turma->nome) }}</span>
                                        <small>{{ (int) $turma->estudantes_transporte_count }} aluno(s)</small>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        <div class="gi-school-transport-card__selection-summary">
                            {{ count($turmasSelecionadas[$agendamentoId] ?? []) }} turma(s) selecionada(s)
                        </div>

                        <button
                            type="button"
                            class="gi-school-transport-card__submit"
                            wire:click="alocar({{ $agendamentoId }})"
                            wire:loading.attr="disabled"
                            wire:target="alocar({{ $agendamentoId }})"
                        >
                            Confirmar atribuição
                        </button>

                        @error("turmasSelecionadas.{$agendamentoId}") <p class="gi-school-transport-card__error">{{ $message }}</p> @enderror
                        @error("veiculosSelecionados.{$agendamentoId}") <p class="gi-school-transport-card__error">{{ $message }}</p> @enderror
                        @error("motoristasSelecionados.{$agendamentoId}") <p class="gi-school-transport-card__error">{{ $message }}</p> @enderror
                        @error('veiculo_id') <p class="gi-school-transport-card__error">{{ $message }}</p> @enderror
                        @error('motorista_id') <p class="gi-school-transport-card__error">{{ $message }}</p> @enderror
                        @error('turma_ids') <p class="gi-school-transport-card__error">{{ $message }}</p> @enderror
                        @error('veiculo') <p class="gi-school-transport-card__error">{{ $message }}</p> @enderror
                        @error('motorista') <p class="gi-school-transport-card__error">{{ $message }}</p> @enderror
                        @error('alocacao') <p class="gi-school-transport-card__error">{{ $message }}</p> @enderror
                        @error('transporte') <p class="gi-school-transport-card__error">{{ $message }}</p> @enderror
                        @error('evento') <p class="gi-school-transport-card__error">{{ $message }}</p> @enderror
                    </div>
                @else
                    <p class="gi-school-transport-card__pending">Aguardando atribuição de veículo e motorista.</p>
                @endif
            @endif
            </div>
        </details>
    @empty
        <div class="gi-event-detail__empty">Nenhuma escola solicitou transporte neste evento.</div>
    @endforelse
</div>
