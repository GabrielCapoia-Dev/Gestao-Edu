@php
    use App\Models\Aluno;
    use App\Services\AlunoService;
    use App\Services\ProfilePreviewService;

    $record = $getRecord();
    $user = app(ProfilePreviewService::class)->effectiveUser();
    $service = app(AlunoService::class);
    $recordKey = $record instanceof Aluno ? (string) $record->getKey() : '';

    $canRemanejar = $record instanceof Aluno && $service->podeRemanejar($record, $user);
    $canVoltarTurmaAnterior = $record instanceof Aluno && $service->podeVoltarTurmaAnterior($record, $user);
    $canMarcarContraTurno = $record instanceof Aluno && $service->podeMarcarContraTurno($record, $user);
    $canEncerrarContraTurno = $record instanceof Aluno && $service->podeEncerrarContraTurno($record, $user);
@endphp

@if ($canRemanejar || $canVoltarTurmaAnterior || $canMarcarContraTurno || $canEncerrarContraTurno)
    <div class="aluno-card-actions aluno-card-actions--top">
        @if ($canRemanejar)
            <x-filament::button
                color="warning"
                icon="heroicon-o-arrows-right-left"
                size="sm"
                wire:click.stop.prevent="mountTableAction('remanejar', '{{ $recordKey }}')"
            >
                Remanejar
            </x-filament::button>
        @endif

        @if ($canVoltarTurmaAnterior)
            <x-filament::button
                color="info"
                icon="heroicon-o-arrow-uturn-left"
                size="sm"
                wire:click.stop.prevent="mountTableAction('voltar_turma_anterior', '{{ $recordKey }}')"
            >
                Voltar turma anterior
            </x-filament::button>
        @endif

        @if ($canMarcarContraTurno)
            <x-filament::button
                color="success"
                icon="heroicon-o-sparkles"
                size="sm"
                wire:click.stop.prevent="mountTableAction('marcar_contra_turno', '{{ $recordKey }}')"
            >
                Marcar contra turno
            </x-filament::button>
        @endif

        @if ($canEncerrarContraTurno)
            <x-filament::button
                color="danger"
                icon="heroicon-o-no-symbol"
                size="sm"
                wire:click.stop.prevent="mountTableAction('encerrar_contra_turno', '{{ $recordKey }}')"
            >
                Encerrar contra turno
            </x-filament::button>
        @endif
    </div>
@endif
