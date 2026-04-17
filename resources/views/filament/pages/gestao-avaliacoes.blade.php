<x-filament-panels::page>
    <div class="space-y-6">
        <section class="relative overflow-hidden rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-900 via-blue-900 to-cyan-900 p-6 text-white shadow-lg">
            <div class="pointer-events-none absolute inset-0 opacity-25">
                <div class="absolute -left-16 -top-16 h-44 w-44 rounded-full bg-sky-300 blur-3xl"></div>
                <div class="absolute -bottom-16 -right-10 h-44 w-44 rounded-full bg-cyan-300 blur-3xl"></div>
            </div>
            <div class="relative flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-cyan-100/90">Avaliações</p>
                    <h1 class="mt-2 text-2xl font-semibold">Gestão de Avaliações</h1>
                    <p class="mt-2 max-w-2xl text-sm text-slate-100/85">
                        Monte ciclos avaliativos com período, status, pautas obrigatórias e turmas vinculadas.
                    </p>
                </div>

                @can('Criar Avaliações')
                    <x-filament::button
                        type="button"
                        color="info"
                        icon="heroicon-o-plus"
                        wire:click="abrirModalCriacao">
                        Nova Avaliação
                    </x-filament::button>
                @endcan
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="grid gap-3 md:grid-cols-[1fr_auto_auto_auto]">
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">
                        Buscar
                    </span>
                    <input
                        type="search"
                        wire:model.live.debounce.300ms="busca"
                        placeholder="Nome da avaliação"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" />
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">
                        Status
                    </span>
                    <select
                        wire:model.live="filtroStatus"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                        <option value="todas">Todos</option>
                        @foreach ($this->statusOptions as $statusValue => $statusLabel)
                            <option value="{{ $statusValue }}">{{ $statusLabel }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">
                        Por página
                    </span>
                    <select
                        wire:model.live="porPagina"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                        <option value="5">5</option>
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </label>

                <div class="flex items-end">
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        Total: <span class="font-semibold">{{ $this->avaliacoes->total() }}</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-slate-50 dark:bg-slate-800/80">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Avaliação</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Período</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Pautas</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Turmas</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Atualizada em</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($this->avaliacoes as $avaliacao)
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $avaliacao->nome }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                    {{ optional($avaliacao->data_inicio)->format('d/m/Y') }}
                                    até
                                    {{ optional($avaliacao->data_fim)->format('d/m/Y') }}
                                </td>
                                <td class="px-4 py-3 text-center text-sm font-semibold text-gray-800 dark:text-gray-200">
                                    {{ $avaliacao->pautas_count }}
                                </td>
                                <td class="px-4 py-3 text-center text-sm font-semibold text-gray-800 dark:text-gray-200">
                                    {{ $avaliacao->turmas_count }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @php
                                        $statusClasses = match ($avaliacao->status) {
                                            'ativa' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300',
                                            'inativa' => 'bg-slate-100 text-slate-700 dark:bg-slate-500/20 dark:text-slate-300',
                                            'encerrada' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300',
                                            'cancelada' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300',
                                            default => 'bg-slate-100 text-slate-700 dark:bg-slate-500/20 dark:text-slate-300',
                                        };
                                    @endphp
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClasses }}">
                                        {{ \App\Models\Avaliacao::statusOptions()[$avaliacao->status] ?? $avaliacao->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                    {{ optional($avaliacao->updated_at)->format('d/m/Y H:i') }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        @can('Editar Avaliações')
                                            <x-filament::button
                                                type="button"
                                                color="gray"
                                                size="xs"
                                                wire:click="abrirModalEdicao({{ $avaliacao->id }})">
                                                Editar
                                            </x-filament::button>
                                        @endcan

                                        @can('Excluir Avaliações')
                                            <x-filament::button
                                                type="button"
                                                color="danger"
                                                size="xs"
                                                wire:click="excluirAvaliacao({{ $avaliacao->id }})"
                                                onclick="return confirm('Deseja realmente excluir esta avaliação?')">
                                                Excluir
                                            </x-filament::button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Nenhuma avaliação encontrada para os filtros aplicados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 px-4 py-3 dark:border-gray-700">
                {{ $this->avaliacoes->links() }}
            </div>
        </section>
    </div>

    @if ($modalAberto)
        <div class="fixed inset-0 z-40 bg-slate-950/60 backdrop-blur-sm" wire:click="fecharModal"></div>
        <div class="fixed inset-0 z-50 overflow-y-auto p-4">
            <div class="mx-auto w-full max-w-5xl rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                        {{ $avaliacaoIdEditando ? 'Editar Avaliação' : 'Nova Avaliação' }}
                    </h3>
                    <button type="button" wire:click="fecharModal" class="text-gray-500 hover:text-gray-800 dark:hover:text-gray-200">
                        <x-filament::icon icon="heroicon-o-x-mark" class="h-5 w-5" />
                    </button>
                </div>

                <div class="space-y-5 p-5">
                    <div class="grid gap-4 md:grid-cols-4">
                        <label class="block md:col-span-2">
                            <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Nome da avaliação</span>
                            <input
                                type="text"
                                wire:model.defer="form.nome"
                                maxlength="255"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" />
                            @error('form.nome')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Data início</span>
                            <input
                                type="date"
                                wire:model.defer="form.data_inicio"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" />
                            @error('form.data_inicio')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Data fim</span>
                            <input
                                type="date"
                                wire:model.defer="form.data_fim"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" />
                            @error('form.data_fim')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </label>
                    </div>

                    <label class="block md:w-64">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Status</span>
                        <select
                            wire:model.defer="form.status"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                            @foreach ($this->statusOptions as $statusValue => $statusLabel)
                                <option value="{{ $statusValue }}">{{ $statusLabel }}</option>
                            @endforeach
                        </select>
                        @error('form.status')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </label>

                    <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Pautas vinculadas</h4>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Selecione ao menos uma pauta existente.</p>

                        <select
                            multiple
                            wire:model.defer="form.pautas_ids"
                            class="mt-3 min-h-44 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                            @foreach ($this->pautasOptions as $pautaId => $pautaLabel)
                                <option value="{{ $pautaId }}">{{ $pautaLabel }}</option>
                            @endforeach
                        </select>
                        @error('form.pautas_ids')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                        @error('form.pautas_ids.*')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </section>

                    <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Turmas vinculadas</h4>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Selecione as turmas que participarão desta avaliação.</p>

                        <select
                            multiple
                            wire:model.defer="form.turmas_ids"
                            class="mt-3 min-h-44 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                            @foreach ($this->turmasOptions as $turmaId => $turmaLabel)
                                <option value="{{ $turmaId }}">{{ $turmaLabel }}</option>
                            @endforeach
                        </select>
                        @error('form.turmas_ids')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                        @error('form.turmas_ids.*')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </section>
                </div>

                <div class="flex justify-end gap-2 border-t border-gray-200 px-5 py-4 dark:border-gray-700">
                    <x-filament::button type="button" color="gray" wire:click="fecharModal">
                        Cancelar
                    </x-filament::button>
                    <x-filament::button type="button" wire:click="salvarAvaliacao">
                        Salvar Avaliação
                    </x-filament::button>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
