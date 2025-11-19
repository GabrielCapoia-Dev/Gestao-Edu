@php
/** @var \App\Models\Aluno $aluno */
$user = auth()->user();
$laudosPivot = $aluno->laudosPivot()->with('laudo')->get();
@endphp

<div class="space-y-6 text-sm max-h-[80vh] overflow-y-auto">

    {{-- Cabeçalho com informações principais --}}
    <div class="bg-gradient-to-r from-primary-50 to-primary-100 dark:from-primary-900/20 dark:to-primary-800/20 rounded-lg p-4 border border-primary-200 dark:border-primary-700">
        <div class="flex items-center gap-3 mb-3">

            <div>
                <h3 class="font-bold text-lg text-gray-900 dark:text-white">{{ $aluno->nome }}</h3>
                <p class="text-xs text-gray-600 dark:text-gray-400">CGM: <span class="font-mono font-semibold">{{ $aluno->cgm }}</span></p>
            </div>
        </div>
    </div>

    {{-- Informações Pessoais --}}
    <div class="border-t pt-4">
        <h4 class="text-sm font-bold text-secundary-700 dark:text-secundary-300 mb-3 flex items-center gap-2">
            <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            Informações Pessoais
        </h4>

        <div class="grid grid-cols-2 gap-4">
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Sexo</p>
                <p class="font-semibold text-gray-900 dark:text-white">
                    @if($aluno->sexo == 'Masculino')
                    <span class="inline-flex items-center gap-1">
                        <span class="w-2 h-2 bg-blue-500 rounded-full"></span>
                        Masculino
                    </span>
                    @elseif($aluno->sexo == 'Feminino')
                    <span class="inline-flex items-center gap-1">
                        <span class="w-2 h-2 bg-pink-500 rounded-full"></span>
                        Feminino
                    </span>
                    @else
                    {{ $aluno->sexo ?? '-' }}
                    @endif
                </p>
            </div>

            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Data de Nascimento</p>
                <p class="font-semibold text-gray-900 dark:text-white">
                    @if($aluno->data_nascimento)
                    {{ \Carbon\Carbon::parse($aluno->data_nascimento)->format('d/m/Y') }}
                    @else
                    -
                    @endif
                </p>
            </div>

            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 col-span-2">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Idade</p>
                <p class="font-semibold text-gray-900 dark:text-white">
                    @if($aluno->data_nascimento)
                    @php
                    $idade = \Carbon\Carbon::parse($aluno->data_nascimento)->age;
                    @endphp
                    <span class="inline-flex items-center gap-2">
                        <svg class="w-4 h-4 text-success-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 15.546c-.523 0-1.046.151-1.5.454a2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.701 2.701 0 00-1.5-.454M9 6v2m3-2v2m3-2v2M9 3h.01M12 3h.01M15 3h.01M21 21v-7a2 2 0 00-2-2H5a2 2 0 00-2 2v7h18zm-3-9v-2a2 2 0 00-2-2H8a2 2 0 00-2 2v2h12z" />
                        </svg>
                        {{ $idade }} {{ $idade == 1 ? 'ano' : 'anos' }}
                    </span>
                    @else
                    -
                    @endif
                </p>
            </div>
        </div>
    </div>

    {{-- Informações Escolares --}}
    <div class="border-t pt-4">
        <h4 class="text-sm font-bold text-secundary-700 dark:text-secundary-300 mb-3 flex items-center gap-2">
            <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
            Informações Acadêmicas
        </h4>

        <div class="bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-900 rounded-lg p-4 space-y-3">
            <div>
                <p class="text-xs text-gray-500 dark:text-gray-400">Escola</p>
                <p class="font-semibold text-gray-900 dark:text-white">
                    {{ $aluno->turma->escola->nome ?? '-' }}
                </p>
                @if($aluno->turma->escola->codigo ?? null)
                <p class="text-xs text-gray-500 dark:text-gray-400 font-mono">
                    Código: {{ $aluno->turma->escola->codigo }}
                </p>
                @endif
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Série</p>
                    <p class="font-semibold text-gray-900 dark:text-white">
                        {{ $aluno->turma->serie->nome ?? '-' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Turma</p>
                    <p class="font-semibold text-gray-900 dark:text-white">
                        {{ $aluno->turma->turma ?? '-' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Turno</p>
                    <p class="font-semibold">
                        @php
                        $turno = $aluno->turma->turno ?? null;
                        $corTurno = match($turno) {
                        'Manhã' => 'text-success-600 dark:text-success-400',
                        'Tarde' => 'text-warning-600 dark:text-warning-400',
                        'Noite' => 'text-danger-600 dark:text-danger-400',
                        default => 'text-gray-600 dark:text-gray-400'
                        };
                        @endphp
                        <span class="{{ $corTurno }}">{{ $turno ?? '-' }}</span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Professor Responsável --}}
    @if($aluno->professor)
    <div class="border-t pt-4">
        <h4 class="text-sm font-bold text-secundary-700 dark:text-secundary-300 mb-3 flex items-center gap-2">
            <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            Professor(a) Responsável
        </h4>

        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 border border-blue-200 dark:border-blue-700">
            <p class="font-semibold text-gray-900 dark:text-white mb-1">{{ $aluno->professor->nome }}</p>

            @if($aluno->professor->email)
            <p class="text-xs text-gray-600 dark:text-gray-400 mb-2">
                <a href="mailto:{{ $aluno->professor->email }}" class="hover:underline">
                    {{ $aluno->professor->email }}
                </a>
            </p>
            @endif

            <div class="flex flex-wrap gap-2 mt-2">
                @if($aluno->professor->especializacao)
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                    {{ $aluno->professor->especializacao }}
                </span>
                @endif

                @if($aluno->professor->professor_srm)
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                    Professor SRM
                </span>
                @endif

                @if($aluno->professor->profissional_apoio)
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                    Profissional de Apoio
                </span>
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- Necessidades Educacionais --}}
    <div class="border-t pt-4">
        <h4 class="text-sm font-bold text-secundary-700 dark:text-secundary-300 mb-3 flex items-center gap-2">
            <svg class="w-4 h-4 text-warning-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            Situações Especiais
        </h4>

        <div class="space-y-2">
            <div class="flex items-center justify-between p-3 rounded-lg {{ $aluno->dificuldade_aprendizagem ? 'bg-danger-50 dark:bg-danger-900/20 border border-danger-200 dark:border-danger-700' : 'bg-gray-50 dark:bg-gray-800' }}">
                <span class="text-sm text-secundary-700 dark:text-secundary-300">Dificuldade de Aprendizagem</span>
                <span class="font-bold {{ $aluno->dificuldade_aprendizagem ? 'text-success-600 dark:text-success-400' : 'text-gray-600 dark:text-gray-400' }}">
                    {{ $aluno->dificuldade_aprendizagem ? 'Sim' : 'Não' }}
                </span>
            </div>

            <div class="flex items-center justify-between p-3 rounded-lg {{ $aluno->frequenta_srm ? 'bg-success-50 dark:bg-success-900/20 border border-success-200 dark:border-success-700' : 'bg-gray-50 dark:bg-gray-800' }}">
                <span class="text-sm text-secundary-700 dark:text-secundary-300">Frequenta SRM</span>
                <span class="font-bold {{ $aluno->frequenta_srm ? 'text-success-600 dark:text-success-400' : 'text-gray-600 dark:text-gray-400' }}">
                    {{ $aluno->frequenta_srm ? 'Sim' : 'Não' }}
                </span>
            </div>

            <div class="flex items-center justify-between p-3 rounded-lg {{ $aluno->encaminhado_para_sme == 'Sim' ? 'bg-warning-50 dark:bg-warning-900/20 border border-warning-200 dark:border-warning-700' : 'bg-gray-50 dark:bg-gray-800' }}">
                <span class="text-sm text-secundary-700 dark:text-secundary-300">Encaminhado para SME</span>
                <span class="font-bold {{ $aluno->encaminhado_para_sme == 'Sim' ? 'text-success-600 dark:text-success-400' : 'text-gray-600 dark:text-gray-400' }}">
                    {{ $aluno->encaminhado_para_sme == 'Sim' ? 'Sim' : 'Não' }}
                </span>
            </div>

            <div class="flex items-center justify-between p-3 rounded-lg {{ $aluno->encaminhado_para_caei == 'Sim' ? 'bg-warning-50 dark:bg-warning-900/20 border border-warning-200 dark:border-warning-700' : 'bg-gray-50 dark:bg-gray-800' }}">
                <span class="text-sm text-secundary-700 dark:text-secundary-300">Encaminhado para CAEI</span>
                <span class="font-bold {{ $aluno->encaminhado_para_caei == 'Sim' ? 'text-success-600 dark:text-success-400' : 'text-gray-600 dark:text-gray-400' }}">
                    {{ $aluno->encaminhado_para_caei == 'Sim' ? 'Sim' : 'Não' }}
                </span>
            </div>

            @if($aluno->avanco_caei && $aluno->avanco_caei != 'Nao está em atendimento')
            <div class="flex items-center justify-between p-3 rounded-lg bg-info-50 dark:bg-info-900/20 border border-info-200 dark:border-info-700">
                <span class="text-sm text-secundary-700 dark:text-secundary-300">Avanço no CAEI</span>
                <span class="font-bold text-info-600 dark:text-info-400">
                    {{ $aluno->avanco_caei == 'Sim' ? 'Sim' : 'Não' }}
                </span>
            </div>
            @endif

            <div class="flex items-center justify-between p-3 rounded-lg {{ $aluno->ja_foi_retido == 'Sim' ? 'bg-danger-50 dark:bg-danger-900/20 border border-danger-200 dark:border-danger-700' : 'bg-gray-50 dark:bg-gray-800' }}">
                <span class="text-sm text-secundary-700 dark:text-secundary-300">Já foi retido</span>
                <span class="font-bold {{ $aluno->ja_foi_retido == 'Sim' ? 'text-success-600 dark:text-success-400' : 'text-gray-600 dark:text-gray-400' }}">
                    {{ $aluno->ja_foi_retido == 'Sim' ? 'Sim' : 'Não' }}
                </span>
            </div>
        </div>
    </div>


    {{-- Acompanhamento Profissional - Só exibe se encaminhado para CAEI --}}
    @if($aluno->encaminhado_para_caei == 'Sim')
    <div class="border-t pt-4">
        <h4 class="text-sm font-bold text-gray-700 dark:text-gray-300 mb-3 flex items-center gap-2">
            <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            Acompanhamento com Especialistas
        </h4>

        <div class="grid grid-cols-3 gap-3">
            @php
            $corFono = match($aluno->status_fonoaudiologo) {
            'Sim, Em Atendimento' => 'border-2 border-info-500 dark:border-info-400 bg-info-50 dark:bg-info-900/20',
            'Sim, Lista de Espera' => 'border-2 border-warning-500 dark:border-warning-400 bg-warning-50 dark:bg-warning-900/20',
            'Sim, Desistente', 'Sim, Desligado' => 'border-2 border-gray-400 dark:border-gray-600 bg-gray-50 dark:bg-gray-800',
            default => 'border-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800'
            };
            $textFono = match($aluno->status_fonoaudiologo) {
            'Sim, Em Atendimento' => 'text-info-600 dark:text-info-400',
            'Sim, Lista de Espera' => 'text-warning-600 dark:text-warning-400',
            default => 'text-gray-500 dark:text-gray-400'
            };
            @endphp
            <div class="text-center p-3 rounded-lg {{ $corFono }}">
                <div class="text-2xl mb-1">👂</div>
                <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Fonoaudiólogo</p>
                <p class="text-xs font-bold {{ $textFono }}">
                    {{ $aluno->status_fonoaudiologo == 'Não' ? 'Não' : ($aluno->status_fonoaudiologo ?? 'Não definido') }}
                </p>
            </div>

            @php
            $corPsi = match($aluno->status_psicologo) {
            'Sim, Em Atendimento' => 'border-2 border-info-500 dark:border-info-400 bg-info-50 dark:bg-info-900/20',
            'Sim, Lista de Espera' => 'border-2 border-warning-500 dark:border-warning-400 bg-warning-50 dark:bg-warning-900/20',
            'Sim, Desistente', 'Sim, Desligado' => 'border-2 border-gray-400 dark:border-gray-600 bg-gray-50 dark:bg-gray-800',
            default => 'border-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800'
            };
            $textPsi = match($aluno->status_psicologo) {
            'Sim, Em Atendimento' => 'text-info-600 dark:text-info-400',
            'Sim, Lista de Espera' => 'text-warning-600 dark:text-warning-400',
            default => 'text-gray-500 dark:text-gray-400'
            };
            @endphp
            <div class="text-center p-3 rounded-lg {{ $corPsi }}">
                <div class="text-2xl mb-1">🧠</div>
                <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Psicólogo</p>
                <p class="text-xs font-bold {{ $textPsi }}">
                    {{ $aluno->status_psicologo == 'Não' ? 'Não' : ($aluno->status_psicologo ?? 'Não definido') }}
                </p>
            </div>

            @php
            $corPsicoped = match($aluno->status_psicopedagogo) {
            'Sim, Em Atendimento' => 'border-2 border-info-500 dark:border-info-400 bg-info-50 dark:bg-info-900/20',
            'Sim, Lista de Espera' => 'border-2 border-warning-500 dark:border-warning-400 bg-warning-50 dark:bg-warning-900/20',
            'Sim, Desistente', 'Sim, Desligado' => 'border-2 border-gray-400 dark:border-gray-600 bg-gray-50 dark:bg-gray-800',
            default => 'border-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800'
            };
            $textPsicoped = match($aluno->status_psicopedagogo) {
            'Sim, Em Atendimento' => 'text-info-600 dark:text-info-400',
            'Sim, Lista de Espera' => 'text-warning-600 dark:text-warning-400',
            default => 'text-gray-500 dark:text-gray-400'
            };
            @endphp
            <div class="text-center p-3 rounded-lg {{ $corPsicoped }}">
                <div class="text-2xl mb-1">📖</div>
                <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Psicopedagogo</p>
                <p class="text-xs font-bold {{ $textPsicoped }}">
                    {{ $aluno->status_psicopedagogo == 'Não' ? 'Não' : ($aluno->status_psicopedagogo ?? 'Não definido') }}
                </p>
            </div>
        </div>
    </div>
    @endif

    {{-- Histórico de Retenções --}}
    @if($aluno->ja_foi_retido == 'Sim' && $aluno->retencoes->isNotEmpty())
    <div class="border-t pt-4">
        <h4 class="text-sm font-bold text-secundary-700 dark:text-secundary-300 mb-3 flex items-center gap-2">
            <svg class="w-4 h-4 text-danger-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            Histórico de Retenções
        </h4>

        <div class="space-y-3">
            @foreach($aluno->retencoes as $retencao)
            <div class="bg-danger-50 dark:bg-danger-900/20 rounded-lg p-3 border border-danger-200 dark:border-danger-700">
                <div class="grid grid-cols-3 gap-2 mb-2">
                    <div>
                        <p class="text-xs text-gray-600 dark:text-gray-400">Vezes Retido</p>
                        <p class="font-bold text-danger-600 dark:text-danger-400">{{ $retencao->vezes_retido }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-600 dark:text-gray-400">Série</p>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $retencao->serie->nome ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-600 dark:text-gray-400">Ano(s)</p>
                        @php
                        $anos = $retencao->ano_retido;

                        // Se vier como JSON string por algum motivo, tenta decodificar
                        if (is_string($anos)) {
                        $decoded = json_decode($anos, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $anos = $decoded;
                        }
                        }

                        // Se for array, junta bonitinho
                        if (is_array($anos)) {
                        $anosTexto = implode(', ', array_filter($anos, fn ($v) => filled($v)));
                        } else {
                        $anosTexto = $anos; // string simples ou null
                        }
                        @endphp

                        <p class="font-semibold text-gray-900 dark:text-white">
                            {{ $anosTexto ?: '-' }}
                        </p>
                    </div>
                </div>
                @if($retencao->motivo_retido)
                <div class="pt-2 border-t border-danger-200 dark:border-danger-700">
                    <p class="text-xs text-gray-600 dark:text-gray-400">Motivo</p>
                    <p class="text-sm text-gray-900 dark:text-white">
                        {{
                is_array($retencao->motivo_retido)
                    ? implode('; ', array_filter($retencao->motivo_retido))
                    : $retencao->motivo_retido
            }}
                    </p>
                </div>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Laudos Médicos --}}
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

    {{-- Resumo Geral --}}
    <div class="border-t pt-4">
        <h4 class="text-sm font-bold text-secundary-700 dark:text-secundary-300 mb-3 flex items-center gap-2">
            <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
            Resumo do Perfil
        </h4>

        <div class="grid grid-cols-2 gap-4">
            {{-- Situação Acadêmica --}}
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 rounded-lg p-4 border border-blue-200 dark:border-blue-700">
                <p class="text-xs font-bold text-blue-700 dark:text-blue-300 mb-2">📚 Situação Acadêmica</p>
                <ul class="text-xs space-y-1.5 text-secundary-700 dark:text-secundary-300">
                    @php
                    $situacoes = [];
                    if ($aluno->dificuldade_aprendizagem) $situacoes[] = '• Dificuldade de aprendizagem';
                    if ($aluno->frequenta_srm) $situacoes[] = '• Atendimento em SRM';
                    if ($aluno->ja_foi_retido == 'Sim') $situacoes[] = '• Retenção (' . $aluno->retencoes->count() . 'x)';
                    if ($aluno->encaminhado_para_sme == 'Sim') $situacoes[] = '• Encaminhado para SME';
                    if ($aluno->encaminhado_para_caei == 'Sim') $situacoes[] = '• Encaminhado para CAEI';
                    @endphp

                    @forelse($situacoes as $situacao)
                    <li>{{ $situacao }}</li>
                    @empty
                    <li class="text-success-600 dark:text-success-400 font-medium">✓ Sem situações especiais</li>
                    @endforelse
                </ul>
            </div>

            {{-- Acompanhamentos Ativos --}}
            <div class="bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20 rounded-lg p-4 border border-green-200 dark:border-green-700">
                <p class="text-xs font-bold text-green-700 dark:text-green-300 mb-2">🩺 Acompanhamentos</p>
                <ul class="text-xs space-y-1.5 text-secundary-700 dark:text-secundary-300">
                    @php
                    $acompanhamentos = [];
                    if ($aluno->status_fonoaudiologo && $aluno->status_fonoaudiologo != 'Não') {
                    $statusFono = str_replace('Sim, ', '', $aluno->status_fonoaudiologo);
                    $acompanhamentos[] = '• Fonoaudiólogo: ' . $statusFono;
                    }
                    if ($aluno->status_psicologo && $aluno->status_psicologo != 'Não') {
                    $statusPsi = str_replace('Sim, ', '', $aluno->status_psicologo);
                    $acompanhamentos[] = '• Psicólogo: ' . $statusPsi;
                    }
                    if ($aluno->status_psicopedagogo && $aluno->status_psicopedagogo != 'Não') {
                    $statusPsicoped = str_replace('Sim, ', '', $aluno->status_psicopedagogo);
                    $acompanhamentos[] = '• Psicopedagogo: ' . $statusPsicoped;
                    }

                    $laudosCount = $aluno->laudosPivot->count();
                    if ($laudosCount > 0) $acompanhamentos[] = '• ' . $laudosCount . ' laudo(s) anexado(s)';
                    @endphp

                    @forelse($acompanhamentos as $acompanhamento)
                    <li>{{ $acompanhamento }}</li>
                    @empty
                    <li class="text-gray-500 dark:text-gray-400 font-medium">ℹ️ Sem acompanhamentos ativos</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    {{-- Rodapé com informações adicionais --}}
    <div class="border-t pt-4 pb-2">
        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 text-center">
            <p class="text-xs text-gray-500 dark:text-gray-400">
                <span class="font-semibold">Última atualização:</span>
                {{ $aluno->updated_at ? $aluno->updated_at->format('d/m/Y H:i') : 'Não disponível' }}
            </p>
        </div>
    </div>

</div>