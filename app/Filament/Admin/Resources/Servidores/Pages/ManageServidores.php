<?php

namespace App\Filament\Admin\Resources\Servidores\Pages;

use App\Filament\Admin\Resources\Servidores\ServidorResource;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\Servidor;
use App\Models\User;
use App\Services\PessoaUsuarioService;
use Filament\Actions\Action;
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
            'eyebrow' => 'Pessoas',
            'title' => 'Central de pessoas',
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
                ->label('Nova pessoa')
                ->visible(fn (): bool => Gate::allows('create', Servidor::class))
                ->modalWidth('6xl')
                ->modalIcon(null)
                ->modalHeading('Nova pessoa')
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
