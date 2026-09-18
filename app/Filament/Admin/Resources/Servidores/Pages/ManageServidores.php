<?php

namespace App\Filament\Admin\Resources\Servidores\Pages;

use App\Filament\Admin\Resources\Servidores\ServidorResource;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\Servidor;
use App\Models\User;
use App\Services\Exports\ExportRequestService;
use App\Services\PessoaUsuarioService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;

class ManageServidores extends ManageRecords
{
    protected static string $resource = ServidorResource::class;

    protected string $view = 'filament.admin.resources.servidores.pages.manage-pessoas';

    #[On('pessoa-form-salvo')]
    public function finalizarFormularioPessoa(): void
    {
        $this->flushCachedTableRecords();
        $this->unmountAction(false);
    }

    #[On('pessoa-form-cancelado')]
    public function cancelarFormularioPessoa(): void
    {
        $this->unmountAction(false);
    }

    public function getHeader(): ?View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),
            'eyebrow' => 'Servidores',
            'title' => 'Central de servidores',
            'description' => 'Gerencie identidade, cargos, vínculos, contas, níveis de acesso e permissões no mesmo lugar.',
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
            Action::make('exportar_servidores_filtrados_xlsx')
                ->label('Exportar XLSX')
                ->icon('heroicon-o-document-arrow-down')
                ->color('info')
                ->visible(fn (): bool => Gate::allows('viewAny', Servidor::class))
                ->requiresConfirmation()
                ->modalHeading('Exportar servidores filtrados em XLSX')
                ->modalDescription('A planilha será gerada em segundo plano com os servidores que atendem aos filtros atuais e ao seu escopo de acesso.')
                ->modalSubmitActionLabel('Enviar para a fila')
                ->action(function (): void {
                    /** @var User|null $user */
                    $user = auth()->user();

                    if (! $user || ! Gate::forUser($user)->allows('viewAny', Servidor::class)) {
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
                                'source' => 'servidores.filtered_header_action',
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
                }),

            Action::make('solicitacoes_acesso')
                ->label(function (): string {
                    $user = auth()->user();
                    $quantidade = $user
                        ? app(PessoaUsuarioService::class)->usuariosSemPessoaQuery($user)->count()
                        : 0;

                    return $quantidade > 0
                        ? "Solicitações de acesso ({$quantidade})"
                        : 'Solicitações de acesso';
                })
                ->icon('heroicon-o-inbox-arrow-down')
                ->color('gray')
                ->visible(fn (): bool => Gate::allows('viewAny', User::class))
                ->url(fn (): string => UserResource::getUrl('index', [
                    'context' => 'solicitacoes',
                    'tableFilters' => [
                        'sem_pessoa' => ['value' => true],
                    ],
                ])),

            Action::make('create')
                ->label('Novo servidor')
                ->visible(fn (): bool => Gate::allows('create', Servidor::class))
                ->modalWidth('6xl')
                ->modalIcon(null)
                ->modalHeading('Novo servidor')
                ->modalDescription('Identidade, cargo, matrículas e lotações no mesmo fluxo.')
                ->formWrapper(false)
                ->modalSubmitAction(false)
                ->modalCancelAction(false)
                ->extraModalWindowAttributes([
                    'class' => 'pessoa-modal-window',
                ])
                ->stickyModalHeader()
                ->closeModalByClickingAway(false)
                ->closeModalByEscaping(false)
                ->modalContent(fn () => view('components.pessoas.form-modal', [
                    'pessoaId' => null,
                ])),
        ];
    }
}
