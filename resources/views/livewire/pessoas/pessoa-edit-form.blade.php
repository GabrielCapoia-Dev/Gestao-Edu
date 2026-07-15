<div class="pe-own-editor" wire:key="pessoa-own-editor-{{ $pessoaId }}">
    <div class="pe-own-loading" wire:loading.flex wire:target="salvar" style="display: none">
        <div class="pe-own-loading-card">
            <span class="pe-own-spinner" aria-hidden="true"></span>
            <div>
                <strong>Salvando alterações...</strong>
                <small>A operação será concluída de forma segura.</small>
            </div>
        </div>
    </div>

    <form wire:submit="salvar" class="pe-own-form" novalidate x-data="{ aba: 'dados' }">
        @if ($errors->any())
            <div class="pe-own-alert" role="alert">
                <strong>Revise os dados antes de salvar.</strong>
                <ul>
                    @foreach ($errors->all() as $erro)
                        <li>{{ $erro }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <nav class="pe-own-main-tabs" aria-label="Seções do formulário">
            <button type="button" :class="{ 'is-active': aba === 'dados' }" @click="aba = 'dados'">
                Dados pessoais
            </button>
            <button type="button" :class="{ 'is-active': aba === 'vinculos' }" @click="aba = 'vinculos'">
                Matrículas e vínculos
            </button>
        </nav>

        <div class="pe-own-body">
            <div x-show="aba === 'dados'" x-cloak>
                <section class="pe-own-section">
                    <header class="pe-own-section-head">
                        <div class="pe-own-section-icon">👤</div>
                        <div>
                            <h3>Identidade da pessoa</h3>
                            <p>Dados básicos usados em todo o sistema.</p>
                        </div>
                    </header>

                    <div class="pe-own-grid pe-own-grid--2">
                        <label class="pe-own-field pe-own-span-2">
                            <span>Nome <b>*</b></span>
                            <input type="text" wire:model.blur="nome" maxlength="255" @disabled(! $gerenciaEstrutura)>
                            @if ($erro = $errors->first('nome')) <small class="is-error">{{ $erro }}</small> @endif
                        </label>

                        <label class="pe-own-field">
                            <span>CPF</span>
                            <input type="text" wire:model.blur="cpf" maxlength="14" placeholder="000.000.000-00" @disabled(! $podeEditarDados)>
                            @if ($erro = $errors->first('cpf')) <small class="is-error">{{ $erro }}</small> @endif
                        </label>

                        <label class="pe-own-field">
                            <span>E-mail <b>*</b></span>
                            <input type="email" wire:model.blur="email" maxlength="255" @disabled(! $podeEditarDados)>
                            @if ($erro = $errors->first('email')) <small class="is-error">{{ $erro }}</small> @endif
                        </label>

                        <label class="pe-own-field">
                            <span>Telefone</span>
                            <input type="text" wire:model.blur="telefone" maxlength="255" placeholder="(00) 00000-0000" @disabled(! $podeEditarDados)>
                            @if ($erro = $errors->first('telefone')) <small class="is-error">{{ $erro }}</small> @endif
                        </label>

                        <label class="pe-own-field">
                            <span>Status <b>*</b></span>
                            <select wire:model="status" @disabled(! $gerenciaEstrutura)>
                                @foreach ($statusOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @if ($erro = $errors->first('status')) <small class="is-error">{{ $erro }}</small> @endif
                        </label>
                    </div>
                </section>

                <section class="pe-own-section pe-own-section--compact">
                    <header class="pe-own-section-head">
                        <div class="pe-own-section-icon">💼</div>
                        <div>
                            <h3>Cargo</h3>
                            <p>Professor e Equipe Gestora usam a mesma identidade e as mesmas matrículas.</p>
                        </div>
                    </header>

                    <div class="pe-own-grid pe-own-grid--2">
                        <label class="pe-own-field pe-own-span-2">
                            <span>Função / cargo <b>*</b></span>
                            <select wire:model.live="cargo" @disabled(! $gerenciaEstrutura)>
                                <option value="{{ \App\Filament\Admin\Resources\Servidores\ServidorResource::CARGO_PROFESSOR }}">Professor</option>
                                @if ($gerenciaEstrutura || $cargo === \App\Filament\Admin\Resources\Servidores\ServidorResource::CARGO_EQUIPE_GESTORA)
                                    <option value="{{ \App\Filament\Admin\Resources\Servidores\ServidorResource::CARGO_EQUIPE_GESTORA }}">Equipe Gestora</option>
                                @endif
                            </select>
                            @if ($erro = $errors->first('cargo')) <small class="is-error">{{ $erro }}</small> @endif
                        </label>

                        @if ($gerenciaEstrutura)
                            <label class="pe-own-field pe-own-span-2">
                                <span>Observações</span>
                                <textarea wire:model.blur="observacoes" maxlength="2000" rows="3"></textarea>
                                @if ($erro = $errors->first('observacoes')) <small class="is-error">{{ $erro }}</small> @endif
                            </label>
                        @endif
                    </div>
                </section>
            </div>

            <div x-show="aba === 'vinculos'" x-cloak>
                <section class="pe-own-section">
                    <header class="pe-own-section-head pe-own-section-head--actions">
                        <div class="pe-own-section-title">
                            <div class="pe-own-section-icon">🎓</div>
                            <div>
                                <h3>Matrículas e lotações</h3>
                                <p>Cada aba representa uma matrícula atual. A exclusão só é persistida ao salvar.</p>
                            </div>
                        </div>

                        @if ($gerenciaEstrutura)
                            <button
                                type="button"
                                class="pe-own-button pe-own-button--secondary"
                                wire:click="adicionarMatricula"
                                wire:loading.attr="disabled"
                                @disabled(count($matriculas) >= \App\Models\PessoaMatricula::MAX_POR_PESSOA)
                            >+ Matrícula</button>
                        @endif
                    </header>

                    @if ($matriculas === [])
                        <div class="pe-own-empty">
                            Nenhuma lotação pedagógica desta unidade está disponível para edição.
                        </div>
                    @else
                        <div class="pe-own-tab-strip" role="tablist" aria-label="Matrículas">
                            @foreach ($matriculas as $matriculaKey => $matricula)
                                @php
                                    $turnoLabel = $turnosOptions[$matricula['turno'] ?? ''] ?? null;
                                    $matriculaLabel = filled($matricula['matricula'] ?? null) ? $matricula['matricula'] : 'Nova matrícula';
                                @endphp
                                <div class="pe-own-tab {{ $matriculaAtiva === $matriculaKey ? 'is-active' : '' }}" wire:key="matricula-tab-{{ $matriculaKey }}">
                                    <button type="button" wire:click="selecionarMatricula('{{ $matriculaKey }}')">
                                        {{ $matriculaLabel }}{{ $turnoLabel ? ' · '.$turnoLabel : '' }}
                                    </button>
                                    @if ($gerenciaEstrutura)
                                        <button
                                            type="button"
                                            class="pe-own-icon-button"
                                            title="Excluir matrícula"
                                            aria-label="Excluir matrícula {{ $matriculaLabel }}"
                                            wire:click="removerMatricula('{{ $matriculaKey }}')"
                                            wire:confirm="Remover esta matrícula e encerrar suas lotações ao salvar?"
                                            wire:loading.attr="disabled"
                                        >×</button>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        @if ($erro = $errors->first('matriculas'))
                            <p class="pe-own-error-block">{{ $erro }}</p>
                        @endif

                        @foreach ($matriculas as $matriculaKey => $matricula)
                            @continue($matriculaAtiva !== $matriculaKey)

                            <div class="pe-own-matricula" wire:key="matricula-content-{{ $matriculaKey }}">
                                <div class="pe-own-grid pe-own-grid--2">
                                    <label class="pe-own-field">
                                        <span>Nº da matrícula <b>*</b></span>
                                        <input type="text" wire:model.blur="matriculas.{{ $matriculaKey }}.matricula" maxlength="255" @disabled(! $gerenciaEstrutura)>
                                        @if ($erro = $errors->first("matriculas.$matriculaKey.matricula")) <small class="is-error">{{ $erro }}</small> @endif
                                    </label>

                                    <label class="pe-own-field">
                                        <span>Turno <b>*</b></span>
                                        <select
                                            wire:change="turnoAlterado('{{ $matriculaKey }}', $event.target.value)"
                                            @disabled(! $gerenciaEstrutura)
                                        >
                                            <option value="">Selecione</option>
                                            @foreach ($turnosOptions as $value => $label)
                                                <option value="{{ $value }}" @selected(($matricula['turno'] ?? '') === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @if ($erro = $errors->first("matriculas.$matriculaKey.turno")) <small class="is-error">{{ $erro }}</small> @endif
                                    </label>
                                </div>

                                @if ($cargo === \App\Filament\Admin\Resources\Servidores\ServidorResource::CARGO_PROFESSOR)
                                    <div class="pe-own-subhead">
                                        <div>
                                            <h4>Escolas / lotações do Professor</h4>
                                            <p>Turmas e componentes são organizados dentro de cada unidade.</p>
                                        </div>
                                        @if ($gerenciaEstrutura)
                                            <button type="button" class="pe-own-button pe-own-button--secondary" wire:click="adicionarLotacao('{{ $matriculaKey }}')">+ Escola</button>
                                        @endif
                                    </div>

                                    @php($lotacoes = $matricula['escolas'] ?? [])
                                    @if ($lotacoes === [])
                                        <div class="pe-own-empty pe-own-empty--small">Esta matrícula ainda não possui lotação escolar.</div>
                                    @else
                                        <div class="pe-own-tab-strip pe-own-tab-strip--schools" role="tablist" aria-label="Escolas da matrícula">
                                            @foreach ($lotacoes as $lotacaoKey => $lotacao)
                                                @php
                                                    $escolaId = (int) ($lotacao['id_escola'] ?? 0);
                                                    $escolaLabel = $escolasOptions[$escolaId] ?? ($escolaId ? 'Escola #'.$escolaId : 'Nova escola');
                                                @endphp
                                                <div class="pe-own-tab {{ ($lotacoesAtivas[$matriculaKey] ?? null) === $lotacaoKey ? 'is-active' : '' }}" wire:key="lotacao-tab-{{ $matriculaKey }}-{{ $lotacaoKey }}">
                                                    <button type="button" wire:click="selecionarLotacao('{{ $matriculaKey }}', '{{ $lotacaoKey }}')">{{ $escolaLabel }}</button>
                                                    @if ($gerenciaEstrutura)
                                                        <button
                                                            type="button"
                                                            class="pe-own-icon-button"
                                                            title="Excluir lotação"
                                                            aria-label="Excluir lotação {{ $escolaLabel }}"
                                                            wire:click="removerLotacao('{{ $matriculaKey }}', '{{ $lotacaoKey }}')"
                                                            wire:confirm="Remover esta lotação e seus vínculos ao salvar?"
                                                        >×</button>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>

                                        @foreach ($lotacoes as $lotacaoKey => $lotacao)
                                            @continue(($lotacoesAtivas[$matriculaKey] ?? null) !== $lotacaoKey)

                                            <div class="pe-own-lotacao" wire:key="lotacao-content-{{ $matriculaKey }}-{{ $lotacaoKey }}">
                                                <label class="pe-own-field">
                                                    <span>Escola / CMEI <b>*</b></span>
                                                    <select
                                                        wire:change="escolaAlterada('{{ $matriculaKey }}', '{{ $lotacaoKey }}', $event.target.value)"
                                                        @disabled(! $gerenciaEstrutura)
                                                    >
                                                        <option value="">Selecione uma escola</option>
                                                        @foreach ($escolasOptions as $value => $label)
                                                            <option value="{{ $value }}" @selected((int) ($lotacao['id_escola'] ?? 0) === (int) $value)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                    @if ($erro = $errors->first("matriculas.$matriculaKey.escolas.$lotacaoKey.id_escola")) <small class="is-error">{{ $erro }}</small> @endif
                                                </label>

                                                <div class="pe-own-subhead pe-own-subhead--assignments">
                                                    <div>
                                                        <h4>Turmas e componentes</h4>
                                                        <p>Vínculos pedagógicos desta escola.</p>
                                                    </div>
                                                    @if ($podeEditarTurmas)
                                                        <button type="button" class="pe-own-button pe-own-button--secondary" wire:click="adicionarVinculo('{{ $matriculaKey }}', '{{ $lotacaoKey }}')">+ Turma</button>
                                                    @endif
                                                </div>

                                                @php($vinculos = $lotacao['vinculos_turma_componente'] ?? [])
                                                <div class="pe-own-assignments">
                                                    @forelse ($vinculos as $vinculoKey => $vinculo)
                                                        <article class="pe-own-assignment" wire:key="vinculo-{{ $matriculaKey }}-{{ $lotacaoKey }}-{{ $vinculoKey }}">
                                                            <label class="pe-own-field">
                                                                <span>Turma <b>*</b></span>
                                                                <select
                                                                    wire:change="turmaAlterada('{{ $matriculaKey }}', '{{ $lotacaoKey }}', '{{ $vinculoKey }}', $event.target.value)"
                                                                    @disabled(! $podeEditarTurmas)
                                                                >
                                                                    <option value="">Selecione</option>
                                                                    @foreach (data_get($turmasOptions, "$matriculaKey.$lotacaoKey", []) as $value => $label)
                                                                        <option value="{{ $value }}" @selected((int) ($vinculo['turma_id'] ?? 0) === (int) $value)>{{ $label }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </label>

                                                            <label class="pe-own-field">
                                                                <span>Componente <b>*</b></span>
                                                                <select wire:model="matriculas.{{ $matriculaKey }}.escolas.{{ $lotacaoKey }}.vinculos_turma_componente.{{ $vinculoKey }}.componente_curricular_id" @disabled(! $podeEditarTurmas)>
                                                                    <option value="">Selecione</option>
                                                                    @foreach (data_get($componentesOptions, "$matriculaKey.$lotacaoKey.$vinculoKey", []) as $value => $label)
                                                                        <option value="{{ $value }}">{{ $label }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </label>

                                                            @if ($podeEditarTurmas)
                                                                <button
                                                                    type="button"
                                                                    class="pe-own-remove-assignment"
                                                                    wire:click="removerVinculo('{{ $matriculaKey }}', '{{ $lotacaoKey }}', '{{ $vinculoKey }}')"
                                                                    aria-label="Remover vínculo de turma e componente"
                                                                >×</button>
                                                            @endif
                                                        </article>
                                                    @empty
                                                        <div class="pe-own-empty pe-own-empty--small">Nenhuma turma ou componente vinculado.</div>
                                                    @endforelse
                                                </div>
                                            </div>
                                        @endforeach
                                    @endif
                                @endif
                            </div>
                        @endforeach
                    @endif
                </section>

                @if ($cargo === \App\Filament\Admin\Resources\Servidores\ServidorResource::CARGO_EQUIPE_GESTORA && $gerenciaEstrutura)
                    @php
                        $temDiretor = in_array(\App\Filament\Admin\Resources\Servidores\Schemas\ServidorEquipeGestoraForm::CARGO_DIRETOR, $cargosGestores, true);
                        $temCoordenador = in_array(\App\Filament\Admin\Resources\Servidores\Schemas\ServidorEquipeGestoraForm::CARGO_COORDENADOR, $cargosGestores, true);
                    @endphp
                    <section class="pe-own-section">
                        <header class="pe-own-section-head">
                            <div class="pe-own-section-icon">🏫</div>
                            <div>
                                <h3>Equipe Gestora</h3>
                                <p>Escola, cargos, portaria e turmas da coordenação.</p>
                            </div>
                        </header>

                        <div class="pe-own-grid pe-own-grid--2">
                            <label class="pe-own-field pe-own-span-2">
                                <span>Escola / CMEI <b>*</b></span>
                                <select wire:change="escolaGestoraAlterada($event.target.value)">
                                    <option value="">Selecione uma escola</option>
                                    @foreach ($escolasOptions as $value => $label)
                                        <option value="{{ $value }}" @selected((int) $idEscolaGestora === (int) $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @if ($erro = $errors->first('idEscolaGestora')) <small class="is-error">{{ $erro }}</small> @endif
                            </label>

                            <fieldset class="pe-own-field pe-own-span-2">
                                <span>Cargos gestores <b>*</b></span>
                                <div class="pe-own-checks pe-own-checks--3">
                                    <label><input type="checkbox" value="diretor" wire:model.live="cargosGestores"> Diretor</label>
                                    <label><input type="checkbox" value="coordenador" wire:model.live="cargosGestores"> Coordenador</label>
                                    <label><input type="checkbox" value="secretario" wire:model.live="cargosGestores"> Secretário</label>
                                </div>
                                @if ($erro = $errors->first('cargosGestores')) <small class="is-error">{{ $erro }}</small> @endif
                            </fieldset>

                            @if ($temDiretor || $temCoordenador)
                                <label class="pe-own-field pe-own-span-2">
                                    <span>Portaria <b>*</b></span>
                                    <input type="text" wire:model.blur="portaria" maxlength="255">
                                    <small>Diretor e Coordenador compartilham a mesma portaria alfanumérica.</small>
                                    @if ($erro = $errors->first('portaria')) <small class="is-error">{{ $erro }}</small> @endif
                                </label>
                            @endif

                            @if ($temCoordenador)
                                <fieldset class="pe-own-field pe-own-span-2">
                                    <span>Turmas da coordenação <b>*</b></span>
                                    <div class="pe-own-checks pe-own-checks--2 pe-own-checks--scroll">
                                        @forelse ($turmasGestaoOptions as $value => $label)
                                            <label><input type="checkbox" value="{{ $value }}" wire:model="turmaIds"> {{ $label }}</label>
                                        @empty
                                            <p>Nenhuma turma disponível nesta escola.</p>
                                        @endforelse
                                    </div>
                                    @if ($erro = $errors->first('turmaIds')) <small class="is-error">{{ $erro }}</small> @endif
                                </fieldset>
                            @endif
                        </div>
                    </section>
                @endif
            </div>
        </div>

        <footer class="pe-own-footer">
            <p>
                @if ($gerenciaEstrutura)
                    Alterações estruturais são aplicadas em uma única transação.
                @elseif ($podeEditarTurmas || $podeEditarDados)
                    Somente os dados autorizados da sua unidade serão atualizados.
                @else
                    Este formulário está disponível somente para consulta.
                @endif
            </p>
            <div>
                @if ($gerenciaEstrutura || $podeEditarDados || $podeEditarTurmas)
                    <button type="submit" class="pe-own-button pe-own-button--primary" wire:loading.attr="disabled" wire:target="salvar">
                        Salvar alterações
                    </button>
                @endif
                <button type="button" class="pe-own-button pe-own-button--cancel" wire:click="cancelar" wire:loading.attr="disabled">
                    Cancelar
                </button>
            </div>
        </footer>
    </form>

    <style>
        [x-cloak] { display: none !important; }
        .pe-own-editor { position: relative; margin: -1.5rem; color: #111827; }
        .pe-own-form { display: grid; min-height: min(720px, calc(100vh - 10rem)); background: #f8fafc; }
        .pe-own-main-tabs { display: flex; gap: .35rem; padding: 1rem 1.5rem 0; border-bottom: 1px solid #dbe3ef; background: #fff; }
        .pe-own-main-tabs button { border: 0; border-radius: .65rem .65rem 0 0; background: transparent; color: #687594; font-weight: 600; padding: .7rem .9rem; cursor: pointer; }
        .pe-own-main-tabs button.is-active { background: #f4f6fb; color: #173f91; }
        .pe-own-body { display: grid; align-content: start; gap: 1.25rem; padding: 1.5rem; overflow: auto; }
        .pe-own-section { overflow: hidden; border: 1px solid #d6e0ef; border-radius: 1rem; background: #fff; box-shadow: 0 12px 34px rgba(15, 23, 42, .045); }
        .pe-own-section + .pe-own-section { margin-top: 1.25rem; }
        .pe-own-section-head { display: flex; align-items: center; gap: .9rem; padding: 1rem 1.2rem; border-bottom: 1px solid #d6e0ef; }
        .pe-own-section-head--actions { justify-content: space-between; }
        .pe-own-section-title { display: flex; align-items: center; gap: .9rem; }
        .pe-own-section-head h3, .pe-own-section-head p, .pe-own-subhead h4, .pe-own-subhead p { margin: 0; }
        .pe-own-section-head h3 { font-size: 1rem; font-weight: 700; }
        .pe-own-section-head p, .pe-own-subhead p { margin-top: .2rem; color: #71809d; font-size: .8rem; }
        .pe-own-section-icon { display: grid; width: 2rem; height: 2rem; place-items: center; border-radius: .6rem; background: #eff5ff; font-size: 1rem; }
        .pe-own-grid { display: grid; gap: 1rem; padding: 1.15rem; }
        .pe-own-grid--2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .pe-own-span-2 { grid-column: span 2; }
        .pe-own-field { display: grid; min-width: 0; gap: .4rem; margin: 0; padding: 0; border: 0; }
        .pe-own-field > span { color: #111827; font-size: .875rem; font-weight: 600; }
        .pe-own-field b { color: #f43f5e; }
        .pe-own-field input[type='text'], .pe-own-field input[type='email'], .pe-own-field select, .pe-own-field textarea { width: 100%; min-height: 2.55rem; border: 1px solid #cfd8e6; border-radius: .65rem; background: #fff; color: #111827; padding: .6rem .75rem; outline: none; box-shadow: 0 1px 2px rgba(15, 23, 42, .04); }
        .pe-own-field textarea { min-height: 5rem; resize: vertical; }
        .pe-own-field input:focus, .pe-own-field select:focus, .pe-own-field textarea:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, .12); }
        .pe-own-field input:disabled, .pe-own-field select:disabled, .pe-own-field textarea:disabled { background: #f3f5f8; color: #667085; cursor: not-allowed; }
        .pe-own-field small { color: #71809d; font-size: .76rem; }
        .pe-own-field small.is-error, .pe-own-error-block { color: #e11d48; }
        .pe-own-alert { margin: 1rem 1.5rem 0; border: 1px solid #fecdd3; border-radius: .75rem; background: #fff1f2; color: #9f1239; padding: .8rem 1rem; }
        .pe-own-alert ul { margin: .4rem 0 0 1.1rem; font-size: .82rem; }
        .pe-own-tab-strip { display: grid; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); border-bottom: 1px solid #d6e0ef; background: #f8fafc; }
        .pe-own-tab { display: flex; min-width: 0; border-top: 3px solid transparent; border-right: 1px solid #d6e0ef; }
        .pe-own-tab.is-active { border-top-color: #1670dc; background: #fff; }
        .pe-own-tab > button:first-child { flex: 1; min-width: 0; overflow: hidden; border: 0; background: transparent; color: #111827; padding: .7rem .85rem; text-align: left; text-overflow: ellipsis; white-space: nowrap; cursor: pointer; }
        .pe-own-icon-button { width: 2.6rem; border: 0; background: transparent; color: #e11d48; font-size: 1.35rem; cursor: pointer; }
        .pe-own-error-block { margin: .65rem 1rem 0; font-size: .82rem; }
        .pe-own-matricula { padding: 1rem; }
        .pe-own-matricula > .pe-own-grid { padding: 0; }
        .pe-own-subhead { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-top: 1.2rem; padding: .9rem 0 .65rem; }
        .pe-own-subhead h4 { font-size: .9rem; }
        .pe-own-tab-strip--schools { border: 1px solid #d6e0ef; border-radius: .8rem .8rem 0 0; }
        .pe-own-lotacao { border: 1px solid #d6e0ef; border-top: 0; border-radius: 0 0 .8rem .8rem; padding: 1rem; }
        .pe-own-subhead--assignments { margin-top: .8rem; }
        .pe-own-assignments { display: grid; gap: .75rem; }
        .pe-own-assignment { position: relative; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; border: 1px solid #dbe3ef; border-radius: .8rem; background: #fbfcfe; padding: .9rem 3rem .9rem .9rem; box-shadow: 0 4px 12px rgba(15, 23, 42, .06); }
        .pe-own-remove-assignment { position: absolute; top: .65rem; right: .7rem; width: 1.8rem; height: 1.8rem; border: 0; border-radius: .45rem; background: #fff1f2; color: #e11d48; font-size: 1.2rem; cursor: pointer; }
        .pe-own-empty { margin: 1rem; border: 1px dashed #cbd5e1; border-radius: .8rem; background: #f8fafc; color: #64748b; padding: 1.2rem; text-align: center; }
        .pe-own-empty--small { margin: .5rem 0; padding: .8rem; font-size: .82rem; }
        .pe-own-button { display: inline-flex; min-height: 2.35rem; align-items: center; justify-content: center; border-radius: .65rem; padding: .5rem .85rem; font-size: .85rem; font-weight: 700; cursor: pointer; }
        .pe-own-button:disabled { opacity: .55; cursor: not-allowed; }
        .pe-own-button--primary { border: 1px solid #123b98; background: #123b98; color: #fff; box-shadow: 0 5px 14px rgba(18, 59, 152, .22); }
        .pe-own-button--secondary, .pe-own-button--cancel { border: 1px solid #d4dce8; background: #fff; color: #26334d; box-shadow: 0 2px 5px rgba(15, 23, 42, .08); }
        .pe-own-checks { display: grid; gap: .6rem; }
        .pe-own-checks--3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .pe-own-checks--2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .pe-own-checks label { display: flex; align-items: center; gap: .55rem; border: 1px solid #d6e0ef; border-radius: .65rem; padding: .65rem .75rem; font-size: .84rem; }
        .pe-own-checks--scroll { max-height: 15rem; overflow: auto; }
        .pe-own-footer { position: sticky; bottom: 0; z-index: 4; display: flex; align-items: center; justify-content: space-between; gap: 1rem; border-top: 1px solid #d6e0ef; background: rgba(255, 255, 255, .97); padding: .8rem 1.5rem; backdrop-filter: blur(8px); }
        .pe-own-footer p { margin: 0; color: #71809d; font-size: .76rem; }
        .pe-own-footer > div { display: flex; gap: .65rem; }
        .pe-own-loading { position: fixed; inset: 0; z-index: 100; align-items: center; justify-content: center; background: rgba(15, 23, 42, .45); }
        .pe-own-loading-card { display: flex; align-items: center; gap: .8rem; min-width: 18rem; border-radius: .9rem; background: #fff; padding: 1rem 1.2rem; box-shadow: 0 24px 70px rgba(15, 23, 42, .3); }
        .pe-own-loading-card div { display: grid; gap: .2rem; }
        .pe-own-loading-card small { color: #71809d; }
        .pe-own-spinner { width: 1.5rem; height: 1.5rem; border: 3px solid #dbe7f8; border-top-color: #2563eb; border-radius: 999px; animation: pe-own-spin .7s linear infinite; }
        @keyframes pe-own-spin { to { transform: rotate(360deg); } }
        @media (max-width: 760px) {
            .pe-own-editor { margin: -1rem; }
            .pe-own-grid--2, .pe-own-assignment, .pe-own-checks--2, .pe-own-checks--3 { grid-template-columns: 1fr; }
            .pe-own-span-2 { grid-column: span 1; }
            .pe-own-section-head--actions, .pe-own-footer { align-items: stretch; flex-direction: column; }
            .pe-own-footer > div { justify-content: flex-end; }
        }
    </style>
</div>
