<x-filament-panels::page>

    {{-- ================================================================ --}}
    {{-- CABEÇALHO                                                        --}}
    {{-- ================================================================ --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Novo Pedido de Merenda</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Monte a relação de itens antes de confirmar o pedido.</p>
        </div>
    </div>

    {{-- ================================================================ --}}
    {{-- OBSERVAÇÕES                                                       --}}
    {{-- ================================================================ --}}
    <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Observações <span class="text-gray-400 font-normal">(opcional)</span>
        </label>
        <textarea
            wire:model="observacoes"
            rows="2"
            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500"
            placeholder="Informações adicionais sobre este pedido..."
        ></textarea>
    </div>

    {{-- ================================================================ --}}
    {{-- TABELA DE ITENS EM MEMÓRIA                                       --}}
    {{-- ================================================================ --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-4">

        {{-- Toolbar da tabela --}}
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                Itens do Pedido
                @if(count($itensPedido) > 0)
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300">
                        {{ count($itensPedido) }}
                    </span>
                @endif
            </span>

            <button
                wire:click="abrirModal"
                type="button"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium transition"
            >
                <x-heroicon-o-plus class="w-4 h-4" />
                Adicionar Item
            </button>
        </div>

        {{-- Tabela --}}
        @if(empty($itensPedido))
            <div class="flex flex-col items-center justify-center py-12 text-gray-400 dark:text-gray-500">
                <x-heroicon-o-shopping-cart class="w-10 h-10 mb-2" />
                <p class="text-sm">Nenhum item adicionado ainda.</p>
            </div>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-700/50">
                        <th class="px-4 py-3">Item</th>
                        <th class="px-4 py-3">Contrato</th>
                        <th class="px-4 py-3">Empresa</th>
                        <th class="px-4 py-3 text-right">Saldo Disponível</th>
                        <th class="px-4 py-3 text-right">Qtd. Pedida</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($itensPedido as $chave => $entry)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition">
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                {{ $entry['item_nome'] }}
                                <span class="text-xs text-gray-400 ml-1">({{ $entry['unidade'] }})</span>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                {{ $entry['numero_contrato'] }}
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                {{ $entry['empresa'] }}
                            </td>
                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">
                                {{ number_format($entry['saldo'], 3, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">
                                {{ number_format($entry['quantidade'], 3, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    wire:click="removerItem('{{ $chave }}')"
                                    type="button"
                                    class="text-red-500 hover:text-red-700 transition"
                                    title="Remover"
                                >
                                    <x-heroicon-o-trash class="w-4 h-4" />
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- ================================================================ --}}
    {{-- BOTÕES DE AÇÃO                                                   --}}
    {{-- ================================================================ --}}
    <div class="flex items-center justify-end gap-3">
        <a
            href="{{ \App\Filament\Admin\Resources\PedidosMerenda\PedidosMerendaResource::getUrl('index') }}"
            class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
        >
            Cancelar
        </a>

        <button
            wire:click="confirmarPedido"
            type="button"
            @if(empty($itensPedido)) disabled @endif
            class="inline-flex items-center gap-2 px-5 py-2 rounded-lg bg-success-600 hover:bg-success-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium transition"
        >
            <x-heroicon-o-check class="w-4 h-4" />
            Confirmar Pedido
        </button>
    </div>


    {{-- ================================================================ --}}
    {{-- MODAL — ADICIONAR ITEM                                           --}}
    {{-- ================================================================ --}}
    @if($modalAberto)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center"
            x-data
            x-init="$el.querySelector('[data-modal-panel]').focus()"
        >
            {{-- Overlay --}}
            <div
                class="absolute inset-0 bg-black/50 backdrop-blur-sm"
                wire:click="fecharModal"
            ></div>

            {{-- Painel --}}
            <div
                data-modal-panel
                tabindex="-1"
                class="relative z-10 w-full max-w-2xl mx-4 bg-white dark:bg-gray-800 rounded-2xl shadow-2xl outline-none"
            >
                {{-- Header --}}
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Adicionar Item ao Pedido</h3>
                    <button wire:click="fecharModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition">
                        <x-heroicon-o-x-mark class="w-5 h-5" />
                    </button>
                </div>

                {{-- Corpo --}}
                <div class="px-6 py-5 space-y-5">

                    {{-- Select de item --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Item <span class="text-danger-500">*</span>
                        </label>
                        <select
                            wire:model.live="itemSelecionado"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500"
                        >
                            <option value="">Selecione um item...</option>
                            @foreach($this->itensComSaldo as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-400">Apenas itens com saldo disponível em contratos ativos.</p>
                    </div>

                    {{-- Lista de contratos com saldo --}}
                    @if($itemSelecionado && count($contratosDoItem) > 0)
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                                Contratos com saldo disponível
                            </p>

                            <div class="space-y-3">
                                @foreach($contratosDoItem as $contratoItemId => $entry)
                                    <div class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/40">

                                        {{-- Infos do contrato --}}
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                                {{ $entry['empresa'] }}
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                Contrato: <span class="font-medium text-gray-700 dark:text-gray-200">{{ $entry['numero_contrato'] }}</span>
                                            </p>
                                        </div>

                                        {{-- Saldo --}}
                                        <div class="text-right shrink-0">
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Saldo</p>
                                            <p class="text-sm font-semibold text-success-600 dark:text-success-400">
                                                {{ number_format($entry['saldo'], 3, ',', '.') }}
                                            </p>
                                        </div>

                                        {{-- Input de quantidade --}}
                                        <div class="shrink-0 w-28">
                                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Qtd. a pedir</label>
                                            <input
                                                type="number"
                                                step="0.001"
                                                min="0"
                                                max="{{ $entry['saldo'] }}"
                                                placeholder="0,000"
                                                wire:change="atualizarQuantidade({{ $contratoItemId }}, $event.target.value)"
                                                class="w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-primary-500"
                                            />
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @elseif($itemSelecionado && count($contratosDoItem) === 0)
                        <div class="flex items-center gap-2 p-3 rounded-lg bg-warning-50 dark:bg-warning-900/20 text-warning-700 dark:text-warning-400 text-sm">
                            <x-heroicon-o-exclamation-triangle class="w-4 h-4 shrink-0" />
                            Nenhum contrato ativo com saldo disponível para este item.
                        </div>
                    @endif
                </div>

                {{-- Footer --}}
                <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                    <button
                        wire:click="fecharModal"
                        type="button"
                        class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                    >
                        Cancelar
                    </button>
                    <button
                        wire:click="confirmarAdicaoItem"
                        type="button"
                        class="px-4 py-2 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium transition"
                    >
                        Confirmar Adição
                    </button>
                </div>
            </div>
        </div>
    @endif

</x-filament-panels::page>