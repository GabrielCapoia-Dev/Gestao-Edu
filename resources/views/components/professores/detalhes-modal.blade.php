@php
/** @var \App\Models\Professor $professor */
$especializacoes = $professor->especializacoes()->get();
@endphp

<div class="space-y-6 text-sm max-h-[80vh] overflow-y-auto">

    {{-- Cabeçalho --}}
    <div
        class="bg-gradient-to-r from-primary-50 to-primary-100 dark:from-primary-900/20 dark:to-primary-800/20 rounded-lg p-4 border border-primary-200 dark:border-primary-700">
        <div class="flex items-center gap-3 mb-3">
            <div
                class="flex h-10 w-10 items-center justify-center rounded-full bg-primary-500/10 text-primary-600 dark:text-primary-300">
                <span class="font-bold text-lg">
                    {{ mb_substr($professor->nome, 0, 1) }}
                </span>
            </div>

            <div>
                <h3 class="font-bold text-lg text-gray-900 dark:text-white">
                    {{ $professor->nome }}
                </h3>

                <div class="text-xs text-gray-600 dark:text-gray-400 flex flex-wrap gap-3 mt-1">
                    @if($professor->matricula)
                    <span class="font-mono">
                        Matrícula: <span class="font-semibold">#{{ $professor->matricula }}</span>
                    </span>
                    @endif

                    @if($professor->email)
                    <span class="truncate max-w-[220px]">
                        E-mail:
                        <a href="mailto:{{ $professor->email }}" class="underline-offset-2 hover:underline">
                            {{ $professor->email }}
                        </a>
                    </span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Escola / Turno / Flags --}}
    <div class="border-t pt-4">
        <h4
            class="text-sm font-bold text-secondary-700 dark:text-secondary-300 mb-3 flex items-center gap-2">
            <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
            Dados do vínculo
        </h4>

        <div
            class="bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-900 rounded-lg p-4 space-y-3">
            <div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Escola</p>
                <p class="font-semibold text-gray-900 dark:text-white">
                    {{ $professor->escola->nome ?? '-' }}
                </p>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Turno</p>
                    @php
                    $turno = $professor->turno;
                    $corTurno = match($turno) {
                    'Manhã' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
                    'Tarde' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-200',
                    'Noite' => 'bg-slate-800 text-slate-100 dark:bg-slate-900/60 dark:text-slate-100',
                    default => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
                    };
                    @endphp
                    <span
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $corTurno }}">
                        {{ $turno ?? '-' }}
                    </span>
                </div>

                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Professor SRM</p>
                    @if($professor->professor_srm)
                    <span
                        class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-purple-500"></span>
                        Sim
                    </span>
                    @else
                    <span class="text-xs text-gray-500 dark:text-gray-400">Não</span>
                    @endif
                </div>

                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Profissional de Apoio</p>
                    @if($professor->profissional_apoio)
                    <span
                        class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        Sim
                    </span>
                    @else
                    <span class="text-xs text-gray-500 dark:text-gray-400">Não</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @can('Visualizar Especializações de Professores')
    {{-- Especializações --}}
    <div class="border-t pt-4">
        <h4
            class="text-sm font-bold text-secondary-700 dark:text-secondary-300 mb-3 flex items-center gap-2">
            <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 14l9-5-9-5-9 5 9 5zm0 0v6" />
            </svg>
            Especializações cadastradas
        </h4>

        @if($especializacoes->isEmpty())
        <div
            class="flex flex-col items-center justify-center rounded-lg border border-dashed border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 py-8 text-center">
            <svg class="w-8 h-8 text-gray-400 dark:text-gray-600 mb-2" xmlns="http://www.w3.org/2000/svg"
                fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M12 6.75v10.5m5.25-5.25H6.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                Nenhuma especialização cadastrada
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Cadastre as formações na aba de edição do professor.
            </p>
        </div>
        @else
        <div class="space-y-3">
            @foreach($especializacoes as $esp)
            <div
                class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-3 space-y-2">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Tipo de formação</p>
                        <p class="font-semibold text-gray-900 dark:text-white">
                            {{ $esp->tipo ?? '-' }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-1 justify-end">
                        @if($esp->especializacao_educacao_especial)
                        <span
                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">
                            🧩 Educação Especial
                        </span>
                        @endif
                    </div>
                </div>

                @if($esp->descricao_especializacao)
                <div class="text-xs text-gray-700 dark:text-gray-200">
                    <p class="font-semibold mb-0.5">Descrição</p>
                    <p class="whitespace-pre-line">
                        {{ $esp->descricao_especializacao }}
                    </p>
                </div>
                @endif

                <div class="flex items-center justify-between mt-1 text-xs text-gray-500 dark:text-gray-400">
                    <div>
                        @if(!empty($esp->ano_conclusao))
                        <span class="mr-3">
                            Ano conclusão: <span class="font-semibold">{{ $esp->ano_conclusao }}</span>
                        </span>
                        @endif
                    </div>

                    @if($esp->anexo_especializacao_path)
                    @php
                    $url = asset('storage/' . ltrim($esp->anexo_especializacao_path, '/'));
                    @endphp
                    <div class="flex items-center gap-2">
                        <a
                            href="{{ $url }}"
                            target="_blank"
                            class="inline-flex items-center gap-1.5 rounded-md
                            bg-primary-100 dark:bg-primary-500/15
                            border border-primary-200 dark:border-primary-500/30
                            px-2.5 py-1 text-[11px] font-semibold
                            text-primary-800 dark:text-primary-200
                            hover:bg-primary-200 dark:hover:bg-primary-500/25
                            hover:shadow-sm
                            transition"
                            style="
                                    background-color: #dc2626;
                                    color: #ffffff;
                                "
                            onmouseover="this.style.backgroundColor='#b91c1c'"
                            onmouseout="this.style.backgroundColor='#dc2626'">

                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z" />
                                <path fill="white" d="M7 11h2a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H7v-6zm1 1v3h1v-3H8zm3-1h2a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1h-1v1h-1v-5zm1 1v2h1v-2h-1zm3-1h3v1h-2v1h2v1h-2v2h-1v-5z" />
                            </svg>
                            Visualizar PDF
                        </a>
                    </div>
                    @else
                    <span class="text-[11px] italic">Nenhum documento anexado</span>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
    @endcan

    {{-- Resumo --}}
    <div class="border-t pt-4 pb-2">
        <h4
            class="text-sm font-bold text-secondary-700 dark:text-secondary-300 mb-3 flex items-center gap-2">
            <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 19V6l12-3v13M9 19l12-3M9 19l-6-3V6l6 3" />
            </svg>
            Resumo do perfil
        </h4>

        <div class="grid grid-cols-2 gap-4">
            <div
                class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 rounded-lg p-3 border border-blue-200 dark:border-blue-700">
                <p class="text-[11px] font-bold text-blue-700 dark:text-blue-300 mb-2">Atuação</p>
                <ul class="text-[11px] space-y-1.5 text-secondary-700 dark:text-secondary-300">
                    <li>• Turno: {{ $professor->turno ?? '-' }}</li>
                    <li>• Escola: {{ $professor->escola->nome ?? '-' }}</li>
                    <li>• SRM: {{ $professor->professor_srm ? 'Sim' : 'Não' }}</li>
                    <li>• Profissional de Apoio: {{ $professor->profissional_apoio ? 'Sim' : 'Não' }}</li>
                </ul>
            </div>

            <div
                class="bg-gradient-to-br from-emerald-50 to-emerald-100 dark:from-emerald-900/20 dark:to-emerald-800/20 rounded-lg p-3 border border-emerald-200 dark:border-emerald-700">
                <p class="text-[11px] font-bold text-emerald-700 dark:text-emerald-300 mb-2">Formação</p>
                @php
                $tipos = $especializacoes->pluck('tipo')->filter()->unique()->values()->all();
                $qtdEducEsp = $especializacoes->where('especializacao_educacao_especial', true)->count();
                @endphp
                <ul class="text-[11px] space-y-1.5 text-secondary-700 dark:text-secondary-300">
                    <li>• Total de especializações: {{ $especializacoes->count() }}</li>
                    <li>• Tipos: {{ empty($tipos) ? '-' : implode(', ', $tipos) }}</li>
                    <li>• Formações em Educação Especial: {{ $qtdEducEsp }}</li>
                </ul>
            </div>
        </div>

        <div class="mt-3 bg-gray-50 dark:bg-gray-800 rounded-lg p-2.5 text-center">
            <p class="text-[11px] text-gray-500 dark:text-gray-400">
                <span class="font-semibold">Última atualização:</span>
                {{ $professor->updated_at ? $professor->updated_at->format('d/m/Y H:i') : 'Não disponível' }}
            </p>
        </div>
    </div>
</div>