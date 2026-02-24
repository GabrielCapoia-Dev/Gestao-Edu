<div class="space-y-6">

    <div>
        <h2 class="text-lg font-bold">
            Protocolo {{ $pedido->numero_protocolo }}
        </h2>
        <p class="text-sm text-gray-500">
            {{ $pedido->descricao_pedido }}
        </p>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm border rounded-lg overflow-hidden">
            <thead class="bg-gray-100 dark:bg-gray-800">
                <tr>
                    <th class="p-3 text-left">Data</th>
                    <th class="p-3 text-left">Status Anterior</th>
                    <th class="p-3 text-left">Novo Status</th>
                    <th class="p-3 text-left">Setor</th>
                    <th class="p-3 text-left">Alterado Por</th>
                    <th class="p-3 text-left">Descrição</th>
                </tr>
            </thead>
            <tbody>

                @forelse($historico as $loopIndex => $item)

                <tr class="border-t {{ $loop->first ? 'bg-gray-50 dark:bg-gray-700 font-medium' : '' }}">

                    <td class="p-3 whitespace-nowrap">
                        {{ $item->created_at->format('d/m/Y H:i') }}
                    </td>

                    <td class="p-3">
                        {{ $item->statusAnterior?->nome ?? '-' }}
                    </td>

                    <td class="p-3">
                        <span class="font-semibold"
                            style="color: {{ $item->statusNovo?->cor ?? '#000' }}">
                            {{ $item->statusNovo?->nome }}
                        </span>
                    </td>

                    <td class="p-3">
                        {{ $item->setor?->nome ?? '-' }}
                    </td>

                    <td class="p-3">
                        {{ $item->usuario?->name ?? 'Sistema' }}
                    </td>

                    <td class="p-3">
                        {{ $item->descricao_alteracao ?? '-' }}
                    </td>

                </tr>

                @empty
                <tr>
                    <td colspan="6" class="p-4 text-center text-gray-500">
                        Nenhum histórico encontrado.
                    </td>
                </tr>
                @endforelse

            </tbody>
        </table>
    </div>
</div>