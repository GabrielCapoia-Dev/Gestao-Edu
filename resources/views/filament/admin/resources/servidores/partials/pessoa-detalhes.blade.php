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

    $temPedagogico = is_array($detalhes['pedagogico']);
    $saldoTitulo = static function ($movimento): string {
        return match ($movimento->tipo) {
            \App\Models\SaldoEleitoral::TIPO_ADICAO => $movimento->status === 'aprovado' ? 'Saldo adicionado' : 'Adição solicitada',
            \App\Models\SaldoEleitoral::TIPO_ESTORNO => $movimento->status === 'aprovado' ? 'Saldo estornado' : 'Solicitação de estorno',
            default => $movimento->status === 'aprovado' ? 'Saldo utilizado' : 'Uso solicitado',
        };
    };
    $saldoTom = static function ($movimento): string {
        if ($movimento->status === 'rejeitado') {
            return 'rejeitado';
        }

        if ($movimento->tipo === \App\Models\SaldoEleitoral::TIPO_ESTORNO) {
            return $movimento->status === 'aprovado' ? 'estornado' : 'pendente';
        }

        return match ([$movimento->tipo, $movimento->status]) {
            [\App\Models\SaldoEleitoral::TIPO_ADICAO, 'aprovado'] => 'aprovado',
            [\App\Models\SaldoEleitoral::TIPO_USO, 'aprovado'] => 'usado',
            default => 'pendente',
        };
    };
    $formatarValorHistorico = function (mixed $valor) use (&$formatarValorHistorico): string {
        if ($valor === null || $valor === '') {
            return '—';
        }

        if (is_bool($valor)) {
            return $valor ? 'Sim' : 'Não';
        }

        if (! is_array($valor)) {
            $texto = (string) $valor;

            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $texto)
                ? \Illuminate\Support\Carbon::parse($texto)->format('d/m/Y')
                : $texto;
        }

        if (array_is_list($valor)) {
            return $valor === []
                ? 'Nenhum vínculo'
                : collect($valor)->values()->map(fn (mixed $item, int $indice): string =>
                    'Vínculo '.($indice + 1).': '.$formatarValorHistorico($item)
                )->implode(' · ');
        }

        $rotulos = [
            'cargo' => 'Cargo', 'escola' => 'Escola', 'setor' => 'Setor',
            'lotacao' => 'Lotação', 'matricula' => 'Matrícula', 'turno' => 'Turno',
            'jornada' => 'Jornada', 'serie' => 'Série', 'turma' => 'Turma',
            'componente' => 'Componente', 'status' => 'Situação',
            'inicio' => 'Início', 'fim' => 'Fim', 'arquivada' => 'Arquivada',
            'ativo' => 'Vínculo ativo', 'nome' => 'Nome',
        ];

        return collect($valor)
            ->reject(fn (mixed $item, string|int $chave): bool => in_array($chave, ['id', 'codigo', 'codigo_escola'], true) || $item === null || $item === '')
            ->map(fn (mixed $item, string|int $chave): string => ($rotulos[$chave] ?? ucfirst((string) $chave))
                .': '.$formatarValorHistorico($item))
            ->implode(' · ');
    };
@endphp

<div class="pessoa-custom-view" x-data="{ tab: 'resumo' }">
    <div class="pessoa-custom-view__exports">
        <a href="{{ $exportarHistoricoUrl }}">Exportar histórico</a>
        <a href="{{ $exportarFichaUrl }}">Exportar ficha do servidor</a>
    </div>
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

        <button
            type="button"
            role="tab"
            x-on:click="tab = 'historico'"
            x-bind:aria-selected="tab === 'historico'"
            x-bind:class="{ 'is-active': tab === 'historico' }"
        >
            <x-filament::icon icon="heroicon-o-clock" />
            Histórico
            <span class="pessoa-custom-view__tab-count">{{ $movimentacoes->count() }}</span>
        </button>

        <button
            type="button"
            role="tab"
            x-on:click="tab = 'saldo-eleitoral'"
            x-bind:aria-selected="tab === 'saldo-eleitoral'"
            x-bind:class="{ 'is-active': tab === 'saldo-eleitoral' }"
        >
            <x-filament::icon icon="heroicon-o-scale" />
            Saldo Eleitoral
            <span class="pessoa-custom-view__tab-count">{{ $saldoEleitoral->count() }}</span>
        </button>
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

        <section
            class="pessoa-custom-view__panel"
            role="tabpanel"
            x-show="tab === 'historico'"
            x-cloak
            x-transition.opacity.duration.150ms
        >
            <div class="pessoa-custom-view__card">
                <header>
                    <span class="pessoa-custom-view__card-icon"><x-filament::icon icon="heroicon-o-clock" /></span>
                    <div>
                        <h3>Histórico de movimentações</h3>
                        <p>Alterações registradas daqui em diante. Registros pedagógicos anteriores permanecem preservados.</p>
                    </div>
                </header>
                @forelse ($movimentacoes as $movimentacao)
                    <article class="pessoa-custom-view__history-item">
                        <strong>{{ $movimentacao->ocorrido_em?->format('d/m/Y H:i') }} · {{ $movimentacao->usuario?->name ?? 'Sistema' }}</strong>
                        @foreach ($movimentacao->alteracoes as $campo => $mudanca)
                            <div>
                                <b>{{ match ($campo) { 'cargo' => 'Cargo e vínculos', 'lotacao' => 'Lotação', 'matriculas' => 'Matrículas e turnos', 'pedagogico' => 'Turmas, séries e componentes', default => $campo } }}</b>
                                <small class="pessoa-custom-view__history-change pessoa-custom-view__history-change--before"><span>Antes</span>{{ $formatarValorHistorico($mudanca['antes'] ?? null) }}</small>
                                <small class="pessoa-custom-view__history-change pessoa-custom-view__history-change--after"><span>Depois</span>{{ $formatarValorHistorico($mudanca['depois'] ?? null) }}</small>
                            </div>
                        @endforeach
                    </article>
                @empty
                    <div class="pessoa-custom-view__empty">
                        <x-filament::icon icon="heroicon-o-information-circle" />
                        <strong>Nenhuma movimentação registrada</strong>
                        <span>As próximas alterações de cargo, escola, lotação, matrícula ou vínculo pedagógico aparecerão aqui.</span>
                    </div>
                @endforelse
            </div>
        </section>

        <section
            class="pessoa-custom-view__panel"
            role="tabpanel"
            x-show="tab === 'saldo-eleitoral'"
            x-cloak
            x-transition.opacity.duration.150ms
        >
            <article class="pessoa-custom-view__card">
                <header>
                    <span class="pessoa-custom-view__card-icon"><x-filament::icon icon="heroicon-o-scale" /></span>
                    <div>
                        <h3>Saldo eleitoral disponível: {{ $saldoEleitoralDisponivel }} dia(s)</h3>
                        <p>Saldo aprovado: {{ $saldoEleitoralDias }} dia(s). Solicitações de uso pendentes já ficam reservadas.</p>
                    </div>
                </header>
                <div class="pessoa-custom-view__saldo-history">
                    @forelse ($saldoEleitoral as $movimento)
                        <article class="pessoa-custom-view__history-item pessoa-custom-view__history-item--saldo pessoa-custom-view__history-item--saldo-{{ $saldoTom($movimento) }}">
                            <strong>{{ $saldoTitulo($movimento) }}: {{ $movimento->dias }} dia(s)</strong>
                            <span>{{ match ($movimento->status) { 'aprovado' => 'Aprovado pelo RH', 'rejeitado' => 'Rejeitado pelo RH', default => 'Pendente de aprovação do RH' } }}{{ $movimento->lancamento_manual ? ' · Desconto lançado pelo RH' : '' }}</span>
                            @if ($movimento->datas)
                                <p>{{ $movimento->tipo === \App\Models\SaldoEleitoral::TIPO_ESTORNO ? 'Datas estornadas' : 'Datas de uso' }}: {{ collect($movimento->datas)->map(fn ($date) => \Illuminate\Support\Carbon::parse($date)->format('d/m/Y'))->join(', ') }}</p>
                            @endif
                            @if ($movimento->movimentoOrigem)
                                <p>Estorno referente ao uso aprovado em {{ ($movimento->movimentoOrigem->decidido_em ?? $movimento->movimentoOrigem->created_at)?->format('d/m/Y') }}.</p>
                            @endif
                            <small>{{ ($movimento->decidido_em ?? $movimento->created_at)?->format('d/m/Y H:i') }} · solicitado/lançado por {{ $movimento->solicitante?->name ?? 'Usuário removido' }}@if ($movimento->aprovador) · analisado por {{ $movimento->aprovador->name }}@endif</small>
                            @if ($movimento->observacao)<p>{{ $movimento->observacao }}</p>@endif
                        </article>
                    @empty
                        <div class="pessoa-custom-view__empty">
                            <x-filament::icon icon="heroicon-o-information-circle" />
                            <strong>Nenhuma movimentação de saldo eleitoral</strong>
                            <span>Solicitações e descontos aparecerão aqui.</span>
                        </div>
                    @endforelse
                </div>
            </article>
        </section>
    </div>
</div>
