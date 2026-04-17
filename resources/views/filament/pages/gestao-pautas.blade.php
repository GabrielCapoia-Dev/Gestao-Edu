<x-filament-panels::page>
    <div class="space-y-6">
        <section class="relative overflow-hidden rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-900 via-indigo-900 to-blue-900 p-6 text-white shadow-lg">
            <div class="pointer-events-none absolute inset-0 opacity-25">
                <div class="absolute -left-16 -top-16 h-44 w-44 rounded-full bg-indigo-300 blur-3xl"></div>
                <div class="absolute -bottom-16 -right-10 h-44 w-44 rounded-full bg-cyan-300 blur-3xl"></div>
            </div>
            <div class="relative flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-cyan-100/90">Avaliações</p>
                    <h1 class="mt-2 text-2xl font-semibold">Pautas</h1>
                    <p class="mt-2 max-w-2xl text-sm text-slate-100/85">
                        Construa perguntas por componente e organize alternativas existentes ou novas em um único fluxo.
                    </p>
                </div>

                @can('Criar Pautas')
                    <x-filament::button
                        type="button"
                        color="info"
                        icon="heroicon-o-plus"
                        wire:click="abrirModalCriacao">
                        Nova Pauta
                    </x-filament::button>
                @endcan
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="grid gap-3 md:grid-cols-[1fr_auto_auto_auto_auto]">
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">
                        Buscar
                    </span>
                    <input
                        type="search"
                        wire:model.live.debounce.300ms="busca"
                        placeholder="Texto da pauta"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" />
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">
                        Componente
                    </span>
                    <select
                        wire:model.live="filtroComponente"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                        <option value="">Todos</option>
                        @foreach ($this->componentesOptions as $componenteId => $componenteNome)
                            <option value="{{ $componenteId }}">{{ $componenteNome }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">
                        Status
                    </span>
                    <select
                        wire:model.live="filtroStatus"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                        <option value="todas">Todas</option>
                        <option value="ativas">Ativas</option>
                        <option value="inativas">Inativas</option>
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
                        Total: <span class="font-semibold">{{ $this->pautas->total() }}</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-slate-50 dark:bg-slate-800/80">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Pauta</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Componente</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Alternativas</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Avaliações</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Atualizada em</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($this->pautas as $pauta)
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ $pauta->texto }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                    {{ $pauta->componente?->nome ?: 'Geral' }}
                                </td>
                                <td class="px-4 py-3 text-center text-sm font-semibold text-gray-800 dark:text-gray-200">
                                    {{ $pauta->alternativas_count }}
                                </td>
                                <td class="px-4 py-3 text-center text-sm font-semibold text-gray-800 dark:text-gray-200">
                                    {{ $pauta->avaliacoes_count }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $pauta->status ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300' : 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300' }}">
                                        {{ $pauta->status ? 'Ativa' : 'Inativa' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                    {{ optional($pauta->updated_at)->format('d/m/Y H:i') }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        @can('Editar Pautas')
                                            <x-filament::button
                                                type="button"
                                                color="gray"
                                                size="xs"
                                                wire:click="abrirModalEdicao({{ $pauta->id }})">
                                                Editar
                                            </x-filament::button>
                                        @endcan

                                        @can('Excluir Pautas')
                                            <x-filament::button
                                                type="button"
                                                color="danger"
                                                size="xs"
                                                wire:click="excluirPauta({{ $pauta->id }})"
                                                onclick="return confirm('Deseja realmente excluir esta pauta?')">
                                                Excluir
                                            </x-filament::button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Nenhuma pauta encontrada para os filtros aplicados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 px-4 py-3 dark:border-gray-700">
                {{ $this->pautas->links() }}
            </div>
        </section>
    </div>

    @if ($modalAberto)
        <div class="fixed inset-0 z-40 bg-slate-950/60 backdrop-blur-sm" wire:click="fecharModal"></div>
        <div class="fixed inset-0 z-50 overflow-y-auto p-4">
            <div class="mx-auto w-full max-w-5xl rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                        {{ $pautaIdEditando ? 'Editar Pauta' : 'Nova Pauta' }}
                    </h3>
                    <button type="button" wire:click="fecharModal" class="text-gray-500 hover:text-gray-800 dark:hover:text-gray-200">
                        <x-filament::icon icon="heroicon-o-x-mark" class="h-5 w-5" />
                    </button>
                </div>

                <div class="space-y-5 p-5">
                    <div class="grid gap-4 md:grid-cols-3">
                        <label class="block md:col-span-2">
                            <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Texto da pauta</span>
                            <textarea
                                wire:model.defer="form.texto"
                                rows="4"
                                maxlength="2000"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"></textarea>
                            @error('form.texto')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </label>

                        <div class="space-y-3">
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Componente (opcional)</span>
                                <select
                                    wire:model.defer="form.componente_curricular_id"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                                    <option value="">Geral (sem componente)</option>
                                    @foreach ($this->componentesOptions as $componenteId => $componenteNome)
                                        <option value="{{ $componenteId }}">{{ $componenteNome }}</option>
                                    @endforeach
                                </select>
                                @error('form.componente_curricular_id')
                                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                @enderror
                            </label>

                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" wire:model.defer="form.status" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                <span class="text-sm text-gray-700 dark:text-gray-200">Pauta ativa</span>
                            </label>
                        </div>
                    </div>

                    <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Alternativas existentes</h4>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Selecione as alternativas já cadastradas para esta pauta.</p>

                        <select
                            multiple
                            wire:model.defer="form.alternativas_ids"
                            class="mt-3 min-h-36 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                            @foreach ($this->alternativasOptions as $alternativa)
                                <option value="{{ $alternativa->id }}">
                                    {{ $alternativa->nome }} {{ $alternativa->status ? '' : '(inativa)' }}
                                </option>
                            @endforeach
                        </select>
                        @error('form.alternativas_ids')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                        @error('form.alternativas_ids.*')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </section>

                    <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Novas alternativas</h4>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Crie novas alternativas sem sair desta tela.</p>
                            </div>
                            <x-filament::button type="button" color="gray" size="xs" wire:click="adicionarNovaAlternativa">
                                Adicionar alternativa
                            </x-filament::button>
                        </div>

                        <div class="mt-3 space-y-3">
                            @forelse ($novasAlternativas as $index => $novaAlternativa)
                                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800/60">
                                    <div class="grid gap-3 md:grid-cols-[1fr_1fr_auto_auto]">
                                        <label class="block">
                                            <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Nome</span>
                                            <input
                                                type="text"
                                                wire:model.defer="novasAlternativas.{{ $index }}.nome"
                                                maxlength="255"
                                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                                        </label>

                                        <label class="block">
                                            <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Observação</span>
                                            <input
                                                type="text"
                                                wire:model.defer="novasAlternativas.{{ $index }}.observacao"
                                                maxlength="1000"
                                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                                        </label>

                                        <label class="inline-flex items-center gap-2 self-end pb-2">
                                            <input type="checkbox" wire:model.defer="novasAlternativas.{{ $index }}.status" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                            <span class="text-sm text-gray-700 dark:text-gray-200">Ativa</span>
                                        </label>

                                        <div class="self-end pb-1">
                                            <x-filament::button
                                                type="button"
                                                color="danger"
                                                size="xs"
                                                wire:click="removerNovaAlternativa({{ $index }})">
                                                Remover
                                            </x-filament::button>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <p class="rounded-lg border border-dashed border-gray-300 px-3 py-4 text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">
                                    Nenhuma nova alternativa adicionada.
                                </p>
                            @endforelse
                        </div>
                    </section>
                </div>

                <div class="flex justify-end gap-2 border-t border-gray-200 px-5 py-4 dark:border-gray-700">
                    <x-filament::button type="button" color="gray" wire:click="fecharModal">
                        Cancelar
                    </x-filament::button>
                    <x-filament::button type="button" wire:click="salvarPauta">
                        Salvar Pauta
                    </x-filament::button>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
