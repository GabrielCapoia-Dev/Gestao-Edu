<x-filament-panels::page>

    {{-- ================================================================= --}}
    {{-- CARDS DO TOPO                                                      --}}
    {{-- ================================================================= --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ($this->cards as $card)
            @php
                $colorMap = [
                    'blue'  => ['bg' => 'fi-color-primary',  'icon' => 'text-primary-500',  'value' => 'text-primary-600 dark:text-primary-400'],
                    'amber' => ['bg' => 'fi-color-warning',  'icon' => 'text-warning-500',  'value' => 'text-warning-600 dark:text-warning-400'],
                    'red'   => ['bg' => 'fi-color-danger',   'icon' => 'text-danger-500',   'value' => 'text-danger-600 dark:text-danger-400'],
                    'green' => ['bg' => 'fi-color-success',  'icon' => 'text-success-500',  'value' => 'text-success-600 dark:text-success-400'],
                ];
                $cm = $colorMap[$card['cor']] ?? $colorMap['blue'];
            @endphp

            <x-filament::section compact>
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 rounded-lg bg-gray-100 dark:bg-white/5 p-2.5">
                        <x-filament::icon
                            :icon="$card['icone']"
                            class="h-5 w-5 {{ $cm['icon'] }}"
                        />
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">
                            {{ $card['titulo'] }}
                        </p>
                        <p class="text-xl font-bold leading-tight {{ $cm['value'] }}">
                            {{ $card['valor'] }}
                        </p>
                        <p class="text-xs text-gray-400 dark:text-gray-500">
                            {{ $card['descricao'] }}
                        </p>
                    </div>
                </div>
            </x-filament::section>
        @endforeach
    </div>

    {{-- ================================================================= --}}
    {{-- PAINEL: ABAS + TABELA                                             --}}
    {{-- ================================================================= --}}
    <x-filament::section>
        <x-slot name="heading">Margens por Item</x-slot>
        <x-slot name="description">
            Saldo disponível consolidado de todos os contratos ativos
        </x-slot>

        {{-- Abas --}}
        <div class="border-b border-gray-200 dark:border-white/10 overflow-x-auto -mx-6 px-6 mb-4">
            <nav class="flex gap-0 -mb-px" role="tablist">
                @foreach ($this->abas as $aba)
                    <button
                        wire:click="mudarAba('{{ $aba['value'] }}')"
                        role="tab"
                        aria-selected="{{ $abaAtiva === $aba['value'] ? 'true' : 'false' }}"
                        @class([
                            'flex-shrink-0 px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 transition-colors duration-150 focus:outline-none',
                            'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400'
                                => $abaAtiva === $aba['value'],
                            'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-white/20'
                                => $abaAtiva !== $aba['value'],
                        ])
                    >
                        {{ $aba['label'] }}
                    </button>
                @endforeach
            </nav>
        </div>

        {{-- Tabela --}}
        <div class="overflow-x-auto -mx-6">
            <table class="w-full text-sm min-w-[540px]">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5">
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Item
                        </th>
                        <th class="hidden md:table-cell px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Categoria
                        </th>
                        <th class="hidden lg:table-cell px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Contratado
                        </th>
                        <th class="hidden lg:table-cell px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Utilizado
                        </th>
                        <th class="hidden lg:table-cell px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Reservado
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Saldo
                        </th>
                        <th class="hidden sm:table-cell px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Margem
                        </th>
                        <th class="hidden md:table-cell px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Contr.
                        </th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @forelse ($this->itensFiltrados as $item)
                        @php
                            $barColor = match($item['status']) {
                                'zerado'  => 'bg-gray-300 dark:bg-gray-600',
                                'critico' => 'bg-danger-500',
                                'baixo'   => 'bg-warning-500',
                                default   => 'bg-success-500',
                            };
                            $saldoColor = $item['saldo_disponivel'] <= 0
                                ? 'text-danger-600 dark:text-danger-400'
                                : 'text-gray-900 dark:text-white';
                        @endphp
                        <tr class="group hover:bg-gray-50 dark:hover:bg-white/[0.02] transition-colors duration-100">

                            {{-- Nome --}}
                            <td class="px-6 py-3">
                                <p class="font-medium text-gray-900 dark:text-white leading-snug">
                                    {{ $item['nome'] }}
                                </p>
                                <p class="text-xs uppercase tracking-wider text-gray-400 dark:text-gray-500 mt-0.5">
                                    {{ $item['unidade'] }}
                                </p>
                            </td>

                            {{-- Categoria --}}
                            <td class="hidden md:table-cell px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                {{ $item['tipo_label'] }}
                            </td>

                            {{-- Contratado --}}
                            <td class="hidden lg:table-cell px-4 py-3 text-right text-sm text-gray-500 dark:text-gray-400">
                                {{ number_format($item['total_contratado'], 3, ',', '.') }}
                            </td>

                            {{-- Utilizado --}}
                            <td class="hidden lg:table-cell px-4 py-3 text-right text-sm text-gray-500 dark:text-gray-400">
                                {{ number_format($item['total_utilizado'], 3, ',', '.') }}
                            </td>

                            {{-- Reservado --}}
                            <td class="hidden lg:table-cell px-4 py-3 text-right text-sm font-medium text-warning-600 dark:text-warning-400">
                                {{ number_format($item['total_reservado'], 3, ',', '.') }}
                            </td>

                            {{-- Saldo --}}
                            <td class="px-4 py-3 text-right text-sm font-bold {{ $saldoColor }}">
                                {{ number_format($item['saldo_disponivel'], 3, ',', '.') }}
                            </td>

                            {{-- Barra --}}
                            <td class="hidden sm:table-cell px-4 py-3">
                                <div class="flex items-center gap-2 min-w-[100px]">
                                    <div class="flex-1 h-1.5 rounded-full bg-gray-200 dark:bg-white/10 overflow-hidden">
                                        <div
                                            class="h-full rounded-full {{ $barColor }}"
                                            style="width: {{ min($item['percentual'], 100) }}%"
                                        ></div>
                                    </div>
                                    <span class="w-10 text-right text-xs font-medium text-gray-500 dark:text-gray-400">
                                        {{ $item['percentual'] }}%
                                    </span>
                                </div>
                            </td>

                            {{-- Nº contratos --}}
                            <td class="hidden md:table-cell px-4 py-3 text-center">
                                <x-filament::badge color="gray" size="sm">
                                    {{ $item['qtd_contratos'] }}
                                </x-filament::badge>
                            </td>

                            {{-- Ação --}}
                            <td class="px-4 py-3 text-right">
                                <x-filament::button
                                    size="sm"
                                    color="gray"
                                    icon="heroicon-o-eye"
                                    wire:click="abrirSlideOver({{ $item['item_id'] }})"
                                >
                                    <span class="hidden sm:inline">Detalhes</span>
                                </x-filament::button>
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-16 text-center">
                                <x-filament::icon
                                    icon="heroicon-o-inbox"
                                    class="mx-auto mb-3 h-10 w-10 text-gray-300 dark:text-gray-600"
                                />
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Nenhum item com saldo disponível nesta categoria.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    {{-- ================================================================= --}}
    {{-- MODAL: Contratos do item selecionado                              --}}
    {{-- ================================================================= --}}
    <x-filament::modal
        id="modal-contratos-item"
        :close-by-clicking-away="true"
        width="2xl"
        wire:model="slideOverAberto"
    >
        <x-slot name="heading">
            {{ $itemSelecionadoNome ?: 'Contratos do Item' }}
        </x-slot>

        <x-slot name="description">
            @if($itemSelecionadoUnidade)
                Unidade: <strong class="uppercase">{{ $itemSelecionadoUnidade }}</strong>
                &middot;
                {{ count($contratosDoItem) }} {{ count($contratosDoItem) === 1 ? 'contrato ativo' : 'contratos ativos' }}
            @endif
        </x-slot>

        {{-- Resumo consolidado --}}
        @if($slideOverAberto && count($contratosDoItem))
            @php
                $tg  = collect($contratosDoItem)->sum('quantidade_total');
                $ug  = collect($contratosDoItem)->sum('quantidade_utilizada');
                $rg  = collect($contratosDoItem)->sum('quantidade_reservada');
                $sg  = collect($contratosDoItem)->sum('saldo_disponivel');
                $vg  = collect($contratosDoItem)->sum('valor_total_disponivel');
                $pg  = $tg > 0 ? round(($sg / $tg) * 100, 1) : 0;
                $bgc = $pg <= 0 ? 'bg-gray-300 dark:bg-gray-600'
                     : ($pg <= 10 ? 'bg-danger-500'
                     : ($pg <= 30 ? 'bg-warning-500'
                     : 'bg-success-500'));
                $sgColor = $sg <= 0
                    ? 'text-danger-600 dark:text-danger-400'
                    : 'text-success-600 dark:text-success-400';
            @endphp

            {{-- Bloco de resumo --}}
            <div class="rounded-xl bg-gray-50 dark:bg-white/5 ring-1 ring-gray-200 dark:ring-white/10 p-4 mb-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-3">
                    Resumo Consolidado
                </p>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">Total Contratado</p>
                        <p class="text-sm font-bold text-gray-900 dark:text-white">
                            {{ number_format($tg, 3, ',', '.') }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">Utilizado</p>
                        <p class="text-sm font-bold text-gray-900 dark:text-white">
                            {{ number_format($ug, 3, ',', '.') }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">Reservado</p>
                        <p class="text-sm font-bold text-warning-600 dark:text-warning-400">
                            {{ number_format($rg, 3, ',', '.') }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">Saldo Disponível</p>
                        <p class="text-sm font-bold {{ $sgColor }}">
                            {{ number_format($sg, 3, ',', '.') }}
                        </p>
                    </div>
                </div>

                {{-- Barra geral --}}
                <div class="mt-3 flex items-center gap-3">
                    <div class="flex-1 h-2 rounded-full bg-gray-200 dark:bg-white/10 overflow-hidden">
                        <div class="h-full rounded-full {{ $bgc }}" style="width: {{ min($pg, 100) }}%"></div>
                    </div>
                    <span class="w-10 text-right text-xs font-semibold text-gray-500 dark:text-gray-400">
                        {{ $pg }}%
                    </span>
                </div>

                <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">
                    Valor financeiro disponível:
                    <span class="font-semibold text-gray-700 dark:text-gray-300">
                        R$ {{ number_format($vg, 2, ',', '.') }}
                    </span>
                </p>
            </div>

            {{-- Lista de contratos --}}
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-3">
                Detalhes por Contrato
            </p>

            <div class="space-y-3 max-h-[55vh] overflow-y-auto pr-1">
                @foreach ($contratosDoItem as $ct)
                    @php
                        $pct = $ct['percentual'];
                        $fc  = $pct <= 0  ? 'bg-gray-300 dark:bg-gray-600'
                             : ($pct <= 10 ? 'bg-danger-500'
                             : ($pct <= 30 ? 'bg-warning-500'
                             : 'bg-success-500'));
                        $sc  = $ct['saldo_disponivel'] <= 0
                            ? 'text-danger-600 dark:text-danger-400'
                            : 'text-success-600 dark:text-success-400';
                    @endphp

                    <div class="rounded-xl ring-1 ring-gray-200 dark:ring-white/10 bg-white dark:bg-white/[0.03] p-4">

                        {{-- Header do card --}}
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div class="min-w-0">
                                <p class="font-semibold text-sm text-gray-900 dark:text-white truncate">
                                    {{ $ct['empresa'] }}
                                </p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                    {{ $ct['numero_contrato'] }}
                                </p>
                            </div>
                            <div class="flex flex-col items-end gap-1.5 flex-shrink-0">
                                <x-filament::badge
                                    :color="$ct['vencido'] ? 'danger' : 'success'"
                                    size="sm"
                                >
                                    {{ $ct['vencido'] ? 'Vencido' : 'Vigente' }}
                                </x-filament::badge>
                                <span class="text-xs text-gray-400 dark:text-gray-500">
                                    Venc: {{ $ct['data_vencimento'] }}
                                </span>
                            </div>
                        </div>

                        {{-- Grade de quantidades --}}
                        <div class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-xs mb-3">
                            <div class="flex justify-between gap-2">
                                <span class="text-gray-400 dark:text-gray-500">Total contratado</span>
                                <span class="font-semibold text-gray-700 dark:text-gray-300">
                                    {{ number_format($ct['quantidade_total'], 3, ',', '.') }}
                                </span>
                            </div>
                            <div class="flex justify-between gap-2">
                                <span class="text-gray-400 dark:text-gray-500">Preço unitário</span>
                                <span class="font-semibold text-gray-700 dark:text-gray-300">
                                    R$ {{ number_format($ct['preco_unitario'], 2, ',', '.') }}
                                </span>
                            </div>
                            <div class="flex justify-between gap-2">
                                <span class="text-gray-400 dark:text-gray-500">Utilizado</span>
                                <span class="font-semibold text-gray-700 dark:text-gray-300">
                                    {{ number_format($ct['quantidade_utilizada'], 3, ',', '.') }}
                                </span>
                            </div>
                            <div class="flex justify-between gap-2">
                                <span class="text-gray-400 dark:text-gray-500">Reservado</span>
                                <span class="font-semibold text-warning-600 dark:text-warning-400">
                                    {{ number_format($ct['quantidade_reservada'], 3, ',', '.') }}
                                </span>
                            </div>
                        </div>

                        {{-- Barra de saldo --}}
                        <div class="flex items-center gap-2 mb-2.5">
                            <div class="flex-1 h-1.5 rounded-full bg-gray-200 dark:bg-white/10 overflow-hidden">
                                <div class="h-full rounded-full {{ $fc }}" style="width: {{ min($pct, 100) }}%"></div>
                            </div>
                            <span class="w-10 text-right text-xs font-medium text-gray-400 dark:text-gray-500">
                                {{ $pct }}%
                            </span>
                        </div>

                        {{-- Saldo + valor financeiro --}}
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-xs text-gray-400 dark:text-gray-500">Saldo: </span>
                                <span class="text-base font-extrabold {{ $sc }}">
                                    {{ number_format($ct['saldo_disponivel'], 3, ',', '.') }}
                                </span>
                            </div>
                            <span class="text-xs text-gray-400 dark:text-gray-500">
                                Valor disp.:
                                <span class="font-semibold text-gray-700 dark:text-gray-300">
                                    R$ {{ number_format($ct['valor_total_disponivel'], 2, ',', '.') }}
                                </span>
                            </span>
                        </div>

                    </div>
                @endforeach
            </div>
        @else
            <div class="flex flex-col items-center justify-center py-12 text-gray-400 dark:text-gray-500">
                <x-filament::icon icon="heroicon-o-inbox" class="mb-3 h-10 w-10 opacity-40" />
                <p class="text-sm">Nenhum contrato ativo para este item.</p>
            </div>
        @endif

        <x-slot name="footerActions">
            <x-filament::button
                color="gray"
                wire:click="fecharSlideOver"
            >
                Fechar
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

</x-filament-panels::page>