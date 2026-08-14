<?php

namespace App\Filament\Admin\Resources\Lotacoes\Pages;

use App\Filament\Admin\Resources\Lotacoes\LotacaoResource;
use App\Models\Lotacao;
use App\Models\User;
use App\Services\Exports\ExportRequestService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Throwable;

class ManageLotacoes extends ManageRecords
{
    protected static string $resource = LotacaoResource::class;

    public function getHeader(): ?View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),
            'eyebrow' => 'Locais de trabalho',
            'title' => 'Lotações',
            'description' => 'Consulte e gerencie as lotações de todos os locais de trabalho disponíveis no seu escopo.',
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportar_lotacoes_xlsx')
                ->label('Exportar XLSX')
                ->icon('heroicon-o-document-arrow-down')
                ->color('info')
                ->authorize(fn (): bool => Gate::allows('viewAny', Lotacao::class))
                ->requiresConfirmation()
                ->modalHeading('Exportar lotações em XLSX')
                ->modalDescription('A planilha será gerada em segundo plano com todas as lotações disponíveis no seu escopo de acesso.')
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
                            type: 'lotacoes_xlsx',
                            format: 'xlsx',
                            label: 'XLSX de lotações',
                            metadata: ['source' => 'lotacoes.header_action'],
                        );

                        Notification::make()
                            ->title($request->wasRecentlyCreated
                                ? 'Exportação enviada para a fila'
                                : 'Exportação já está em andamento')
                            ->body('Acompanhe o progresso pelo ícone de downloads no topo.')
                            ->success()
                            ->send();
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title('Não foi possível iniciar a exportação')
                            ->body('Tente novamente em alguns instantes.')
                            ->danger()
                            ->send();
                    }
                }),

            CreateAction::make()
                ->label('Nova lotação')
                ->mutateDataUsing(fn (array $data): array => LotacaoResource::validarLocalVisivel($data)),
        ];
    }
}
