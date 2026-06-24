@php
    use App\Models\Aluno;
    use App\Services\AlunoService;
    use App\Services\ProfilePreviewService;

    $record = $getRecord();
    $user = app(ProfilePreviewService::class)->effectiveUser();
    $service = app(AlunoService::class);
    $recordKey = $record instanceof Aluno ? (string) $record->getKey() : '';

    $canParecerTransferencia = $record instanceof Aluno && $service->podeGerarParecerTransferencia($record, $user);
    $canEdit = $record instanceof Aluno && $service->podeEditarAluno($record, $user);
    $canDelete = $record instanceof Aluno && $service->podeExcluirAluno($record, $user);
@endphp

@if ($canParecerTransferencia || $canEdit || $canDelete)
    <div class="aluno-card-actions aluno-card-actions--footer">
        @if ($canParecerTransferencia)
            <x-filament::button
                color="info"
                icon="heroicon-o-document-arrow-down"
                size="sm"
                wire:click.stop.prevent="mountTableAction('parecer_transferencia', '{{ $recordKey }}')"
            >
                Parecer de Transferencia
            </x-filament::button>
        @endif

        @if ($canEdit)
            <x-filament::button
                color="primary"
                icon="heroicon-o-pencil-square"
                size="sm"
                wire:click.stop.prevent="mountTableAction('edit', '{{ $recordKey }}')"
            >
                Editar
            </x-filament::button>
        @endif

        @if ($canDelete)
            <x-filament::button
                color="danger"
                icon="heroicon-o-trash"
                size="sm"
                wire:click.stop.prevent="mountTableAction('delete', '{{ $recordKey }}')"
            >
                Excluir
            </x-filament::button>
        @endif
    </div>
@endif
