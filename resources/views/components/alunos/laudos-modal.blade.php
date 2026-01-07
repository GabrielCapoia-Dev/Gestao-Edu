@php
/** @var \App\Models\Aluno $aluno */
$user = auth()->user();
$laudosPivot = $aluno->laudosPivot()->with('laudo')->get();
@endphp

<div class="max-h-[600px] overflow-y-auto pr-1 space-y-3 scrollbar-thin scrollbar-thumb-gray-300 dark:scrollbar-thumb-gray-600 scrollbar-track-transparent">
    @forelse ($laudosPivot as $pivot)
    <div class="flex items-center justify-between rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 transition hover:bg-gray-50 dark:hover:bg-gray-700/50">
        <div class="flex-1 min-w-0">
            <div class="font-medium text-gray-900 dark:text-gray-100">
                {{ $pivot->laudo?->nome ?? 'Laudo sem descrição' }}
            </div>

            @if ($pivot->created_at)
            <div class="mt-1 flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                </svg>
                Anexado em {{ $pivot->created_at->format('d/m/Y') }} às {{ $pivot->created_at->format('H:i') }}
            </div>
            @endif
        </div>

        <div class="ml-4 flex-shrink-0 flex items-center gap-2">
            @can('view', $pivot)
            <a
                href="{{ route('laudos.show', $pivot) }}"
                target="_blank"
                class="inline-flex items-center gap-1.5 rounded-md bg-primary-50 dark:bg-primary-400/10 px-3 py-1.5 text-sm font-medium text-primary-600 dark:text-primary-400 hover:bg-primary-100 dark:hover:bg-primary-400/20 transition">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                Visualizar
            </a>
            @endcan

            @can('download', $pivot)
            <a
                href="{{ route('laudos.download', $pivot) }}"
                class="inline-flex items-center gap-1.5 rounded-md bg-primary-50 dark:bg-primary-400/10 px-3 py-1.5 text-sm font-medium text-secondary-600 dark:text-secondary-400 hover:bg-primary-100 dark:hover:bg-primary-400/20 transition">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                Baixar
            </a>
            @endcan

            @if (!Gate::allows('view', $pivot) && !Gate::allows('download', $pivot))
            <span class="inline-flex items-center gap-1.5 rounded-md bg-gray-50 dark:bg-gray-800 px-3 py-1.5 text-xs text-gray-500 dark:text-gray-400">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                </svg>
                Sem acesso
            </span>
            @endif
        </div>
    </div>
    @empty
    <div class="flex flex-col items-center justify-center rounded-lg border border-dashed border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 py-12 text-center">
        <svg class="w-12 h-12 text-gray-400 dark:text-gray-600 mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
        </svg>
        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
            Nenhum laudo cadastrado
        </p>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            Este(a) estudante ainda não possui laudos anexados.
        </p>
    </div>
    @endforelse
</div>