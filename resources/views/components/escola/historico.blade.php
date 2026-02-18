<div class="space-y-4">

    <table class="w-full text-sm border rounded-lg overflow-hidden">
        <thead class="bg-gray-100 dark:bg-gray-800">
            <tr>
                <th class="p-2 text-left">Data</th>
                <th class="p-2 text-left">Nome</th>
                <th class="p-2 text-left">Email</th>
                <th class="p-2 text-left">Telefone</th>
                <th class="p-2 text-left">Cidade</th>
                <th class="p-2 text-left">UF</th>
                <th class="p-2 text-left">Status</th>
            </tr>
        </thead>
        <tbody>
        @foreach($historico as $item)
            <tr class="border-t">
                <td class="p-2">{{ $item->created_at->format('d/m/Y H:i') }}</td>
                <td class="p-2">{{ $item->nome }}</td>
                <td class="p-2">{{ $item->email }}</td>
                <td class="p-2">{{ $item->telefone }}</td>
                <td class="p-2">{{ $item->cidade }}</td>
                <td class="p-2">{{ $item->estado }}</td>
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
