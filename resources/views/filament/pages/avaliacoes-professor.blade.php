<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="grid gap-4 md:grid-cols-2">
                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                        Avaliação
                    </span>
                    <select
                        wire:model.live="avaliacao"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                        <option value="">Selecione uma avaliação</option>
                        @foreach ($this->avaliacoesDisponiveis as $avaliacaoItem)
                            <option value="{{ $avaliacaoItem->id }}">
                                {{ $avaliacaoItem->nome }} |
                                {{ optional($avaliacaoItem->data_inicio)->format('d/m/Y') }}
                                até
                                {{ optional($avaliacaoItem->data_fim)->format('d/m/Y') }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                        Turma
                    </span>
                    <select
                        wire:model.live="turma"
                        @disabled(! $avaliacao)
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                        <option value="">Selecione uma turma</option>
                        @foreach ($this->turmasDisponiveis as $turmaItem)
                            <option value="{{ $turmaItem->id }}">
                                {{ $turmaItem->escola?->nome }} - {{ $turmaItem->serie?->nome }} - Turma
                                {{ $turmaItem->nome }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>

            @php($progresso = $this->progresso)
            @php($percentual = $progresso['total'] > 0 ? min(100, (int) round(($progresso['preenchidas'] / $progresso['total']) * 100)) : 0)

            <div class="mt-4">
                <div class="mb-1 flex items-center justify-between text-xs text-gray-600 dark:text-gray-300">
                    <span>Progresso do preenchimento</span>
                    <span>{{ $progresso['preenchidas'] }}/{{ $progresso['total'] }}</span>
                </div>
                <div class="h-2 rounded-full bg-gray-100 dark:bg-gray-700">
                    <div
                        class="h-2 rounded-full bg-primary-600 transition-all"
                        style="width: {{ $percentual }}%"></div>
                </div>
            </div>
        </div>

        @if ($this->avaliacoesDisponiveis->isEmpty())
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-600/40 dark:bg-amber-900/20 dark:text-amber-200">
                Não existem avaliações pendentes para seus componentes neste momento.
            </div>
        @elseif (! $avaliacao)
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800/40 dark:text-gray-200">
                Selecione uma avaliação para começar.
            </div>
        @elseif ($this->turmasDisponiveis->isEmpty())
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-600/40 dark:bg-amber-900/20 dark:text-amber-200">
                Esta avaliação não possui turmas com pautas vinculadas aos componentes que você leciona.
            </div>
        @elseif (! $turma)
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800/40 dark:text-gray-200">
                Selecione a turma para visualizar as pautas e os alunos.
            </div>
        @elseif ($this->pautasDisponiveis->isEmpty())
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-600/40 dark:bg-amber-900/20 dark:text-amber-200">
                Nenhuma pauta desta avaliação está disponível para os seus componentes nessa turma.
            </div>
        @else
            @foreach ($this->pautasDisponiveis as $pauta)
                <div
                    wire:key="pauta-{{ $pauta->id }}"
                    class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ $pauta->texto }}
                        </h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Componente:
                            {{ $pauta->componente?->nome ?? 'Geral (sem componente específico)' }}
                        </p>
                    </div>

                    <div class="space-y-4 p-4">
                        <div class="grid gap-3 md:grid-cols-[1fr_auto]">
                            <label class="block">
                                <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-600 dark:text-gray-300">
                                    Avaliação em massa
                                </span>
                                <select
                                    wire:model="avaliacaoEmMassa.{{ $pauta->id }}"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                                    <option value="">Selecione uma alternativa</option>
                                    @foreach ($pauta->alternativas as $alternativa)
                                        <option value="{{ $alternativa->id }}">
                                            {{ $alternativa->nome }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>

                            <div class="flex items-end">
                                <x-filament::button
                                    type="button"
                                    wire:click="aplicarEmMassa({{ $pauta->id }})"
                                    color="gray">
                                    Aplicar para todos os alunos
                                </x-filament::button>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">
                                            Aluno
                                        </th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">
                                            Alternativa
                                        </th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">
                                            Observação (opcional)
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach ($this->alunosDaTurma as $aluno)
                                        <tr wire:key="pauta-{{ $pauta->id }}-aluno-{{ $aluno->id }}">
                                            <td class="px-3 py-2 align-top">
                                                <p class="font-medium text-gray-900 dark:text-gray-100">
                                                    {{ $aluno->nome }}
                                                </p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                                    CGM: {{ $aluno->cgm }}
                                                </p>
                                            </td>
                                            <td class="px-3 py-2 align-top">
                                                <select
                                                    wire:model="respostas.{{ $pauta->id }}.{{ $aluno->id }}.alternativa_id"
                                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                                                    <option value="">Selecione</option>
                                                    @foreach ($pauta->alternativas as $alternativa)
                                                        <option value="{{ $alternativa->id }}">
                                                            {{ $alternativa->nome }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td class="px-3 py-2 align-top">
                                                <input
                                                    type="text"
                                                    wire:model.blur="respostas.{{ $pauta->id }}.{{ $aluno->id }}.observacao"
                                                    maxlength="1000"
                                                    placeholder="Observação livre"
                                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" />
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="flex justify-end">
                <x-filament::button type="button" wire:click="salvarRespostas">
                    Salvar Avaliação da Turma
                </x-filament::button>
            </div>
        @endif
    </div>
</x-filament-panels::page>
