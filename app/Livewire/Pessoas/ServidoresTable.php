<?php

namespace App\Livewire\Pessoas;

use App\Filament\Admin\Resources\Servidores\ServidorResource;
use App\Models\Servidor;
use App\Models\User;
use App\Services\Exports\ExportRequestService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;

class ServidoresTable extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function mount(): void
    {
        abort_unless(static::canView(), 403);
    }

    public static function canView(): bool
    {
        return Gate::allows('viewAny', Servidor::class);
    }

    #[On('pessoa-form-salvo')]
    public function atualizarAposSalvarPessoa(): void
    {
        $this->resetTable();
        $this->unmountAction(false);
    }

    #[On('pessoa-form-cancelado')]
    public function fecharFormularioPessoa(): void
    {
        $this->unmountAction(false);
    }

    public function table(Table $table): Table
    {
        $table = ServidorResource::table(
            $table->query(ServidorResource::getEloquentQuery()),
        );

        return $table
            ->heading('Servidores')
            ->headerActions([
                $this->exportarFiltradosAction(),
            ])
            ->queryStringIdentifier('servidores-livewire')
            ->emptyStateHeading('Nenhum servidor encontrado')
            ->emptyStateDescription('Ajuste os filtros ou cadastre um novo servidor.')
            ->emptyStateIcon('heroicon-o-users');
    }

    private function exportarFiltradosAction(): Action
    {
        return Action::make('exportar_servidores_filtrados_xlsx')
            ->label('Exportar filtrados')
            ->icon('heroicon-o-document-arrow-down')
            ->color('info')
            ->visible(fn (): bool => Gate::allows('viewAny', Servidor::class))
            ->requiresConfirmation()
            ->modalHeading('Exportar servidores filtrados em XLSX')
            ->modalDescription('A planilha será gerada em segundo plano com os servidores que atendem aos filtros atuais e ao seu escopo de acesso.')
            ->modalSubmitActionLabel('Enviar para a fila')
            ->action(function (): void {
                $user = auth()->user();

                if (! $user instanceof User || ! Gate::forUser($user)->allows('viewAny', Servidor::class)) {
                    return;
                }

                $query = $this->getFilteredTableQuery();
                $model = $query->getModel();
                $ids = $query
                    ->pluck($model->qualifyColumn($model->getKeyName()))
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if ($ids === []) {
                    Notification::make()
                        ->title('Nenhum servidor encontrado para exportação')
                        ->warning()
                        ->send();

                    return;
                }

                try {
                    $request = app(ExportRequestService::class)->queue(
                        user: $user,
                        type: 'servidores_filtrados',
                        format: 'xlsx',
                        filters: ['ids' => $ids],
                        label: 'XLSX de servidores filtrados',
                        metadata: [
                            'source' => 'servidores.livewire_table',
                            'records_count' => count($ids),
                        ],
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
            });
    }
}
