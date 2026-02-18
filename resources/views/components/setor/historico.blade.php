<div class="space-y-4">

    <table class="w-full text-sm border rounded-lg overflow-hidden">
        <thead class="bg-gray-100 dark:bg-gray-800">
            <tr>
                <th class="p-2 text-left">Data</th>
                <th class="p-2 text-left">Nome</th>
                <th class="p-2 text-left">Status</th>
                <th class="p-2 text-left">Alterado Por</th>
                <th class="p-2 text-left">Status Histórico</th>
            </tr>
        </thead>
        <tbody>
            @foreach($historico as $item)
            <tr class="border-t">
                <td class="p-2">{{ $item->created_at->format('d/m/Y H:i') }}</td>
                <td class="p-2">{{ $item->nome }}</td>
                <td class="p-2">{{ $item->status }}</td>
                <td class="p-2">{{ $item->alterado_por }}</td>

                <td class="p-2">
                    @if($item->ativo)
                    <span class="text-green-600 font-semibold">Ativo</span>
                    @else
                    <span class="text-gray-500">Versão Antiga</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

</div>