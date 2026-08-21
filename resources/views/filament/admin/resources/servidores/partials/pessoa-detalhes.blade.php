@php
    $dadosPessoais = [
        ['label' => 'CPF', 'value' => $detalhes['cpf'], 'icon' => 'heroicon-o-identification'],
        ['label' => 'E-mail', 'value' => $detalhes['email'], 'icon' => 'heroicon-o-envelope'],
        ['label' => 'Telefone', 'value' => $detalhes['telefone'], 'icon' => 'heroicon-o-phone'],
        ['label' => 'Situação cadastral', 'value' => $detalhes['status']['label'], 'icon' => 'heroicon-o-check-badge'],
        ['label' => 'Cadastrada em', 'value' => $detalhes['criado_em'], 'icon' => 'heroicon-o-calendar-days'],
        ['label' => 'Última atualização', 'value' => $detalhes['atualizado_em'], 'icon' => 'heroicon-o-clock'],
    ];

    $dadosFuncionais = collect([
        ['label' => 'Cargo', 'value' => $detalhes['cargo'], 'icon' => 'heroicon-o-briefcase', 'visible' => true],
        ['label' => 'Carga horária', 'value' => $detalhes['carga_horaria'], 'icon' => 'heroicon-o-clock', 'visible' => true],
        ['label' => 'Jornada', 'value' => $detalhes['jornada']['label'], 'icon' => 'heroicon-o-arrows-right-left', 'visible' => true],
        ['label' => 'Lotação', 'value' => $detalhes['lotacao'], 'icon' => 'heroicon-o-map-pin', 'visible' => $detalhes['lotacao_visivel']],
        ['label' => 'Escola da lotação', 'value' => $detalhes['lotacao_escola'], 'icon' => 'heroicon-o-building-library', 'visible' => $detalhes['lotacao_visivel']],
        ['label' => 'Setor', 'value' => $detalhes['setor'], 'icon' => 'heroicon-o-building-office-2', 'visible' => filled($detalhes['setor'])],
        ['label' => 'Portaria', 'value' => $detalhes['portaria'], 'icon' => 'heroicon-o-document-text', 'visible' => filled($detalhes['portaria'])],
    ])->where('visible', true)->values()->all();

    $temAcesso = is_array($detalhes['acesso']);
    $temPedagogico = is_array($detalhes['pedagogico']);
@endphp

<div class="pessoa-custom-view" x-data="{ tab: 'resumo' }">
    <section class="pessoa-custom-view__hero">
        <div class="pessoa-custom-view__identity">
            <div class="pessoa-custom-view__avatar" aria-hidden="true">
                {{ $detalhes['iniciais'] }}
            </div>

            <div class="pessoa-custom-view__identity-copy">
                <p class="pessoa-custom-view__eyebrow">Ficha funcional</p>
                <h2>{{ $detalhes['nome'] }}</h2>

                <div class="pessoa-custom-view__badges">
                    <span class="pessoa-custom-view__badge pessoa-custom-view__badge--primary">
                        {{ $detalhes['cargo'] }}
                    </span>
                    <span class="pessoa-custom-view__badge pessoa-custom-view__badge--{{ $detalhes['status']['tone'] }}">
                        <span class="pessoa-custom-view__status-dot"></span>
                        {{ $detalhes['status']['label'] }}
                    </span>
                    <span class="pessoa-custom-view__badge pessoa-custom-view__badge--neutral">
                        {{ $detalhes['carga_horaria'] }}
                    </span>
                </div>
            </div>
        </div>

        <div class="pessoa-custom-view__contacts">
            <div>
                <x-filament::icon icon="heroicon-o-envelope" />
                <span>
                    <small>E-mail</small>
                    <strong>{{ $detalhes['email'] }}</strong>
                </span>
            </div>
            <div>
                <x-filament::icon icon="heroicon-o-phone" />
                <span>
                    <small>Telefone</small>
                    <strong>{{ $detalhes['telefone'] }}</strong>
                </span>
            </div>
        </div>
    </section>

    <section class="pessoa-custom-view__summary" aria-label="Resumo funcional">
        <article>
            <span class="pessoa-custom-view__summary-icon">
                <x-filament::icon icon="heroicon-o-clock" />
            </span>
            <div>
                <small>Carga horária</small>
                <strong>{{ $detalhes['carga_horaria'] }}</strong>
            </div>
        </article>

        <article>
            <span class="pessoa-custom-view__summary-icon">
                <x-filament::icon icon="heroicon-o-arrows-right-left" />
            </span>
            <div>
                <small>Jornada</small>
                <strong>{{ $detalhes['jornada']['label'] }}</strong>
            </div>
        </article>

        <article>
            <span class="pessoa-custom-view__summary-icon">
                <x-filament::icon icon="heroicon-o-identification" />
            </span>
            <div>
                <small>Matrículas</small>
                <strong>{{ count($detalhes['matriculas']) }}</strong>
            </div>
        </article>

        <article>
            <span class="pessoa-custom-view__summary-icon">
                <x-filament::icon icon="heroicon-o-building-library" />
            </span>
            <div>
                <small>Escolas vinculadas</small>
                <strong>{{ count($detalhes['escolas']) }}</strong>
            </div>
        </article>
    </section>

    <nav class="pessoa-custom-view__tabs" role="tablist" aria-label="Seções da ficha">
        <button
            type="button"
            role="tab"
            x-on:click="tab = 'resumo'"
            x-bind:aria-selected="tab === 'resumo'"
            x-bind:class="{ 'is-active': tab === 'resumo' }"
        >
            <x-filament::icon icon="heroicon-o-squares-2x2" />
            Visão geral
        </button>

        <button
            type="button"
            role="tab"
            x-on:click="tab = 'vinculos'"
            x-bind:aria-selected="tab === 'vinculos'"
            x-bind:class="{ 'is-active': tab === 'vinculos' }"
        >
            <x-filament::icon icon="heroicon-o-identification" />
            Vínculos e lotações
        </button>

        @if ($temAcesso)
            <button
                type="button"
                role="tab"
                x-on:click="tab = 'acesso'"
                x-bind:aria-selected="tab === 'acesso'"
                x-bind:class="{ 'is-active': tab === 'acesso' }"
            >
                <x-filament::icon icon="heroicon-o-shield-check" />
                Acesso ao sistema
            </button>
        @endif

        @if ($temPedagogico)
            <button
                type="button"
                role="tab"
                x-on:click="tab = 'pedagogico'"
                x-bind:aria-selected="tab === 'pedagogico'"
                x-bind:class="{ 'is-active': tab === 'pedagogico' }"
            >
                <x-filament::icon icon="heroicon-o-academic-cap" />
                {{ $detalhes['pedagogico']['label'] }}
                <span class="pessoa-custom-view__tab-count">
                    {{ $detalhes['pedagogico']['total_turmas'] }}
                </span>
            </button>
        @endif
    </nav>

    <div class="pessoa-custom-view__content">
        <section
            class="pessoa-custom-view__panel"
            role="tabpanel"
            x-show="tab === 'resumo'"
            x-transition.opacity.duration.150ms
        >
            <div class="pessoa-custom-view__card-grid">
                <article class="pessoa-custom-view__card">
                    <header>
                        <span class="pessoa-custom-view__card-icon">
                            <x-filament::icon icon="heroicon-o-user" />
                        </span>
                        <div>
                            <h3>Dados pessoais</h3>
                            <p>Identificação e canais de contato.</p>
                        </div>
                    </header>

                    <dl class="pessoa-custom-view__details-grid">
                        @foreach ($dadosPessoais as $campo)
                            <div>
                                <dt>
                                    <x-filament::icon :icon="$campo['icon']" />
                                    {{ $campo['label'] }}
                                </dt>
                                <dd>{{ $campo['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </article>

                <article class="pessoa-custom-view__card">
                    <header>
                        <span class="pessoa-custom-view__card-icon">
                            <x-filament::icon icon="heroicon-o-briefcase" />
                        </span>
                        <div>
                            <h3>Dados funcionais</h3>
                            <p>Cargo, jornada e local de atuação.</p>
                        </div>
                    </header>

                    <dl class="pessoa-custom-view__details-grid">
                        @foreach ($dadosFuncionais as $campo)
                            <div>
                                <dt>
                                    <x-filament::icon :icon="$campo['icon']" />
                                    {{ $campo['label'] }}
                                </dt>
                                <dd>{{ $campo['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </article>

                <article class="pessoa-custom-view__card pessoa-custom-view__card--full">
                    <header>
                        <span class="pessoa-custom-view__card-icon">
                            <x-filament::icon icon="heroicon-o-chat-bubble-left-ellipsis" />
                        </span>
                        <div>
                            <h3>Observações</h3>
                            <p>Informações complementares do cadastro.</p>
                        </div>
                    </header>

                    <p class="pessoa-custom-view__notes">{{ $detalhes['observacoes'] }}</p>
                </article>
            </div>
        </section>

        <section
            class="pessoa-custom-view__panel"
            role="tabpanel"
            x-show="tab === 'vinculos'"
            x-cloak
            x-transition.opacity.duration.150ms
        >
            <div class="pessoa-custom-view__card-grid">
                <article class="pessoa-custom-view__card">
                    <header>
                        <span class="pessoa-custom-view__card-icon">
                            <x-filament::icon icon="heroicon-o-identification" />
                        </span>
                        <div>
                            <h3>Matrículas</h3>
                            <p>Números funcionais e respectivos turnos.</p>
                        </div>
                    </header>

                    <div class="pessoa-custom-view__item-list">
                        @forelse ($detalhes['matriculas'] as $matricula)
                            <div class="pessoa-custom-view__list-item">
                                <span class="pessoa-custom-view__list-icon">
                                    <x-filament::icon icon="heroicon-o-identification" />
                                </span>
                                <div>
                                    <small>Número da matrícula</small>
                                    <strong>{{ $matricula['numero'] }}</strong>
                                </div>
                                <span class="pessoa-custom-view__list-tag">{{ $matricula['turno'] }}</span>
                            </div>
                        @empty
                            <div class="pessoa-custom-view__empty">
                                <x-filament::icon icon="heroicon-o-information-circle" />
                                <strong>Nenhuma matrícula cadastrada</strong>
                                <span>Não há vínculo funcional por matrícula nesta pessoa.</span>
                            </div>
                        @endforelse
                    </div>
                </article>

                <article class="pessoa-custom-view__card">
                    <header>
                        <span class="pessoa-custom-view__card-icon">
                            <x-filament::icon icon="heroicon-o-building-library" />
                        </span>
                        <div>
                            <h3>Escolas vinculadas</h3>
                            <p>Unidades visíveis dentro do seu escopo.</p>
                        </div>
                    </header>

                    <div class="pessoa-custom-view__school-list">
                        @forelse ($detalhes['escolas'] as $escola)
                            <div>
                                <x-filament::icon icon="heroicon-o-building-library" />
                                <span>{{ $escola }}</span>
                            </div>
                        @empty
                            <div class="pessoa-custom-view__empty">
                                <x-filament::icon icon="heroicon-o-information-circle" />
                                <strong>Nenhuma escola vinculada</strong>
                                <span>Não existem unidades escolares disponíveis para este cadastro.</span>
                            </div>
                        @endforelse
                    </div>
                </article>

                @if ($detalhes['lotacao_visivel'])
                    <article class="pessoa-custom-view__card pessoa-custom-view__card--full pessoa-custom-view__placement">
                        <span class="pessoa-custom-view__placement-icon">
                            <x-filament::icon icon="heroicon-o-map-pin" />
                        </span>
                        <div>
                            <small>Lotação principal</small>
                            <strong>{{ $detalhes['lotacao'] }}</strong>
                            <span>{{ $detalhes['lotacao_escola'] }}</span>
                        </div>
                    </article>
                @endif
            </div>
        </section>

        @if ($temAcesso)
            <section
                class="pessoa-custom-view__panel"
                role="tabpanel"
                x-show="tab === 'acesso'"
                x-cloak
                x-transition.opacity.duration.150ms
            >
                <article class="pessoa-custom-view__card">
                    <header>
                        <span class="pessoa-custom-view__card-icon">
                            <x-filament::icon icon="heroicon-o-shield-check" />
                        </span>
                        <div>
                            <h3>Acesso ao sistema</h3>
                            <p>Conta, aprovação, perfis e últimas atividades.</p>
                        </div>
                        <span class="pessoa-custom-view__badge pessoa-custom-view__badge--{{ $detalhes['acesso']['tone'] }}">
                            <span class="pessoa-custom-view__status-dot"></span>
                            {{ $detalhes['acesso']['status'] }}
                        </span>
                    </header>

                    @if ($detalhes['acesso']['possui_conta'])
                        <dl class="pessoa-custom-view__details-grid pessoa-custom-view__details-grid--access">
                            <div>
                                <dt>Nome da conta</dt>
                                <dd>{{ $detalhes['acesso']['nome'] }}</dd>
                            </div>
                            <div>
                                <dt>E-mail de acesso</dt>
                                <dd>{{ $detalhes['acesso']['email'] }}</dd>
                            </div>
                            <div>
                                <dt>E-mail aprovado</dt>
                                <dd>{{ $detalhes['acesso']['email_aprovado'] }}</dd>
                            </div>
                            <div>
                                <dt>E-mail verificado</dt>
                                <dd>{{ $detalhes['acesso']['email_verificado'] }}</dd>
                            </div>
                            <div>
                                <dt>Troca de senha</dt>
                                <dd>{{ $detalhes['acesso']['troca_senha'] }}</dd>
                            </div>
                            <div>
                                <dt>Escola da conta</dt>
                                <dd>{{ $detalhes['acesso']['escola'] }}</dd>
                            </div>
                            <div>
                                <dt>Último login</dt>
                                <dd>{{ $detalhes['acesso']['ultimo_login'] }}</dd>
                            </div>
                            <div>
                                <dt>Última atividade</dt>
                                <dd>{{ $detalhes['acesso']['ultima_atividade'] }}</dd>
                            </div>
                        </dl>

                        <div class="pessoa-custom-view__roles">
                            <small>Níveis de acesso</small>
                            <div>
                                @forelse ($detalhes['acesso']['perfis'] as $perfil)
                                    <span>{{ $perfil }}</span>
                                @empty
                                    <span class="is-empty">Nenhum nível de acesso atribuído</span>
                                @endforelse
                            </div>
                        </div>
                    @else
                        <div class="pessoa-custom-view__empty pessoa-custom-view__empty--large">
                            <x-filament::icon icon="heroicon-o-user-minus" />
                            <strong>Esta pessoa ainda não possui uma conta vinculada</strong>
                            <span>O cadastro funcional existe, mas não há credencial de acesso ao sistema.</span>
                        </div>
                    @endif
                </article>
            </section>
        @endif

        @if ($temPedagogico)
            <section
                class="pessoa-custom-view__panel"
                role="tabpanel"
                x-show="tab === 'pedagogico'"
                x-cloak
                x-transition.opacity.duration.150ms
            >
                <div class="pessoa-custom-view__pedagogical-heading">
                    <span>
                        <x-filament::icon icon="heroicon-o-academic-cap" />
                    </span>
                    <div>
                        <h3>{{ $detalhes['pedagogico']['label'] }}</h3>
                        <p>{{ $detalhes['pedagogico']['descricao'] }}</p>
                    </div>
                </div>

                @if ($detalhes['pedagogico']['tipo'] === 'professor')
                    @include('filament.admin.resources.servidores.partials.turmas-componentes-groups', [
                        'grupos' => $detalhes['pedagogico']['grupos'],
                    ])
                @else
                    @include('filament.admin.resources.servidores.partials.turmas-coordenacao-groups', [
                        'grupos' => $detalhes['pedagogico']['grupos'],
                    ])
                @endif
            </section>
        @endif
    </div>
</div>
