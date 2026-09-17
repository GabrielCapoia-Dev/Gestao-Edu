@php
    $user = $this->getUser();
    $isApproved = $user->canAuthenticate();
    $cpfPendente = $this->hasCpfPending();
    $pessoa = $this->getPessoa();
    $isProfessor = $this->hasProfessorProfile();
    $gestaoVinculos = $this->getGestaoVinculos();
    $isCoordinator = $this->hasCoordinatorProfile();
    $contextosProfessor = $isProfessor ? $this->getProfessorContexts() : collect();
    $contextosProfessorVinculados = $isProfessor ? $this->getProfessorLinkedContexts($contextosProfessor) : collect();
    $podeAnalisarSolicitacoes = $this->canReviewProfessorRequests();
    $solicitacoesParaAnalise = $podeAnalisarSolicitacoes && ! app(\App\Services\PessoaScopeService::class)->ehEquipeGestora($user)
        ? $this->getProfessorRequestsForReview()
        : collect();
@endphp

<div class="profile-page fi-fixed-positioning-context">
    <form wire:submit="save" class="profile-page__form">
        <header class="profile-hero">
            <div class="profile-hero__identity">
                <label class="profile-photo" title="Alterar foto de perfil">
                    @if ($this->profilePhoto || filled($user->avatar_url))
                        <span class="profile-photo__initials">{{ $this->getProfileInitials() }}</span>
                        <img src="{{ $this->getPhotoPreviewUrl() }}" alt="Foto de {{ $user->name }}" x-on:error="$el.remove()">
                    @elseif (! $user->hasRole('Admin'))
                        <span class="profile-photo__initials">{{ $this->getProfileInitials() }}</span>
                    @endif
                    <input wire:model="profilePhoto" type="file" accept="image/jpeg,image/png,image/webp">
                    <span class="profile-photo__edit" aria-hidden="true"><x-filament::icon icon="heroicon-o-camera" /></span>
                    @if ($cpfPendente)<span class="profile-photo__notice" title="CPF pendente">!</span>@endif
                </label>

                <div class="profile-hero__copy">
                    <span class="profile-eyebrow">Perfil do usuário</span>
                    <h1>{{ $user->name }}</h1>
                    <div class="profile-hero__badges">
                        <span class="profile-badge">{{ $this->getCargoLabel() }}</span>
                        <span class="profile-badge profile-badge--{{ $isApproved ? 'success' : 'warning' }}"><span></span>{{ $isApproved ? 'Conta ativa' : 'Conta inativa' }}</span>
                        <span class="profile-badge">{{ $this->getMatriculaLabel() }}</span>
                    </div>
                </div>
            </div>

            <div class="profile-hero__contacts">
                <div><x-filament::icon icon="heroicon-o-envelope" /><span><small>E-mail</small><strong>{{ $user->email }}</strong></span></div>
                <div><x-filament::icon icon="heroicon-o-identification" /><span><small>Identificador da conta</small><strong>{{ $user->codigo ?: 'Não informado' }}</strong></span></div>
            </div>
        </header>

        @error('profilePhoto')<p class="profile-field-error">{{ $message }}</p>@enderror

        <section class="profile-summary" aria-label="Resumo funcional">
            @foreach ([
                ['icon' => 'heroicon-o-briefcase', 'label' => 'Cargo', 'value' => $this->getCargoLabel()],
                ['icon' => 'heroicon-o-building-library', 'label' => 'Escola', 'value' => $this->getEscolaLabel()],
                ['icon' => 'heroicon-o-identification', 'label' => 'Matrícula', 'value' => $this->getMatriculaLabel()],
                ['icon' => 'heroicon-o-clock', 'label' => 'Turno', 'value' => $this->getTurnoLabel()],
            ] as $summary)
                <article>
                    <span><x-filament::icon :icon="$summary['icon']" /></span>
                    <div><small>{{ $summary['label'] }}</small><strong>{{ $summary['value'] }}</strong></div>
                </article>
            @endforeach
        </section>

        @if ($cpfPendente)
            <aside class="profile-completion" role="status">
                <span class="profile-completion__icon">!</span>
                <div><strong>Seu cadastro está quase completo</strong><p>Informe o CPF. Após o primeiro preenchimento, apenas uma equipe autorizada poderá alterá-lo.</p></div>
            </aside>
        @endif

        <div class="profile-layout">
            <section class="profile-card">
                <div class="profile-card__heading">
                    <span class="profile-card__icon"><x-filament::icon icon="heroicon-o-user-circle" /></span>
                    <div><h2>Dados da conta</h2><p>Informações que você pode atualizar.</p></div>
                </div>

                <div class="profile-fields">
                    <label class="profile-field">
                        <span>Nome</span>
                        <span class="profile-input"><x-filament::icon icon="heroicon-o-user" /><input wire:model="data.name" type="text" autocomplete="name" maxlength="255" required></span>
                        @error('data.name')<small class="profile-field-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="profile-field">
                        <span>E-mail</span>
                        <span class="profile-input profile-input--readonly"><x-filament::icon icon="heroicon-o-envelope" /><input value="{{ $user->email }}" type="email" disabled><em>Protegido</em></span>
                    </label>
                    @if ($cpfPendente)
                        <label class="profile-field profile-field--full">
                            <span>CPF</span>
                            <span class="profile-input"><x-filament::icon icon="heroicon-o-identification" /><input wire:model="data.cpf" x-mask="999.999.999-99" type="text" inputmode="numeric" maxlength="14" pattern="[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2}" autocomplete="off" placeholder="000.000.000-00"></span>
                            @error('data.cpf')<small class="profile-field-error">{{ $message }}</small>@enderror
                        </label>
                    @else
                        <div class="profile-readonly profile-field--full">
                            <span class="profile-readonly__icon"><x-filament::icon icon="heroicon-o-identification" /></span>
                            <span><small>CPF</small><strong>{{ \App\Models\Pessoa::formatarCpf($pessoa?->cpf) }}</strong></span><em>Somente leitura</em>
                        </div>
                    @endif
                    @if ($gestaoVinculos->isNotEmpty())
                        <div class="profile-manager-portarias profile-field--full">
                            <label class="profile-field">
                                <span>Portaria</span>
                                <span class="profile-input"><x-filament::icon icon="heroicon-o-document-text" /><input wire:model="data.portaria" type="text" maxlength="255" placeholder="Informe a portaria"></span>
                                <small>Aplicada aos vínculos ativos de Diretor e Coordenador.</small>
                                @error('data.portaria')<small class="profile-field-error">{{ $message }}</small>@enderror
                            </label>
                        </div>
                    @endif
                    @if ($isProfessor)
                        <div class="profile-functional-link profile-field--full">
                            <div class="profile-functional-link__field">
                                {{ $this->componentesFuncionaisForm }}
                            </div>
                            <button type="button" class="profile-inline-button" wire:click="confirmProfessorFunctionalComponents" wire:loading.attr="disabled">Confirmar componentes</button>
                        </div>
                    @endif
                </div>
            </section>

            <section class="profile-card">
                <div class="profile-card__heading">
                    <span class="profile-card__icon"><x-filament::icon icon="heroicon-o-briefcase" /></span>
                    <div><h2>Ficha funcional</h2><p>Dados definidos pela administração.</p></div>
                </div>

                <div class="profile-facts">
                    @foreach ([
                        ['icon' => 'heroicon-o-briefcase', 'label' => 'Cargo', 'value' => $this->getCargoLabel()],
                        ['icon' => 'heroicon-o-building-office', 'label' => 'Escola', 'value' => $this->getEscolaLabel()],
                        ['icon' => 'heroicon-o-identification', 'label' => 'Matrícula', 'value' => $this->getMatriculaLabel()],
                        ['icon' => 'heroicon-o-clock', 'label' => 'Turno', 'value' => $this->getTurnoLabel()],
                        ['icon' => 'heroicon-o-building-office-2', 'label' => 'Setor', 'value' => $this->getSetorLabel()],
                        ['icon' => 'heroicon-o-check-circle', 'label' => 'Status', 'value' => $this->getStatusLabel()],
                    ] as $fact)
                        <div class="profile-fact"><span><x-filament::icon :icon="$fact['icon']" /></span><div><small>{{ $fact['label'] }}</small><strong>{{ $fact['value'] }}</strong></div></div>
                    @endforeach
                </div>
            </section>
        </div>

        @if ($isProfessor)
            @if ($contextosProfessorVinculados->isNotEmpty())
                <section class="profile-card profile-card--assignments profile-card--linked">
                    <div class="profile-card__heading">
                        <span class="profile-card__icon"><x-filament::icon icon="heroicon-o-check-badge" /></span>
                        <div><h2>Minhas turmas e componentes</h2><p>Visualização dos vínculos de professor ativos.</p></div>
                    </div>

                    <div class="profile-linked-list">
                        @foreach ($contextosProfessorVinculados as $contexto)
                            <section class="profile-linked-context">
                                <h3><x-filament::icon icon="heroicon-o-identification" /> Matrícula {{ $contexto['matricula'] }}</h3>
                                @foreach ($contexto['escolas'] as $escola)
                                    <div class="profile-linked-school">
                                        <h4><x-filament::icon icon="heroicon-o-building-office" /> {{ $escola['nome'] }}</h4>
                                        @foreach ($escola['series'] as $serie)
                                            <details class="profile-linked-series">
                                                <summary><span>{{ $serie['nome'] }}</span><small>{{ $serie['turmas']->count() }} turma{{ $serie['turmas']->count() === 1 ? '' : 's' }}</small><x-filament::icon icon="heroicon-o-chevron-down" /></summary>
                                                <div>
                                                    @foreach ($serie['turmas'] as $turma)
                                                        @foreach ($turma['componentes'] as $opcao)
                                                            <article class="profile-linked-item">
                                                                <span class="profile-assignment-item__icon"><x-filament::icon icon="heroicon-o-book-open" /></span>
                                                                <strong>{{ $opcao['componente']->nome }}</strong>
                                                                <span>Turma {{ $turma['turma']->nome }} · {{ \App\Models\Professor::TURNOS[$turma['turma']->turno] ?? $turma['turma']->turno }}</span>
                                                            </article>
                                                        @endforeach
                                                    @endforeach
                                                </div>
                                            </details>
                                        @endforeach
                                    </div>
                                @endforeach
                            </section>
                        @endforeach
                    </div>
                </section>
            @endif

            <details class="profile-card profile-card--assignments profile-card--collapsible">
                <summary class="profile-card__heading profile-card__heading--collapsible">
                    <span class="profile-card__icon"><x-filament::icon icon="heroicon-o-academic-cap" /></span>
                    <div><h2>Solicitar acesso a turmas</h2><p>Explore as séries e solicite acesso a um componente, mesmo que já tenha professor.</p></div>
                    <x-filament::icon class="profile-card__chevron" icon="heroicon-o-chevron-down" />
                </summary>

                <div x-data="{ matricula: @js($contextosProfessor->first()['chave'] ?? ''), escola: @js($contextosProfessor->first()['escolas']->first()['id'] ?? 0) }">
                    @if ($contextosProfessor->count() > 1)
                        <div class="profile-assignment-tabs" role="tablist" aria-label="Matrículas">
                            @foreach ($contextosProfessor as $contexto)
                                <button type="button" role="tab" :aria-selected="matricula === @js($contexto['chave'])" :class="{ 'is-active': matricula === @js($contexto['chave']) }" x-on:click="matricula = @js($contexto['chave']); escola = {{ $contexto['escolas']->first()['id'] }}">Matrícula {{ $contexto['matricula'] }}</button>
                            @endforeach
                        </div>
                    @endif

                    @forelse ($contextosProfessor as $contexto)
                        <div x-show="matricula === @js($contexto['chave'])" x-cloak>
                            @if ($contexto['escolas']->count() > 1)
                                <div class="profile-assignment-tabs profile-assignment-tabs--schools" role="tablist" aria-label="Escolas da matrícula {{ $contexto['matricula'] }}">
                                    @foreach ($contexto['escolas'] as $escola)
                                        <button type="button" role="tab" :aria-selected="escola === {{ $escola['id'] }}" :class="{ 'is-active': escola === {{ $escola['id'] }} }" x-on:click="escola = {{ $escola['id'] }}">{{ $escola['nome'] }}</button>
                                    @endforeach
                                </div>
                            @endif

                            @foreach ($contexto['escolas'] as $escola)
                                <div x-show="escola === {{ $escola['id'] }}" x-cloak>
                                    <div class="profile-series-browser" x-data="{ serieSelecionada: 0 }">
                                        <div class="profile-assignment-tabs profile-assignment-tabs--series" role="tablist" aria-label="Séries">
                                            @foreach ($escola['series'] as $serieIndex => $serie)
                                                <button type="button" role="tab" :aria-selected="serieSelecionada === {{ $serieIndex }}" :class="{ 'is-active': serieSelecionada === {{ $serieIndex }} }" x-on:click="serieSelecionada = {{ $serieIndex }}"><x-filament::icon icon="heroicon-o-academic-cap" />{{ $serie['nome'] }}<small>{{ $serie['turmas']->count() }} turma{{ $serie['turmas']->count() === 1 ? '' : 's' }}</small></button>
                                            @endforeach
                                        </div>
                                        @forelse ($escola['series'] as $serieIndex => $serie)
                                            <div x-show="serieSelecionada === {{ $serieIndex }}" x-cloak>
                                                <div class="profile-series-group__classes">
                                                    @foreach ($serie['turmas'] as $turma)
                                                        <details class="profile-class-group profile-class-group--collapsible" x-data="{ aberto: false }" x-bind:open="aberto">
                                                            <summary class="profile-class-group__heading" x-on:click.prevent="aberto = !aberto">
                                                                <h4>Turma {{ $turma['turma']->nome }} <small>· {{ \App\Models\Professor::TURNOS[$turma['turma']->turno] ?? $turma['turma']->turno }}</small></h4><x-filament::icon class="profile-series-group__chevron" icon="heroicon-o-chevron-down" />
                                                            </summary>
                                                            <div class="profile-class-group__components">
                                                                @forelse ($turma['componentes'] as $opcao)
                                                                    <article class="profile-assignment-item" wire:key="professor-option-{{ $contexto['chave'] }}-{{ $escola['id'] }}-{{ $turma['turma']->id }}-{{ $opcao['componente']->id }}">
                                                                        <span class="profile-assignment-item__icon"><x-filament::icon icon="heroicon-o-book-open" /></span>
                                                                        <div>
                                                                            <strong>{{ $opcao['componente']->nome }}</strong>
                                                                            <span>{{ match ($opcao['estado']) { 'meu' => 'Você é o professor deste componente', 'ocupado' => 'Professor atual: '.($opcao['professor_atual'] ?: 'Não informado'), default => 'Sem professor vinculado' } }}</span>
                                                                            @if ($opcao['estado'] === 'ocupado')
                                                                                <small class="profile-assignment-item__notice"><x-filament::icon icon="heroicon-o-information-circle" /> Ao solicitar, você estará solicitando substituir o professor atual deste componente nesta turma. A alteração só será válida após aprovação.</small>
                                                                            @endif
                                                                        </div>
                                                                        @if ($opcao['estado'] === 'meu')
                                                                            <em class="profile-request-status profile-request-status--approved">Meu vínculo</em>
                                                                        @elseif ($opcao['pendente'])
                                                                            <em class="profile-request-status profile-request-status--pending">Aguardando aprovação</em>
                                                                        @else
                                                                            <button type="button" class="profile-inline-button" wire:click="requestProfessorComponent({{ $escola['professor_id'] }}, {{ $turma['turma']->id }}, {{ $opcao['componente']->id }})" @if ($opcao['estado'] === 'ocupado') wire:confirm="Você está solicitando substituir o professor atual deste componente nesta turma. Deseja continuar?" @endif wire:loading.attr="disabled">{{ $opcao['estado'] === 'ocupado' ? 'Solicitar substituição' : 'Solicitar vínculo' }}</button>
                                                                        @endif
                                                                    </article>
                                                                @empty
                                                                    <p class="profile-class-group__empty">Nenhum componente configurado para esta turma.</p>
                                                                @endforelse
                                                            </div>
                                                        </details>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @empty
                                            <div class="profile-assignment-empty">Não há turmas cadastradas nesta escola.</div>
                                        @endforelse
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @empty
                        <div class="profile-assignment-empty">Nenhum vínculo ativo de professor foi encontrado.</div>
                    @endforelse
                </div>
            </details>
        @endif

        @if ($isCoordinator)
            <section class="profile-card profile-card--coordination">
                <div class="profile-card__heading">
                    <span class="profile-card__icon"><x-filament::icon icon="heroicon-o-academic-cap" /></span>
                    <div><h2>Minhas turmas de coordenação</h2><p>Adicione ou remova sua coordenação nas turmas das suas escolas.</p></div>
                </div>
                @foreach ($gestaoVinculos->filter(fn ($vinculo): bool => (bool) $vinculo->funcaoAdministrativa?->coordenacao_pedagogica) as $vinculo)
                    <div class="profile-coordination-school">
                        <h3><x-filament::icon icon="heroicon-o-building-office" /> {{ $vinculo->escola?->nome ?: 'Escola não informada' }}</h3>
                        <div class="profile-coordination-grid">
                            @foreach ($this->getCoordinatorTurmas($vinculo->id)->sortBy(fn ($turma) => ($turma->serie?->nome ?? '').' '.$turma->nome) as $turma)
                                @php $coordenando = $vinculo->vinculosTurmaAtivos->contains('turma_id', $turma->id); @endphp
                                <article class="profile-coordination-item">
                                    <div><strong>{{ $turma->serie?->nome ?: 'Série não informada' }} · Turma {{ $turma->nome }}</strong><small>{{ \App\Models\Professor::TURNOS[$turma->turno] ?? $turma->turno }}</small></div>
                                    <button type="button" class="profile-inline-button {{ $coordenando ? 'profile-inline-button--muted' : '' }}" wire:click="toggleCoordinatorTurma({{ $turma->id }}, {{ $coordenando ? 'false' : 'true' }})" @if (! $coordenando) wire:confirm="Ao vincular, você substituirá o coordenador atual desta turma, se houver. Deseja continuar?" @endif wire:loading.attr="disabled">{{ $coordenando ? 'Remover' : 'Vincular' }}</button>
                                </article>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </section>
        @endif

        @if ($podeAnalisarSolicitacoes && $solicitacoesParaAnalise->isNotEmpty())
            <section class="profile-card profile-card--requests">
                <div class="profile-card__heading">
                    <span class="profile-card__icon"><x-filament::icon icon="heroicon-o-clipboard-document-check" /></span>
                    <div><h2>Solicitações de professores</h2><p>Aprove apenas vínculos válidos para a escola indicada.</p></div>
                    <span class="profile-card__counter">{{ $solicitacoesParaAnalise->count() }} pendente{{ $solicitacoesParaAnalise->count() === 1 ? '' : 's' }}</span>
                </div>

                <div class="profile-review-list">
                    @foreach ($solicitacoesParaAnalise as $solicitacao)
                        <article wire:key="review-request-{{ $solicitacao->id }}">
                            <span class="profile-assignment-item__icon"><x-filament::icon icon="heroicon-o-user-plus" /></span>
                            <div>
                                <strong>{{ $solicitacao->professor?->nomeCanonico() }}</strong>
                                <span>{{ $solicitacao->vinculo?->componente?->nome }} · {{ $solicitacao->vinculo?->turma?->serie?->nome }} · Turma {{ $solicitacao->vinculo?->turma?->nome }}</span>
                                <small>{{ $solicitacao->vinculo?->turma?->escola?->nome }}@if ($solicitacao->vinculo?->professor_id && $solicitacao->vinculo->professor_id !== $solicitacao->professor_id) · Substitui {{ $solicitacao->vinculo->professor?->nomeCanonico() }}@endif</small>
                            </div>
                            <div class="profile-review-actions">
                                <button type="button" class="profile-inline-button profile-inline-button--muted" wire:click="rejectProfessorLink({{ $solicitacao->id }})" wire:loading.attr="disabled">Recusar</button>
                                <button type="button" class="profile-inline-button" wire:click="approveProfessorLink({{ $solicitacao->id }})" @if ($solicitacao->vinculo?->professor_id && $solicitacao->vinculo->professor_id !== $solicitacao->professor_id) wire:confirm="Aprovar a substituição? O professor atual perderá este vínculo." @endif wire:loading.attr="disabled">Aprovar</button>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="profile-card">
            <div class="profile-card__heading">
                <span class="profile-card__icon"><x-filament::icon icon="heroicon-o-shield-check" /></span>
                <div><h2>Segurança da conta</h2><p>Preencha apenas se desejar trocar a senha.</p></div>
            </div>
            <div class="profile-fields profile-fields--security">
                <label class="profile-field"><span>Nova senha</span><span class="profile-input"><x-filament::icon icon="heroicon-o-lock-closed" /><input wire:model.live.debounce.500ms="data.password" type="password" autocomplete="new-password"></span>@error('data.password')<small class="profile-field-error">{{ $message }}</small>@enderror</label>
                <label class="profile-field"><span>Confirmar nova senha</span><span class="profile-input"><x-filament::icon icon="heroicon-o-lock-closed" /><input wire:model="data.passwordConfirmation" type="password" autocomplete="new-password"></span></label>
                <label class="profile-field"><span>Senha atual</span><span class="profile-input"><x-filament::icon icon="heroicon-o-key" /><input wire:model="data.currentPassword" type="password" autocomplete="current-password"></span>@error('data.currentPassword')<small class="profile-field-error">{{ $message }}</small>@enderror</label>
            </div>
        </section>

        <footer class="profile-actions">
            <a href="{{ filament()->getUrl() }}" class="profile-button profile-button--secondary"><x-filament::icon icon="heroicon-o-arrow-left" /> Voltar ao início</a>
            <button type="submit" class="profile-button profile-button--primary" wire:loading.attr="disabled" wire:target="save,profilePhoto"><x-filament::icon icon="heroicon-o-check" /><span wire:loading.remove wire:target="save">Salvar alterações</span><span wire:loading wire:target="save">Salvando...</span></button>
        </footer>
    </form>

    <x-filament-actions::modals />
</div>
