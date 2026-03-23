<x-filament-panels::page>

    {{-- =========================================================== --}}
    {{-- CARDS DO TOPO                                               --}}
    {{-- =========================================================== --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($this->cards as $card)
            @php
                $cores = [
                    'blue'  => ['bg' => 'bg-blue-50 dark:bg-blue-950/40',   'icon' => 'text-blue-600 dark:text-blue-400',  'ring' => 'ring-blue-200 dark:ring-blue-800',  'value' => 'text-blue-700 dark:text-blue-300'],
                    'amber' => ['bg' => 'bg-amber-50 dark:bg-amber-950/40', 'icon' => 'text-amber-600 dark:text-amber-400','ring' => 'ring-amber-200 dark:ring-amber-800','value' => 'text-amber-700 dark:text-amber-300'],
                    'red'   => ['bg' => 'bg-red-50 dark:bg-red-950/40',     'icon' => 'text-red-600 dark:text-red-400',    'ring' => 'ring-red-200 dark:ring-red-800',    'value' => 'text-red-700 dark:text-red-300'],
                    'green' => ['bg' => 'bg-green-50 dark:bg-green-950/40', 'icon' => 'text-green-600 dark:text-green-400','ring' => 'ring-green-200 dark:ring-green-800','value' => 'text-green-700 dark:text-green-300'],
                ];
                $c = $cores[$card['cor']] ?? $cores['blue'];
            @endphp

            <div class="rounded-xl ring-1 {{ $c['ring'] }} {{ $c['bg'] }} p-5 flex items-center gap-4">
                <div class="flex-shrink-0 flex items-center justify-center w-12 h-12 rounded-lg bg-white/60 dark:bg-white/10 ring-1 {{ $c['ring'] }}">
                    <x-filament::icon
                        :icon="$card['icone']"
                        class="w-6 h-6 {{ $c['icon'] }}"
                    />
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">{{ $card['titulo'] }}</p>
                    <p class="text-2xl font-bold {{ $c['value'] }} leading-tight">{{ $card['valor'] }}</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500">{{ $card['descricao'] }}</p>
                </div>
            </div>
        @endforeach
    </div>

    {{-- =========================================================== --}}
    {{-- SEÇÃO PRINCIPAL: ABAS + TABELA                              --}}
    {{-- =========================================================== --}}
    <div class="mt-2 rounded-xl ring-1 ring-gray-200 dark:ring-gray-700 bg-white dark:bg-gray-900 overflow-hidden">

        {{-- Abas --}}
        <div class="border-b border-gray-200 dark:border-gray-700 overflow-x-auto">
            <nav class="flex gap-0 px-4 pt-3" aria-label="Categorias">
                @foreach ($this->abas as $aba)
                    <button
                        wire:click="mudarAba('{{ $aba['value'] }}')"
                        class="relative px-4 py-2.5 text-sm font-medium whitespace-nowrap transition-colors focus:outline-none
                            {{ $abaAtiva === $aba['value']
                                ? 'text-primary-600 dark:text-primary-400 border-b-2 border-primary-600 dark:border-primary-400'
                                : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 border-b-2 border-transparent' }}"
                    >
                        {{ $aba['label'] }}
                    </button>
                @endforeach
            </nav>
        </div>

        {{-- Tabela --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Item</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider hidden md:table-cell">Categoria</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider hidden lg:table-cell">Total Contratado</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider hidden lg:table-cell">Utilizado</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider hidden lg:table-cell">Reservado</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Saldo Disponível</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider hidden sm:table-cell">Margem</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider hidden md:table-cell">Contratos</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($this->itensFiltrados as $item)
                        @php
                            $statusCor = match($item['status']) {
                                'zerado'  => ['bar' => 'bg-gray-300 dark:bg-gray-600',    'badge' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',    'text' => 'Zerado'],
                                'critico' => ['bar' => 'bg-red-500',                       'badge' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',    'text' => 'Crítico'],
                                'baixo'   => ['bar' => 'bg-amber-500',                     'badge' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400', 'text' => 'Baixo'],
                                default   => ['bar' => 'bg-green-500',                     'badge' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400', 'text' => 'Normal'],
                            };
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors">

                            {{-- Nome + unidade --}}
                            <td class="px-4 py-3">
                                <div class="flex flex-col">
                                    <span class="font-medium text-gray-900 dark:text-gray-100 leading-tight">{{ $item['nome'] }}</span>
                                    <span class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ strtoupper($item['unidade']) }}</span>
                                </div>
                            </td>

                            {{-- Categoria --}}
                            <td class="px-4 py-3 hidden md:table-cell">
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $item['tipo_label'] }}</span>
                            </td>

                            {{-- Total Contratado --}}
                            <td class="px-4 py-3 text-right hidden lg:table-cell">
                                <span class="text-gray-700 dark:text-gray-300">{{ number_format($item['total_contratado'], 3, ',', '.') }}</span>
                            </td>

                            {{-- Utilizado --}}
                            <td class="px-4 py-3 text-right hidden lg:table-cell">
                                <span class="text-gray-500 dark:text-gray-400">{{ number_format($item['total_utilizado'], 3, ',', '.') }}</span>
                            </td>

                            {{-- Reservado --}}
                            <td class="px-4 py-3 text-right hidden lg:table-cell">
                                <span class="text-amber-600 dark:text-amber-400">{{ number_format($item['total_reservado'], 3, ',', '.') }}</span>
                            </td>

                            {{-- Saldo Disponível --}}
                            <td class="px-4 py-3 text-right">
                                <span class="font-semibold {{ $item['saldo_disponivel'] <= 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-gray-100' }}">
                                    {{ number_format($item['saldo_disponivel'], 3, ',', '.') }}
                                </span>
                            </td>

                            {{-- Barra de progresso --}}
                            <td class="px-4 py-3 hidden sm:table-cell">
                                <div class="flex items-center gap-2 min-w-[100px]">
                                    <div class="flex-1 h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                        <div
                                            class="h-full rounded-full transition-all {{ $statusCor['bar'] }}"
                                            style="width: {{ min($item['percentual'], 100) }}%"
                                        ></div>
                                    </div>
                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400 w-10 text-right">{{ $item['percentual'] }}%</span>
                                </div>
                            </td>

                            {{-- Quantidade de contratos --}}
                            <td class="px-4 py-3 text-center hidden md:table-cell">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-gray-100 dark:bg-gray-800 text-xs font-semibold text-gray-600 dark:text-gray-300">
                                    {{ $item['qtd_contratos'] }}
                                </span>
                            </td>

                            {{-- Ação: abrir slideOver --}}
                            <td class="px-4 py-3 text-center">
                                <button
                                    wire:click="abrirSlideOver({{ $item['item_id'] }})"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium
                                           text-primary-600 dark:text-primary-400
                                           bg-primary-50 dark:bg-primary-950/40
                                           hover:bg-primary-100 dark:hover:bg-primary-900/50
                                           ring-1 ring-primary-200 dark:ring-primary-800
                                           transition-colors"
                                >
                                    <x-filament::icon icon="heroicon-o-eye" class="w-3.5 h-3.5" />
                                    Contratos
                                </button>
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-12 text-center text-gray-400 dark:text-gray-500">
                                <x-filament::icon icon="heroicon-o-inbox" class="w-10 h-10 mx-auto mb-2 opacity-40" />
                                <p class="text-sm">Nenhum item com saldo disponível nesta categoria.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>

    {{-- =========================================================== --}}
    {{-- SLIDE-OVER: Contratos do item selecionado                   --}}
    {{-- =========================================================== --}}
    @if ($slideOverAberto)
        {{-- Backdrop --}}
        <div
            class="fixed inset-0 z-40 bg-gray-950/60 dark:bg-gray-950/80"
            wire:click="fecharSlideOver"
            x-data
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
        ></div>

        {{-- Painel --}}
        <div
            class="fixed inset-y-0 right-0 z-50 w-full max-w-2xl flex flex-col bg-white dark:bg-gray-900 shadow-2xl"
            x-data
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="translate-x-full opacity-0"
            x-transition:enter-end="translate-x-0 opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="translate-x-0 opacity-100"
            x-transition:leave-end="translate-x-full opacity-0"
        >
            {{-- Header do slide-over --}}
            <div class="flex items-start justify-between gap-4 px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100 leading-tight truncate">
                        {{ $itemSelecionadoNome }}
                    </h2>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                        Unidade: <span class="font-medium uppercase">{{ $itemSelecionadoUnidade }}</span>
                        &middot;
                        {{ count($contratosDoItem) }} {{ count($contratosDoItem) === 1 ? 'contrato ativo' : 'contratos ativos' }}
                    </p>
                </div>
                <button
                    wire:click="fecharSlideOver"
                    class="flex-shrink-0 p-1.5 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                >
                    <x-filament::icon icon="heroicon-o-x-mark" class="w-5 h-5" />
                </button>
            </div>

            {{-- Resumo agregado --}}
            @php
                $totalGeral     = collect($contratosDoItem)->sum('quantidade_total');
                $utilizadoGeral = collect($contratosDoItem)->sum('quantidade_utilizada');
                $reservadoGeral = collect($contratosDoItem)->sum('quantidade_reservada');
                $saldoGeral     = collect($contratosDoItem)->sum('saldo_disponivel');
                $valorGeral     = collect($contratosDoItem)->sum('valor_total_disponivel');
                $pctGeral       = $totalGeral > 0 ? round(($saldoGeral / $totalGeral) * 100, 1) : 0;
            @endphp

            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-700">
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Resumo Consolidado</p>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="flex flex-col">
                        <span class="text-xs text-gray-400 dark:text-gray-500">Total Contratado</span>
                        <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ number_format($totalGeral, 3, ',', '.') }}</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs text-gray-400 dark:text-gray-500">Utilizado</span>
                        <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ number_format($utilizadoGeral, 3, ',', '.') }}</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs text-gray-400 dark:text-gray-500">Reservado</span>
                        <span class="text-sm font-semibold text-amber-600 dark:text-amber-400">{{ number_format($reservadoGeral, 3, ',', '.') }}</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs text-gray-400 dark:text-gray-500">Saldo Disponível</span>
                        <span class="text-sm font-semibold {{ $saldoGeral <= 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                            {{ number_format($saldoGeral, 3, ',', '.') }}
                        </span>
                    </div>
                </div>

                {{-- Barra de progresso geral --}}
                <div class="mt-3 flex items-center gap-3">
                    <div class="flex-1 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                        @php
                            $barCor = $pctGeral <= 0 ? 'bg-gray-400' : ($pctGeral <= 10 ? 'bg-red-500' : ($pctGeral <= 30 ? 'bg-amber-500' : 'bg-green-500'));
                        @endphp
                        <div class="h-full rounded-full {{ $barCor }}" style="width: {{ min($pctGeral, 100) }}%"></div>
                    </div>
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 w-12 text-right">{{ $pctGeral }}%</span>
                </div>

                <div class="mt-2 text-xs text-gray-400 dark:text-gray-500">
                    Valor financeiro disponível:
                    <span class="font-semibold text-gray-600 dark:text-gray-300">R$ {{ number_format($valorGeral, 2, ',', '.') }}</span>
                </div>
            </div>

            {{-- Lista de contratos --}}
            <div class="flex-1 overflow-y-auto px-6 py-4 space-y-3">
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Detalhes por Contrato</p>

                @forelse ($contratosDoItem as $contrato)
                    @php
                        $pct    = $contrato['percentual'];
                        $barC   = $pct <= 0 ? 'bg-gray-400' : ($pct <= 10 ? 'bg-red-500' : ($pct <= 30 ? 'bg-amber-500' : 'bg-green-500'));
                        $saldoC = $contrato['saldo_disponivel'] <= 0
                            ? 'text-red-600 dark:text-red-400'
                            : 'text-green-700 dark:text-green-400';
                        $vencidoLabel = $contrato['vencido'] ? 'bg-red-100 text-red-600 dark:bg-red-900/40 dark:text-red-400' : 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400';
                        $vencidoTexto = $contrato['vencido'] ? 'Vencido' : 'Vigente';
                    @endphp

                    <div class="rounded-xl ring-1 ring-gray-200 dark:ring-gray-700 bg-gray-50 dark:bg-gray-800/40 p-4">
                        {{-- Cabeçalho do card --}}
                        <div class="flex items-start justify-between gap-2 mb-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">
                                    {{ $contrato['empresa'] }}
                                </p>
                                <p class="text-xs text-gray-400 dark:text-gray-500">
                                    {{ $contrato['numero_contrato'] }}
                                </p>
                            </div>
                            <div class="flex flex-col items-end gap-1.5 flex-shrink-0">
                                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {{ $vencidoLabel }}">
                                    {{ $vencidoTexto }}
                                </span>
                                <span class="text-xs text-gray-400 dark:text-gray-500">
                                    Venc: {{ $contrato['data_vencimento'] }}
                                </span>
                            </div>
                        </div>

                        {{-- Grade de quantidades --}}
                        <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-xs mb-3">
                            <div class="flex justify-between">
                                <span class="text-gray-400 dark:text-gray-500">Total contratado</span>
                                <span class="font-medium text-gray-700 dark:text-gray-300">{{ number_format($contrato['quantidade_total'], 3, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400 dark:text-gray-500">Preço unitário</span>
                                <span class="font-medium text-gray-700 dark:text-gray-300">R$ {{ number_format($contrato['preco_unitario'], 2, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400 dark:text-gray-500">Utilizado</span>
                                <span class="font-medium text-gray-700 dark:text-gray-300">{{ number_format($contrato['quantidade_utilizada'], 3, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400 dark:text-gray-500">Reservado</span>
                                <span class="font-medium text-amber-600 dark:text-amber-400">{{ number_format($contrato['quantidade_reservada'], 3, ',', '.') }}</span>
                            </div>
                        </div>

                        {{-- Saldo + barra --}}
                        <div class="flex items-center gap-3 mb-2">
                            <div class="flex-1 h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div class="h-full rounded-full {{ $barC }}" style="width: {{ min($pct, 100) }}%"></div>
                            </div>
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400 w-10 text-right">{{ $pct }}%</span>
                        </div>

                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-xs text-gray-400 dark:text-gray-500">Saldo disponível: </span>
                                <span class="text-sm font-bold {{ $saldoC }}">
                                    {{ number_format($contrato['saldo_disponivel'], 3, ',', '.') }}
                                </span>
                            </div>
                            <div class="text-xs text-gray-400 dark:text-gray-500">
                                Valor: <span class="font-semibold text-gray-600 dark:text-gray-300">R$ {{ number_format($contrato['valor_total_disponivel'], 2, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center py-12 text-gray-400 dark:text-gray-500">
                        <x-filament::icon icon="heroicon-o-inbox" class="w-10 h-10 mb-2 opacity-40" />
                        <p class="text-sm">Nenhum contrato ativo para este item.</p>
                    </div>
                @endforelse
            </div>

            {{-- Footer do slide-over --}}
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                <button
                    wire:click="fecharSlideOver"
                    class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium
                           text-gray-700 dark:text-gray-200
                           bg-gray-100 dark:bg-gray-800
                           hover:bg-gray-200 dark:hover:bg-gray-700
                           ring-1 ring-gray-300 dark:ring-gray-600
                           transition-colors"
                >
                    <x-filament::icon icon="heroicon-o-x-mark" class="w-4 h-4" />
                    Fechar
                </button>
            </div>
        </div>
    @endif

</x-filament-panels::page>