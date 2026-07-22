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
                    $diferencaRota = (int) $rota['diferenca'];
                @endphp
                <article @class([
                    'gi-vehicle-relation__item',
                    'is-full' => $diferencaRota === 0,
                    'is-overloaded' => $diferencaRota < 0,
                ])>
                    <header>
                        <div>
                            <strong>{{ $alocacaoRota->veiculo?->identificacao ?: $alocacaoRota->veiculo?->placa }}</strong>
                            <span>{{ $alocacaoRota->veiculo?->placa }} · {{ $alocacaoRota->motorista?->nome ?? 'Motorista não informado' }}</span>
                        </div>
                        <b>{{ $rota['total'] }}/{{ $rota['capacidade'] }} aluno(s)</b>
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

                    <footer>
                        @if ($diferencaRota < 0)
                            Superlotação de {{ abs($diferencaRota) }} lugar(es)
                        @elseif ($diferencaRota === 0)
                            Lotação máxima atingida
                        @else
                            {{ $diferencaRota }} lugar(es) disponível(is)
                        @endif
                    </footer>
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

        <article @class([
            'gi-school-transport-card',
            'has-assignment' => $item['alocacoes']->isNotEmpty(),
        ]) wire:key="transporte-escola-{{ $agendamentoId }}">
            <header class="gi-school-transport-card__header">
                <div>
                    <strong>{{ $agendamento->escola?->nome ?? 'Escola não informada' }}</strong>
                    <span>
                        {{ $agendamento->precisa_transporte
                            ? number_format((int) $agendamento->quantidade_estimada_transporte, 0, ',', '.').' aluno(s)'
                            : 'Transporte não solicitado' }}
                    </span>
                </div>
                <small>{{ $item['turmas']->count() }} turma(s)</small>
            </header>

            @foreach ($item['alocacoes'] as $alocacao)
                @php
                    $alunosAlocados = (int) $turmas->whereIn('id', $alocacao->turmas->modelKeys())->sum('estudantes_transporte_count');
                    $capacidade = (int) $alocacao->veiculo?->capacidade_passageiros;
                    $diferenca = $capacidade - $alunosAlocados;
                @endphp
                <div @class([
                    'gi-school-transport-card__allocation',
                    'is-overloaded' => $diferenca < 0,
                    'is-full' => $diferenca === 0,
                ])>
                    <div class="gi-school-transport-card__allocation-heading">
                        <strong>{{ $alocacao->veiculo?->identificacao ?: $alocacao->veiculo?->placa }}</strong>
                        <span>
                            @if ($diferenca < 0)
                                Superlotado em {{ abs($diferenca) }}
                            @elseif ($diferenca === 0)
                                Lotação máxima
                            @else
                                {{ $diferenca }} lugar(es) livre(s)
                            @endif
                        </span>
                    </div>
                    <p><b>Motorista:</b> {{ $alocacao->motorista?->nome ?? 'Não informado' }}</p>
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
                        <label class="gi-school-transport-card__field">
                            <span>Veículo</span>
                            <select wire:model.live="veiculosSelecionados.{{ $agendamentoId }}">
                                <option value="">Selecione</option>
                                @foreach ($item['veiculos'] as $veiculo)
                                    @php $saldo = (int) $veiculo->lugares_disponiveis - $item['total_selecionado']; @endphp
                                    <option value="{{ $veiculo->getKey() }}">
                                        {{ $veiculo->identificacao ? $veiculo->identificacao.' — ' : '' }}{{ $veiculo->placa }} · {{ $veiculo->lugares_disponiveis }} livre(s){{ $veiculo->ja_alocado ? ' · já em rota' : '' }}{{ $saldo < 0 ? ' · excede em '.abs($saldo) : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        @php
                            $motoristaFixado = $motoristasPorVeiculo[(int) ($veiculosSelecionados[$agendamentoId] ?? 0)] ?? null;
                        @endphp
                        @if ($motoristaFixado)
                            <div class="gi-school-transport-card__fixed-driver">
                                <span>Motorista da rota</span>
                                <strong>{{ $motoristaFixado }}</strong>
                            </div>
                        @else
                            <label class="gi-school-transport-card__field">
                                <span>Motorista</span>
                                <select wire:model="motoristasSelecionados.{{ $agendamentoId }}">
                                    <option value="">Selecione</option>
                                    @foreach ($motoristas as $motoristaId => $motorista)
                                        <option value="{{ $motoristaId }}">{{ $motorista }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endif

                        <fieldset>
                            <legend>Turmas que este ônibus levará</legend>
                            <div class="gi-school-transport-card__checks">
                                @foreach ($item['disponiveis'] as $turma)
                                    <label>
                                        <input
                                            type="checkbox"
                                            value="{{ $turma->getKey() }}"
                                            wire:model.live="turmasSelecionadas.{{ $agendamentoId }}"
                                        >
                                        <span>{{ trim(($turma->serie?->nome ? $turma->serie->nome.' ' : '').$turma->nome) }}</span>
                                        <small>{{ (int) $turma->estudantes_transporte_count }} aluno(s)</small>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        <div class="gi-school-transport-card__selection-summary">
                            {{ number_format($item['total_selecionado'], 0, ',', '.') }} estudante(s) selecionado(s)
                        </div>

                        @if ($item['necessita_superlotacao'])
                            <label class="gi-school-transport-card__overload">
                                <input type="checkbox" wire:model.live="permitirSuperlotacao.{{ $agendamentoId }}">
                                <span>Confirmar superlotação deste veículo</span>
                            </label>
                        @endif

                        <button
                            type="button"
                            class="gi-school-transport-card__submit"
                            wire:click="alocar({{ $agendamentoId }})"
                            wire:loading.attr="disabled"
                            wire:target="alocar({{ $agendamentoId }})"
                        >
                            Atribuir veículo e motorista
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
        </article>
    @empty
        <div class="gi-event-detail__empty">Nenhuma escola solicitou transporte neste evento.</div>
    @endforelse
</div>
