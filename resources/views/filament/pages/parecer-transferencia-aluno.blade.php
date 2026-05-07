<x-filament-panels::page>
    <div class="gi-page av-page">
        <section class="gi-hero">
            <div>
                <p class="gi-eyebrow">Alunos</p>
                <h1>Parecer de Transferencia</h1>
                <p>Localize o aluno matriculado, revise as avaliacoes registradas e gere os documentos para transferencia.</p>
            </div>
        </section>

        <section class="gi-panel av-professor-control-panel">
            <label class="gi-field">
                <span>Buscar aluno</span>
                <input type="search" wire:model.live.debounce.400ms="busca" placeholder="Nome, CGM, escola ou serie">
            </label>
        </section>

        <div class="av-stack">
            <section class="gi-panel">
                <div class="gi-toolbar">
                    <div>
                        <h3 class="av-pauta-title">Alunos matriculados</h3>
                        <p class="av-pauta-meta">{{ $this->alunos->count() }} resultado(s)</p>
                    </div>
                </div>

                <div class="gi-table-wrap">
                    <table class="gi-table">
                        <thead>
                            <tr>
                                <th>Aluno</th>
                                <th>Escola</th>
                                <th>Turma</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->alunos as $aluno)
                            <tr wire:key="parecer-aluno-{{ $aluno->id }}">
                                <td>
                                    <strong>{{ $aluno->nome }}</strong>
                                    <small>CGM: {{ $aluno->cgm }}</small>
                                </td>
                                <td>{{ $aluno->turma?->escola?->nome }}</td>
                                <td>{{ $aluno->turma?->serie?->nome }} - {{ $aluno->turma?->nome }}</td>
                                <td>{{ $aluno->statusLabel() }}</td>
                                <td>
                                    <button type="button" class="gi-action" wire:click="selecionarAluno({{ $aluno->id }})">
                                        Abrir
                                    </button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5">Nenhum aluno matriculado encontrado.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            @if ($this->alunoSelecionado)
            @php($aluno = $this->alunoSelecionado)
            <section class="gi-panel">
                <div class="gi-toolbar">
                    <div>
                        <h3 class="av-pauta-title">{{ $aluno->nome }}</h3>
                        <p class="av-pauta-meta">
                            CGM: {{ $aluno->cgm }} |
                            {{ $aluno->turma?->escola?->nome }} |
                            {{ $aluno->turma?->serie?->nome }} - {{ $aluno->turma?->nome }}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="gi-action gi-action--primary"
                        wire:click="gerarParecerTransferencia"
                        wire:confirm="Caso deseje continuar, o aluno sera marcado como transferido e essa acao nao podera ser revertida. Deseja gerar o Parecer de Transferencia?"
                        wire:loading.attr="disabled"
                        wire:target="gerarParecerTransferencia">
                        Gerar Parecer de Transferencia
                    </button>
                </div>
            </section>

            @forelse ($this->avaliacoesDoAluno as $avaliacao)
            <section class="gi-panel av-turma-section" wire:key="parecer-avaliacao-{{ $avaliacao['id'] }}">
                <div class="av-pauta-toggle">
                    <div class="av-pauta-toggle-main">
                        <h3 class="av-pauta-title">{{ $avaliacao['nome'] }}</h3>
                        <p class="av-pauta-meta">
                            {{ $avaliacao['tipo'] }} |
                            {{ $avaliacao['periodo'] }} |
                            {{ $avaliacao['periodo_datas'] }}
                        </p>
                    </div>

                    <div class="av-pauta-toggle-side">
                        <div class="av-pauta-progress-head">
                            <span>{{ $avaliacao['preenchidas'] }}/{{ $avaliacao['total'] }}</span>
                            <span>{{ $avaliacao['percentual'] }}%</span>
                        </div>
                        <div class="av-progress-track av-progress-track--compact">
                            <div class="av-progress-bar" style="width: {{ $avaliacao['percentual'] }}%"></div>
                        </div>
                    </div>
                </div>

                <div class="av-turma-content">
                    @foreach ($avaliacao['componentes'] as $componente)
                    <section class="av-aluno-componente">
                        <h4>{{ $componente['nome'] }}</h4>

                        <div class="gi-table-wrap">
                            <table class="gi-table">
                                <thead>
                                    <tr>
                                        <th>Pauta</th>
                                        <th>Alternativas</th>
                                        <th>Resposta</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($componente['pautas'] as $pauta)
                                    <tr>
                                        <td>{{ $pauta['texto'] }}</td>
                                        <td>{{ implode(', ', $pauta['alternativas']) }}</td>
                                        <td>
                                            {{ $pauta['resposta'] ?: 'Pendente' }}
                                            @if ($pauta['bloqueada'])
                                            <small>Bloqueada por historico</small>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                    @endforeach
                </div>
            </section>
            @empty
            <section class="av-note av-note--warning">
                Nenhuma avaliacao vinculada a turma atual do aluno.
            </section>
            @endforelse
            @else
            <section class="av-note">
                Selecione um aluno para visualizar as avaliacoes.
            </section>
            @endif
        </div>

        @include('filament.pages.partials.avaliacoes-page-styles')
    </div>
</x-filament-panels::page>
