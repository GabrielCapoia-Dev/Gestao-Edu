<?php

namespace App\Filament\Admin\Resources\Escolas\Pages;

use App\Filament\Admin\Resources\Escolas\EscolaResource;
use App\Models\Escola;
use App\Models\User;
use App\Services\Exports\ExportRequestService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class ManageEscolas extends ManageRecords
{
    protected static string $resource = EscolaResource::class;

        public function getHeader(): ?View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),

            'eyebrow' => 'Pedagógico',
            'title' => "Escolas",
            'description' => 'Gerencie as escolas da rede educacional, adicione novas instituições e mantenha um registro atualizado das informações.',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportar_escolas_xlsx')
                ->label('Exportar XLSX')
                ->icon('heroicon-o-document-arrow-down')
                ->color('info')
                ->authorize(fn (): bool => Gate::allows('viewAny', Escola::class))
                ->requiresConfirmation()
                ->modalHeading('Exportar escolas em XLSX')
                ->modalDescription('A planilha será gerada em segundo plano com todas as escolas disponíveis no seu escopo de acesso.')
                ->modalSubmitActionLabel('Enviar para a fila')
                ->action(function (): void {
                    /** @var User|null $user */
                    $user = auth()->user();

                    if (! $user) {
                        return;
                    }

                    try {
                        $request = app(ExportRequestService::class)->queue(
                            user: $user,
                            type: 'escolas_xlsx',
                            format: 'xlsx',
                            label: 'XLSX de escolas',
                            metadata: ['source' => 'escolas.header_action'],
                        );

                        Notification::make()
                            ->title($request->wasRecentlyCreated
                                ? 'Exportação enviada para a fila'
                                : 'Exportação já está em andamento')
                            ->body('Acompanhe o progresso em Minhas Exportações.')
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
                }),

            CreateAction::make()
                ->mutateDataUsing(function (array $data): array {
                    $data['ativo'] = true;
                    return $data;
                }),
        ];
    }
}
