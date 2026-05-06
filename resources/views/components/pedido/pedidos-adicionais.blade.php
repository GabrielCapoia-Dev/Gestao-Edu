<div class="space-y-3">
    @forelse ($adicionais as $adicional)
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="text-sm font-semibold text-gray-950 dark:text-white">
                        {{ $adicional->numero_protocolo }}
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-300">
                        {{ $adicional->tipoManutencao?->nome ?? 'Tipo nao informado' }}
                    </div>
                </div>

                <span class="rounded-md px-2 py-1 text-xs font-semibold text-white"
                    style="background-color: {{ $adicional->tipoStatus?->cor ?? '#64748b' }}">
                    {{ $adicional->tipoStatus?->nome ?? 'Pedido Adicional' }}
                </span>
            </div>

            <div class="mt-3 text-sm text-gray-700 dark:text-gray-200">
                {{ $adicional->descricao_pedido }}
            </div>

            @if ($adicional->problemas->isNotEmpty())
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($adicional->problemas as $problema)
                        <span class="rounded-md bg-gray-100 px-2 py-1 text-xs text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                            {{ $problema->texto_problema }}
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    @empty
        <div class="rounded-lg border border-dashed border-gray-300 p-6 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
            Nenhum pedido adicional vinculado.
        </div>
    @endforelse
</div>
