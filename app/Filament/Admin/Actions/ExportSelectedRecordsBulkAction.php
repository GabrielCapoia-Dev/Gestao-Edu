<?php

namespace App\Filament\Admin\Actions;

use App\Models\Aluno;
use App\Models\Turma;
use App\Models\User;
use App\Services\Exports\ExportRequestService;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class ExportSelectedRecordsBulkAction
{
    public static function make(
        string $type,
        string $label,
        string $source,
        string $actionLabel = 'Exportar XLSX',
        string $modalHeading = 'Exportar registros selecionados em XLSX',
        string $modalDescription = 'A planilha será gerada em segundo plano com os dados dos registros selecionados.',
    ): BulkAction
    {
        return BulkAction::make("exportar_{$type}_xlsx")
            ->label($actionLabel)
            ->icon('heroicon-o-document-arrow-down')
            ->color('info')
            ->requiresConfirmation()
            ->modalHeading($modalHeading)
            ->modalDescription($modalDescription)
            ->modalSubmitActionLabel('Enviar para a fila')
            ->fetchSelectedRecords(false)
            ->visible(fn (): bool => self::podeExportar($type))
            ->action(function (Builder $recordsQuery) use ($type, $label, $source): void {
                /** @var User|null $user */
                $user = auth()->user();
                $model = $recordsQuery->getModel();
                $ids = $recordsQuery
                    ->pluck($model->qualifyColumn($model->getKeyName()))
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if (! $user || ! self::podeExportar($type) || $ids === []) {
                    Notification::make()
                        ->title($ids === []
                            ? 'Nenhum registro selecionado para exportação'
                            : 'Você não possui permissão para exportar estes dados')
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    $request = app(ExportRequestService::class)->queue(
                        user: $user,
                        type: $type,
                        format: 'xlsx',
                        filters: ['ids' => $ids],
                        label: $label,
                        metadata: ['source' => $source],
                    );

                    Notification::make()
                        ->title($request->wasRecentlyCreated
                            ? 'Exportação enviada para a fila'
                            : 'Exportação já está em andamento')
                        ->body('Acompanhe o progresso pelo ícone de downloads no topo.')
                        ->success()
                        ->send();
                } catch (\Throwable $exception) {
                    report($exception);

                    Notification::make()
                        ->title('Não foi possível iniciar a exportação')
                        ->body('Tente novamente em alguns instantes.')
                        ->danger()
                        ->send();
                }
            })
            ->deselectRecordsAfterCompletion();
    }

    private static function podeExportar(string $type): bool
    {
        return match ($type) {
            'turmas_selecionadas', 'turmas_selecionadas_detalhado' => Gate::allows('export', Turma::class),
            'alunos_selecionados' => Gate::allows('export', Aluno::class),
            default => false,
        };
    }
}
