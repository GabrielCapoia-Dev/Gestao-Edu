<div class="evento-custom-modal" wire:key="evento-custom-modal">
    @if ($podeCriar && $mostrarGatilho)
        <button type="button" class="evento-custom-modal__trigger" wire:click="abrir">
            <x-heroicon-o-plus />
            <span>Novo evento</span>
        </button>
    @endif

    @if ($podeCriar && $aberto)
            <div class="evento-custom-modal__backdrop" role="presentation">
                <section
                    class="evento-custom-modal__panel"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="evento-custom-modal-title"
                    wire:key="evento-custom-modal-panel"
                >
                    <header class="evento-custom-modal__header">
                        <div class="evento-custom-modal__header-icon" aria-hidden="true">
                            <x-heroicon-o-calendar-days />
                        </div>
                        <div>
                            <p>Agenda escolar</p>
                            <h2 id="evento-custom-modal-title">Planeje um novo evento</h2>
                            <span>Organize as informações, defina o local e escolha quem deverá acompanhar este evento.</span>
                        </div>
                        <div class="evento-custom-modal__tip">
                            <x-heroicon-o-sparkles aria-hidden="true" />
                            <span>Preencha somente o que fizer sentido para sua agenda.</span>
                        </div>
                        <button
                            type="button"
                            class="evento-custom-modal__close"
                            wire:click="fechar"
                            aria-label="Fechar formulário de evento"
                        >
                            <x-heroicon-o-x-mark />
                        </button>
                    </header>

                    <form wire:submit.prevent="salvar" class="evento-custom-modal__form" novalidate>
                        <div class="evento-custom-modal__body">
                            <nav class="evento-custom-modal__steps" aria-label="Etapas do evento">
                                @foreach ([1 => 'Dados do evento', 2 => 'Convidar participantes', 3 => 'Transporte escolar'] as $numero => $rotulo)
                                    <div @class(['evento-custom-modal__step', 'is-active' => $etapa === $numero, 'is-complete' => $etapa > $numero])>
                                        <span>{{ $etapa > $numero ? '✓' : sprintf('%02d', $numero) }}</span>
                                        <strong>{{ $rotulo }}</strong>
                                    </div>
                                @endforeach
                            </nav>

                            @if ($errors->any())
                                <div class="evento-custom-modal__errors" role="alert">
                                    @foreach ($errors->all() as $mensagem)
                                        <span>{{ $mensagem }}</span>
                                    @endforeach
                                </div>
                            @endif

                            @if ($etapa === 1)
                                <section class="evento-custom-modal__step-content" aria-labelledby="evento-etapa-1">
                                    <div class="evento-custom-modal__section-heading">
                                        <div>
                                            <p>Etapa 1</p>
                                            <h3 id="evento-etapa-1">Dados do evento</h3>
                                        </div>
                                        <span>Informe apenas os dados necessários.</span>
                                    </div>

                                    <div class="evento-custom-modal__field evento-custom-modal__field--full">
                                        <label for="evento-titulo">Título <em>*</em></label>
                                        <input id="evento-titulo" type="text" wire:model="data.titulo" maxlength="160" autocomplete="off">
                                        @error('data.titulo') <small>{{ $message }}</small> @enderror
                                    </div>

                                    <div class="evento-custom-modal__field evento-custom-modal__field--full">
                                        <label for="evento-descricao">Descrição</label>
                                        <textarea id="evento-descricao" wire:model="data.descricao" rows="3" maxlength="5000"></textarea>
                                        @error('data.descricao') <small>{{ $message }}</small> @enderror
                                    </div>

                                    <div class="evento-custom-modal__field evento-custom-modal__field--full">
                                        <label for="evento-local">Local do evento</label>
                                        <input id="evento-local" type="text" wire:model="data.local" maxlength="255" placeholder="Ex.: Centro de Formação Municipal" autocomplete="off">
                                        @error('data.local') <small>{{ $message }}</small> @enderror
                                    </div>

                                    <input type="hidden" wire:model="data.latitude">
                                    <input type="hidden" wire:model="data.longitude">
                                    <div class="evento-custom-modal__map evento-custom-modal__field--full">
                                        @include('filament.admin.pages.fields.evento-local-map')
                                    </div>

                                    <div class="evento-custom-modal__grid evento-custom-modal__grid--three">
                                        <div class="evento-custom-modal__field">
                                            <label for="evento-cor">Identificação visual <em>*</em></label>
                                            <select id="evento-cor" wire:model="data.cor">
                                                @foreach (App\Models\Enums\EventoCalendarioCor::cases() as $cor)
                                                    <option value="{{ $cor->value }}">{{ $cor->label() }}</option>
                                                @endforeach
                                            </select>
                                            @error('data.cor') <small>{{ $message }}</small> @enderror
                                        </div>
                                        <div class="evento-custom-modal__field">
                                            <label for="evento-link">Link de ação</label>
                                            <input id="evento-link" type="url" wire:model="data.link_acao" maxlength="2048" placeholder="https://exemplo.gov.br/..." autocomplete="url">
                                            @error('data.link_acao') <small>{{ $message }}</small> @enderror
                                        </div>
                                        <div class="evento-custom-modal__field">
                                            <label for="evento-texto-botao">Texto do botão</label>
                                            <input id="evento-texto-botao" type="text" wire:model="data.texto_botao" maxlength="80" placeholder="Ex.: Saiba mais">
                                            @error('data.texto_botao') <small>{{ $message }}</small> @enderror
                                        </div>
                                    </div>

                                    <div class="evento-custom-modal__grid evento-custom-modal__grid--four">
                                        <div class="evento-custom-modal__field">
                                            <label for="evento-data">Data do evento <em>*</em></label>
                                            <input id="evento-data" type="date" wire:model="data.data_evento">
                                            @error('data.data_evento') <small>{{ $message }}</small> @enderror
                                        </div>
                                        <div class="evento-custom-modal__field">
                                            <label for="evento-periodo">Período</label>
                                            <select id="evento-periodo" wire:model="data.periodo" wire:change="aplicarPeriodo">
                                                <option value="">Preencher automaticamente</option>
                                                <option value="manha">Manhã</option>
                                                <option value="tarde">Tarde</option>
                                                <option value="noite">Noite</option>
                                                <option value="dia_todo">Dia todo</option>
                                            </select>
                                            <span class="evento-custom-modal__helper">Sugere os horários.</span>
                                        </div>
                                        <div class="evento-custom-modal__field">
                                            <label for="evento-inicio">Início <em>*</em></label>
                                            <input id="evento-inicio" type="time" wire:model="data.hora_inicio">
                                            @error('data.hora_inicio') <small>{{ $message }}</small> @enderror
                                        </div>
                                        <div class="evento-custom-modal__field">
                                            <label for="evento-fim">Fim <em>*</em></label>
                                            <input id="evento-fim" type="time" wire:model="data.hora_fim">
                                            @error('data.hora_fim') <small>{{ $message }}</small> @enderror
                                        </div>
                                    </div>
                                </section>
                            @elseif ($etapa === 2)
                                <section class="evento-custom-modal__step-content" aria-labelledby="evento-etapa-2">
                                    <div class="evento-custom-modal__section-heading">
                                        <div>
                                            <p>Etapa 2</p>
                                            <h3 id="evento-etapa-2">Convidar participantes</h3>
                                        </div>
                                        <span>A consulta só acontece quando você clicar em Filtrar participantes.</span>
                                    </div>

                                    <div class="evento-custom-modal__grid evento-custom-modal__grid--two">
                                        <div class="evento-custom-modal__field">
                                            <label for="publico-escolas">Escolas</label>
                                            <select id="publico-escolas" wire:model="data.publico_escola_ids" multiple size="4">
                                                @foreach ($escolasOpcoes as $id => $nome)
                                                    <option value="{{ $id }}">{{ $nome }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="evento-custom-modal__field">
                                            <label for="publico-funcoes">Cargos</label>
                                            <select id="publico-funcoes" wire:model="data.publico_funcao_ids" multiple size="4">
                                                @foreach ($funcoesOpcoes as $id => $nome)
                                                    <option value="{{ $id }}">{{ $nome }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="evento-custom-modal__field">
                                            <label for="publico-turnos">Turnos</label>
                                            <select id="publico-turnos" wire:model="data.publico_turnos" multiple size="4">
                                                @foreach (['manha' => 'Manhã', 'tarde' => 'Tarde', 'noite' => 'Noite', 'integral' => 'Integral'] as $id => $nome)
                                                    <option value="{{ $id }}">{{ $nome }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="evento-custom-modal__field">
                                            <label for="publico-series">Séries</label>
                                            <select id="publico-series" wire:model="data.publico_serie_ids" multiple size="4">
                                                @foreach ($seriesOpcoes as $id => $nome)
                                                    <option value="{{ $id }}">{{ $nome }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="evento-custom-modal__field evento-custom-modal__field--full">
                                            <label for="publico-componentes">Componentes curriculares</label>
                                            <select id="publico-componentes" wire:model="data.publico_componente_ids" multiple size="4">
                                                @foreach ($componentesOpcoes as $id => $nome)
                                                    <option value="{{ $id }}">{{ $nome }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="evento-custom-modal__action-row">
                                        <button type="button" class="evento-custom-modal__button evento-custom-modal__button--primary" wire:click="aplicarFiltrosParticipantes">
                                            <x-heroicon-o-funnel />
                                            <span>Filtrar participantes</span>
                                        </button>
                                        <span>Use os filtros acima e atualize a lista somente quando necessário.</span>
                                    </div>

                                    @php($gruposParticipantes = collect($participantes)->groupBy('escola'))
                                    <div class="evento-custom-modal__result-card">
                                        <div class="evento-custom-modal__result-heading">
                                            <div>
                                                <p>Participantes selecionados</p>
                                                <h3>{{ count($participantes) }} pessoa(s) encontrada(s)</h3>
                                            </div>
                                            <span>{{ $participantesConsultados ? 'Consulta atualizada' : 'Aguardando filtros' }}</span>
                                        </div>
                                        @if (! $participantesConsultados)
                                            <p class="evento-custom-modal__empty">Aplique os filtros e clique em Filtrar participantes para listar as pessoas.</p>
                                        @elseif ($gruposParticipantes->isEmpty())
                                            <p class="evento-custom-modal__empty">Nenhuma pessoa encontrada para os filtros informados.</p>
                                        @else
                                            <div class="evento-custom-modal__groups">
                                                @foreach ($gruposParticipantes as $escola => $pessoas)
                                                    <details>
                                                        <summary><strong>{{ $escola }}</strong><span>{{ $pessoas->count() }} pessoa(s)⌄</span></summary>
                                                        <div class="evento-custom-modal__table-wrap">
                                                            <table>
                                                                <thead><tr><th>Nome</th><th>E-mail</th><th>Cargo</th><th></th></tr></thead>
                                                                <tbody>
                                                                    @foreach ($pessoas as $pessoa)
                                                                        <tr>
                                                                            <td>{{ $pessoa['nome'] }}</td>
                                                                            <td>{{ $pessoa['email'] }}</td>
                                                                            <td>{{ $pessoa['cargo'] }}</td>
                                                                            <td><button type="button" wire:click="removerParticipante({{ $pessoa['id'] }})" aria-label="Remover {{ $pessoa['nome'] }}">&times;</button></td>
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
                                </section>
                            @else
                                <section class="evento-custom-modal__step-content" aria-labelledby="evento-etapa-3">
                                    <div class="evento-custom-modal__section-heading">
                                        <div>
                                            <p>Etapa 3</p>
                                            <h3 id="evento-etapa-3">Transporte escolar</h3>
                                        </div>
                                        <span>Selecione os alunos somente quando o evento precisar de transporte.</span>
                                    </div>

                                    <div class="evento-custom-modal__question">
                                        <strong>Vai precisar de transporte para os alunos? <em>*</em></strong>
                                        <div>
                                            <button type="button" @class(['is-selected' => ($data['precisa_transporte_evento'] ?? 'nao') === 'sim']) wire:click="definirTransporte('sim')" @disabled($somenteTransporte)>Sim</button>
                                            <button type="button" @class(['is-selected' => ($data['precisa_transporte_evento'] ?? 'nao') === 'nao']) wire:click="definirTransporte('nao')" @disabled($somenteTransporte)>Não</button>
                                        </div>
                                    </div>

                                    @if (($data['precisa_transporte_evento'] ?? 'nao') === 'sim')
                                        <div class="evento-custom-modal__transport-filters">
                                            <div class="evento-custom-modal__grid evento-custom-modal__grid--four">
                                                <div class="evento-custom-modal__field">
                                                    <label for="transporte-escolas">Escolas</label>
                                                    <select id="transporte-escolas" wire:model="data.transporte_escola_ids" wire:change="invalidarAlunos" multiple size="4">
                                                        @foreach ($escolasOpcoes as $id => $nome)
                                                            <option value="{{ $id }}">{{ $nome }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="evento-custom-modal__field">
                                                    <label for="transporte-prefixos">Tipo de escola</label>
                                                    <select id="transporte-prefixos" wire:model="data.transporte_prefixos" wire:change="invalidarAlunos" multiple size="4">
                                                        <option value="CMEI">CMEI</option>
                                                        <option value="ESCOLA">Escola</option>
                                                    </select>
                                                </div>
                                                <div class="evento-custom-modal__field">
                                                    <label for="transporte-series">Séries</label>
                                                    <select id="transporte-series" wire:model="data.transporte_serie_ids" wire:change="invalidarAlunos" multiple size="4">
                                                        @foreach ($seriesOpcoes as $id => $nome)
                                                            <option value="{{ $id }}">{{ $nome }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="evento-custom-modal__field">
                                                    <label for="transporte-turnos">Turnos</label>
                                                    <select id="transporte-turnos" wire:model="data.transporte_turnos" wire:change="invalidarAlunos" multiple size="4">
                                                        @foreach (['manha' => 'Manhã', 'tarde' => 'Tarde', 'noite' => 'Noite', 'integral' => 'Integral'] as $id => $nome)
                                                            <option value="{{ $id }}">{{ $nome }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="evento-custom-modal__action-row">
                                                <button type="button" class="evento-custom-modal__button evento-custom-modal__button--primary" wire:click="buscarAlunos" wire:loading.attr="disabled" wire:target="buscarAlunos">
                                                    <x-heroicon-o-magnifying-glass />
                                                    <span wire:loading.remove wire:target="buscarAlunos">Buscar alunos</span>
                                                    <span wire:loading wire:target="buscarAlunos">Buscando…</span>
                                                </button>
                                                <span>Escolha ao menos um filtro. A consulta não roda enquanto você apenas preenche o formulário.</span>
                                            </div>

                                            @php
                                                $gruposAlunos = collect($alunos)->groupBy('escola')->map(fn ($escola) => $escola->groupBy('serie'));
                                                $excecoesAlunos = collect($data['transporte_excecoes_aluno_ids'] ?? [])->map(fn ($id): int => (int) $id)->all();
                                            @endphp
                                            <div class="evento-custom-modal__result-card">
                                                <div class="evento-custom-modal__result-heading">
                                                    <div>
                                                        <p>Alunos selecionados</p>
                                                        <h3>{{ count($alunos) }} aluno(s) estimado(s) para transporte</h3>
                                                    </div>
                                                    <span>{{ collect($alunos)->pluck('turma')->unique()->count() }} turma(s)</span>
                                                </div>
                                                @if (! $alunosConsultados)
                                                    <p class="evento-custom-modal__empty">Busque os alunos para conferir a seleção por escola, série e turma.</p>
                                                @elseif ($gruposAlunos->isEmpty())
                                                    <p class="evento-custom-modal__empty">Nenhum aluno encontrado para os filtros informados.</p>
                                                @else
                                                    <div class="evento-custom-modal__groups">
                                                        @foreach ($gruposAlunos as $escola => $porSerie)
                                                            <details>
                                                                <summary><strong>{{ $escola }}</strong><span>{{ $porSerie->flatten(1)->count() }} aluno(s)⌄</span></summary>
                                                                @foreach ($porSerie as $serie => $porTurma)
                                                                    <details class="evento-custom-modal__nested-group">
                                                                        <summary><strong>{{ $serie }}</strong><span>{{ $porTurma->count() }} aluno(s)⌄</span></summary>
                                                                        @foreach ($porTurma->groupBy('turma') as $turma => $pessoas)
                                                                            <details class="evento-custom-modal__nested-group">
                                                                                <summary><strong>{{ $turma }} · {{ $pessoas->first()['turno'] }}</strong><span>{{ $pessoas->count() }} aluno(s)⌄</span></summary>
                                                                                <div class="evento-custom-modal__table-wrap">
                                                                                    <table>
                                                                                        <thead><tr><th>Aluno</th><th></th></tr></thead>
                                                                                        <tbody>
                                                                                            @foreach ($pessoas as $aluno)
                                                                                                <tr>
                                                                                                    <td>{{ $aluno['nome'] }}</td>
                                                                                                    <td><button type="button" wire:click="removerAluno({{ $aluno['id'] }})" aria-label="Remover {{ $aluno['nome'] }}">&times;</button></td>
                                                                                                </tr>
                                                                                            @endforeach
                                                                                        </tbody>
                                                                                    </table>
                                                                                </div>
                                                                            </details>
                                                                        @endforeach
                                                                    </details>
                                                                @endforeach
                                                            </details>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </section>
                            @endif
                        </div>

                        <footer class="evento-custom-modal__footer">
                            <button type="button" class="evento-custom-modal__button evento-custom-modal__button--secondary" wire:click="fechar">Cancelar</button>
                            @if ($etapa > 1)
                                <button type="button" class="evento-custom-modal__button evento-custom-modal__button--secondary" wire:click="voltar">Voltar</button>
                            @endif
                            @if ($etapa < 3)
                                <button type="button" class="evento-custom-modal__button evento-custom-modal__button--primary" wire:click="avancar">Próximo</button>
                            @else
                                <button type="submit" class="evento-custom-modal__button evento-custom-modal__button--primary" wire:loading.attr="disabled" wire:target="salvar">
                                    <span wire:loading.remove wire:target="salvar">Criar evento</span>
                                    <span wire:loading wire:target="salvar">Salvando…</span>
                                </button>
                            @endif
                        </footer>
                    </form>
                </section>
            </div>
    @endif
</div>
